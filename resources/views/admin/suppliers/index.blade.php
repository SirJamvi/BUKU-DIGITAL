@extends('admin.layouts.app')
@section('title', 'Manajemen Supplier')
@section('content')
    <x-card title="Daftar Supplier">
        <x-slot name="headerActions">
            <x-button href="{{ route('admin.suppliers.create') }}" variant="primary">Tambah Supplier</x-button>
        </x-slot>
        @include('components.alert')
        <x-table>
            @slot('thead')
                <tr><th>Nama</th><th>Kontak</th><th>Telepon</th><th>Aksi</th></tr>
            @endslot
            @foreach($suppliers as $supplier)
                <tr>
                    <td>{{ $supplier->name }}</td>
                    <td>{{ $supplier->contact_person }}</td>
                    <td>{{ $supplier->phone }}</td>
                    <td>
                        <x-button href="{{ route('admin.suppliers.edit', $supplier) }}" variant="warning" class="btn-sm">Edit</x-button>
                    </td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
@endsection