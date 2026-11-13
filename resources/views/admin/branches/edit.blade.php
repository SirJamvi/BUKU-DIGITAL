@extends('admin.layouts.app')

@section('title', 'Edit Cabang: ' . $branch->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.branches.index') }}">Manajemen Cabang</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit Cabang</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            @include('components.alert')

            <x-card title="Edit Data Cabang: {{ $branch->name }}">
                <form action="{{ route('admin.branches.update', $branch) }}" method="POST">
                    @csrf
                    @method('PUT')

                    @include('admin.branches._form')

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="{{ route('admin.branches.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary" style="background-color: var(--admin-accent); border-color: var(--admin-accent);">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection

