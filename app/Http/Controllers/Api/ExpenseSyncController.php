<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ExpenseCategory;

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

        // 2. Validasi Data (Tambahkan user_id)
        $request->validate([
            'user_id' => 'required|integer',  // ID Driver yang checkout
            'amount' => 'required|numeric',
            'description' => 'required|string',
            'date' => 'required|date',
            'reference_id' => 'required|string',
        ]);

        try {
            // 3. Cari Kategori Gaji untuk Business ID 10
            $category = DB::table('expense_categories')
                ->where('name', 'LIKE', '%Gaji%')
                ->where('business_id', 10)
                ->first();

            $categoryId = $category ? $category->id : null;

            // Jika kategori tidak ditemukan, gunakan ID default atau return error
            if (!$categoryId) {
                Log::warning("⚠️ Kategori Gaji tidak ditemukan untuk Business ID 10");
                // Opsi 1: Return error
                // return response()->json(['message' => 'Kategori Gaji tidak ditemukan untuk Business ID 10'], 404);
                
                // Opsi 2: Gunakan ID kategori default (sesuaikan dengan DB Anda)
                $categoryId = 2; // Sesuaikan dengan ID kategori 'Gaji' milik Business 10
            }

            // 4. Cek Duplikasi berdasarkan reference_id
            $exists = DB::table('cash_flow')
                ->where('reference_id', 'ATT-' . $request->reference_id)
                ->where('business_id', 10)
                ->exists();

            if ($exists) {
                Log::info("ℹ️ Data sudah ada: ATT-{$request->reference_id}");
                return response()->json(['message' => 'Data already synced'], 200);
            }

            // 5. Insert Data ke cash_flow
            $id = DB::table('cash_flow')->insertGetId([
                'business_id' => 10,
                'type' => 'expense',
                'category_id' => $categoryId,
                'amount' => $request->amount,
                'description' => $request->description,
                'date' => $request->date,
                'reference_id' => 'ATT-' . $request->reference_id,
                'user_id' => $request->user_id,  // Simpan ID Driver
                'created_by' => null, // Biarkan null agar aman dari error foreign key
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("✅ Sukses Insert Gaji ke Buku Digital | ID: {$id} | User: {$request->user_id} | Amount: {$request->amount} | Ref: ATT-{$request->reference_id}");

            return response()->json([
                'message' => 'Sukses sync gaji',
                'id' => $id,
                'user_id' => $request->user_id,
                'amount' => $request->amount
            ], 200);

        } catch (\Exception $e) {
            Log::error("🔥 Error saat insert Buku Digital: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}