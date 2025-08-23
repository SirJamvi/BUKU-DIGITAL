@extends('admin.layouts.app')

@section('title', 'Edit Pengeluaran')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.expenses') }}">Pengeluaran</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
    <x-card title="Formulir Edit Pengeluaran">
        <form action="{{ route('admin.financial.expenses.update', $expense->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <x-input 
                name="category_name" 
                label="Kategori Pengeluaran" 
                :value="$expense->category->name"
                required 
            />
            <x-input type="number" name="amount" label="Jumlah (Rp)" :value="$expense->amount" required />
            <x-input type="date" name="date" label="Tanggal Pengeluaran" :value="\Carbon\Carbon::parse($expense->date)->toDateString()" required />
            <x-input type="textarea" name="description" label="Deskripsi" :value="$expense->description" required />
            
            <div class="d-flex justify-content-end mt-4">
                <x-button href="{{ route('admin.financial.expenses') }}" variant="secondary" class="me-2">Batal</x-button>
                <x-button type="submit" variant="primary">Simpan Perubahan</x-button>
            </div>
        </form>
    </x-card>
@endsection