@extends('admin.layouts.app')
@section('title', 'Edit Supplier')
@section('content')
    <x-card title="Formulir Edit Supplier">
        <form action="{{ route('admin.suppliers.update', $supplier) }}" method="POST">
            @csrf
            @method('PUT')
            @include('admin.suppliers._form', ['supplier' => $supplier])
            <x-button type="submit" variant="primary">Update</x-button>
        </form>
    </x-card>
@endsection