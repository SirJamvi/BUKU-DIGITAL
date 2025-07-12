{{-- resources/views/admin/settings/system.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Pengaturan Sistem')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.settings.index') }}">Pengaturan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Sistem</li>
@endsection

@section('content')
    <x-card title="Formulir Pengaturan Sistem">
        @include('components.alert')

        <form action="{{ route('admin.settings.system.update') }}" method="POST">
            @csrf
            @method('PUT')

            {{-- Nama Aplikasi --}}
            <x-input 
                name="app_name" 
                label="Nama Aplikasi" 
                :value="$settings['app_name'] ?? config('app.name')"
                placeholder="Nama yang akan ditampilkan di seluruh sistem" 
                required 
            />

            {{-- Mode Pemeliharaan --}}
            <div class="form-check form-switch mb-3">
                <input 
                    class="form-check-input" 
                    type="checkbox" 
                    name="maintenance_mode" 
                    id="maintenanceMode" 
                    value="1" 
                    @if($settings['maintenance_mode'] ?? false) checked @endif
                >
                <label class="form-check-label" for="maintenanceMode">
                    Aktifkan Mode Pemeliharaan (Maintenance Mode)
                </label>
                <small class="d-block text-muted">Jika diaktifkan, hanya admin yang dapat mengakses situs.</small>
            </div>


            <div class="d-flex justify-content-end mt-4">
                <x-button type="submit" variant="primary"><i class="fas fa-save me-2"></i>Simpan Pengaturan</x-button>
            </div>
        </form>
    </x-card>
@endsection