@extends('admin.layouts.app')

@section('title', 'Tambah Cabang Baru')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.branches.index') }}">Manajemen Cabang</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah Cabang</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            @include('components.alert')

            <x-card title="Formulir Tambah Cabang Baru">
                <form action="{{ route('admin.branches.store') }}" method="POST">
                    @csrf
                    @include('admin.branches._form')

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="{{ route('admin.branches.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary" style="background-color: var(--admin-accent); border-color: var(--admin-accent);">
                            <i class="fas fa-save me-1"></i> Simpan Cabang
                        </button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection

