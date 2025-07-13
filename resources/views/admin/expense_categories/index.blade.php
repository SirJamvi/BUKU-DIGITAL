@extends('admin.layouts.app')
@section('title', 'Kategori Pengeluaran')
@section('content')
    <x-card title="Daftar Kategori Pengeluaran">
        <x-slot name="headerActions">
            <x-button href="{{ route('admin.expense_categories.create') }}" variant="primary">
                Tambah Kategori
            </x-button>
        </x-slot>
        <x-table>
            @slot('thead')
                <tr><th>Nama</th><th>Tipe</th></tr>
            @endslot
            @foreach($categories as $category)
                <tr><td>{{ $category->name }}</td><td>{{ $category->type }}</td></tr>
            @endforeach
        </x-table>
    </x-card>
@endsection