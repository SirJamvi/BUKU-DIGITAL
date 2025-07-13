@extends('admin.layouts.app')
@section('title', 'Tambah Supplier')
@section('content')
    <x-card title="Formulir Tambah Supplier">
        <form action="{{ route('admin.suppliers.store') }}" method="POST">
            @csrf
            @include('admin.suppliers._form')
            <x-button type="submit" variant="primary">Simpan</x-button>
        </form>
    </x-card>
@endsection