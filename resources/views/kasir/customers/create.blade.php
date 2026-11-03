{{-- resources/views/kasir/customers/create.blade.php --}}
@extends('kasir.layouts.app')

@section('title', 'Tambah Pelanggan Baru')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('kasir.customers.index') }}">Pelanggan</a></li>
    <li class="breadcrumb-item active" aria-current="page">Tambah Baru</li>
@endsection

@section('content')
    <x-card title="Formulir Tambah Pelanggan">
        <form action="{{ route('kasir.customers.store') }}" method="POST">
            @csrf
            
            <x-input name="name" label="Nama Lengkap Pelanggan" placeholder="Masukkan nama pelanggan" required />
            <x-input type="tel" name="phone" label="Nomor Telepon Utama (WhatsApp)" placeholder="Contoh: 08123456789" />

            {{-- Repeater Multi Nomor HP --}}
            <div class="mb-3 p-3 bg-light rounded-3 border">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <label class="form-label mb-0 fw-bold text-dark">
                            <i class="fas fa-phone-volume me-1 text-primary"></i> Nomor Telepon Tambahan (Opsional)
                        </label>
                        <div class="form-text text-muted small">
                            Tambahkan nomor lain (misal: Kasir, Owner, Supir). Pesanan AI dari nomor-nomor ini akan otomatis masuk ke pelanggan yang sama.
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-phone">
                        <i class="fas fa-plus me-1"></i> Tambah Nomor HP
                    </button>
                </div>

                <div id="phones-container">
                    {{-- Baris nomor tambahan diinjeksi via JavaScript --}}
                </div>
            </div>

            <x-input type="email" name="email" label="Alamat Email (Opsional)" placeholder="email@pelanggan.com" />
            
            <div class="mb-3">
                <label for="address" class="form-label">Alamat (Opsional)</label>
                <textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror" rows="3" placeholder="Masukkan alamat lengkap pelanggan">{{ old('address') }}</textarea>
                @error('address')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-end mt-4">
                <x-button href="{{ route('kasir.customers.index') }}" variant="secondary" class="me-2">Batal</x-button>
                <x-button type="submit" variant="primary" style="background-color: var(--kasir-accent); border-color: var(--kasir-accent);">
                    <i class="fas fa-save me-2"></i>Simpan Pelanggan
                </x-button>
            </div>
        </form>
    </x-card>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('phones-container');
    const btnAdd = document.getElementById('btn-add-phone');
    let phoneIndex = 0;

    function addPhoneRow(phoneVal = '', labelVal = '') {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center mb-2 phone-row';
        row.innerHTML = `
            <div class="col-md-6 col-12">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-phone fa-sm text-muted"></i></span>
                    <input type="tel" name="phones[${phoneIndex}][phone]" value="${phoneVal}" 
                           class="form-control" placeholder="Nomor HP (Contoh: 087772591491)" required>
                </div>
            </div>
            <div class="col-md-5 col-9">
                <input type="text" name="phones[${phoneIndex}][label]" value="${labelVal}" 
                       class="form-control" placeholder="Label/Keterangan (misal: Kasir Toko, Owner, Supir)" list="phone-label-suggestions">
            </div>
            <div class="col-md-1 col-3 text-end">
                <button type="button" class="btn btn-outline-danger w-100 btn-remove-phone" title="Hapus nomor ini">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `;

        row.querySelector('.btn-remove-phone').addEventListener('click', function () {
            row.remove();
        });

        container.appendChild(row);
        phoneIndex++;
    }

    if (btnAdd) {
        btnAdd.addEventListener('click', function () {
            addPhoneRow();
        });
    }

    // Restore old inputs if validation failed
    @if(old('phones'))
        @foreach(old('phones') as $p)
            addPhoneRow("{{ $p['phone'] ?? '' }}", "{{ $p['label'] ?? '' }}");
        @endforeach
    @endif
});
</script>
<datalist id="phone-label-suggestions">
    <option value="Kasir Toko">
    <option value="Owner / Pemilik">
    <option value="Supir / Kurir">
    <option value="Admin Cabang">
    <option value="Keluarga">
</datalist>
@endpush