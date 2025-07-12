{{-- resources/views/admin/users/create.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Tambah Pengguna Baru')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Pengguna</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah Baru</li>
@endsection

@section('content')
    <x-card title="Formulir Tambah Pengguna">
        <form action="{{ route('admin.users.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <x-input name="name" label="Nama Lengkap" placeholder="Masukkan nama pengguna" required />
                </div>
                <div class="col-md-6">
                    <x-input type="email" name="email" label="Alamat Email" placeholder="email@contoh.com" required />
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <x-input type="password" name="password" label="Kata Sandi" placeholder="Masukkan kata sandi" required />
                </div>
                <div class="col-md-6">
                    <x-input type="password" name="password_confirmation" label="Konfirmasi Kata Sandi" placeholder="Ulangi kata sandi" required />
                </div>
            </div>
             <div class="row">
                <div class="col-md-6">
                    <x-select 
                        name="role" 
                        label="Role Pengguna" 
                        :options="['admin' => 'Admin', 'kasir' => 'Kasir']" 
                        required 
                    />
                </div>
                <div class="col-md-6">
                     <div class="form-group">
                        <label class="form-label">Status</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" checked>
                            <label class="form-check-label" for="isActive">Aktif</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <x-button href="{{ route('admin.users.index') }}" variant="secondary" class="me-2">Batal</x-button>
                <x-button type="submit" variant="primary"><i class="fas fa-save me-2"></i>Simpan Pengguna</x-button>
            </div>
        </form>
    </x-card>
@endsection