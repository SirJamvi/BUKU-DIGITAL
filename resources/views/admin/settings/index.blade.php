 
{{-- resources/views/admin/settings/index.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Pengaturan')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Pengaturan</li>
@endsection

@section('content')
    <x-card title="Pusat Pengaturan">
        <p>Kelola pengaturan profil Anda dan konfigurasi umum sistem melalui menu di bawah ini.</p>
        
        @include('components.alert')

        <div class="list-group">
            <a href="{{ route('admin.settings.profile') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-user-edit fa-fw me-2"></i>
                    <strong>Profil Saya</strong>
                    <br>
                    <small class="text-muted">Perbarui informasi pribadi Anda seperti nama, email, dan kata sandi.</small>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
            <a href="{{ route('admin.settings.system') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                 <div>
                    <i class="fas fa-cogs fa-fw me-2"></i>
                    <strong>Pengaturan Sistem</strong>
                    <br>
                    <small class="text-muted">Konfigurasi umum aplikasi seperti nama aplikasi dan mode pemeliharaan.</small>
                </div>
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </x-card>
@endsection