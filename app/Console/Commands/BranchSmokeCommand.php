<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Business;
use App\Models\Branch;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Customer;
use App\Models\CashFlow;
use App\Models\Inventory;
use App\Support\BranchContext;
use App\Services\Admin\FinancialService;
use Symfony\Component\Process\Process;

class BranchSmokeCommand extends Command
{
    /**
     * Signature console command.
     */
    protected $signature = 'branch:smoke
                            {--business= : ID Business yang diuji (default: business pertama)}
                            {--admin= : ID User Admin (default: auto-select user dengan role admin)}
                            {--kasir= : ID User Kasir uji (default: auto-select user dengan role kasir/driver)}
                            {--other-business= : ID Business lain untuk uji IDOR lintas tenant}
                            {--only= : Batasi pengujian ke skenario tertentu: A, B, C, D, E, F, atau G}
                            {--mode= : Batasi mode pengujian untuk skenario A dan C: pusat atau cabang}
                            {--with-exports : Jalankan juga rute ekspor PDF/Excel (secara default dilewati / SKIP-EXPORT)}
                            {--no-subprocess : Jalankan in-process tanpa proses anak terpisah}
                            {--json-out= : Simpan hasil pengujian ke file JSON (dipakai oleh proses induk)}';

    /**
     * Deskripsi console command.
     */
    protected $description = 'Automated in-process smoke test untuk semua route GET multi-cabang (Admin, Kasir, IDOR, API, Tutup Buku)';

    /**
     * Koleksi hasil eksekusi pengujian.
     *
     * @var array
     */
    protected array $results = [];

    /**
     * Counter ringkasan.
     */
    protected int $countOk = 0;
    protected int $countFail = 0;
    protected int $countError = 0;
    protected int $countSkip = 0;
    protected int $countKilled = 0;

    /**
     * Counter skenario terautentikasi.
     */
    protected int $totalAuthScenarios = 0;
    protected int $okAuthScenarios = 0;

    /**
     * Jalankan perintah smoke test.
     */
    public function handle(): int
    {
        // Matikan query log agar tidak memakan memori di background
        DB::disableQueryLog();

        $only = $this->option('only') ? strtoupper(trim($this->option('only'))) : null;
        $noSubprocess = (bool) $this->option('no-subprocess');
        $jsonOut = $this->option('json-out');

        // 1. Resolusi Target Business
        $businessId = $this->option('business') ? (int) $this->option('business') : Business::value('id');
        if (!$businessId) {
            $this->error("Tabel businesses kosong atau ID business tidak ditemukan.");
            return 1;
        }

        $business = Business::find($businessId);
        if (!$business) {
            $this->error("Business dengan ID {$businessId} tidak ditemukan.");
            return 1;
        }

        // Resolusi Admin / Owner (K1 & H2.a)
        $adminCandidates = User::where('business_id', $businessId)->whereIn('role', ['admin', 'owner'])->get();
        if ($adminCandidates->isEmpty()) {
            $this->error("Tidak ditemukan akun dengan role 'admin' atau 'owner' di Business ID {$businessId} ({$business->name}).");
            return 1;
        }

        $adminId = $this->option('admin') ? (int) $this->option('admin') : null;
        if ($adminId) {
            $adminUser = $adminCandidates->firstWhere('id', $adminId);
            if (!$adminUser) {
                $this->error("User ID {$adminId} bukan user dengan role 'admin'/'owner' di Business ID {$businessId}.");
                $this->line("Kandidat Admin / Owner yang tersedia di database:");
                foreach ($adminCandidates as $cand) {
                    $this->line("  - ID: {$cand->id} | {$cand->name} ({$cand->email}) | Role: {$cand->role}");
                }
                return 1;
            }
        } else {
            $adminUser = $adminCandidates->where('is_active', true)->first() ?? $adminCandidates->first();
        }

        // Resolusi Kasir (K1 & H2.a)
        $kasirCandidates = User::where('business_id', $businessId)->whereIn('role', ['kasir', 'driver'])->get();
        if ($kasirCandidates->isEmpty()) {
            $this->error("Tidak ditemukan akun dengan role 'kasir' atau 'driver' di Business ID {$businessId} ({$business->name}).");
            return 1;
        }

        $kasirId = $this->option('kasir') ? (int) $this->option('kasir') : null;
        if ($kasirId) {
            $kasirUser = $kasirCandidates->firstWhere('id', $kasirId);
            if (!$kasirUser) {
                $this->error("User ID {$kasirId} bukan user dengan role 'kasir'/'driver' di Business ID {$businessId}.");
                $this->line("Kandidat Kasir / Driver yang tersedia di database:");
                foreach ($kasirCandidates as $cand) {
                    $this->line("  - ID: {$cand->id} | {$cand->name} ({$cand->email}) | Role: {$cand->role}");
                }
                return 1;
            }
        } else {
            // Auto-select kasir yang BERBEDA dari admin
            $kasirUser = $kasirCandidates->where('id', '!=', $adminUser->id)->where('is_active', true)->first()
                ?? $kasirCandidates->where('id', '!=', $adminUser->id)->first();
        }

        // Validasi: Admin dan Kasir TIDAK boleh sama (H2.a)
        if (!$kasirUser || $adminUser->id === $kasirUser->id) {
            $this->error("User Admin dan User Kasir tidak boleh menggunakan user yang sama!");
            $this->line("\nKandidat Admin yang tersedia di database:");
            foreach ($adminCandidates as $cand) {
                $this->line("  - ID: {$cand->id} | {$cand->name} ({$cand->email}) | Role: {$cand->role}");
            }
            $this->line("\nKandidat Kasir / Driver yang tersedia di database:");
            foreach ($kasirCandidates as $cand) {
                $this->line("  - ID: {$cand->id} | {$cand->name} ({$cand->email}) | Role: {$cand->role}");
            }
            $this->warn("\nSilakan tentukan dua user yang berbeda lewat opsi --admin dan --kasir.");
            return 1;
        }

        $otherBusinessId = $this->option('other-business') ? (int) $this->option('other-business') : null;
        if (!$otherBusinessId) {
            $otherBusinessId = Business::where('id', '!=', $businessId)->value('id');
        }

        // Resolusi cabang aktif kasir & admin
        $branches = Branch::where('business_id', $businessId)->where('is_active', true)->get();
        if ($branches->isEmpty()) {
            $this->error("Tidak ada cabang aktif untuk business ID {$businessId}.");
            return 1;
        }

        $activeBranch = $kasirUser->branches()->where('is_active', true)->first()
            ?? Branch::where('id', $kasirUser->branch_id)->where('is_active', true)->first()
            ?? $branches->first();

        // 2. Inventarisasi Route GET
        $adminRoutes = [];
        $kasirRoutes = [];

        foreach (Route::getRoutes()->getRoutesByMethod()['GET'] ?? [] as $route) {
            if ($this->shouldExcludeRoute($route)) {
                continue;
            }

            $uri = $route->uri();
            $name = $route->getName() ?? '';

            if (str_starts_with($uri, 'admin') || str_starts_with($name, 'admin.')) {
                $adminRoutes[] = $route;
            } elseif (str_starts_with($uri, 'kasir') || str_starts_with($name, 'kasir.')) {
                $kasirRoutes[] = $route;
            }
        }

        // 3. JIKA MODE SUB-PROSES / CHILD ATAU ONLY: Jalankan langsung in-process
        if ($only || $noSubprocess) {
            return $this->runSlicesInProcess(
                $adminUser,
                $kasirUser,
                $activeBranch,
                $businessId,
                $otherBusinessId,
                $adminRoutes,
                $kasirRoutes,
                $only,
                $this->option('mode'),
                $jsonOut
            );
        }

        // 4. JIKA MODE INDUK (PARENT): Cek ketersediaan proc_open (K1)
        $disabledFunctions = explode(',', (string) ini_get('disable_functions'));
        $disabledFunctions = array_map('trim', $disabledFunctions);
        $procOpenAvailable = function_exists('proc_open') && !in_array('proc_open', $disabledFunctions, true);

        if (!$procOpenAvailable) {
            $this->warn("\n[PERINGATAN] proc_open dinonaktifkan di hosting ini (disable_functions).");
            $this->warn("Beralih otomatis ke mode single process (--no-subprocess).");
            $this->line("Jika proses terkena Killed oleh hosting, jalankan per skenario via shell loop:");
            $this->line("  for s in A B C D E F G; do php artisan branch:smoke --only=\$s --business={$businessId} --admin={$adminUser->id} --kasir={$kasirUser->id}; done\n");

            return $this->runSlicesInProcess(
                $adminUser,
                $kasirUser,
                $activeBranch,
                $businessId,
                $otherBusinessId,
                $adminRoutes,
                $kasirRoutes,
                null,
                null,
                null
            );
        }

        // 5. JALANKAN PROSES INDUK YANG MEMICU SUB-PROSES TERISOLASI PER SKENARIO (K1)
        $this->info("================================================================================");
        $this->info("      AUTOMATED MULTI-PROCESS BRANCH SMOKE TEST (ISOLASI MEMORI ANTI-KILLED)    ");
        $this->info("================================================================================");
        $this->comment("Target Business : [ID: {$business->id}] {$business->name}");
        $this->comment("Admin Uji       : [ID: {$adminUser->id}] {$adminUser->name} ({$adminUser->email})");
        $this->comment("Kasir Uji       : [ID: {$kasirUser->id}] {$kasirUser->name} ({$kasirUser->email})");
        $this->comment("Cabang Uji Kasir: [ID: {$activeBranch->id}] {$activeBranch->name} ({$activeBranch->code})");
        if ($otherBusinessId) {
            $otherBiz = Business::find($otherBusinessId);
            $this->comment("Tenant Pembanding: [ID: {$otherBusinessId}] " . ($otherBiz ? $otherBiz->name : 'N/A'));
        }
        $this->line("Mode Eksekusi   : Sub-proses terisolasi per skenario (PHP_BINARY: " . PHP_BINARY . ")");
        $this->line("Rute Ekspor     : " . ($this->option('with-exports') ? 'DIUJI (--with-exports)' : 'DILEWATI (SKIP-EXPORT)'));

        $slices = [
            ['scenario' => 'A', 'mode' => 'pusat',  'title' => 'A. Admin Pusat'],
            ['scenario' => 'A', 'mode' => 'cabang', 'title' => 'A. Admin Cabang'],
            ['scenario' => 'B', 'mode' => 'cabang', 'title' => 'B. Kasir Cabang'],
            ['scenario' => 'C', 'mode' => 'cabang', 'title' => 'C. Batas Role (Cabang)'],
            ['scenario' => 'C', 'mode' => 'pusat',  'title' => 'C. Batas Role (Pusat)'],
            ['scenario' => 'D', 'mode' => null,     'title' => 'D. Kontrol Positif & IDOR'],
            ['scenario' => 'E', 'mode' => null,     'title' => 'E. API Kasir & Header'],
            ['scenario' => 'F', 'mode' => null,     'title' => 'F. Tanpa Cabang / Tanpa Biz'],
            ['scenario' => 'G', 'mode' => null,     'title' => 'G. Tutup Buku Dry-Run'],
        ];

        foreach ($slices as $slice) {
            $this->line("\n<info>▶ Menjalankan {$slice['title']} dalam sub-proses terpisah...</info>");
            $tempJson = storage_path('app/smoke_' . Str::random(12) . '.json');

            $cmd = [
                PHP_BINARY,
                base_path('artisan'),
                'branch:smoke',
                "--business={$businessId}",
                "--admin={$adminUser->id}",
                "--kasir={$kasirUser->id}",
                "--only={$slice['scenario']}",
                "--no-subprocess",
                "--json-out={$tempJson}",
            ];

            if (!empty($slice['mode'])) {
                $cmd[] = "--mode={$slice['mode']}";
            }
            if ($otherBusinessId) {
                $cmd[] = "--other-business={$otherBusinessId}";
            }
            if ($this->option('with-exports')) {
                $cmd[] = "--with-exports";
            }

            $process = new Process($cmd, base_path(), null, null, 300);
            $process->run(function ($type, $buffer) {
                foreach (explode("\n", $buffer) as $line) {
                    $trimmed = trim($line);
                    if (!empty($trimmed) && (str_starts_with($trimmed, '▶') || str_starts_with($trimmed, '[') || str_starts_with($trimmed, '  ['))) {
                        $this->line("    " . $trimmed);
                    }
                }
            });

            $exitCode = $process->getExitCode();
            $out = trim($process->getOutput());
            $err = trim($process->getErrorOutput());

            if ($exitCode === 0 && file_exists($tempJson)) {
                $jsonContent = file_get_contents($tempJson);
                $data = json_decode($jsonContent, true);

                if (is_array($data)) {
                    foreach ($data['results'] ?? [] as $r) {
                        $this->results[] = $r;
                    }
                    $this->countOk += (int) ($data['countOk'] ?? 0);
                    $this->countFail += (int) ($data['countFail'] ?? 0);
                    $this->countError += (int) ($data['countError'] ?? 0);
                    $this->countSkip += (int) ($data['countSkip'] ?? 0);
                    $this->totalAuthScenarios += (int) ($data['totalAuthScenarios'] ?? 0);
                    $this->okAuthScenarios += (int) ($data['okAuthScenarios'] ?? 0);

                    $peakMb = $data['peakMemoryMb'] ?? 'N/A';
                    $this->line("  ✔ Sub-proses selesai normal (OK: " . ($data['countOk'] ?? 0) . ", Non-OK: " . (($data['countFail'] ?? 0) + ($data['countError'] ?? 0) + ($data['countSkip'] ?? 0)) . " | Memori Puncak: {$peakMb} MB)");
                } else {
                    $this->recordResult($slice['title'], 'Sub-Process JSON Parse', $slice['mode'] ?? 'N/A', 0, 'ERROR', 'Gagal mem-parsing output JSON sub-proses');
                }
                @unlink($tempJson);
            } else {
                $isKilled = ($exitCode === 137 || $exitCode === 9 || str_contains($out . $err, 'Killed'));
                $status = $isKilled ? 'KILLED' : 'ERROR';
                $msg = $isKilled
                    ? "PROSES TERBUNUH (KILLED oleh hosting / batas cgroup). Exit: {$exitCode}"
                    : "Sub-proses keluar dengan kode {$exitCode}: " . Str::limit($err ?: $out, 120);

                $this->recordResult($slice['title'], 'Proses Terisolasi', $slice['mode'] ?? 'N/A', 0, $status, $msg);

                // Tetap coba muat data parsial jika sempat tertulis
                if (file_exists($tempJson)) {
                    $data = json_decode(file_get_contents($tempJson), true);
                    if (is_array($data) && !empty($data['results'])) {
                        foreach ($data['results'] as $r) {
                            $this->results[] = $r;
                        }
                    }
                    @unlink($tempJson);
                }
            }
        }

        // 6. Rangkuman & Ekspor CSV oleh Proses Induk
        return $this->renderFinalSummary();
    }

    /**
     * Menjalankan skenario yang diminta dalam satu proses (digunakan saat --only atau fallback).
     */
    protected function runSlicesInProcess(
        User $adminUser,
        User $kasirUser,
        Branch $activeBranch,
        int $businessId,
        ?int $otherBusinessId,
        array $adminRoutes,
        array $kasirRoutes,
        ?string $only,
        ?string $mode,
        ?string $jsonOut
    ): int {
        $mode = $mode ? strtolower(trim($mode)) : null;

        if (!$jsonOut) {
            $this->info("================================================================================");
            $this->info("            AUTOMATED IN-PROCESS BRANCH SMOKE TEST (BUKU-DIGITAL)               ");
            $this->info("================================================================================");
            $this->comment("Target Business : [ID: {$businessId}]");
            $this->comment("Admin Uji       : [ID: {$adminUser->id}] {$adminUser->name}");
            $this->comment("Kasir Uji       : [ID: {$kasirUser->id}] {$kasirUser->name}");
            $this->comment("Cabang Uji Kasir: [ID: {$activeBranch->id}] {$activeBranch->name} ({$activeBranch->code})");
            $this->line("Rute Ekspor     : " . ($this->option('with-exports') ? 'DIUJI (--with-exports)' : 'DILEWATI (SKIP-EXPORT)'));
        }

        // Skenario A
        if (!$only || $only === 'A') {
            if (!$mode || $mode === 'pusat') {
                try {
                    $this->runScenarioAPusat($adminUser, $adminRoutes);
                } catch (\Throwable $e) {
                    $msg = get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
                    $this->recordResult('A. Admin Pusat', 'Fatal Scenario Exception', 'Pusat', 500, 'ERROR', $msg);
                }
            }
            if (!$mode || $mode === 'cabang') {
                try {
                    $this->runScenarioACabang($adminUser, $activeBranch, $adminRoutes);
                } catch (\Throwable $e) {
                    $msg = get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
                    $this->recordResult('A. Admin Cabang', 'Fatal Scenario Exception', 'Cabang', 500, 'ERROR', $msg);
                }
            }
        }

        // Skenario B
        if (!$only || $only === 'B') {
            try {
                $this->runScenarioB($kasirUser, $activeBranch, $kasirRoutes);
            } catch (\Throwable $e) {
                $msg = get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
                $this->recordResult('B. Kasir Cabang', 'Fatal Scenario Exception', 'Cabang', 500, 'ERROR', $msg);
            }
        }

        // Skenario C
        if (!$only || $only === 'C') {
            if (!$mode || $mode === 'cabang') {
                try {
                    $this->runScenarioCCabang($kasirUser, $activeBranch, $adminRoutes);
                } catch (\Throwable $e) {
                    $msg = get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
                    $this->recordResult('C. Batas Role', 'Fatal Scenario Exception', 'Kasir -> Admin', 500, 'ERROR', $msg);
                }
            }
            if (!$mode || $mode === 'pusat') {
                try {
                    $this->runScenarioCPusat($kasirUser, $adminRoutes);
                } catch (\Throwable $e) {
                    $msg = get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
                    $this->recordResult('C. Batas Role', 'Fatal Scenario Exception', 'Kasir (No-Branch) -> Admin', 500, 'ERROR', $msg);
                }
            }
        }

        // Skenario D
        if (!$only || $only === 'D') {
            try {
                $this->runScenarioD($adminUser, $kasirUser, $activeBranch, $businessId, $otherBusinessId);
            } catch (\Throwable $e) {
                $msg = get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
                $this->recordResult('D. IDOR & Control', 'Fatal Scenario Exception', 'N/A', 500, 'ERROR', $msg);
            }
        }

        // Skenario E
        if (!$only || $only === 'E') {
            try {
                $this->runScenarioE($kasirUser, $activeBranch, $businessId, $otherBusinessId);
            } catch (\Throwable $e) {
                $msg = get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
                $this->recordResult('E. API Kasir', 'Fatal Scenario Exception', 'N/A', 500, 'ERROR', $msg);
            }
        }

        // Skenario F
        if (!$only || $only === 'F') {
            try {
                $this->runScenarioF($businessId);
            } catch (\Throwable $e) {
                $msg = get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
                $this->recordResult('F. Tanpa Cabang/Biz', 'Fatal Scenario Exception', 'N/A', 500, 'ERROR', $msg);
            }
        }

        // Skenario G
        if (!$only || $only === 'G') {
            try {
                $this->runScenarioG($adminUser, $businessId);
            } catch (\Throwable $e) {
                $msg = get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
                $this->recordResult('G. Tutup Buku Dry-Run', 'Fatal Scenario Exception', 'N/A', 500, 'ERROR', $msg);
            }
        }

        // Jika dipanggil oleh proses induk dengan --json-out
        if ($jsonOut) {
            $exportData = [
                'results'            => $this->results,
                'countOk'            => $this->countOk,
                'countFail'          => $this->countFail,
                'countError'         => $this->countError,
                'countSkip'          => $this->countSkip,
                'countKilled'        => $this->countKilled,
                'totalAuthScenarios' => $this->totalAuthScenarios,
                'okAuthScenarios'    => $this->okAuthScenarios,
                'peakMemoryMb'       => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ];
            file_put_contents($jsonOut, json_encode($exportData, JSON_PRETTY_PRINT));
            return ($this->countFail > 0 || $this->countError > 0) ? 1 : 0;
        }

        return $this->renderFinalSummary();
    }

    /**
     * Menampilkan tabel ringkasan akhir dan ekspor file CSV.
     */
    protected function renderFinalSummary(): int
    {
        $this->line("\n================================================================================");
        $this->info("                          HASIL PENGUJIAN SMOKE TEST                            ");
        $this->line("================================================================================");

        $tableHeaders = ['Skenario', 'Route / Target', 'Role / Mode', 'HTTP', 'Status', 'Pesan'];
        $tableRows = [];

        foreach ($this->results as $row) {
            $statusFormatted = match ($row['status']) {
                'OK'     => "<fg=green;options=bold>OK</>",
                'FAIL'   => "<fg=red;options=bold>FAIL</>",
                'ERROR'  => "<fg=yellow;options=bold>ERROR</>",
                'SKIP'   => "<fg=gray;options=bold>SKIP</>",
                'KILLED' => "<fg=red;options=bold>KILLED</>",
                default  => $row['status'],
            };

            $tableRows[] = [
                $row['scenario'],
                Str::limit($row['target'], 40),
                $row['mode'],
                $row['http'] > 0 ? (string) $row['http'] : '-',
                $statusFormatted,
                Str::limit($row['message'], 62),
            ];
        }

        $this->table($tableHeaders, $tableRows);

        // Ekspor Hasil ke CSV
        $timestamp = date('Ymd_His');
        $csvPath = storage_path("app/branch_smoke_{$timestamp}.csv");
        $this->exportCsv($csvPath);

        // Ringkasan Akhir
        $this->line("\n================================================================================");
        $this->info("                              RINGKASAN AKHIR                                   ");
        $this->line("================================================================================");
        $totalTests = count($this->results);
        $this->line("Total Pengujian         : <fg=white;options=bold>{$totalTests}</>");
        $this->line("Lolos (OK)              : <fg=green;options=bold>{$this->countOk}</>");
        $this->line("Gagal (FAIL)            : <fg=red;options=bold>{$this->countFail}</>");
        $this->line("Error (ERROR)           : <fg=yellow;options=bold>{$this->countError}</>");
        $this->line("Terbunuh (KILLED)       : <fg=red;options=bold>{$this->countKilled}</>");
        $this->line("Dilewati (SKIP)         : <fg=gray;options=bold>{$this->countSkip}</>");
        $this->line("Skenario Terautentikasi : <fg=white;options=bold>{$this->okAuthScenarios} dari {$this->totalAuthScenarios}</>");
        $this->line("Puncak Memori Akhir     : <fg=magenta;options=bold>" . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB</>");
        $this->line("File Laporan CSV        : <fg=cyan>{$csvPath}</>");

        if ($this->countError > 0 || $this->countFail > 0 || $this->countKilled > 0) {
            $this->warn("\nPERINGATAN: Ditemukan kegagalan, error, atau proses terbunuh pada pengujian teknis!");
            return 1;
        }

        $this->info("\nSEMUA PENGUJIAN TEKNIS LOLOS DENGAN SUKSES (100% OK)!");
        return 0;
    }

    /**
     * Skenario A Pusat: Admin Mode Pusat.
     */
    protected function runScenarioAPusat(User $admin, array $routes): void
    {
        $this->line("\n<info>▶ Menjalankan Skenario A: Smoke Route GET Admin (Mode Pusat)</info>");

        foreach ($routes as $route) {
            $uri = '/' . ltrim($route->uri(), '/');

            // K1: Lewati rute ekspor secara default
            if ($this->isExportRoute($route) && !$this->option('with-exports')) {
                $this->recordResult('A. Admin Pusat', $uri, 'Pusat (all)', 0, 'SKIP', 'SKIP-EXPORT (Gunakan --with-exports untuk menguji)');
                continue;
            }

            $res = $this->executeWebRequest($uri, $admin, 'all');
            $http = $res['status'];
            $this->totalAuthScenarios++;

            if ($http >= 200 && $http < 300) {
                $status = 'OK';
                $msg = 'Sukses (200)';
                $this->okAuthScenarios++;
            } elseif ($http === 302) {
                $location = $res['location'] ?? '';
                if (str_contains(strtolower($location), 'login')) {
                    $status = 'FAIL';
                    $msg = "Tidak terautentikasi: dialihkan ke login ({$location})";
                } else {
                    $status = 'OK';
                    $msg = "Redirect wajar (302) ke {$location}";
                    $this->okAuthScenarios++;
                }
            } elseif ($http >= 500) {
                $status = 'ERROR';
                $msg = $res['error_details'] ?? '500 Internal Server Error';
            } else {
                $status = 'FAIL';
                $msg = "HTTP {$http}: " . ($res['error_details'] ?? 'Unexpected response');
            }

            $this->recordResult('A. Admin Pusat', $uri, 'Pusat (all)', $http, $status, $msg);
        }

        $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->line("  [Memori Puncak Skenario A Pusat: {$peakMb} MB]");
    }

    /**
     * Skenario A Cabang: Admin Mode Cabang.
     */
    protected function runScenarioACabang(User $admin, Branch $branch, array $routes): void
    {
        $this->line("\n<info>▶ Menjalankan Skenario A: Smoke Route GET Admin (Mode Cabang {$branch->code})</info>");

        foreach ($routes as $route) {
            $uri = '/' . ltrim($route->uri(), '/');

            if ($this->isExportRoute($route) && !$this->option('with-exports')) {
                $this->recordResult('A. Admin Cabang', $uri, "Cabang ({$branch->code})", 0, 'SKIP', 'SKIP-EXPORT (Gunakan --with-exports untuk menguji)');
                continue;
            }

            $res = $this->executeWebRequest($uri, $admin, $branch->id);
            $http = $res['status'];
            $this->totalAuthScenarios++;

            if ($http >= 200 && $http < 300) {
                $status = 'OK';
                $msg = 'Sukses (200)';
                $this->okAuthScenarios++;
            } elseif ($http === 302) {
                $location = $res['location'] ?? '';
                if (str_contains(strtolower($location), 'login')) {
                    $status = 'FAIL';
                    $msg = "Tidak terautentikasi: dialihkan ke login ({$location})";
                } else {
                    $status = 'OK';
                    $msg = "Redirect wajar (302) ke {$location}";
                    $this->okAuthScenarios++;
                }
            } elseif ($http >= 500) {
                $status = 'ERROR';
                $msg = $res['error_details'] ?? '500 Internal Server Error';
            } else {
                $status = 'FAIL';
                $msg = "HTTP {$http}: " . ($res['error_details'] ?? 'Unexpected response');
            }

            $this->recordResult('A. Admin Cabang', $uri, "Cabang ({$branch->code})", $http, $status, $msg);
        }

        $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->line("  [Memori Puncak Skenario A Cabang: {$peakMb} MB]");
    }

    /**
     * Skenario B: Smoke semua route GET kasir di cabang aktif.
     */
    protected function runScenarioB(User $kasir, Branch $branch, array $routes): void
    {
        $this->line("\n<info>▶ Menjalankan Skenario B: Smoke Route GET Kasir di Cabang Aktif ({$branch->code})</info>");

        foreach ($routes as $route) {
            $uri = '/' . ltrim($route->uri(), '/');

            if ($this->isExportRoute($route) && !$this->option('with-exports')) {
                $this->recordResult('B. Kasir Cabang', $uri, "Cabang ({$branch->code})", 0, 'SKIP', 'SKIP-EXPORT (Gunakan --with-exports untuk menguji)');
                continue;
            }

            $res = $this->executeWebRequest($uri, $kasir, $branch->id);
            $http = $res['status'];
            $this->totalAuthScenarios++;

            if ($http >= 200 && $http < 300) {
                $status = 'OK';
                $msg = 'Sukses (200)';
                $this->okAuthScenarios++;
            } elseif ($http === 302) {
                $location = $res['location'] ?? '';
                if (str_contains(strtolower($location), 'login')) {
                    $status = 'FAIL';
                    $msg = "Tidak terautentikasi: dialihkan ke login ({$location})";
                } else {
                    $status = 'OK';
                    $msg = "Redirect wajar (302) ke {$location}";
                    $this->okAuthScenarios++;
                }
            } elseif ($http >= 500) {
                $status = 'ERROR';
                $msg = $res['error_details'] ?? '500 Internal Server Error';
            } else {
                $status = 'FAIL';
                $msg = "HTTP {$http}: " . ($res['error_details'] ?? 'Unexpected response');
            }

            $this->recordResult('B. Kasir Cabang', $uri, "Cabang ({$branch->code})", $http, $status, $msg);
        }

        $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->line("  [Memori Puncak Skenario B: {$peakMb} MB]");
    }

    /**
     * Skenario C Cabang: Kasir dengan sesi cabang mengakses rute admin.
     */
    protected function runScenarioCCabang(User $kasir, Branch $branch, array $adminRoutes): void
    {
        $this->line("\n<info>▶ Menjalankan Skenario C: Batas Role (Kasir Cabang -> Route Admin)</info>");

        foreach ($adminRoutes as $route) {
            $uri = '/' . ltrim($route->uri(), '/');
            $res = $this->executeWebRequest($uri, $kasir, $branch->id);
            $http = $res['status'];
            $this->totalAuthScenarios++;

            if ($http === 403) {
                $status = 'OK';
                $msg = 'Ditolak dengan 403 Forbidden';
                $this->okAuthScenarios++;
            } elseif ($http === 302) {
                $status = 'OK';
                $location = $res['location'] ?? 'login';
                $msg = "Akses ditolak & dialihkan secara aman (302) ke {$location}";
                $this->okAuthScenarios++;
            } elseif ($http >= 500) {
                $status = 'ERROR';
                $msg = $res['error_details'] ?? '500 Internal Server Error';
            } else {
                $status = 'FAIL';
                $msg = "PELANGGARAN ROLE: Kasir cabang berhasil mengakses halaman admin (HTTP {$http})!";
            }

            $this->recordResult('C. Batas Role', $uri, 'Kasir (Cabang) -> Admin', $http, $status, $msg);
        }

        $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->line("  [Memori Puncak Skenario C Cabang: {$peakMb} MB]");
    }

    /**
     * Skenario C Pusat: Kasir tanpa cabang mengakses rute admin.
     */
    protected function runScenarioCPusat(User $kasir, array $adminRoutes): void
    {
        $this->line("\n<info>▶ Menjalankan Skenario C: Batas Role (Kasir No-Branch -> Route Admin)</info>");

        foreach ($adminRoutes as $route) {
            $uri = '/' . ltrim($route->uri(), '/');
            $res = $this->executeWebRequest($uri, $kasir, null);
            $http = $res['status'];
            $this->totalAuthScenarios++;

            if ($http === 403) {
                $status = 'OK';
                $msg = 'Ditolak dengan 403 Forbidden';
                $this->okAuthScenarios++;
            } elseif ($http === 302) {
                $status = 'OK';
                $location = $res['location'] ?? 'login';
                $msg = "Akses ditolak & dialihkan secara aman (302) ke {$location}";
                $this->okAuthScenarios++;
            } elseif ($http >= 500) {
                $status = 'ERROR';
                $msg = $res['error_details'] ?? '500 Internal Server Error';
            } else {
                $status = 'FAIL';
                $msg = "PELANGGARAN ROLE: Kasir berhasil mengakses halaman admin (HTTP {$http})!";
            }

            $this->recordResult('C. Batas Role', $uri, 'Kasir (No-Branch) -> Admin', $http, $status, $msg);
        }

        $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->line("  [Memori Puncak Skenario C Pusat: {$peakMb} MB]");
    }

    /**
     * Skenario D: Uji Kontrol Positif & Kerentanan IDOR (H2.e).
     */
    protected function runScenarioD(User $admin, User $kasir, Branch $kasirBranch, int $businessId, ?int $otherBusinessId): void
    {
        $this->line("\n<info>▶ Menjalankan Skenario D: Kontrol Positif & Uji Kerentanan IDOR</info>");

        $myBranchTx = Transaction::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('branch_id', $kasirBranch->id)
            ->latest('id')
            ->first();

        $unpaidBranchTx = Transaction::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('branch_id', $kasirBranch->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->latest('id')
            ->first();

        $branchExpense = CashFlow::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('branch_id', $kasirBranch->id)
            ->where('type', 'expense')
            ->latest('id')
            ->first();

        $controlOk = true;

        if ($myBranchTx) {
            $uri = "/kasir/transactions/{$myBranchTx->id}";
            $res = $this->executeWebRequest($uri, $kasir, $kasirBranch->id);
            $this->totalAuthScenarios++;
            $status = ($res['status'] === 200) ? 'OK' : ($res['status'] >= 500 ? 'ERROR' : 'FAIL');
            if ($status === 'OK') $this->okAuthScenarios(); else $controlOk = false;
            $this->recordResult('D. Kontrol Positif', $uri, 'Kasir -> Trx Sendiri', $res['status'], $status, $res['error_details'] ?? 'Akses data cabang sendiri');

            $uriEdit = "/kasir/transactions/{$myBranchTx->id}/edit";
            $resEdit = $this->executeWebRequest($uriEdit, $kasir, $kasirBranch->id);
            $this->totalAuthScenarios++;
            // Kasir boleh edit jika dibuat < 15 menit lalu oleh dirinya sendiri (TransactionPolicy).
            // Jika > 15 menit atau bukan pembuat, TransactionPolicy menolak dengan 403 Forbidden (kebijakan valid).
            $isAllowedByPolicy = ($resEdit['status'] === 200);
            $isDeniedByPolicy = ($resEdit['status'] === 403);
            $statusEdit = ($isAllowedByPolicy || $isDeniedByPolicy) ? 'OK' : ($resEdit['status'] >= 500 ? 'ERROR' : 'FAIL');
            $msgEdit = $isAllowedByPolicy
                ? 'Edit form cabang sendiri terbuka'
                : ($isDeniedByPolicy ? 'Ditolak Policy (403): Hanya pembuat trx < 15 menit yang boleh edit' : ($resEdit['error_details'] ?? 'Edit form gagal'));
            if ($statusEdit === 'OK') $this->okAuthScenarios();
            $this->recordResult('D. Kontrol Positif', $uriEdit, 'Kasir -> Edit Trx Sendiri', $resEdit['status'], $statusEdit, $msgEdit);

            $uriReceipt = "/kasir/pos/receipt/{$myBranchTx->id}";
            $resReceipt = $this->executeWebRequest($uriReceipt, $kasir, $kasirBranch->id);
            $this->totalAuthScenarios++;
            $statusReceipt = ($resReceipt['status'] === 200) ? 'OK' : ($resReceipt['status'] >= 500 ? 'ERROR' : 'FAIL');
            if ($statusReceipt === 'OK') $this->okAuthScenarios(); else $controlOk = false;
            $this->recordResult('D. Kontrol Positif', $uriReceipt, 'Kasir -> Struk Sendiri', $resReceipt['status'], $statusReceipt, $resReceipt['error_details'] ?? 'Cetak struk lunas cabang sendiri');

            $uriAdmBr = "/admin/transactions/{$myBranchTx->id}";
            $resAdmBr = $this->executeWebRequest($uriAdmBr, $admin, $kasirBranch->id);
            $this->totalAuthScenarios++;
            $statusAdmBr = ($resAdmBr['status'] === 200) ? 'OK' : ($resAdmBr['status'] >= 500 ? 'ERROR' : 'FAIL');
            if ($statusAdmBr === 'OK') $this->okAuthScenarios(); else $controlOk = false;
            $this->recordResult('D. Kontrol Positif', $uriAdmBr, 'Admin (Cabang) -> Trx', $resAdmBr['status'], $statusAdmBr, $resAdmBr['error_details'] ?? 'Admin pantau trx cabang');

            $uriAdmPusat = "/admin/transactions/{$myBranchTx->id}";
            $resAdmPusat = $this->executeWebRequest($uriAdmPusat, $admin, 'all');
            $this->totalAuthScenarios++;
            $statusAdmPusat = ($resAdmPusat['status'] === 200) ? 'OK' : ($resAdmPusat['status'] >= 500 ? 'ERROR' : 'FAIL');
            if ($statusAdmPusat === 'OK') $this->okAuthScenarios(); else $controlOk = false;
            $this->recordResult('D. Kontrol Positif', $uriAdmPusat, 'Admin (Pusat) -> Trx', $resAdmPusat['status'], $statusAdmPusat, $resAdmPusat['error_details'] ?? 'Admin konsolidasi trx');
        } else {
            $this->recordResult('D. Kontrol Positif', 'N/A', 'Kasir -> Trx Sendiri', 0, 'SKIP', "Skip: Tidak ada data transaksi di cabang {$kasirBranch->code}");
            $controlOk = false;
        }

        if ($unpaidBranchTx) {
            $uriUnpaid = "/kasir/pos/receipt-unpaid/{$unpaidBranchTx->id}";
            $resUnpaid = $this->executeWebRequest($uriUnpaid, $kasir, $kasirBranch->id);
            $this->totalAuthScenarios++;
            $statusUnpaid = ($resUnpaid['status'] === 200) ? 'OK' : ($resUnpaid['status'] >= 500 ? 'ERROR' : 'FAIL');
            if ($statusUnpaid === 'OK') $this->okAuthScenarios();
            $this->recordResult('D. Kontrol Positif', $uriUnpaid, 'Kasir -> Struk Belum Lunas', $resUnpaid['status'], $statusUnpaid, $resUnpaid['error_details'] ?? 'Cetak struk kasbon');
        } else {
            $this->recordResult('D. Kontrol Positif', 'N/A', 'Kasir -> Struk Belum Lunas', 0, 'SKIP', 'Skip: Tidak ada transaksi kasbon/unpaid di cabang ini');
        }

        if ($branchExpense) {
            $uriExpEdit = "/admin/financial/expenses/{$branchExpense->id}/edit";
            $resExpEdit = $this->executeWebRequest($uriExpEdit, $admin, $kasirBranch->id);
            $this->totalAuthScenarios++;
            $statusExpEdit = ($resExpEdit['status'] === 200) ? 'OK' : ($resExpEdit['status'] >= 500 ? 'ERROR' : 'FAIL');
            if ($statusExpEdit === 'OK') $this->okAuthScenarios();
            $this->recordResult('D. Kontrol Positif', $uriExpEdit, 'Admin (Cabang) -> Edit Biaya', $resExpEdit['status'], $statusExpEdit, $resExpEdit['error_details'] ?? 'Edit form biaya cabang');
        } else {
            $this->recordResult('D. Kontrol Positif', 'N/A', 'Admin -> Edit Biaya Cabang', 0, 'SKIP', 'Skip: Tidak ada pengeluaran kas di cabang ini');
        }

        // D2. Lintas Cabang
        $otherBranch = Branch::where('business_id', $businessId)
            ->where('id', '!=', $kasirBranch->id)
            ->where('is_active', true)
            ->first();

        if ($otherBranch) {
            $txOtherBranch = Transaction::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('branch_id', $otherBranch->id)
                ->latest('id')
                ->first();

            if ($txOtherBranch) {
                $uriOther = "/kasir/transactions/{$txOtherBranch->id}";
                $resOther = $this->executeWebRequest($uriOther, $kasir, $kasirBranch->id);
                $this->totalAuthScenarios++;
                $isIsolated = in_array($resOther['status'], [403, 404], true);
                $statusOther = $isIsolated ? 'OK' : ($resOther['status'] >= 500 ? 'ERROR' : 'FAIL');
                if ($statusOther === 'OK') $this->okAuthScenarios();
                $msgOther = $isIsolated
                    ? "Ditolak ({$resOther['status']}): Transaksi cabang {$otherBranch->code} terisolasi"
                    : "IDOR BOCOR: Kasir {$kasirBranch->code} membuka transaksi cabang {$otherBranch->code} (HTTP {$resOther['status']})";
                $this->recordResult('D. IDOR Cabang', $uriOther, 'Kasir -> Cabang Lain', $resOther['status'], $statusOther, $msgOther);

                $uriOtherEdit = "/kasir/transactions/{$txOtherBranch->id}/edit";
                $resOtherEdit = $this->executeWebRequest($uriOtherEdit, $kasir, $kasirBranch->id);
                $this->totalAuthScenarios++;
                $isIsolatedEdit = in_array($resOtherEdit['status'], [403, 404], true);
                $statusOtherEdit = $isIsolatedEdit ? 'OK' : ($resOtherEdit['status'] >= 500 ? 'ERROR' : 'FAIL');
                if ($statusOtherEdit === 'OK') $this->okAuthScenarios();
                $msgOtherEdit = $isIsolatedEdit
                    ? "Ditolak ({$resOtherEdit['status']}): Edit transaksi cabang {$otherBranch->code} terisolasi"
                    : "IDOR BOCOR: Kasir membuka form edit cabang lain (HTTP {$resOtherEdit['status']})";
                $this->recordResult('D. IDOR Cabang', $uriOtherEdit, 'Kasir -> Edit Cabang Lain', $resOtherEdit['status'], $statusOtherEdit, $msgOtherEdit);
            } else {
                $this->recordResult('D. IDOR Cabang', 'N/A', 'Kasir -> Cabang Lain', 0, 'SKIP', "Skip: Cabang {$otherBranch->code} tidak memiliki transaksi");
            }
        } else {
            $this->recordResult('D. IDOR Cabang', 'N/A', 'Lintas Cabang', 0, 'SKIP', 'Skip: Bisnis ini hanya memiliki 1 cabang aktif');
        }

        // D3. Lintas Tenant
        if ($otherBusinessId) {
            $txOtherBiz = Transaction::withoutGlobalScopes()
                ->where('business_id', $otherBusinessId)
                ->latest('id')
                ->first();

            if ($txOtherBiz) {
                $uriTxBiz = "/admin/transactions/{$txOtherBiz->id}";
                $resTxBiz = $this->executeWebRequest($uriTxBiz, $admin, 'all');
                $this->totalAuthScenarios++;
                $isTenantIsolated = in_array($resTxBiz['status'], [403, 404], true);
                $statusTxBiz = $isTenantIsolated ? 'OK' : ($resTxBiz['status'] >= 500 ? 'ERROR' : 'FAIL');
                if ($statusTxBiz === 'OK') $this->okAuthScenarios();
                $msgTxBiz = $isTenantIsolated
                    ? "Ditolak ({$resTxBiz['status']}): Data transaksi tenant {$otherBusinessId} terisolasi"
                    : "IDOR BOCOR: Admin membuka transaksi business ID {$otherBusinessId} (HTTP {$resTxBiz['status']})";
                $this->recordResult('D. IDOR Tenant', $uriTxBiz, 'Admin -> Other Biz Trx', $resTxBiz['status'], $statusTxBiz, $msgTxBiz);

                $uriTxKasir = "/kasir/transactions/{$txOtherBiz->id}";
                $resTxKasir = $this->executeWebRequest($uriTxKasir, $kasir, $kasirBranch->id);
                $this->totalAuthScenarios++;
                $isKasirTenantIsolated = in_array($resTxKasir['status'], [403, 404], true);
                $statusTxKasir = $isKasirTenantIsolated ? 'OK' : ($resTxKasir['status'] >= 500 ? 'ERROR' : 'FAIL');
                if ($statusTxKasir === 'OK') $this->okAuthScenarios();
                $msgTxKasir = $isKasirTenantIsolated
                    ? "Ditolak ({$resTxKasir['status']}): Data transaksi tenant {$otherBusinessId} terisolasi"
                    : "IDOR BOCOR: Kasir membuka transaksi business ID {$otherBusinessId} (HTTP {$resTxKasir['status']})";
                $this->recordResult('D. IDOR Tenant', $uriTxKasir, 'Kasir -> Other Biz Trx', $resTxKasir['status'], $statusTxKasir, $msgTxKasir);
            } else {
                $this->recordResult('D. IDOR Tenant', 'N/A', 'Trx Other Biz', 0, 'SKIP', "Skip: Tenant pembanding ID {$otherBusinessId} tidak memiliki transaksi");
            }

            $custOtherBiz = Customer::withoutGlobalScopes()
                ->where('business_id', $otherBusinessId)
                ->latest('id')
                ->first();

            if ($custOtherBiz) {
                $uriCust = "/admin/customers/{$custOtherBiz->id}";
                $resCust = $this->executeWebRequest($uriCust, $admin, 'all');
                $this->totalAuthScenarios++;
                $isCustIsolated = in_array($resCust['status'], [403, 404], true);
                $statusCust = $isCustIsolated ? 'OK' : ($resCust['status'] >= 500 ? 'ERROR' : 'FAIL');
                if ($statusCust === 'OK') $this->okAuthScenarios();
                $msgCust = $isCustIsolated
                    ? "Ditolak ({$resCust['status']}): Data pelanggan tenant {$otherBusinessId} terlindungi"
                    : "IDOR BOCOR: Data customer tenant lain terbuka (HTTP {$resCust['status']})";
                $this->recordResult('D. IDOR Tenant', $uriCust, 'Admin -> Customer Other Biz', $resCust['status'], $statusCust, $msgCust);
            } else {
                $this->recordResult('D. IDOR Tenant', 'N/A', 'Customer Other Biz', 0, 'SKIP', "Skip: Tenant pembanding ID {$otherBusinessId} tidak memiliki customer");
            }
        } else {
            $this->recordResult('D. IDOR Tenant', 'N/A', 'Lintas Business', 0, 'SKIP', 'Skip: Opsi --other-business tidak tersedia');
        }

        $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->line("  [Memori Puncak Skenario D: {$peakMb} MB]");
    }

    /**
     * Skenario E: API kasir (route GET di api/v1/kasir) & Header X-Branch-Id (Sanctum Asli).
     */
    protected function runScenarioE(User $kasir, Branch $kasirBranch, int $businessId, ?int $otherBusinessId): void
    {
        $this->line("\n<info>▶ Menjalankan Skenario E: Uji API Kasir (api/v1/kasir) & Header X-Branch-Id</info>");

        $apiRoutes = [
            '/api/v1/kasir/pos-data',
            '/api/v1/kasir/ai-orders',
            '/api/v1/kasir/orders/delivery',
        ];

        $accessibleCount = count(app(BranchContext::class)->accessibleBranchIds($kasir));

        foreach ($apiRoutes as $uri) {
            $resNoHeader = $this->executeApiRequest($uri, $kasir, null);
            $httpNoHeader = $resNoHeader['status'];
            $this->totalAuthScenarios++;

            if ($accessibleCount === 1) {
                $status = ($httpNoHeader === 200) ? 'OK' : ($httpNoHeader >= 500 ? 'ERROR' : 'FAIL');
                $msg = ($status === 'ERROR')
                    ? "ERROR: " . ($resNoHeader['error_details'] ?? "HTTP {$httpNoHeader}")
                    : "Single-branch kasir: auto-assigned cabang (HTTP {$httpNoHeader})";
            } else {
                $status = ($httpNoHeader === 422) ? 'OK' : ($httpNoHeader >= 500 ? 'ERROR' : 'FAIL');
                $msg = ($status === 'ERROR')
                    ? "ERROR: " . ($resNoHeader['error_details'] ?? "HTTP {$httpNoHeader}")
                    : "Multi-branch kasir: wajib sertakan X-Branch-Id (HTTP {$httpNoHeader})";
            }
            if ($status === 'OK') $this->okAuthScenarios();
            $this->recordResult('E. API Kasir', $uri, 'Tanpa Header', $httpNoHeader, $status, $msg);

            $resInvalid = $this->executeApiRequest($uri, $kasir, '999999');
            $httpInvalid = $resInvalid['status'];
            $this->totalAuthScenarios++;
            $statusInvalid = ($httpInvalid === 403) ? 'OK' : ($httpInvalid >= 500 ? 'ERROR' : 'FAIL');
            if ($statusInvalid === 'OK') $this->okAuthScenarios();
            $msgInvalid = ($statusInvalid === 'ERROR')
                ? "ERROR: " . ($resInvalid['error_details'] ?? "HTTP {$httpInvalid}")
                : "ID cabang 999999 ditolak (HTTP {$httpInvalid})";
            $this->recordResult('E. API Kasir', $uri, 'X-Branch-Id: 999999', $httpInvalid, $statusInvalid, $msgInvalid);

            if ($otherBusinessId) {
                $otherBizBranch = Branch::where('business_id', $otherBusinessId)->first();
                if ($otherBizBranch) {
                    $resOtherBiz = $this->executeApiRequest($uri, $kasir, (string) $otherBizBranch->id);
                    $httpOtherBiz = $resOtherBiz['status'];
                    $this->totalAuthScenarios++;
                    $statusOtherBiz = ($httpOtherBiz === 403) ? 'OK' : ($httpOtherBiz >= 500 ? 'ERROR' : 'FAIL');
                    if ($statusOtherBiz === 'OK') $this->okAuthScenarios();
                    $msgOtherBiz = ($statusOtherBiz === 'ERROR')
                        ? "ERROR: " . ($resOtherBiz['error_details'] ?? "HTTP {$httpOtherBiz}")
                        : "Cabang tenant lain ditolak (HTTP {$httpOtherBiz})";
                    $this->recordResult('E. API Kasir', $uri, "X-Branch-Id: {$otherBizBranch->id} (Other Biz)", $httpOtherBiz, $statusOtherBiz, $msgOtherBiz);
                }
            }

            $inactiveBranch = Branch::where('business_id', $businessId)->where('is_active', false)->first();
            if ($inactiveBranch) {
                $resInactive = $this->executeApiRequest($uri, $kasir, (string) $inactiveBranch->id);
                $httpInactive = $resInactive['status'];
                $this->totalAuthScenarios++;
                $statusInactive = ($httpInactive === 403) ? 'OK' : ($httpInactive >= 500 ? 'ERROR' : 'FAIL');
                if ($statusInactive === 'OK') $this->okAuthScenarios();
                $msgInactive = ($statusInactive === 'ERROR')
                    ? "ERROR: " . ($resInactive['error_details'] ?? "HTTP {$httpInactive}")
                    : "Cabang nonaktif ditolak (HTTP {$httpInactive})";
                $this->recordResult('E. API Kasir', $uri, "X-Branch-Id: {$inactiveBranch->id} (Inactive)", $httpInactive, $statusInactive, $msgInactive);
            }
        }

        $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->line("  [Memori Puncak Skenario E: {$peakMb} MB]");
    }

    /**
     * Skenario F: User tanpa cabang dan user tanpa business.
     */
    protected function runScenarioF(int $businessId): void
    {
        $this->line("\n<info>▶ Menjalankan Skenario F: User Tanpa Cabang dan User Tanpa Business</info>");

        DB::beginTransaction();
        try {
            $orphanKasir = new User();
            $orphanKasir->business_id = $businessId;
            $orphanKasir->name = 'Smoke Orphan Kasir';
            $orphanKasir->email = 'smoke_orphan_' . Str::random(8) . '@example.test';
            $orphanKasir->password = bcrypt('secret123');
            $orphanKasir->role = 'kasir';
            $orphanKasir->branch_id = null;
            $orphanKasir->is_active = true;
            $orphanKasir->save();

            $resWeb = $this->executeWebRequest('/kasir/pos', $orphanKasir, null);
            $httpWeb = $resWeb['status'];
            $this->totalAuthScenarios++;
            $statusWeb = ($httpWeb === 403 || $httpWeb === 302) ? 'OK' : ($httpWeb >= 500 ? 'ERROR' : 'FAIL');
            if ($statusWeb === 'OK') $this->okAuthScenarios();
            $msgWeb = ($statusWeb === 'OK') ? "Ditolak/dialihkan secara aman (HTTP {$httpWeb})" : "BOCOR/ERROR: User tanpa cabang mendapat HTTP {$httpWeb}";
            $this->recordResult('F. Tanpa Cabang', '/kasir/pos', 'Kasir branch_id=null', $httpWeb, $statusWeb, $msgWeb);

            $resApi = $this->executeApiRequest('/api/v1/kasir/pos-data', $orphanKasir, null);
            $httpApi = $resApi['status'];
            $this->totalAuthScenarios++;
            $statusApi = ($httpApi === 403 || $httpApi === 422) ? 'OK' : ($httpApi >= 500 ? 'ERROR' : 'FAIL');
            if ($statusApi === 'OK') $this->okAuthScenarios();
            $msgApi = ($statusApi === 'OK')
                ? "Ditolak 403/422 secara aman"
                : (($statusApi === 'ERROR') ? "ERROR: " . ($resApi['error_details'] ?? "HTTP {$httpApi}") : "BOCOR/ERROR: API kasir tanpa cabang mendapat HTTP {$httpApi}");
            $this->recordResult('F. Tanpa Cabang', '/api/v1/kasir/pos-data', 'API Kasir branch_id=null', $httpApi, $statusApi, $msgApi);

            $noBizUser = new User();
            $noBizUser->business_id = null;
            $noBizUser->name = 'Smoke No Business User';
            $noBizUser->email = 'smoke_nobiz_' . Str::random(8) . '@example.test';
            $noBizUser->password = bcrypt('secret123');
            $noBizUser->role = 'kasir';
            $noBizUser->branch_id = null;
            $noBizUser->is_active = true;
            $noBizUser->save();

            $resNoBiz = $this->executeWebRequest('/kasir/dashboard', $noBizUser, null);
            $httpNoBiz = $resNoBiz['status'];
            $this->totalAuthScenarios++;
            $statusNoBiz = ($httpNoBiz === 403 || $httpNoBiz === 302) ? 'OK' : ($httpNoBiz >= 500 ? 'ERROR' : 'FAIL');
            if ($statusNoBiz === 'OK') $this->okAuthScenarios();
            $msgNoBiz = ($statusNoBiz === 'OK') ? "Ditolak/dialihkan secara aman (HTTP {$httpNoBiz})" : "BOCOR/ERROR: User business_id=null mendapat HTTP {$httpNoBiz}";
            $this->recordResult('F. Tanpa Business', '/kasir/dashboard', 'User business_id=null', $httpNoBiz, $statusNoBiz, $msgNoBiz);
        } finally {
            DB::rollBack();
        }

        $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->line("  [Memori Puncak Skenario F: {$peakMb} MB]");
    }

    /**
     * Skenario G: Dry-run tutup buku bulanan konsolidasi.
     */
    protected function runScenarioG(User $admin, int $businessId): void
    {
        $this->line("\n<info>▶ Menjalankan Skenario G: Dry-Run Tutup Buku Bulanan Konsolidasi</info>");

        DB::beginTransaction();
        try {
            $latestPeriod = DB::table('transactions')
                ->where('business_id', $businessId)
                ->where('status', 'completed')
                ->where('type', 'sale')
                ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as period")
                ->orderByDesc('transaction_date')
                ->value('period');

            if (!$latestPeriod) {
                $latestPeriod = now()->format('Y-m');
            }

            $financialService = app(FinancialService::class);
            $res = $financialService->processMonthlyClosing($latestPeriod, $businessId, $admin->id, true);

            $bIncome = (float) ($res['total_branch_income'] ?? 0);
            $bGross  = (float) ($res['total_branch_gross'] ?? 0);
            $bExp    = (float) ($res['total_branch_expense'] ?? 0);
            $hExp    = (float) ($res['total_holding_expense'] ?? 0);
            $cIncome = (float) ($res['consolidated_income'] ?? 0);
            $cGross  = (float) ($res['consolidated_gross'] ?? 0);
            $cExp    = (float) ($res['consolidated_expense'] ?? 0);
            $cNet    = (float) ($res['consolidated_net_profit'] ?? 0);

            $diffIncome  = abs($cIncome - $bIncome);
            $diffGross   = abs($cGross - $bGross);
            $diffExp     = abs($cExp - ($bExp + $hExp));
            $expectedNet = $cGross - $cExp;
            $diffNet     = abs($cNet - $expectedNet);

            $totalDiff   = $diffIncome + $diffGross + $diffExp + $diffNet;
            $branchCount = count($res['branches'] ?? []);

            $this->totalAuthScenarios++;

            if ($totalDiff < 0.01) {
                $status = 'OK';
                $msg = "Periode {$latestPeriod}: {$branchCount} cabang, Net Konsolidasi Rp " . number_format($cNet, 0, ',', '.') . " (Selisih: Rp 0,00)";
                $this->okAuthScenarios++;
            } else {
                $status = 'FAIL';
                $msg = "SELISIH DITEMUKAN: Income diff: {$diffIncome}, Gross diff: {$diffGross}, Exp diff: {$diffExp}, Net diff: {$diffNet}";
            }

            $this->recordResult('G. Tutup Buku Dry-Run', "Period {$latestPeriod}", 'Admin Konsolidasi', 200, $status, $msg);
        } catch (\Throwable $e) {
            $msg = get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
            $this->recordResult('G. Tutup Buku Dry-Run', 'Period ' . now()->format('Y-m'), 'Admin Konsolidasi', 500, 'ERROR', $msg);
        } finally {
            DB::rollBack();
        }

        $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->line("  [Memori Puncak Skenario G: {$peakMb} MB]");
    }

    /**
     * Eksekusi request web in-process.
     */
    protected function executeWebRequest(string $uri, User $user, mixed $branchSessionId): array
    {
        $session = app('session')->driver();
        $session->setId('smoke_' . Str::random(32));
        $session->start();
        $session->flush();

        if ($branchSessionId !== null) {
            $session->put('active_branch_id', $branchSessionId);
        }
        $session->put(Auth::guard('web')->getName(), $user->getAuthIdentifier());
        $session->save();

        $cookies = [$session->getName() => $session->getId()];
        $server = [
            'HTTP_ACCEPT'     => 'text/html,application/xhtml+xml,application/xml;q=0.9',
            'HTTP_USER_AGENT' => 'BranchSmokeCommand/1.0',
            'HTTP_HOST'       => 'localhost',
            'REQUEST_URI'     => $uri,
        ];

        $request = Request::create($uri, 'GET', [], $cookies, [], $server);
        $request->setLaravelSession($session);

        return $this->executeRequestInternal($request, $user, 'web');
    }

    /**
     * Eksekusi request API Sanctum in-process dengan token asli.
     */
    protected function executeApiRequest(string $uri, ?User $user, ?string $branchHeader): array
    {
        $server = [
            'HTTP_ACCEPT'     => 'application/json',
            'HTTP_USER_AGENT' => 'BranchSmokeCommand/1.0',
            'HTTP_HOST'       => 'localhost',
            'REQUEST_URI'     => $uri,
        ];

        if ($branchHeader !== null) {
            $server['HTTP_X_BRANCH_ID'] = (string) $branchHeader;
        }

        $request = Request::create($uri, 'GET', [], [], [], $server);

        return $this->executeRequestInternal($request, $user, 'sanctum');
    }

    /**
     * Pipeline eksekusi request internal dalam transaksi rollback dengan pembebasan memori ketat (K1).
     */
    protected function executeRequestInternal(Request $request, ?User $user, string $guard): array
    {
        // Jalankan garbage collector tiap 10 request (K1)
        static $reqCount = 0;
        $reqCount++;
        if ($reqCount % 10 === 0) {
            gc_collect_cycles();
        }

        DB::beginTransaction();
        try {
            app(\App\Support\BranchContext::class)->setNone();

            if ($user) {
                if ($guard === 'sanctum') {
                    $token = $user->createToken('smoke')->plainTextToken;
                    $request->headers->set('Authorization', 'Bearer ' . $token);
                    $request->server->set('HTTP_AUTHORIZATION', 'Bearer ' . $token);
                    Auth::guard('sanctum')->setUser($user);
                    Auth::shouldUse('sanctum');
                } else {
                    Auth::guard('web')->setUser($user);
                    Auth::shouldUse('web');
                }
            } else {
                Auth::forgetGuards();
                Auth::shouldUse('web');
            }

            /** @var \Illuminate\Contracts\Http\Kernel $kernel */
            $kernel = app(\Illuminate\Contracts\Http\Kernel::class);

            $response = $kernel->handle($request);
            $statusCode = $response->getStatusCode();
            $location = $response->headers->get('Location');
            $kernel->terminate($request, $response);

            $exception = $response->exception ?? null;
            $errorDetails = null;
            if ($exception instanceof \Throwable) {
                $errorDetails = get_class($exception) . ': ' . $exception->getMessage() . ' (' . basename($exception->getFile()) . ':' . $exception->getLine() . ')';
            }

            $res = [
                'status'        => $statusCode,
                'location'      => $location,
                'error_details' => $errorDetails,
            ];

            // Bebaskan memory dari payload response dan exception segera (K1)
            $response = null;
            $exception = null;
            unset($response, $exception);

            return $res;
        } catch (\Throwable $e) {
            return [
                'status'        => 500,
                'location'      => null,
                'error_details' => get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
            ];
        } finally {
            DB::rollBack();
            Auth::forgetGuards();
            Auth::shouldUse('web');
            try {
                if (app()->bound('session')) {
                    app('session')->driver()->flush();
                }
            } catch (\Throwable) {}
            app(\App\Support\BranchContext::class)->setNone();
            app()->forgetInstance(\App\Support\BranchContext::class);
        }
    }

    /**
     * Helper untuk increment auth counter.
     */
    protected function okAuthScenarios(): void
    {
        $this->okAuthScenarios++;
    }

    /**
     * Cek apakah route adalah rute ekspor/unduh (K1).
     */
    protected function isExportRoute(\Illuminate\Routing\Route $route): bool
    {
        $uri = strtolower($route->uri());
        $name = strtolower($route->getName() ?? '');

        return str_contains($uri, 'export') ||
               str_contains($uri, 'pdf') ||
               str_contains($uri, 'excel') ||
               str_contains($uri, 'download') ||
               str_contains($name, 'export') ||
               str_contains($name, 'pdf') ||
               str_contains($name, 'excel') ||
               str_contains($name, 'download');
    }

    /**
     * Cek apakah route harus dikecualikan dari general smoke scan.
     */
    protected function shouldExcludeRoute(\Illuminate\Routing\Route $route): bool
    {
        $uri = strtolower($route->uri());
        $name = strtolower($route->getName() ?? '');

        // Hanya method GET
        if (!in_array('GET', $route->methods(), true)) {
            return true;
        }

        // Pengecualian webhook, sync, cron, whatsapp
        if (str_contains($uri, 'webhook') ||
            str_contains($uri, 'sync') ||
            str_contains($uri, 'whatsapp') ||
            str_contains($uri, 'cron') ||
            str_contains($name, 'webhook') ||
            str_contains($name, 'sync') ||
            str_contains($name, 'whatsapp')) {
            return true;
        }

        // Pengecualian logout
        if (str_contains($uri, 'logout') || str_contains($name, 'logout')) {
            return true;
        }

        // Pengecualian route dengan parameter wajib {...} (diuji terpisah pada Skenario D)
        if (preg_match('/\{[a-zA-Z0-9_]+\}/', $uri)) {
            return true;
        }

        return false;
    }

    /**
     * Catat baris hasil pengujian dan update counter.
     */
    protected function recordResult(string $scenario, string $target, string $mode, int $http, string $status, string $message): void
    {
        $this->results[] = [
            'scenario' => $scenario,
            'target'   => $target,
            'mode'     => $mode,
            'http'     => $http,
            'status'   => $status,
            'message'  => $message,
        ];

        match ($status) {
            'OK'     => $this->countOk++,
            'FAIL'   => $this->countFail++,
            'ERROR'  => $this->countError++,
            'SKIP'   => $this->countSkip++,
            'KILLED' => $this->countKilled++,
            default  => null,
        };

        // Realtime console feedback: HANYA untuk status non-OK agar output ringkas (K1)
        if ($status !== 'OK') {
            $badge = match ($status) {
                'FAIL'   => '<fg=red>[FAIL]</>',
                'ERROR'  => '<fg=yellow>[ERROR]</>',
                'SKIP'   => '<fg=gray>[SKIP]</>',
                'KILLED' => '<fg=red;options=bold>[KILLED]</>',
                default  => "[{$status}]",
            };

            $httpStr = $http > 0 ? (string) $http : '-';
            $this->line("  {$badge} <fg=cyan>{$target}</> ({$mode}) => HTTP {$httpStr} | {$message}");
        }
    }

    /**
     * Ekspor hasil ke file CSV.
     */
    protected function exportCsv(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $handle = fopen($path, 'w');
        if (!$handle) {
            $this->warn("Gagal membuka file CSV di {$path} untuk penulisan.");
            return;
        }

        fputcsv($handle, ['Skenario', 'Route / Target', 'Role / Mode', 'HTTP Code', 'Status', 'Pesan']);

        foreach ($this->results as $row) {
            fputcsv($handle, [
                $row['scenario'],
                $row['target'],
                $row['mode'],
                $row['http'],
                $row['status'],
                $row['message'],
            ]);
        }

        fclose($handle);
    }
}
