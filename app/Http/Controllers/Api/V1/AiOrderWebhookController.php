<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Customer;
use App\Models\TemporaryOrder;
use App\Models\WaOrder;
use Carbon\Carbon;
use App\Services\Ai\LlmOrderService;
use App\Services\Whatsapp\FonnteService;
use Illuminate\Support\Facades\Log;

class AiOrderWebhookController extends Controller
{
    protected LlmOrderService $llmService;
    protected FonnteService $fonnteService;

    public function __construct(
        LlmOrderService $llmService,
        FonnteService $fonnteService
    ) {
        $this->llmService = $llmService;
        $this->fonnteService = $fonnteService;
    }

    /**
     * Endpoint Penerima Webhook WhatsApp Fonnte (POST /api/v1/webhook/fonnte).
     */
    public function handle(Request $request): JsonResponse
    {
        Log::info('[AiOrderWebhook] Webhook Fonnte Masuk. Payload: ' . json_encode($request->all()));

        $sender = $request->input('sender') ?? $request->input('from') ?? $request->input('phone');
        $message = trim((string) ($request->input('message') ?? $request->input('text') ?? ''));

        // 1. Abaikan pesan dari Grup WhatsApp
        if ($request->input('isgroup') == true || $request->input('isgroup') === 'true') {
            return response()->json(['status' => 'ignored', 'message' => 'Pesan dari grup diabaikan'], 200);
        }

        // 2. Abaikan pesan media/VN jika URL media kosong
        if ($message === 'non-text message' || $message === 'non-button message') {
            return response()->json(['status' => 'ignored', 'message' => 'Pesan media tidak didukung oleh konfigurasi saat ini'], 200);
        }

        if (empty($sender) || empty($message)) {
            return response()->json([
                'status'  => 'ignored',
                'message' => 'Pengirim atau pesan kosong',
            ], 200);
        }

        // ==============================================================
        // 1. CARI NOMOR WA DI MODEL CUSTOMER (Tabel customers)
        // ==============================================================
        $customer = $this->findCustomerByPhone($sender);

        $finalCustomerId = $customer ? $customer->id : 108;
        $finalCustomerName = $customer ? $customer->name : 'Pelanggan Baru (' . $sender . ')';

        // ==============================================================
        // 2. CEK PEMBATALAN PESANAN (MENDUKUNG PENDING & VALIDATED / MASUK POS)
        // ==============================================================
        // SEBELUM pengecekan pembatalan: Cari order aktif terakhir hari ini (status: pending atau validated)
        $activeOrder = TemporaryOrder::where('sender_phone', $sender)
            ->whereIn('status', ['pending', 'validated'])
            ->whereDate('created_at', Carbon::today())
            ->latest('id')
            ->first();

        // Pelanggan membalas pembatalan (misal: "batal", "cancel", "gak jadi")
        if ($this->isCancellation($message)) {
            if ($activeOrder) {
                $activeOrder->update([
                    'status'    => 'cancelled',
                    'last_chat' => $message,
                ]);

                $reply = "❌ Pesanan Anda ({$activeOrder->jumlah_ball} Ball Es Kristal {$activeOrder->ukuran_produk}) telah dibatalkan. Silakan chat kembali jika ingin membuat pesanan baru.";
                $this->fonnteService->sendMessage($sender, $reply);

                Log::info("[AiOrderWebhook] Order #{$activeOrder->id} ({$activeOrder->status}) berhasil dibatalkan oleh pelanggan.");

                return response()->json([
                    'status'        => 'success',
                    'action'        => 'cancelled',
                    'order_id'      => $activeOrder->id,
                    'reply_message' => $reply ?? 'Pesan balasan tidak tersedia',
                ], 200);
            }

            $reply = "Tidak ada pesanan aktif (menunggu konfirmasi atau antrean kasir) yang dapat dibatalkan saat ini.";
            $this->fonnteService->sendMessage($sender, $reply);

            return response()->json([
                'status'        => 'ignored',
                'message'       => 'Tidak ada pesanan aktif untuk dibatalkan',
                'reply_message' => $reply,
            ], 200);
        }

        // --------------------------------------------------------------
        // KASUS A: CUSTOMER SEDANG MEMILIKI PESANAN PENDING
        // --------------------------------------------------------------
        $pendingOrder = ($activeOrder && $activeOrder->status === 'pending')
            ? $activeOrder
            : TemporaryOrder::where('sender_phone', $sender)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

        if ($pendingOrder) {
            // Pelanggan membalas konfirmasi "oke" / "ya"
            if ($this->isConfirmation($message)) {
                // ATURAN REVISI: CUKUP update status temporary_orders menjadi 'validated'.
                // JANGAN PERNAH panggil DB::transaction untuk memasukkan data ke transactions/transaction_details.
                $pendingOrder->update([
                    'status'    => 'validated',
                    'last_chat' => $message,
                ]);

                // Kirim notifikasi konfirmasi ke pelanggan
                $reply = "Pesanan terkonfirmasi! Pesanan {$pendingOrder->jumlah_ball} Ball Es Kristal {$pendingOrder->ukuran_produk} Anda telah masuk ke antrean kasir. Terima kasih!";
                $this->fonnteService->sendMessage($sender, $reply);

                Log::info("[AiOrderWebhook] Order #{$pendingOrder->id} diubah menjadi 'validated' dan siap di-drag kasir.");

                return response()->json([
                    'status'        => 'success',
                    'action'        => 'validated',
                    'order_id'      => $pendingOrder->id,
                    'reply_message' => $reply ?? 'Pesan balasan tidak tersedia',
                ], 200);
            }

            // Pelanggan mengirim revisi (misal: "bukan, yang 15kg saja")
            $parsed = $this->llmService->parseOrder($message);

            if (!$parsed) {
                // Jika sedang pending, tapi pelanggan mengirim sapaan/teks lain yang bukan revisi
                $reply = "Pesanan Anda ({$pendingOrder->jumlah_ball} Ball Es Kristal {$pendingOrder->ukuran_produk}) sedang menunggu konfirmasi. Balas OKE untuk memproses ke kasir atau BATAL jika ingin membatalkan.";
                $this->fonnteService->sendMessage($sender, $reply);

                return response()->json([
                    'status'        => 'pending_reminder',
                    'message'       => 'Chat bukan revisi pesanan',
                    'order_id'      => $pendingOrder->id,
                    'reply_message' => $reply ?? 'Pesan balasan tidak tersedia',
                ], 200);
            }

            $pendingOrder->update([
                'jumlah_ball'   => $parsed['jumlah_ball'],
                'ukuran_produk' => $parsed['ukuran_produk'],
                'delivery_time' => $parsed['waktu_pengiriman'] ?? null,
                'last_chat'     => $message,
            ]);

            // Kirim balasan revisi TANPA harga (dengan cek tagihan kasbon)
            $reply = "Pesanan diterima: {$parsed['jumlah_ball']} Ball Es Kristal {$parsed['ukuran_produk']}.";

            if ($customer && $customer->getTotalKasbon() > 0) {
                $totalKasbonFormatted = number_format($customer->getTotalKasbon(), 0, ',', '.');
                $reply .= " Namun saat ini sistem mencatat ada tagihan kasbon sebesar Rp {$totalKasbonFormatted} yang belum lunas. Mohon titipkan pelunasan ke supir kami saat pengiriman ya. Balas OKE untuk konfirmasi.";
            } elseif (!$customer) {
                $reply .= " Karena Anda adalah pelanggan baru, mohon balas OKE beserta Nama Toko dan Alamat Anda untuk konfirmasi.";
            } else {
                $reply .= " Balas OKE untuk konfirmasi.";
            }
            $this->fonnteService->sendMessage($sender, $reply);

            return response()->json([
                'status'        => 'success',
                'action'        => 'revised',
                'order_id'      => $pendingOrder->id,
                'reply_message' => $reply ?? 'Pesan balasan tidak tersedia',
                'data'          => $parsed,
            ], 200);
        }

        // --------------------------------------------------------------
        // KASUS B: PESANAN BARU (BELUM ADA PENDING ORDER)
        // --------------------------------------------------------------
        $parsed = $this->llmService->parseOrder($message);

        // Jika pesan bernilai null (sapaan seperti "bang", "halo", "p", dll), sistem JANGAN membuat TemporaryOrder
        if (!$parsed) {
            $reply = "Halo! Ada yang bisa kami bantu untuk pemesanan Es Kristalnya? (Sedia ukuran 20kg, 15kg, 10kg).";
            $this->fonnteService->sendMessage($sender, $reply);

            return response()->json([
                'status'        => 'greeting_replied',
                'message'       => 'Pesan berupa sapaan/bukan format pesanan',
                'reply_message' => $reply ?? 'Pesan balasan tidak tersedia',
            ], 200);
        }

        $newOrder = TemporaryOrder::create([
            'customer_id'   => $finalCustomerId,
            'sender_phone'  => $sender,
            'jumlah_ball'   => $parsed['jumlah_ball'],
            'ukuran_produk' => $parsed['ukuran_produk'],
            'delivery_time' => $parsed['waktu_pengiriman'] ?? null,
            'status'        => 'pending',
            'last_chat'     => $message,
        ]);

        // Logika AI Penagih Kasbon & Balasan WhatsApp
        $reply = "Pesanan diterima: {$parsed['jumlah_ball']} Ball Es Kristal {$parsed['ukuran_produk']}.";

        if ($customer && $customer->getTotalKasbon() > 0) {
            $totalKasbonFormatted = number_format($customer->getTotalKasbon(), 0, ',', '.');
            $reply .= " Namun saat ini sistem mencatat ada tagihan kasbon sebesar Rp {$totalKasbonFormatted} yang belum lunas. Mohon titipkan pelunasan ke supir kami saat pengiriman ya. Balas OKE untuk konfirmasi.";
        } elseif (!$customer) {
            $reply .= " Karena Anda adalah pelanggan baru, mohon balas OKE beserta Nama Toko dan Alamat Anda untuk konfirmasi.";
        } else {
            $reply .= " Balas OKE untuk konfirmasi.";
        }
        $this->fonnteService->sendMessage($sender, $reply);

        return response()->json([
            'status'        => 'success',
            'action'        => 'pending_created',
            'order_id'      => $newOrder->id,
            'reply_message' => $reply ?? 'Pesan balasan tidak tersedia',
            'data'          => $parsed,
        ], 200);
    }

    /**
     * Endpoint API untuk Kasir: Mengambil antrean pesanan AI berstatus 'validated'
     * GET /api/v1/kasir/ai-orders
     */
    public function getValidatedOrders(Request $request): JsonResponse
    {
        $orders = TemporaryOrder::where('status', 'validated')
            ->with(['customer:id,name,phone,address'])
            ->latest('updated_at')
            ->get()
            ->map(function ($order) {
                $customerName = ($order->customer_id == 108 || !$order->customer)
                    ? 'Pelanggan Baru (' . $order->sender_phone . ')'
                    : $order->customer->name;

                return [
                    'id'                      => $order->id,
                    'customer_id'             => $order->customer_id,
                    'customer_name'           => $customerName,
                    'sender_phone'            => $order->sender_phone,
                    'customer_address'        => $order->customer ? $order->customer->address : '-',
                    'jumlah_ball'             => (int) $order->jumlah_ball,
                    'ukuran_produk'           => $order->ukuran_produk,
                    'delivery_time'           => $order->delivery_time ? $order->delivery_time->format('Y-m-d H:i:s') : null,
                    'delivery_time_formatted' => $order->delivery_time ? $order->delivery_time->timezone('Asia/Jakarta')->format('H:i') : null,
                    'status'                  => $order->status,
                    'last_chat'               => $order->last_chat,
                    'created_at'              => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : null,
                    'time_formatted'          => $order->created_at ? $order->created_at->timezone('Asia/Jakarta')->format('H:i') : '',
                    'time_ago'                => $order->created_at ? $order->created_at->locale('id')->diffForHumans() : '',
                ];
            });

        return response()->json([
            'status' => 'success',
            'count'  => $orders->count(),
            'data'   => $orders,
        ]);
    }

    /**
     * Endpoint Opsional: Menandai pesanan telah selesai dipindahkan ke keranjang kasir
     * POST /api/v1/kasir/ai-orders/{id}/processed
     */
    public function markAsProcessed(int $id): JsonResponse
    {
        $order = TemporaryOrder::findOrFail($id);
        $order->update(['status' => 'cancelled']);

        return response()->json([
            'status'  => 'success',
            'message' => "Order #{$id} berhasil dipindahkan ke transaksi kasir.",
        ]);
    }

    /**
     * Cari customer berdasarkan variasi nomor telepon WhatsApp.
     */
    protected function findCustomerByPhone(string $phone): ?Customer
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (empty($clean)) {
            return null;
        }

        $variations = array_unique(array_filter([
            $phone,
            $clean,
            str_starts_with($clean, '62') ? '0' . substr($clean, 2) : null,
            str_starts_with($clean, '0') ? '62' . substr($clean, 1) : null,
            '+' . $clean,
        ]));

        $customer = Customer::whereIn('phone', $variations)->first();

        if (!$customer && strlen($clean) >= 9) {
            $suffix = substr($clean, -9);
            $customer = Customer::where('phone', 'like', "%{$suffix}")->first();
        }

        return $customer;
    }

    /**
     * Deteksi kata kunci konfirmasi manual.
     */
    protected function isConfirmation(string $message): bool
    {
        $clean = strtolower(trim($message));
        $keywords = [
            'oke', 'ok', 'ya', 'okedeh', 'lanjut', 'sip', 'iya', 'gass', 'kirim',
            'sesuai', 'siap', 'betul', 'benar', 'deal', 'gas', 'acc', 'yup'
        ];

        if (in_array($clean, $keywords)) {
            return true;
        }

        foreach ($keywords as $kw) {
            if (str_starts_with($clean, $kw . ' ') || str_starts_with($clean, $kw . ',') || str_starts_with($clean, $kw . '!')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deteksi kata kunci pembatalan.
     */
    protected function isCancellation(string $message): bool
    {
        $clean = strtolower(trim($message));
        $keywords = [
            'batal', 'cancel', 'ga jadi', 'gajadi', 'gak jadi', 'gakjadi', 'ngga dulu', 'ga dulu', 'tidak', 'engga', 'stop',
            'nggak jadi', 'tidak jadi'
        ];

        if (in_array($clean, $keywords)) {
            return true;
        }

        foreach ($keywords as $kw) {
            if (str_contains($clean, $kw)) {
                return true;
            }
        }

        return false;
    }
}
