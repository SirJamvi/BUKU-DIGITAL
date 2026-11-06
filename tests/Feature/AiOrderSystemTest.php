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
}
