@extends('admin.layouts.app')
@section('title', 'Tambah Kategori Pengeluaran')
@section('content')
    <x-card title="Formulir Kategori Pengeluaran">
        <form action="{{ route('admin.expense_categories.store') }}" method="POST">
            @csrf
            <x-input name="name" label="Nama Kategori" required />
            <x-input name="type" label="Tipe" placeholder="Contoh: Operasional, Pemasaran" required />
            <x-button type="submit" variant="primary">Simpan</x-button>
        </form>
    </x-card>
@endsection