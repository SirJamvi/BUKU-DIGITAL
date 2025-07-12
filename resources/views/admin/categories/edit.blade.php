@extends('admin.layouts.app')

@section('title', 'Edit Kategori Produk')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.categories.index') }}">Kategori Produk</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Form Edit Kategori</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.categories.update', $category) }}" method="POST">
                @csrf
                @method('PUT')
                {{-- Memanggil form parsial dengan data yang ada --}}
                @include('admin.categories._form', ['category' => $category])
                
                <div class="text-end">
                    <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
@endsection 