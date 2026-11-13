<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBranchRequest;
use App\Http\Requests\Admin\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Support\BranchCache;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BranchController extends Controller
{
    /**
     * Tampilkan daftar cabang pada bisnis pengguna.
     */
    public function index(): View
    {
        $businessId = Auth::user()->business_id;

        $branches = Branch::where('business_id', $businessId)
            ->with('manager')
            ->withCount('inventories')
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.branches.index', compact('branches'));
    }

    /**
     * Tampilkan form pembuatan cabang baru.
     */
    public function create(): View
    {
        $businessId = Auth::user()->business_id;

        $managers = User::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.branches.create', compact('managers'));
    }

    /**
     * Simpan cabang baru dan otomatis inisialisasi baris inventori untuk semua produk aktif.
     */
    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $businessId = Auth::user()->business_id;
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $businessId) {
            $branch = Branch::create([
                'business_id' => $businessId,
                'name'        => $validated['name'],
                'code'        => strtoupper(trim($validated['code'])),
                'is_main'     => false,
                'address'     => $validated['address'] ?? null,
                'phone'       => $validated['phone'] ?? null,
                'manager_id'  => $validated['manager_id'] ?? null,
                'is_active'   => $validated['is_active'] ?? true,
            ]);

            // Inisialisasi inventori produk untuk cabang baru secara aman
            app(BranchContext::class)->runUnscoped(function () use ($branch, $businessId) {
                Product::where('business_id', $businessId)
                    ->where('is_active', true)
                    ->chunk(500, function ($products) use ($branch, $businessId) {
                        foreach ($products as $prod) {
                            Inventory::firstOrCreate(
                                [
                                    'business_id' => $businessId,
                                    'branch_id'   => $branch->id,
                                    'product_id'  => $prod->id,
                                ],
                                [
                                    'current_stock' => 0,
                                    'cost_price'    => (float) ($prod->cost_price ?? 0),
                                ]
                            );
                        }
                    });
            });

            BranchCache::flush($businessId);
        });

        return redirect()->route('admin.branches.index')
            ->with('success', 'Cabang baru berhasil dibuat dan stok produk telah diinisialisasi.');
    }

    /**
     * Tampilkan form edit cabang.
     */
    public function edit(Branch $branch): View
    {
        $this->authorizeBranch($branch);

        $businessId = Auth::user()->business_id;

        $managers = User::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.branches.edit', compact('branch', 'managers'));
    }

    /**
     * Perbarui data cabang (Kode cabang bersifat immutable / tidak dapat diubah).
     */
    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranch($branch);
        $businessId = Auth::user()->business_id;

        $validated = $request->validated();

        // Jangan izinkan deaktifasi jika cabang utama
        if ($branch->is_main && isset($validated['is_active']) && !$validated['is_active']) {
            return back()->with('error', 'Cabang utama (Pusat) tidak dapat dinonaktifkan.');
        }

        $branch->update([
            'name'       => $validated['name'],
            'address'    => $validated['address'] ?? null,
            'phone'      => $validated['phone'] ?? null,
            'manager_id' => $validated['manager_id'] ?? null,
            'is_active'  => $branch->is_main ? true : ($validated['is_active'] ?? $branch->is_active),
        ]);

        BranchCache::flush($businessId);

        return redirect()->route('admin.branches.index')
            ->with('success', "Data cabang '{$branch->name}' berhasil diperbarui.");
    }

    /**
     * Toggle status aktif / nonaktif cabang.
     */
    public function toggleActive(Branch $branch): RedirectResponse
    {
        $this->authorizeBranch($branch);
        $businessId = Auth::user()->business_id;

        if ($branch->is_main) {
            return back()->with('error', 'Cabang utama (Pusat) tidak dapat dinonaktifkan.');
        }

        $newStatus = !$branch->is_active;

        if (!$newStatus) {
            // Periksa sisa stok fisik sebelum menonaktifkan
            $totalStock = (float) $branch->inventories()->sum('current_stock');
            $branch->update(['is_active' => false]);
            BranchCache::flush($businessId);

            if ($totalStock > 0) {
                return back()->with('warning', "Cabang '{$branch->name}' telah dinonaktifkan. Peringatan: Cabang ini masih memiliki total sisa stok {$totalStock} unit.");
            }

            return back()->with('success', "Cabang '{$branch->name}' berhasil dinonaktifkan.");
        }

        $branch->update(['is_active' => true]);
        BranchCache::flush($businessId);

        return back()->with('success', "Cabang '{$branch->name}' berhasil diaktifkan kembali.");
    }

    /**
     * Penghapusan cabang dilarang demi integritas data keuangan & transaksi historis.
     */
    public function destroy(Branch $branch): RedirectResponse
    {
        return back()->with('error', 'Cabang tidak dapat dihapus untuk menjaga integritas riwayat transaksi dan pembukuan. Anda dapat menonaktifkannya.');
    }

    /**
     * Pastikan cabang milik business_id user yang sedang login.
     */
    protected function authorizeBranch(Branch $branch): void
    {
        if ($branch->business_id !== Auth::user()->business_id) {
            abort(404);
        }
    }
}

