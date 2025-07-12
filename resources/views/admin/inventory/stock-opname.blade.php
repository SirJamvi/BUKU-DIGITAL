{{-- resources/views/admin/inventory/stock-opname.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Proses Stock Opname')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.inventory.index') }}">Inventaris</a></li>
    <li class="breadcrumb-item active" aria-current="page">Stock Opname</li>
@endsection

@section('content')
    <x-card title="Formulir Stock Opname">
        <p>Gunakan formulir ini untuk menyesuaikan jumlah stok di sistem dengan jumlah stok fisik yang ada di gudang. Perbedaan stok akan dicatat sebagai 'adjustment'.</p>
        
        @include('components.alert')

        {{-- 
            CATATAN PENGEMBANGAN:
            Untuk fungsionalitas penuh, form ini memerlukan JavaScript untuk:
            1. Menambahkan baris item baru secara dinamis.
            2. Menghapus baris item.
            3. Menggunakan AJAX untuk mencari produk (berdasarkan nama/SKU) dan mengisi data 'Stok Sistem' secara otomatis.
        --}}
        
        <form action="{{ route('admin.inventory.process-stock-opname') }}" method="POST">
            @csrf
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">Produk</th>
                            <th class="text-center">Stok Sistem</th>
                            <th class="text-center" style="width: 15%;">Stok Fisik Aktual</th>
                            <th>Catatan (Opsional)</th>
                            <th class="text-center" style="width: 5%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="opname-items-wrapper">
                        {{-- Baris item akan ditambahkan di sini oleh JavaScript --}}
                        {{-- Contoh baris statis untuk panduan --}}
                        <tr>
                            <td>
                                {{-- Input 'inventory_id' akan diisi oleh JS setelah produk dipilih --}}
                                <input type="hidden" name="items[0][inventory_id]" value="1"> 
                                <input type="text" class="form-control" placeholder="Cari nama atau SKU produk..." value="Es Kristal" readonly>
                            </td>
                            <td>
                                <input type="text" class="form-control text-center" value="100" readonly>
                            </td>
                            <td>
                                <input type="number" name="items[0][actual_stock]" class="form-control text-center" required>
                            </td>
                            <td>
                                <input type="text" name="items[0][notes]" class="form-control" placeholder="Contoh: Selisih karena rusak">
                            </td>
                            <td class="text-center">
                                <x-button type="button" variant="danger" class="btn-sm remove-item-btn">
                                    <i class="fas fa-trash"></i>
                                </x-button>
                            </td>
                        </tr>
                        {{-- Akhir dari contoh baris --}}
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between mt-3">
                <x-button type="button" variant="success" id="add-item-btn">
                    <i class="fas fa-plus me-2"></i>Tambah Baris
                </x-button>
                <x-button type="submit" variant="primary">
                    <i class="fas fa-save me-2"></i>Proses & Simpan Penyesuaian
                </x-button>
            </div>
        </form>
    </x-card>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Logika sederhana untuk menambah dan menghapus baris.
        // Untuk aplikasi produksi, ini harus dikembangkan lebih lanjut.
        
        const wrapper = document.getElementById('opname-items-wrapper');
        let itemIndex = 1;

        document.getElementById('add-item-btn').addEventListener('click', function() {
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>
                    <input type="hidden" name="items[${itemIndex}][inventory_id]" value="">
                    <input type="text" class="form-control" placeholder="Cari nama atau SKU produk...">
                </td>
                <td>
                    <input type="text" class="form-control text-center" value="-" readonly>
                </td>
                <td>
                    <input type="number" name="items[${itemIndex}][actual_stock]" class="form-control text-center" required>
                </td>
                <td>
                    <input type="text" name="items[${itemIndex}][notes]" class="form-control">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remove-item-btn">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            wrapper.appendChild(newRow);
            itemIndex++;
        });

        wrapper.addEventListener('click', function(e) {
            if (e.target && (e.target.matches('.remove-item-btn') || e.target.closest('.remove-item-btn'))) {
                e.target.closest('tr').remove();
            }
        });
    });
</script>
@endpush