@extends('admin.layouts.app')

@section('title', 'Catat Pengeluaran Baru')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item active">Catat Pengeluaran</li>
@endsection

@section('content')
    <x-card title="Formulir Pencatatan Pengeluaran">
        <form action="{{ route('admin.expenses.store') }}" method="POST">
            @csrf
            
            {{-- INI BAGIAN YANG DIPERBARUI --}}
            <x-input 
                name="category_name" 
                label="Kategori Pengeluaran" 
                placeholder="Contoh: Biaya Operasional, Gaji, atau Pembelian Bahan Baku" 
                required 
            />
            
            <x-input type="number" name="amount" label="Jumlah (Rp)" placeholder="Masukkan jumlah pengeluaran" required />
            <x-input type="date" name="date" label="Tanggal Pengeluaran" :value="now()->toDateString()" required />
            <x-input type="textarea" name="description" label="Deskripsi" placeholder="Contoh: Pembelian bahan baku dari Supplier X" required />
            
            <div class="d-flex justify-content-end mt-4">
                <x-button href="{{ route('admin.financial.expenses') }}" variant="secondary" class="me-2">Batal</x-button>
                <x-button type="submit" variant="primary">Simpan Pengeluaran</x-button>
            </div>
        </form>
    </x-card>
@endsection