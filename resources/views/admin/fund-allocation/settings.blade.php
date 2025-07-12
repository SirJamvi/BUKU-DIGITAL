{{-- resources/views/admin/fund-allocation/settings.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Pengaturan Alokasi Dana')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.fund-allocation.index') }}">Alokasi Dana</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pengaturan</li>
@endsection

@section('content')
    <x-card title="Formulir Pengaturan Alokasi Dana">
        <p>Sesuaikan persentase alokasi dana dari keuntungan bersih. Total persentase tidak boleh melebihi 100%.</p>
        
        @include('components.alert')

        <form action="{{ route('admin.fund-allocation.settings.update') }}" method="POST">
            @csrf
            @method('PUT')

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row">
                @forelse ($settings as $index => $setting)
                    <div class="col-md-6 mb-3">
                        <label for="setting-{{ $setting->id }}" class="form-label">{{ $setting->allocation_name }}</label>
                        <div class="input-group">
                            <input type="hidden" name="settings[{{ $index }}][id]" value="{{ $setting->id }}">
                            <input 
                                type="number" 
                                class="form-control" 
                                id="setting-{{ $setting->id }}" 
                                name="settings[{{ $index }}][percentage]"
                                value="{{ old('settings.'.$index.'.percentage', $setting->percentage) }}"
                                min="0" max="100" step="0.01"
                            >
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <p class="text-center text-muted">Tidak ada pengaturan alokasi yang tersedia.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-3 d-flex justify-content-end">
                <x-button type="reset" variant="secondary" class="me-2">Reset</x-button>
                <x-button type="submit" variant="primary"><i class="fas fa-save me-2"></i>Simpan Pengaturan</x-button>
            </div>
        </form>
    </x-card>
@endsection