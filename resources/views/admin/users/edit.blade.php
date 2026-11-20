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
                <x-select
                    name="role"
                    label="Role Utama"
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

        {{-- Role Tambahan / Rangkap Jabatan --}}
        @php
            $secondaryRoleOptions = \App\Models\User::SECONDARY_ROLES;
            $selectedSecondaryRoles = old('secondary_roles', is_array($user->secondary_roles) ? $user->secondary_roles : []);
            if (!is_array($selectedSecondaryRoles)) {
                $selectedSecondaryRoles = [];
            }
            $selectedSecondaryRoles = array_map('strtolower', $selectedSecondaryRoles);
        @endphp
        <input type="hidden" name="secondary_roles_submitted" value="1">
        <div class="row mt-2">
            <div class="col-12">
                <label class="form-label fw-bold">Role Tambahan / Rangkap Jabatan <span class="text-muted fw-normal">(Opsional)</span></label>
                <p class="text-muted small mb-2">Centang jabatan tambahan jika karyawan ini merangkap tugas (misal: KO merangkap Kasir, atau Kasir merangkap Driver). Opsi yang sama dengan Role Utama otomatis disembunyikan.</p>
                <div class="d-flex flex-wrap gap-2" id="secondary-roles-wrapper">
                    @foreach($secondaryRoleOptions as $roleValue => $roleLabel)
                        @php
                            $isChecked = in_array($roleValue, $selectedSecondaryRoles, true);
                        @endphp
                        <div class="secondary-role-item" data-role-option="{{ $roleValue }}">
                            <div class="form-check border px-3 py-2 rounded {{ $isChecked ? 'bg-primary bg-opacity-10 border-primary' : 'bg-light' }}">
                                <input
                                    class="form-check-input secondary-role-checkbox"
                                    type="checkbox"
                                    name="secondary_roles[]"
                                    value="{{ $roleValue }}"
                                    id="secondary_role_{{ $roleValue }}"
                                    {{ $isChecked ? 'checked' : '' }}
                                >
                                <label class="form-check-label ms-1 fw-semibold" for="secondary_role_{{ $roleValue }}">
                                    {{ $roleLabel }}
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('secondary_roles')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
                @error('secondary_roles.*')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- Penugasan Cabang (Khusus Staf Non-Admin) --}}
        <input type="hidden" name="branch_ids_submitted" value="1">
        <div class="row mt-3">
            <div class="col-12">
                <label class="form-label fw-bold">Penugasan Cabang (Kasir / Driver / KO)</label>
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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const primaryRoleSelect = document.getElementById('role');
        const secondaryItems = document.querySelectorAll('.secondary-role-item');

        function syncSecondaryRoleOptions() {
            const primaryRole = (primaryRoleSelect?.value || '').trim().toLowerCase();
            secondaryItems.forEach(function (item) {
                const optionValue = (item.dataset.roleOption || '').toLowerCase();
                const checkbox = item.querySelector('.secondary-role-checkbox');
                if (primaryRole !== '' && optionValue === primaryRole) {
                    item.classList.add('d-none');
                    if (checkbox) {
                        checkbox.checked = false;
                        checkbox.disabled = true;
                    }
                } else {
                    item.classList.remove('d-none');
                    if (checkbox) {
                        checkbox.disabled = false;
                    }
                }
            });
        }

        if (primaryRoleSelect) {
            primaryRoleSelect.addEventListener('change', syncSecondaryRoleOptions);
            syncSecondaryRoleOptions();
        }
    });
</script>
@endpush