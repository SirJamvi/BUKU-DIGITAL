<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\Whatsapp\FonnteService;
use App\Services\Whatsapp\PhoneNumberHelper;

class AdminGroupNotifier
{
    protected FonnteService $fonnteService;

    public function __construct(FonnteService $fonnteService)
    {
        $this->fonnteService = $fonnteService;
    }

    /**
     * Kirim pesan ke grup admin dengan pengaman try-catch, anti-spam, dan dry-run.
     */
    public function sendToAdminGroup(string $message, string $eventType, ?string $sender = null): bool
    {
        $enabled = config('ai_order.admin_notify', true);
        $groupId = config('ai_order.admin_group_id', '');

        if (!$enabled || empty($groupId)) {
            Log::info("[AdminGroupNotifier] Notifikasi admin dilewati: enabled=" . ($enabled ? 'true' : 'false') . ", group=" . ($groupId ?: 'KOSONG'));
            return false;
        }

        // Anti-spam deduplikasi per tipe per pengirim (15 menit)
        if ($sender) {
            $dedupKey = 'admin_notif_' . $eventType . '_' . PhoneNumberHelper::normalize($sender);
            if (!Cache::add($dedupKey, true, now()->addMinutes(15))) {
                Log::info("[AdminGroupNotifier] Notifikasi diduplikasi diabaikan: {$dedupKey}");
                return false;
            }
        }

        // Dry-run check
        $isDryRun = config('ai_order.dry_run', false);
        if ($isDryRun) {
            Log::info("[AdminGroupNotifier - DRY RUN] Kirim ke grup {$groupId}:\n{$message}");
            return true;
        }

        try {
            $result = $this->fonnteService->sendMessage($groupId, $message);
            Log::info("[AdminGroupNotifier] Berhasil kirim ke grup {$groupId}: {$eventType}");
            return true;
        } catch (\Throwable $e) {
            Log::warning("[AdminGroupNotifier] Gagal mengirim notifikasi ke grup {$groupId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * (a) Human Mode
     * Format: 🔔 [HUMAN MODE] Pelanggan {nama/nomor} meminta bantuan admin. Pesan: "{pesan}"
     */
    public function notifyHumanMode(string $customerName, string $sender, string $message): bool
    {
        $masked = PhoneNumberHelper::mask($sender);
        $label = ($customerName !== 'Kak' && !empty($customerName)) ? "{$customerName} ({$masked})" : $masked;
        $cleanMsg = trim(preg_replace('/\s+/', ' ', $message));
        if (strlen($cleanMsg) > 100) {
            $cleanMsg = substr($cleanMsg, 0, 97) . '...';
        }

        $text = "🔔 [HUMAN MODE] Pelanggan {$label} meminta bantuan admin. Pesan: \"{$cleanMsg}\"";
        return $this->sendToAdminGroup($text, 'human_mode', $sender);
    }

    /**
     * (b) Pelanggan Baru Terdaftar & Order Masuk
     * Format: 🆕 [PELANGGAN BARU] {nama_toko} ({alamat}) order {qty} ball {ukuran}. Status: menunggu kasir.
     */
    public function notifyNewCustomerOrder(string $namaToko, string $alamat, int $qty, string $ukuran, string $sender): bool
    {
        $text = "🆕 [PELANGGAN BARU] {$namaToko} ({$alamat}) order {$qty} ball {$ukuran}. Status: menunggu kasir.";
        return $this->sendToAdminGroup($text, 'new_customer_order', $sender);
    }

    /**
     * (c) Stok Tidak Cukup (Dilarang sebutkan angka stok riil)
     * Format: ⚠️ [STOK KURANG] Permintaan {qty} ball {ukuran} dari {nama/nomor} melebihi sisa stok.
     */
    public function notifyStockInsufficient(string $customerName, string $sender, int $qty, string $ukuran): bool
    {
        $masked = PhoneNumberHelper::mask($sender);
        $label = ($customerName !== 'Kak' && !empty($customerName)) ? "{$customerName} ({$masked})" : $masked;

        $text = "⚠️ [STOK KURANG] Permintaan {$qty} ball {$ukuran} dari {$label} melebihi sisa stok.";
        return $this->sendToAdminGroup($text, 'stock_insufficient', $sender);
    }

    /**
     * (d) Gemini Error & Fallback Aktif (Max 1x per 10 menit global)
     * Format: ⚠️ [SISTEM] Gemini API mengalami kendala, sistem beralih ke regex parser.
     */
    public function notifyGeminiFailure(string $errorMessage = ''): bool
    {
        // Rate-limit max 1x per 10 menit global
        $throttleKey = 'admin_notif_gemini_failure_throttle';
        if (!Cache::add($throttleKey, true, now()->addMinutes(10))) {
            return false;
        }

        $text = "⚠️ [SISTEM] Gemini API mengalami kendala, sistem beralih ke regex parser.";
        return $this->sendToAdminGroup($text, 'gemini_failure', null);
    }

    /**
     * Catat ID grup yang mengirim pesan 1x jika admin_group_id belum diisi di .env
     */
    public static function logDiscoveredGroupOnce(string $groupId): void
    {
        if (empty(config('ai_order.admin_group_id', '')) && str_ends_with($groupId, '@g.us')) {
            $key = 'logged_discovered_group_' . $groupId;
            if (Cache::add($key, true, now()->addHours(24))) {
                Log::notice("[AdminGroupNotifier] Terdeteksi pesan dari WhatsApp Group ID: {$groupId}. Pasang ini di AI_ORDER_ADMIN_GROUP_ID bila ini grup admin.");
            }
        }
    }
}
