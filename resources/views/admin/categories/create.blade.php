@extends('admin.layouts.app')

@section('title', 'Tambah Kategori Produk Baru')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.categories.index') }}">Kategori Produk</a></li>
    <li class="breadcrumb-item active">Tambah Baru</li>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Form Tambah Kategori</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.categories.store') }}" method="POST">
                @csrf
                {{-- Memanggil form parsial --}}
                @include('admin.categories._form')
                
                <div class="text-end">
                    <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endsection