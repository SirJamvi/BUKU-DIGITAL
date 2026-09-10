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

        // 2. Validasi Data
        $request->validate([
            'amount' => 'required|numeric',
            'description' => 'required|string',
            'date' => 'required|date',
            'reference_id' => 'required|string',
        ]);

        try {
            // 3. Cari Kategori Gaji
            $category = DB::table('expense_categories')
                ->where('name', 'LIKE', '%Gaji%')
                ->where('business_id', 10) // ✅ Filter berdasarkan Business ID 10 juga
                ->first();

            $categoryId = $category ? $category->id : null;

            // Jika kategori tidak ditemukan, buat kategori default untuk Business ID 10
            if (!$categoryId) {
                 // Opsional: Return error atau gunakan ID kategori default/umum
                 // return response()->json(['message' => 'Kategori Gaji tidak ditemukan untuk Business ID 10'], 404);
                 
                 // ATAU Hardcode ID kategori jika Anda tahu ID-nya:
                 $categoryId = 2; // Sesuaikan dengan ID kategori 'Gaji' milik Business 10
            }

            // 4. Cek Duplikasi
            $exists = DB::table('cash_flow')
                ->where('reference_id', 'ATT-' . $request->reference_id)
                ->exists();

            if ($exists) {
                return response()->json(['message' => 'Data already synced'], 200);
            }

            // 5. Insert Data
            $id = DB::table('cash_flow')->insertGetId([
                'business_id' => 10, // ✅ PERBAIKAN: Ubah dari 1 ke 10
                'type' => 'expense',
                'category_id' => $categoryId,
                'amount' => $request->amount,
                'description' => $request->description,
                'date' => $request->date,
                'reference_id' => 'ATT-' . $request->reference_id,
                'created_by' => null, // Biarkan null agar aman dari error foreign key user
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("✅ Sukses Insert ke Buku Digital ID: $id (Business: 10)");

            return response()->json(['message' => 'Sukses sync gaji', 'id' => $id], 200);

        } catch (\Exception $e) {
            Log::error("🔥 Error saat insert Buku Digital: " . $e->getMessage());
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}