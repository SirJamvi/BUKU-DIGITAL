<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseSyncController extends Controller
{
    public function storeFromAttendance(Request $request)
    {
        // Ambil kunci dari CONFIG
        $serverKey = config('services.sync.secret'); 

        // 1. Validasi Keamanan
        if ($request->header('X-Sync-Secret') !== $serverKey) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 2. Validasi Data
        $request->validate([
            'user_id'      => 'required|integer',  // ID Driver / Karyawan
            'amount'       => 'required|numeric',
            'description'  => 'required|string',
            'date'         => 'required|date',
            'reference_id' => 'required|string',
        ]);

        try {
            // 3. Ambil user pengirim untuk menentukan business_id dinamis
            $user = User::find($request->user_id);
            if (!$user || !$user->business_id) {
                return response()->json([
                    'message' => "User ID {$request->user_id} tidak ditemukan atau belum terafiliasi dengan unit bisnis."
                ], 422);
            }

            $businessId = (int) $user->business_id;

            // 4. Cari Kategori Gaji dinamis berdasarkan business_id
            $category = ExpenseCategory::where('business_id', $businessId)
                ->where('name', 'LIKE', '%Gaji%')
                ->first();

            if (!$category) {
                Log::warning("⚠️ Kategori Gaji tidak ditemukan untuk Business ID {$businessId}");
                return response()->json([
                    'message' => "Kategori pengeluaran 'Gaji' tidak ditemukan untuk bisnis ID {$businessId}. Harap buat kategori terlebih dahulu."
                ], 422);
            }

            $categoryId = $category->id;
            $referenceId = 'ATT-' . $request->reference_id;

            // 5. Cegah Duplikasi berdasarkan reference_id (Idempotent)
            $exists = DB::table('cash_flow')
                ->where('reference_id', $referenceId)
                ->where('business_id', $businessId)
                ->exists();

            if ($exists) {
                Log::info("ℹ️ Data sudah ada: {$referenceId}");
                return response()->json(['message' => 'Data already synced'], 200);
            }

            // 6. Siapkan Payload Insert
            $insertData = [
                'business_id'  => $businessId,
                'type'         => 'expense',
                'category_id'  => $categoryId,
                'amount'       => $request->amount,
                'description'  => $request->description,
                'date'         => $request->date,
                'reference_id' => $referenceId,
                'created_by'   => $request->user_id,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            // Tulis is_cogs jika kolom sudah ada di database
            if (Schema::hasColumn('cash_flow', 'is_cogs')) {
                $insertData['is_cogs'] = (bool) $category->is_cogs;
            }

            $id = DB::table('cash_flow')->insertGetId($insertData);

            Log::info("✅ Sukses Insert Gaji ke Buku Digital | ID: {$id} | User: {$request->user_id} | Business: {$businessId} | Amount: {$request->amount} | Ref: {$referenceId}");

            return response()->json([
                'message'     => 'Sukses sync gaji',
                'id'          => $id,
                'user_id'     => $request->user_id,
                'business_id' => $businessId,
                'amount'      => $request->amount,
            ], 200);

        } catch (\Exception $e) {
            Log::error("🔥 Error saat insert Buku Digital: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}