<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchSwitchController extends Controller
{
    public function __construct(
        protected BranchContext $branchContext
    ) {}

    /**
     * Tampilan pemilihan cabang aktif saat login untuk staf multi-cabang.
     */
    public function selectBranchForm()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->isAdmin()) {
            return redirect()->route('redirect.dashboard');
        }

        $branches = $user->activeAccessibleBranches();

        if ($branches->isEmpty()) {
            Auth::logout();
            return redirect()->route('login')->withErrors([
                'email' => 'Semua cabang penugasan Anda nonaktif atau Anda belum ditugaskan. Hubungi Admin.',
            ]);
        }

        if ($branches->count() === 1) {
            $user->setActiveBranch($branches->first());
            return redirect()->route('redirect.dashboard');
        }

        return view('auth.branch-select', compact('branches'));
    }

    /**
     * Mengganti cabang aktif pada session (Pusat / Monitoring Cabang).
     */
    public function switchBranch(Request $request, $branchId)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Mode Semua Cabang (Konsolidasi Pusat) - Khusus Admin
        if ($branchId === 'all') {
            if (!$user->isAdmin()) {
                return back()->with('error', 'Akses ditolak. Hanya Admin yang dapat melihat laporan konsolidasi seluruh cabang.');
            }

            $user->setActiveBranch('all');
            $this->branchContext->setConsolidated($user->business_id);

            return redirect()->intended(route('admin.dashboard'))->with('success', 'Beralih ke mode Pusat (Konsolidasi Seluruh Cabang).');
        }

        // Mode Cabang Tertentu
        $branch = Branch::where('id', $branchId)
            ->where('business_id', $user->business_id)
            ->where('is_active', true)
            ->first();

        if (!$branch) {
            return back()->with('error', 'Cabang tidak ditemukan atau tidak aktif.');
        }

        // Validasi hak akses staf jika bukan admin
        if (!$user->isAdmin() && !$user->hasAccessToBranch($branch)) {
            return back()->with('error', 'Anda tidak memiliki hak akses untuk cabang ini.');
        }

        $user->setActiveBranch($branch);
        $this->branchContext->setBranch($branch->id, $user->business_id);

        if ($user->isAdmin()) {
            return back()->with('success', "Beralih ke monitoring cabang: {$branch->name} ({$branch->code}).");
        }

        $redirect = redirect()->intended(route('redirect.dashboard'))->with('success', "Cabang aktif: {$branch->name} ({$branch->code}).");

        // Cek apakah kasir belum menginput modal awal hari ini di cabang tujuan
        if ($user->role === 'kasir') {
            $today = \Carbon\Carbon::today('Asia/Jakarta')->toDateString();
            $hasOpeningInTarget = \App\Models\CashierOpeningBalance::where('user_id', $user->id)
                ->where('branch_id', $branch->id)
                ->whereDate('date', $today)
                ->exists();

            if (!$hasOpeningInTarget) {
                $redirect->with('info', "Belum ada modal awal di cabang ini hari ini.");
            }
        }

        return $redirect;
    }
}
