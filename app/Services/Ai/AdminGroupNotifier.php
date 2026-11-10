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
     * Format standar pesan notifikasi grup admin (Maks 5 baris, body maks 200 karakter).
     * Nomor telepon ditampilkan penuh format kanonik (digit saja) dan link wa.me.
     * Jika pengirim LID tanpa nomor, nama profil ditampilkan tanpa link dan tidak error.
     */
    public function formatAdminMessage(string $title, string $displayName, ?string $sender, ?string $body = null, ?int $silenceMinutes = null): string
    {
        $cleanPhone = $sender ? preg_replace('/[^0-9]/', '', $sender) : '';
        $hasValidPhone = !empty($cleanPhone) && strlen($cleanPhone) >= 9 && !str_starts_with((string) $sender, 'lid_');

        $lines = [];
        $lines[] = "🔔 [{$title}] {$displayName}";
        if ($hasValidPhone) {
            $lines[] = "📱 +{$cleanPhone}";
        }
        if (!empty($body)) {
            $cleanBody = trim(preg_replace('/\s+/', ' ', $body));
            $truncated = mb_strimwidth($cleanBody, 0, 200, '...');
            $lines[] = "💬 \"{$truncated}\"";
        }
        if ($hasValidPhone) {
            $lines[] = "👉 https://wa.me/{$cleanPhone}";
        }
        if ($silenceMinutes !== null && $silenceMinutes > 0) {
            $lines[] = "🤖 Bot hening {$silenceMinutes} menit";
        }

        return implode("\n", array_slice($lines, 0, 5));
    }

    /**
     * Kirim pesan ke grup admin dengan pengaman try-catch, anti-spam, dan dry-run.
     * PENTING: Kunci dedupe TIDAK BOLEH ditulis saat dry-run aktif!
     */
    public function sendToAdminGroup(string $message, string $eventType, ?string $sender = null, int $dedupeMinutes = 15): bool
    {
        $enabled = config('ai_order.admin_notify', true);
        $groupId = trim(config('ai_order.admin_group_id', ''));

        if (!$enabled) {
            Log::info("[AdminGroupNotifier] Notifikasi admin dilewati: enabled=false");
            return false;
        }

        // Dry-run check (PENTING: Jangan tulis kunci dedupe ke cache saat dry-run!)
        $isDryRun = config('ai_order.dry_run', false);
        if ($isDryRun) {
            $targetGroup = $groupId ?: 'DRY-RUN-GROUP';
            Log::info("[AdminGroupNotifier - DRY RUN] Kirim ke grup {$targetGroup}:\n{$message}");
            return true;
        }

        if (empty($groupId)) {
            Log::info("[AdminGroupNotifier] Notifikasi admin dilewati: group=KOSONG");
            return false;
        }

        // Pastikan akhiran @g.us ada jika format berupa numeric WhatsApp Group JID

        // Anti-spam deduplikasi per tipe per pengirim (hanya dijalankan saat LIVE)
        if ($sender) {
            $dedupKey = 'admin_notif_' . $eventType . '_' . PhoneNumberHelper::normalize($sender);
            if (!Cache::add($dedupKey, true, now()->addMinutes($dedupeMinutes))) {
                Log::info("[AdminGroupNotifier] Notifikasi diduplikasi diabaikan: {$dedupKey}");
                return false;
            }
        }

        try {
            $result = $this->fonnteService->sendMessage($groupId, $message);
            if (!($result['status'] ?? false)) {
                $reason = $result['response']['reason'] ?? ($result['error'] ?? 'Pengiriman Fonnte ditolak/gagal');
                Log::warning("[AdminGroupNotifier] Gagal mengirim notifikasi ke grup {$groupId}: {$reason}");
                return false;
            }
            Log::info("[AdminGroupNotifier] Berhasil kirim ke grup {$groupId}: {$eventType}");
            return true;
        } catch (\Throwable $e) {
            Log::warning("[AdminGroupNotifier] Gagal mengirim notifikasi ke grup {$groupId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * (a) Human Mode (Staf Diminta Bantuan)
     */
    public function notifyHumanMode(string $customerName, string $sender, string $message): bool
    {
        $silence = (int) config('ai_order.handoff_minutes', 15);
        $text = $this->formatAdminMessage('HUMAN MODE', $customerName, $sender, $message, $silence);
        return $this->sendToAdminGroup($text, 'human_mode', $sender);
    }

    /**
     * (a2) Komplain / Status Pengiriman
     */
    public function notifyDeliveryComplaint(string $customerName, string $sender, string $message): bool
    {
        $silence = (int) config('ai_order.handoff_minutes', 15);
        $text = $this->formatAdminMessage('KOMPLAIN / STATUS', $customerName, $sender, $message, $silence);
        return $this->sendToAdminGroup($text, 'complaint_status', $sender);
    }

    /**
     * (a3) Pertanyaan Netral tentang Driver / Pengiriman
     */
    public function notifyDriverInquiry(string $customerName, string $sender, string $message): bool
    {
        $silence = (int) config('ai_order.handoff_minutes', 15);
        $text = $this->formatAdminMessage('PERTANYAAN / DRIVER', $customerName, $sender, $message, $silence);
        return $this->sendToAdminGroup($text, 'driver_inquiry', $sender);
    }

    /**
     * (b) Pelanggan Baru Terdaftar & Order Masuk
     */
    public function notifyNewCustomerOrder(string $namaToko, string $alamat, float|int $qty, string $ukuran, string $sender): bool
    {
        $qtyStr = (fmod((float) $qty, 1.0) === 0.0) ? (string) (int) $qty : (string) $qty;
        $body = "{$alamat}. Order: {$qtyStr} ball {$ukuran}. Status: menunggu kasir.";
        $text = $this->formatAdminMessage('PELANGGAN BARU', $namaToko, $sender, $body, null);
        return $this->sendToAdminGroup($text, 'new_customer_order', $sender);
    }

    /**
     * (c) Stok Tidak Cukup (Dilarang sebutkan angka stok riil)
     */
    public function notifyStockInsufficient(string $customerName, string $sender, float|int $qty, string $ukuran): bool
    {
        $qtyStr = (fmod((float) $qty, 1.0) === 0.0) ? (string) (int) $qty : (string) $qty;
        $body = "Permintaan {$qtyStr} ball {$ukuran} melebihi sisa stok.";
        $text = $this->formatAdminMessage('STOK KURANG', $customerName, $sender, $body, null);
        return $this->sendToAdminGroup($text, 'stock_insufficient', $sender);
    }

    /**
     * (d) Pesanan Mendesak / Urgent
     */
    public function notifyUrgentOrder(string $customerName, string $sender): bool
    {
        $body = "Pelanggan meminta pesanan disegerakan!";
        $text = $this->formatAdminMessage('URGENT', $customerName, $sender, $body, null);
        return $this->sendToAdminGroup($text, 'urgent_order', $sender);
    }

    /**
     * (e) Chat Pelanggan Diteruskan Selama Human Mode (Maks 1x per 2 menit)
     */
    public function notifyForwardCustomerChat(string $customerName, string $sender, string $message): bool
    {
        $text = $this->formatAdminMessage('CHAT DITERUSKAN', $customerName, $sender, $message, null);
        return $this->sendToAdminGroup($text, 'forward_chat', $sender, 2);
    }

    /**
     * (f) Bot Buntu (Kendala Memahami Setelah 2 Pertanyaan Berulang)
     */
    public function notifyBotStuck(string $customerName, string $sender, string $message): bool
    {
        $silence = (int) config('ai_order.handoff_minutes', 15);
        $body = "Bot mengalami kendala memahami percakapan berulang. Chat terakhir: {$message}";
        $text = $this->formatAdminMessage('BOT BUNTU', $customerName, $sender, $body, $silence);
        return $this->sendToAdminGroup($text, 'bot_stuck', $sender, 15);
    }

    /**
     * (g) Gemini Error & Fallback Aktif (Max 1x per 10 menit global)
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
