{{-- resources/views/admin/users/edit.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Edit Pengguna: ' . $user->name)

@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Pengguna</a></li>
<li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
<x-card title="Formulir Edit Pengguna">
    <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-md-6">
                <x-input name="name" label="Nama Lengkap" :value="$user->name" required />
            </div>
            <div class="col-md-6">
                <x-input type="email" name="email" label="Alamat Email" :value="$user->email" required />
            </div>
        </div>

        <hr>
        <p class="text-muted">Kosongkan kolom kata sandi jika tidak ingin mengubahnya.</p>
        <div class="row">
            <div class="col-md-6">
                <x-input type="password" name="password" label="Kata Sandi Baru" />
            </div>
            <div class="col-md-6">
                <x-input type="password" name="password_confirmation" label="Konfirmasi Kata Sandi Baru" />
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                {{-- PERBAIKAN DI SINI: Menambahkan opsi Driver --}}
                <x-select
                    name="role"
                    label="Role Pengguna"
                    :options="['admin' => 'Admin', 'kasir' => 'Kasir', 'driver' => 'Driver', 'ko' => 'KO']"
                    :selected="$user->role"
                    required />
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="form-check form-switch mt-2">
                        {{-- TAMBAHKAN HIDDEN INPUT DI SINI --}}
                        <input type="hidden" name="is_active" value="0">

                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" @if($user->is_active) checked @endif>
                        <label class="form-check-label" for="isActive">Aktif</label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Penugasan Cabang (Khusus Kasir / Driver) --}}
        <input type="hidden" name="branch_ids_submitted" value="1">
        <div class="row mt-3">
            <div class="col-12">
                <label class="form-label fw-bold">Penugasan Cabang (Kasir / Driver)</label>
                <p class="text-muted small mb-2">Pilih cabang yang dapat diakses oleh pengguna ini. Pengguna Admin/Owner secara otomatis memiliki akses ke seluruh cabang.</p>
                <div class="row">
                    @foreach($branches ?? [] as $branch)
                        @php
                            $isAssigned = $user->branches->contains('id', $branch->id);
                        @endphp
                        <div class="col-md-4 mb-2">
                            <div class="form-check border p-2 rounded {{ $isAssigned ? 'bg-light' : '' }}">
                                <input class="form-check-input ms-1" type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" id="branch_{{ $branch->id }}" {{ $isAssigned ? 'checked' : '' }}>
                                <label class="form-check-label ms-2" for="branch_{{ $branch->id }}">
                                    <strong>{{ $branch->name }}</strong> ({{ $branch->code }})
                                    @if($branch->is_main) <span class="badge bg-primary ms-1">Pusat</span> @endif
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-3">
            <x-button href="{{ route('admin.users.index') }}" variant="secondary" class="me-2">Batal</x-button>
            <x-button type="submit" variant="primary"><i class="fas fa-save me-2"></i>Simpan Perubahan</x-button>
        </div>
    </form>
</x-card>
@endsection