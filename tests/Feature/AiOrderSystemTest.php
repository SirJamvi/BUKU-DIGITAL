<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use App\Models\Customer;
use App\Models\CustomerPhone;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\TemporaryOrder;
use App\Models\AiOrderDraft;
use App\Services\Whatsapp\PhoneNumberHelper;
use App\Services\Ai\ReplyTemplates;

class AiOrderSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Config::set('services.ai_order.debounce_seconds', 0); // Non-aktifkan sleep saat testing
        Config::set('ai_order.debounce_seconds', 0);
        Config::set('ai_order.after_response', false);
        Config::set('ai_order.dry_run', true);
        Config::set('ai_order.followup_minutes', 5);
        Config::set('ai_order.ack_minutes', 10);
        Config::set('ai_order.ack_reply', true);
        Config::set('ai_order.admin_notify', true);
        Config::set('ai_order.admin_group_id', '120363028123456789@g.us');
        putenv('AI_ORDER_DEBOUNCE_SECONDS=0');
        putenv('AI_ORDER_AFTER_RESPONSE=false');

        // Setup master produk es kristal untuk pengujian stok
        $p20 = Product::create([
            'business_id' => 1,
            'name'        => 'Es Kristal 20kg',
            'category_id' => 1,
            'unit'        => 'Ball',
            'base_price'  => 19000,
            'cost_price'  => 12000,
            'is_active'   => true,
        ]);
        $p20->inventory()->create([
            'business_id'   => 1,
            'current_stock' => 100,
            'min_stock'     => 10,
        ]);

        $p15 = Product::create([
            'business_id' => 1,
            'name'        => 'Es Kristal 15kg',
            'category_id' => 1,
            'unit'        => 'Ball',
            'base_price'  => 15000,
            'cost_price'  => 10000,
            'is_active'   => true,
        ]);
        $p15->inventory()->create([
            'business_id'   => 1,
            'current_stock' => 50,
            'min_stock'     => 5,
        ]);

        $p10 = Product::create([
            'business_id' => 1,
            'name'        => 'Es Kristal 10kg',
            'category_id' => 1,
            'unit'        => 'Ball',
            'base_price'  => 10000,
            'cost_price'  => 7000,
            'is_active'   => true,
        ]);
        $p10->inventory()->create([
            'business_id'   => 1,
            'current_stock' => 30,
            'min_stock'     => 5,
        ]);
    }

    /**
     * Skenario 1: Transkrip nyata pelanggan baru (bubble terpecah digabung)
     * "A pesen es 1" -> "Caffe dang Ding dung" + "Klari" -> "Yg 20"
     * Hasil: Tidak perlu ketik ulang; 1 customer terbentuk; order validated 1 ball 20kg.
     */
    public function test_scenario_1_real_transcript_new_customer_split_bubbles(): void
    {
        Http::fake([
            'https://api.fonnte.com/*' => Http::response(['status' => true], 200),
            'https://generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'nama_toko' => null,
                    'alamat' => null,
                    'jumlah_ball' => 1,
                    'ukuran_produk' => null,
                    'waktu_pengiriman' => null,
                    'is_registration' => false,
                    'unknown_ukuran' => false,
                ])]]]]]], 200)
                ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'nama_toko' => 'Caffe dang Ding dung',
                    'alamat' => 'Klari',
                    'jumlah_ball' => null,
                    'ukuran_produk' => null,
                    'waktu_pengiriman' => null,
                    'is_registration' => true,
                    'unknown_ukuran' => false,
                ])]]]]]], 200)
                ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'nama_toko' => null,
                    'alamat' => null,
                    'jumlah_ball' => null,
                    'ukuran_produk' => '20kg',
                    'waktu_pengiriman' => null,
                    'is_registration' => false,
                    'unknown_ukuran' => false,
                ])]]]]]], 200),
        ]);

        $sender = '081299991111';

        // Bubble 1: "A pesen es 1"
        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'A pesen es 1',
        ])->assertStatus(200);

        // Bubble 2 & 3: "Caffe dang Ding dung" + "Klari"
        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => "Caffe dang Ding dung\nKlari",
        ])->assertStatus(200);

        // Bubble 4: "Yg 20"
        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'Yg 20',
        ])->assertStatus(200);

        // Verifikasi database: Tepat 1 customer dan 1 order status validated
        $this->assertEquals(1, Customer::where('phone', '6281299991111')->count());
        $customer = Customer::where('phone', '6281299991111')->first();
        $this->assertEquals('Caffe dang Ding dung', $customer->name);

        $order = TemporaryOrder::where('sender_phone', '6281299991111')->first();
        $this->assertNotNull($order);
        $this->assertEquals('validated', $order->status);
        $this->assertEquals(1, $order->jumlah_ball);
        $this->assertEquals('20kg', $order->ukuran_produk);
    }

    /**
     * Skenario 2: Satu pesan lengkap pelanggan baru (One-Step Registration)
     */
    public function test_scenario_2_complete_single_message_new_customer(): void
    {
        Http::fake([
            'https://api.fonnte.com/*' => Http::response(['status' => true], 200),
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'nama_toko' => 'Warung Kopi Senja',
                    'alamat' => 'Jl. Pemuda No 12',
                    'jumlah_ball' => 2,
                    'ukuran_produk' => '20kg',
                    'waktu_pengiriman' => '2026-11-06 14:00:00',
                    'is_registration' => true,
                    'unknown_ukuran' => false,
                ])]]]]]
            ], 200),
        ]);

        $sender = '081388882222';

        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'Halo dari Warung Kopi Senja alamat Jl. Pemuda No 12 mau pesan es kristal 2 ball yang 19 jam 2 siang',
        ])->assertStatus(200);

        $canonical = PhoneNumberHelper::normalize($sender);
        $customer = Customer::where('phone', $canonical)->first();
        $this->assertNotNull($customer);
        $this->assertEquals('Warung Kopi Senja', $customer->name);

        $order = TemporaryOrder::where('sender_phone', $canonical)->first();
        $this->assertNotNull($order);
        $this->assertEquals('validated', $order->status);
        $this->assertEquals(2, $order->jumlah_ball);
        $this->assertEquals('20kg', $order->ukuran_produk);
    }

    /**
     * Skenario 3: Pelanggan lama "3 ball yang 19 jam 4 sore" -> langsung validated, balasan memuat BATAL dan UBAH
     */
    public function test_scenario_3_existing_customer_direct_validated(): void
    {
        $sender = '6281577773333';
        $customer = Customer::create([
            'business_id' => 1,
            'name'        => 'Cafe Mentari',
            'phone'       => $sender,
            'address'     => 'Jl. Flamboyan',
            'join_date'   => now()->subMonths(2),
            'status'      => 'active',
        ]);

        $capturedReply = '';
        Http::fake([
            'https://api.fonnte.com/*' => function ($request) use (&$capturedReply) {
                $capturedReply = $request['message'] ?? '';
                return Http::response(['status' => true], 200);
            },
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'nama_toko' => null,
                    'alamat' => null,
                    'jumlah_ball' => 3,
                    'ukuran_produk' => '20kg',
                    'waktu_pengiriman' => '2026-11-06 16:00:00',
                    'is_registration' => false,
                    'unknown_ukuran' => false,
                ])]]]]]
            ], 200),
        ]);

        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => '3 ball yang 19 jam 4 sore ya',
        ])->assertStatus(200);

        $order = TemporaryOrder::where('sender_phone', $sender)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals('validated', $order->status);
        $this->assertEquals(3, $order->jumlah_ball);
        $this->assertEquals('20kg', $order->ukuran_produk);

        $this->assertStringContainsString('BATAL', $capturedReply);
        $this->assertStringContainsString('UBAH', $capturedReply);
    }

    /**
     * Skenario 4: BATAL sebelum dan sesudah processed; UBAH lalu "jadi 3 ball" mengupdate order yang sama
     */
    public function test_scenario_4_batal_and_ubah_flow(): void
    {
        $sender = '6281688884444';
        $customer = Customer::create([
            'business_id' => 1,
            'name'        => 'Depot Segar',
            'phone'       => $sender,
            'address'     => 'Pasar Baru',
            'join_date'   => now()->subMonths(1),
            'status'      => 'active',
        ]);

        $order = TemporaryOrder::create([
            'customer_id'   => $customer->id,
            'sender_phone'  => $sender,
            'jumlah_ball'   => 1,
            'ukuran_produk' => '20kg',
            'status'        => 'validated',
            'last_chat'     => '1 ball yang 20',
        ]);

        $capturedReply = '';
        Http::fake([
            'https://api.fonnte.com/*' => function ($request) use (&$capturedReply) {
                $capturedReply = $request['message'] ?? '';
                return Http::response(['status' => true], 200);
            },
        ]);

        // A. UBAH
        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'ubah',
        ])->assertStatus(200);
        $this->assertStringContainsString('perubahannya', $capturedReply);

        // Kirim perubahan: "jadi 3 ball"
        Http::fake([
            'https://api.fonnte.com/*' => function ($request) use (&$capturedReply) {
                $capturedReply = $request['message'] ?? '';
                return Http::response(['status' => true], 200);
            },
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'nama_toko' => null,
                    'alamat' => null,
                    'jumlah_ball' => 3,
                    'ukuran_produk' => '20kg',
                    'waktu_pengiriman' => null,
                    'is_registration' => false,
                    'unknown_ukuran' => false,
                ])]]]]]
            ], 200),
        ]);

        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'jadi 3 ball',
        ])->assertStatus(200);

        // Order yang sama diperbarui (tidak membuat order ganda)
        $this->assertEquals(1, TemporaryOrder::where('sender_phone', $sender)->count());
        $order->refresh();
        $this->assertEquals(3, $order->jumlah_ball);

        // B. BATAL
        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'batal',
        ])->assertStatus(200);
        $order->refresh();
        $this->assertEquals('cancelled', $order->status);

        // C. Jika order sudah berstatus processed
        $order->update(['status' => 'processed']);
        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'batal',
        ])->assertStatus(200);
        $this->assertStringContainsString('sudah diproses kasir', $capturedReply);
    }

    /**
     * Skenario 5: Stok kurang -> Guard PHP menolak dan balasan tidak membocorkan angka stok
     */
    public function test_scenario_5_stock_guard_without_leaking_numbers(): void
    {
        $sender = '6281788885555';
        Customer::create([
            'business_id' => 1,
            'name'        => 'Resto Maju',
            'phone'       => $sender,
            'address'     => 'Jl. Veteran',
            'join_date'   => now(),
            'status'      => 'active',
        ]);

        // Stok 20kg ada 100, pesan 250 ball
        $capturedReply = '';
        Http::fake([
            'https://api.fonnte.com/*' => function ($request) use (&$capturedReply) {
                $capturedReply = $request['message'] ?? '';
                return Http::response(['status' => true], 200);
            },
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'nama_toko' => null,
                    'alamat' => null,
                    'jumlah_ball' => 250,
                    'ukuran_produk' => '20kg',
                    'waktu_pengiriman' => null,
                    'is_registration' => false,
                    'unknown_ukuran' => false,
                ])]]]]]
            ], 200),
        ]);

        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'pesan 250 ball yang 20kg',
        ])->assertStatus(200);

        $this->assertStringContainsString('belum cukup', $capturedReply);
        // Pastikan angka sisa stok riil (100) tidak dibocorkan di balasan
        $this->assertStringNotContainsString('100', $capturedReply);
    }

    /**
     * Skenario 6: Pesan grup WhatsApp -> diabaikan tanpa log payload berat
     */
    public function test_scenario_6_group_message_ignored(): void
    {
        $response = $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => '12036302839281928@g.us',
            'message' => 'pesan es 5 ball',
            'isgroup' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ignored', 'reason' => 'group']);
        $this->assertEquals(0, TemporaryOrder::count());
    }

    /**
     * Skenario 7: Retry Fonnte (payload identik) -> hanya diproses sekali
     */
    public function test_scenario_7_duplicate_webhook_retry_ignored(): void
    {
        Http::fake([
            'https://api.fonnte.com/*' => Http::response(['status' => true], 200),
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'nama_toko' => null,
                    'alamat' => null,
                    'jumlah_ball' => 1,
                    'ukuran_produk' => '20kg',
                    'waktu_pengiriman' => null,
                    'is_registration' => false,
                    'unknown_ukuran' => false,
                ])]]]]]
            ], 200),
        ]);

        $payload = [
            'id'      => 'msg_unique_abc_123',
            'sender'  => '628199997777',
            'message' => 'pesan 1 ball 20kg',
        ];

        // Request 1: diproses
        $res1 = $this->postJson('/api/v1/webhook/fonnte', $payload);
        $res1->assertStatus(200);

        // Request 2 (Retry dengan id sama): langsung diabaikan oleh Atomic Cache Lock
        $res2 = $this->postJson('/api/v1/webhook/fonnte', $payload);
        $res2->assertStatus(200);
        $res2->assertJson(['status' => 'ignored', 'message' => 'Duplicate webhook request']);
    }

    /**
     * Skenario 8: Share location mengisi alamat; lokasi tanpa order -> location_only
     */
    public function test_scenario_8_share_location(): void
    {
        $capturedReply = '';
        Http::fake([
            'https://api.fonnte.com/*' => function ($request) use (&$capturedReply) {
                $capturedReply = $request['message'] ?? '';
                return Http::response(['status' => true], 200);
            },
        ]);

        $sender = '628188880000';

        // Kirim lokasi saja
        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'   => $sender,
            'message'  => 'non-text message',
            'location' => '-6.2088,106.8456',
        ])->assertStatus(200);

        $this->assertStringContainsString('Lokasi diterima', $capturedReply);

        $draft = AiOrderDraft::where('sender_phone', $sender)->first();
        $this->assertNotNull($draft);
        $this->assertEquals('-6.2088', $draft->data['lat']);
        $this->assertEquals('106.8456', $draft->data['lng']);
    }

    /**
     * Skenario 9: Gemini timeout/error -> fallback regex; "jam 10" bukan ukuran 10kg; "yang 5" memicu unknown_ukuran
     */
    public function test_scenario_9_regex_fallback_and_unknown_ukuran(): void
    {
        $capturedReply = '';
        Http::fake([
            'https://api.fonnte.com/*' => function ($request) use (&$capturedReply) {
                $capturedReply = $request['message'] ?? '';
                return Http::response(['status' => true], 200);
            },
            // Simulasikan Gemini Error 500 / timeout
            'https://generativelanguage.googleapis.com/*' => Http::response('Internal Server Error', 500),
        ]);

        $sender = '628177771122';
        Customer::create([
            'business_id' => 1,
            'name'        => 'Warung Barokah',
            'phone'       => $sender,
            'address'     => 'Jl. Melati',
            'join_date'   => now(),
            'status'      => 'active',
        ]);

        // Pesan: "kirim 2 ball jam 10 pagi" -> "jam 10" adalah waktu, bukan ukuran 10kg! Default ukuran = 20kg.
        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'kirim 2 ball jam 10 pagi',
        ])->assertStatus(200);

        $order = TemporaryOrder::where('sender_phone', $sender)->first();
        $this->assertNotNull($order);
        $this->assertEquals(2, $order->jumlah_ball);
        $this->assertEquals('20kg', $order->ukuran_produk); // Default ukuran 20kg, bukan 10kg

        // Pesan dengan ukuran tidak dikenal: "yang 5" -> memicu unknown_ukuran
        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'pesan 2 ball yang 5',
        ])->assertStatus(200);

        $this->assertStringContainsString('Ukuran yang tersedia 20 / 15 / 10 kg', $capturedReply);
    }

    /**
     * Skenario 10: Format nomor 0812... / 62812... / +62812... merujuk ke satu customer yang sama
     */
    public function test_scenario_10_phone_normalization_single_customer(): void
    {
        $canonical = '628123456789';
        $customer = Customer::create([
            'business_id' => 1,
            'name'        => 'Kedai Kopi Satu',
            'phone'       => $canonical,
            'address'     => 'Jl. Pahlawan',
            'join_date'   => now(),
            'status'      => 'active',
        ]);

        $this->assertEquals($canonical, PhoneNumberHelper::normalize('08123456789'));
        $this->assertEquals($canonical, PhoneNumberHelper::normalize('+628123456789'));
        $this->assertEquals($canonical, PhoneNumberHelper::normalize('628123456789'));

        // Cek pencarian variasi
        $variations = PhoneNumberHelper::variations('08123456789');
        $found = Customer::whereIn('phone', $variations)->first();
        $this->assertNotNull($found);
        $this->assertEquals($customer->id, $found->id);
    }

    /**
     * Skenario 11: Follow-up pesan ukuran saja dengan nilai sama -> balas 'pesanan tetap', DB tidak berubah
     */
    public function test_scenario_11_followup_same_size_reply_pesanan_tetap(): void
    {
        $sender = '6281211110001';
        $customer = Customer::create([
            'business_id' => 1,
            'name'        => 'Cafe Ding',
            'phone'       => $sender,
            'address'     => 'Klari',
            'join_date'   => now(),
            'status'      => 'active',
        ]);

        $order = TemporaryOrder::create([
            'customer_id'   => $customer->id,
            'sender_phone'  => $sender,
            'jumlah_ball'   => 1,
            'ukuran_produk' => '20kg',
            'status'        => 'validated',
            'last_chat'     => 'pesen es 1',
        ]);

        $capturedReply = '';
        $this->mockFonnteService(function ($target, $message) use (&$capturedReply) {
            $capturedReply = $message;
            return ['status' => true];
        });

        // Bubble susulan 10 detik kemudian: "yang 20kg"
        $response = $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'yang 20kg',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('pesanan tetap 1 ball 20kg', $capturedReply);

        // Database tidak berubah
        $order->refresh();
        $this->assertEquals(1, $order->jumlah_ball);
        $this->assertEquals('20kg', $order->ukuran_produk);
        $this->assertEquals('validated', $order->status);
    }

    /**
     * Skenario 12: Follow-up pesan qty saja dengan nilai beda -> order berjalan ter-update
     */
    public function test_scenario_12_followup_different_qty_updates_order(): void
    {
        $sender = '6281211110002';
        $customer = Customer::create([
            'business_id' => 1,
            'name'        => 'Warung Barokah',
            'phone'       => $sender,
            'address'     => 'Telukjambe',
            'join_date'   => now(),
            'status'      => 'active',
        ]);

        $order = TemporaryOrder::create([
            'customer_id'   => $customer->id,
            'sender_phone'  => $sender,
            'jumlah_ball'   => 1,
            'ukuran_produk' => '20kg',
            'status'        => 'validated',
            'last_chat'     => 'pesen 1 ball 20kg',
        ]);

        $capturedReply = '';
        $this->mockFonnteService(function ($target, $message) use (&$capturedReply) {
            $capturedReply = $message;
            return ['status' => true];
        });

        // Follow-up: "jadi 2 ball"
        $response = $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'jadi 2 ball',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('Diubah: 1 ball -> 2 ball 20kg', $capturedReply);

        // Order ter-update di database
        $order->refresh();
        $this->assertEquals(2, $order->jumlah_ball);
        $this->assertEquals('20kg', $order->ukuran_produk);
    }

    /**
     * Skenario 13: Ack 'oke' / 'makasih' dalam jendela ack -> balas 'Siap Kak' tanpa tanya qty
     */
    public function test_scenario_13_ack_within_window_replies_siap_kak(): void
    {
        $sender = '6281211110003';
        $customer = Customer::create([
            'business_id' => 1,
            'name'        => 'Kedai Kopi',
            'phone'       => $sender,
            'address'     => 'Galuh Mas',
            'join_date'   => now(),
            'status'      => 'active',
        ]);

        TemporaryOrder::create([
            'customer_id'   => $customer->id,
            'sender_phone'  => $sender,
            'jumlah_ball'   => 2,
            'ukuran_produk' => '20kg',
            'status'        => 'validated',
            'last_chat'     => 'order 2 ball 20kg',
        ]);

        $capturedReply = '';
        $this->mockFonnteService(function ($target, $message) use (&$capturedReply) {
            $capturedReply = $message;
            return ['status' => true];
        });

        // Kirim ack: "makasih min"
        $response = $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'makasih min',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(ReplyTemplates::render('ack'), trim($capturedReply));
        $this->assertStringNotContainsString('Mau berapa ball', $capturedReply);
    }

    /**
     * Skenario 14: Follow-up lewat dari jendela -> mulai order baru (atau tanya qty normal)
     */
    public function test_scenario_14_followup_outside_window_asks_qty(): void
    {
        $sender = '6281211110004';
        $customer = Customer::create([
            'business_id' => 1,
            'name'        => 'Depot Air',
            'phone'       => $sender,
            'address'     => 'Cikampek',
            'join_date'   => now(),
            'status'      => 'active',
        ]);

        // Order yang dibuat 15 menit lalu (di luar jendela followup 5 menit)
        $order = TemporaryOrder::create([
            'customer_id'   => $customer->id,
            'sender_phone'  => $sender,
            'jumlah_ball'   => 1,
            'ukuran_produk' => '20kg',
            'status'        => 'validated',
            'last_chat'     => 'order 1 ball 20kg',
        ]);
        $order->created_at = now()->subMinutes(15);
        $order->updated_at = now()->subMinutes(15);
        $order->save();

        $capturedReply = '';
        $this->mockFonnteService(function ($target, $message) use (&$capturedReply) {
            $capturedReply = $message;
            return ['status' => true];
        });

        // Pesan ukuran saja "yang 20kg" setelah 15 menit -> tanya qty (bukan update order lama)
        $response = $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'yang 20kg',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('Mau berapa ball', $capturedReply);
    }

    /**
     * Skenario 15: Follow-up pada order yang sudah 'processed' -> template already_processed
     */
    public function test_scenario_15_followup_on_processed_order_returns_already_processed(): void
    {
        $sender = '6281211110005';
        $customer = Customer::create([
            'business_id' => 1,
            'name'        => 'Kantin RS',
            'phone'       => $sender,
            'address'     => 'Karawang Barat',
            'join_date'   => now(),
            'status'      => 'active',
        ]);

        TemporaryOrder::create([
            'customer_id'   => $customer->id,
            'sender_phone'  => $sender,
            'jumlah_ball'   => 1,
            'ukuran_produk' => '20kg',
            'status'        => 'processed', // Sudah diproses kasir!
            'last_chat'     => 'order 1 ball',
        ]);

        $capturedReply = '';
        $this->mockFonnteService(function ($target, $message) use (&$capturedReply) {
            $capturedReply = $message;
            return ['status' => true];
        });

        // Pelanggan coba ubah: "jadinya 2 ball"
        $response = $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => 'jadinya 2 ball',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('sudah diproses', $capturedReply);
    }

    /**
     * Skenario 16: Soft-reservation 24 jam: order validated > 24 jam di-lazy-expire
     */
    public function test_scenario_16_soft_reservation_lazy_expires_orders_over_24h(): void
    {
        $sender = '6281211110006';
        $orderOld = TemporaryOrder::create([
            'customer_id'   => 1,
            'sender_phone'  => $sender,
            'jumlah_ball'   => 10,
            'ukuran_produk' => '20kg',
            'status'        => 'validated',
        ]);
        $orderOld->created_at = now()->subHours(25);
        $orderOld->save();

        $orderRecent = TemporaryOrder::create([
            'customer_id'   => 1,
            'sender_phone'  => $sender,
            'jumlah_ball'   => 5,
            'ukuran_produk' => '20kg',
            'status'        => 'validated',
        ]);

        // Jalankan lazy-expire
        $expiredCount = TemporaryOrder::lazyExpireOldOrders();
        $this->assertEquals(1, $expiredCount);

        $orderOld->refresh();
        $this->assertEquals('expired', $orderOld->status);

        $orderRecent->refresh();
        $this->assertEquals('validated', $orderRecent->status);

        // Service hanya menghitung order validated dalam 24 jam terakhir (5 ball)
        $llmService = app(\App\Services\Ai\LlmOrderService::class);
        $stock = $llmService->getAvailableStockWithReservation('20kg');
        $this->assertEquals(5, $stock['reserved']);
    }

    /**
     * Skenario 17: Notifikasi grup admin terkirim saat human mode / pelanggan baru / stok kurang
     */
    public function test_scenario_17_admin_group_notifier_events(): void
    {
        $capturedGroupMessages = [];
        $this->mockFonnteService(function ($target, $message) use (&$capturedGroupMessages) {
            $capturedGroupMessages[] = ['target' => $target, 'message' => $message];
            return ['status' => true];
        });

        $notifier = app(\App\Services\Ai\AdminGroupNotifier::class);
        Config::set('ai_order.dry_run', false);

        // 1. Human Mode
        $notifier->notifyHumanMode('Toko Kopi', '628123456789', 'admin mau nanya');
        $this->assertNotEmpty($capturedGroupMessages);
        $this->assertStringContainsString('[HUMAN MODE]', $capturedGroupMessages[0]['message']);

        // 2. Pelanggan Baru Order
        $capturedGroupMessages = [];
        $notifier->notifyNewCustomerOrder('Kedai Baru', 'Jl Mangga', 2, '20kg', '628123456789');
        $this->assertNotEmpty($capturedGroupMessages);
        $this->assertStringContainsString('[PELANGGAN BARU]', $capturedGroupMessages[0]['message']);

        // 3. Stok Kurang
        $capturedGroupMessages = [];
        $notifier->notifyStockInsufficient('Toko Kopi', '628123456789', 500, '20kg');
        $this->assertNotEmpty($capturedGroupMessages);
        $this->assertStringContainsString('[STOK KURANG]', $capturedGroupMessages[0]['message']);
        $this->assertStringNotContainsString('100', $capturedGroupMessages[0]['message']); // Angka stok riil tidak bocor
    }

    /**
     * Skenario 18: Pesanan Baru Terpisah ("3 ball yang 19 jam 4 sore" memicu pesanan ke-2 tanpa menimpa order pertama)
     */
    public function test_scenario_18_new_separate_order_in_followup_window(): void
    {
        $sender = '6281211110007';
        $customer = Customer::create([
            'business_id' => 1,
            'name'        => 'Resto Rasa',
            'phone'       => $sender,
            'address'     => 'Klari',
            'join_date'   => now(),
            'status'      => 'active',
        ]);

        // Order pertama #1 sudah validated
        $order1 = TemporaryOrder::create([
            'customer_id'   => $customer->id,
            'sender_phone'  => $sender,
            'jumlah_ball'   => 1,
            'ukuran_produk' => '20kg',
            'status'        => 'validated',
            'last_chat'     => 'pesen 1 ball',
        ]);

        $capturedReply = '';
        $this->mockFonnteService(function ($target, $message) use (&$capturedReply) {
            $capturedReply = $message;
            return ['status' => true];
        });

        // Masuk pesan baru dengan kuantitas, ukuran, dan waktu: "3 ball yang 19 jam 4 sore"
        $response = $this->postJson('/api/v1/webhook/fonnte', [
            'sender'  => $sender,
            'message' => '3 ball yang 19 jam 4 sore',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('Pesanan ke-2 masuk: 3 ball 20kg', $capturedReply);
        $this->assertStringContainsString('pesanan sebelumnya tetap ada', $capturedReply);

        // Verifikasi di database: ada 2 order berbeda untuk sender ini!
        $orders = TemporaryOrder::where('sender_phone', $sender)->get();
        $this->assertCount(2, $orders);

        $order1->refresh();
        $this->assertEquals(1, $order1->jumlah_ball); // Order pertama tidak ditimpa!

        $order2 = $orders->where('id', '!=', $order1->id)->first();
        $this->assertNotNull($order2);
        $this->assertEquals(3, $order2->jumlah_ball);
        $this->assertEquals('20kg', $order2->ukuran_produk);
    }

    /**
     * Skenario 19: Template Lokasi Adaptif
     */
    public function test_scenario_19_adaptive_location_templates(): void
    {
        $sender = '6281211110008';

        $capturedReply = '';
        $this->mockFonnteService(function ($target, $message) use (&$capturedReply) {
            $capturedReply = $message;
            return ['status' => true];
        });

        // Kasus 1: Lokasi diterima tanpa qty di draft -> minta qty & ukuran
        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'   => $sender,
            'location' => '-6.3000,107.3000',
        ])->assertStatus(200);

        $this->assertStringContainsString('Mau pesan berapa ball & ukuran berapa', $capturedReply);

        // Kasus 2: Draft sudah punya qty & ukuran tapi belum ada nama toko -> minta nama toko
        $draft = AiOrderDraft::getOrCreateDraft($sender);
        $draft->mergeData(['jumlah_ball' => 2, 'ukuran_produk' => '20kg']);

        $capturedReply = '';
        $this->postJson('/api/v1/webhook/fonnte', [
            'sender'   => $sender,
            'location' => '-6.3000,107.3000',
        ])->assertStatus(200);

        $this->assertStringContainsString('Nama tokonya apa Kak', $capturedReply);
    }

    /**
     * Skenario 20: Re-check Buffer (balasan pertanyaan ditahan saat buffer pengirim masih berisi bubble lain)
     */
    public function test_scenario_20_recheck_buffer_suppresses_question_replies(): void
    {
        $sender = '6281211110009';

        // Simulasikan ada bubble baru di buffer
        Cache::put('debounce_buffer_' . $sender, [
            ['message' => 'bubble berikutnya sedang antre', 'payload' => [], 'timestamp' => microtime(true)]
        ], now()->addMinutes(2));

        $capturedReply = '';
        $this->mockFonnteService(function ($target, $message) use (&$capturedReply) {
            $capturedReply = $message;
            return ['status' => true];
        });

        // Pesan parsial yang harusnya tanya qty ("yang 20kg")
        $processor = app(\App\Services\Ai\AiOrderProcessor::class);
        $result = $processor->process($sender, 'yang 20kg');

        // Balasan pertanyaan ditahan agar dibalas di gelombang berikutnya
        $this->assertEquals('pending_more_bubbles', $result['status']);
        $this->assertEmpty($capturedReply);
    }

    /**
     * Skenario 21: FonnteService menjaga target grup WhatsApp (@g.us) agar tidak terhapus regex
     */
    public function test_scenario_21_fonnte_service_preserves_group_jid(): void
    {
        $capturedPayloads = [];
        Http::fake([
            'https://api.fonnte.com/*' => function ($request) use (&$capturedPayloads) {
                $capturedPayloads[] = $request->data();
                return Http::response(['status' => true], 200);
            },
        ]);

        Config::set('services.fonnte.token', 'dummy-token');
        $fonnteService = app(\App\Services\Whatsapp\FonnteService::class);

        // 1. Target grup WhatsApp (mengandung @g.us)
        $fonnteService->sendMessage('120363423987962193@g.us', 'Halo grup admin');
        $this->assertNotEmpty($capturedPayloads);
        $this->assertEquals('120363423987962193@g.us', $capturedPayloads[0]['target']);

        // 2. Target nomor biasa dengan strip/spasi
        $capturedPayloads = [];
        $fonnteService->sendMessage('0812-3456-7890', 'Halo personal');
        $this->assertNotEmpty($capturedPayloads);
        $this->assertEquals('081234567890', $capturedPayloads[0]['target']);
    }

    /**
     * Skenario 22: Default kuantitas 1 ball jika pelanggan menyebut niat pesan & ukuran tanpa angka qty
     */
    public function test_scenario_22_default_quantity_when_size_and_intent_mentioned_without_qty(): void
    {
        $sender = '6281211110022';
        $customer = Customer::create([
            'business_id' => 1,
            'name'        => 'Warung Titik Kumpul',
            'phone'       => $sender,
            'address'     => 'Deket Unsika',
            'join_date'   => now(),
            'status'      => 'active',
        ]);

        $capturedReply = '';
        $this->mockFonnteService(function ($target, $message) use (&$capturedReply) {
            $capturedReply = $message;
            return ['status' => true];
        });

        // Pelanggan menyebut ukuran tanpa angka qty: "kirim es ya kaya biasa yang 20ribu sekarang"
        $processor = app(\App\Services\Ai\AiOrderProcessor::class);
        $result = $processor->process($sender, 'kirim es ya kaya biasa yang 20ribu sekarang');

        $this->assertEquals('order_validated', $result['status']);
        $this->assertStringContainsString('1 ball 20kg', $capturedReply);

        $order = TemporaryOrder::where('sender_phone', $sender)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals(1, $order->jumlah_ball);
        $this->assertEquals('20kg', $order->ukuran_produk);
        $this->assertEquals('validated', $order->status);
    }

    /**
     * Skenario 23: Regresi Fase 12 - Idempotensi payload asli Fonnte (inboxid: 0, tanpa ID unik)
     */
    public function test_scenario_23_phase12_idempotency_real_fonnte_payloads(): void
    {
        Http::fake([
            'https://api.fonnte.com/*' => Http::response(['status' => true], 200),
        ]);

        Config::set('ai_order.after_response', false); // mode synchronous untuk feature test

        // (a) 2 bubble beda dari satu pengirim dalam 1 menit -> keduanya diterima dan masuk buffer debounce
        $senderA = '6281299991101';
        $payloadA1 = [
            'sender'    => $senderA,
            'message'   => 'pesan es 2 ball',
            'inboxid'   => 0,
            'timestamp' => 1731234500,
        ];
        $payloadA2 = [
            'sender'    => $senderA,
            'message'   => 'yang 20kg',
            'inboxid'   => 0,
            'timestamp' => 1731234510,
        ];

        $resA1 = $this->postJson('/api/v1/webhook/fonnte', $payloadA1);
        $resA1->assertStatus(200);
        $this->assertNotEquals('ignored', $resA1->json('status'));

        $resA2 = $this->postJson('/api/v1/webhook/fonnte', $payloadA2);
        $resA2->assertStatus(200);
        $this->assertNotEquals('ignored', $resA2->json('status'));

        // (b) 2 pengirim berbeda pada detik yang sama -> keduanya diterima, tidak boleh saling memblokir
        $senderB = '6281299991102';
        $senderC = '6281299991103';
        $timeSame = 1731234550;

        $resB = $this->postJson('/api/v1/webhook/fonnte', [
            'sender'    => $senderB,
            'message'   => 'pesan es 1 ball',
            'inboxid'   => 0,
            'timestamp' => $timeSame,
        ]);
        $resB->assertStatus(200);
        $this->assertNotEquals('ignored', $resB->json('status'));

        $resC = $this->postJson('/api/v1/webhook/fonnte', [
            'sender'    => $senderC,
            'message'   => 'pesan es 3 ball',
            'inboxid'   => 0,
            'timestamp' => $timeSame,
        ]);
        $resC->assertStatus(200);
        $this->assertNotEquals('ignored', $resC->json('status'));

        // (c) Payload identik (retry Fonnte) dengan inboxid: 0 -> hanya diproses sekali
        $senderD = '6281299991104';
        $payloadRetry = [
            'sender'    => $senderD,
            'message'   => 'tes retry pesan',
            'inboxid'   => 0,
            'timestamp' => 1731234600,
        ];

        $resD1 = $this->postJson('/api/v1/webhook/fonnte', $payloadRetry);
        $resD1->assertStatus(200);

        $resD2 = $this->postJson('/api/v1/webhook/fonnte', $payloadRetry);
        $resD2->assertStatus(200);
        $resD2->assertJson(['status' => 'ignored', 'message' => 'Duplicate webhook request']);

        // (d) Payload dengan ID nyata
        $senderE = '6281299991105';
        $payloadRealId = [
            'id'        => 'fonnte_msg_real_9999',
            'sender'    => $senderE,
            'message'   => 'pesan via real id',
            'inboxid'   => 8888,
            'timestamp' => 1731234700,
        ];

        $resE1 = $this->postJson('/api/v1/webhook/fonnte', $payloadRealId);
        $resE1->assertStatus(200);

        $resE2 = $this->postJson('/api/v1/webhook/fonnte', $payloadRealId);
        $resE2->assertStatus(200);
        $resE2->assertJson(['status' => 'ignored', 'message' => 'Duplicate webhook request']);
    }
}

