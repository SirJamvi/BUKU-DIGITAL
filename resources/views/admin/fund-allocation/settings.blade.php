@extends('admin.layouts.app')

@section('title', 'Pengaturan Alokasi Dana')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.fund-allocation.index') }}">Alokasi Dana</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pengaturan</li>
@endsection

@push('styles')
<style>
    /* Style untuk total persentase */
    #total-percentage-bar {
        transition: width 0.3s ease-in-out;
    }
</style>
@endpush

@section('content')
    
    {{-- Notifikasi akan ditangani oleh AJAX --}}
    <div id="alert-container"></div>

    <div class="row g-4">
        {{-- Kolom Kiri: Edit Pengaturan yang Ada --}}
        <div class="col-lg-7">
            <x-card title="Pengaturan Persentase Alokasi">
                <p>Sesuaikan persentase alokasi dana dari keuntungan bersih. Total persentase tidak boleh melebihi 100%.</p>
                
                <form action="{{ route('admin.fund-allocation.settings.update') }}" method="POST" id="updateSettingsForm">
                    @csrf
                    @method('PUT')

                    <div id="settings-list" class="mb-3">
                        @forelse ($settings as $index => $setting)
                            <div class="row g-3 mb-3 align-items-center" data-id="{{ $setting->id }}">
                                <input type="hidden" name="settings[{{ $index }}][id]" value="{{ $setting->id }}">
                                
                                <div class="col-sm-5">
                                    <label for="setting-name-{{ $setting->id }}" class="form-label d-sm-none">Nama Alokasi</label>
                                    <input type="text" class="form-control" id="setting-name-{{ $setting->id }}"
                                        name="settings[{{ $index }}][allocation_name]" 
                                        value="{{ old('settings.'.$index.'.allocation_name', $setting->allocation_name) }}"
                                        @if($setting->is_default) readonly @endif>
                                </div>
                                <div class="col-sm-5">
                                    <label for="setting-percent-{{ $setting->id }}" class="form-label d-sm-none">Persentase</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control percentage-input" 
                                            id="setting-percent-{{ $setting->id }}"
                                            name="settings[{{ $index }}][percentage]"
                                            value="{{ old('settings.'.$index.'.percentage', $setting->percentage) }}"
                                            min="0" max="100" step="0.01">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-sm-2 text-end">
                                    @if(!$setting->is_default)
                                        <x-button type="button" variant="danger" class="btn-sm delete-setting-btn" data-id="{{ $setting->id }}" data-name="{{ $setting->allocation_name }}">
                                            <i class="fas fa-trash"></i>
                                        </x-button>
                                    @else
                                        <x-button type="button" variant="secondary" class="btn-sm" disabled title="Kategori default tidak bisa dihapus">
                                            <i class="fas fa-lock"></i>
                                        </x-button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-center text-muted" id="no-settings-message">Tidak ada pengaturan alokasi yang tersedia.</p>
                        @endforelse
                    </div>

                    {{-- Total Percentage Bar --}}
                    <div class="mb-3">
                        <label class="form-label">Total Persentase: <strong id="total-percentage-label">0%</strong></label>
                        <div class="progress" style="height: 10px;">
                            <div id="total-percentage-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <x-button type="submit" variant="primary" id="save-changes-btn">
                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>

        {{-- Kolom Kanan: Tambah Pengaturan Baru --}}
        <div class="col-lg-5">
            <x-card title="Tambah Kategori Alokasi Baru">
                <form action="{{ route('admin.fund-allocation.settings.store') }}" method="POST" id="addSettingForm">
                    @csrf
                    <div class="mb-3">
                        <x-input name="allocation_name" id="new_allocation_name" label="Nama Alokasi" placeholder="Contoh: Sedekah, Dana Liburan" required />
                    </div>
                    <div class="mb-3">
                        <x-input type="number" name="percentage" id="new_percentage" label="Persentase (%)" placeholder="Contoh: 10" min="0" max="100" step="0.01" required />
                    </div>
                    <div class="mb-3">
                        <x-input name="category" id="new_category" label="Kategori Internal" placeholder="Contoh: custom_fund (huruf kecil, tanpa spasi)" required />
                        <small class="form-text">Ini adalah ID unik untuk sistem, gunakan huruf kecil dan underscore. Contoh: `sedekah`, `dana_liburan`.</small>
                    </div>
                    <div class="d-flex justify-content-end">
                        <x-button type="submit" variant="success" id="add-setting-btn">
                            <i class="fas fa-plus me-2"></i>Tambah Kategori
                        </x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const addForm = document.getElementById('addSettingForm');
    const updateForm = document.getElementById('updateSettingsForm');
    const settingsList = document.getElementById('settings-list');
    const alertContainer = document.getElementById('alert-container');
    const totalPercentageLabel = document.getElementById('total-percentage-label');
    const totalPercentageBar = document.getElementById('total-percentage-bar');
    const noSettingsMessage = document.getElementById('no-settings-message');

    // --- Fungsi Helper untuk Menampilkan Alert ---
    function showAlert(message, type = 'success') {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        alertContainer.innerHTML = alertHtml;
        window.scrollTo(0, 0); // Scroll ke atas agar user lihat notifikasi
    }

    // --- Fungsi untuk Menghitung Total Persentase ---
    function calculateTotalPercentage() {
        let total = 0;
        const inputs = document.querySelectorAll('.percentage-input');
        inputs.forEach(input => {
            total += parseFloat(input.value) || 0;
        });

        total = parseFloat(total.toFixed(2));
        totalPercentageLabel.textContent = `${total}%`;
        totalPercentageBar.style.width = `${total}%`;
        totalPercentageBar.setAttribute('aria-valuenow', total);

        if (total > 100) {
            totalPercentageBar.classList.remove('bg-success');
            totalPercentageBar.classList.add('bg-danger');
            totalPercentageLabel.classList.remove('text-dark');
            totalPercentageLabel.classList.add('text-danger');
        } else {
            totalPercentageBar.classList.remove('bg-danger');
            totalPercentageBar.classList.add('bg-success');
            totalPercentageLabel.classList.remove('text-danger');
            totalPercentageLabel.classList.add('text-dark');
        }
    }

    // --- Inisialisasi Perhitungan Saat Halaman Dimuat ---
    calculateTotalPercentage();

    // --- Event Listener untuk Input Persentase ---
    settingsList.addEventListener('input', function(e) {
        if (e.target.classList.contains('percentage-input')) {
            calculateTotalPercentage();
        }
    });

    // --- Handler untuk Form Tambah Kategori (AJAX) ---
    addForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('add-setting-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...';

        const formData = new FormData(this);
        
        fetch(this.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                // Tambahkan item baru ke DOM (kita reload saja agar mudah)
                location.reload(); 
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Terjadi kesalahan jaringan. Silakan coba lagi.', 'danger');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus me-2"></i>Tambah Kategori';
        });
    });

    // --- Handler untuk Hapus Kategori (AJAX) ---
    settingsList.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.delete-setting-btn');
        
        if (deleteBtn) {
            const id = deleteBtn.dataset.id;
            const name = deleteBtn.dataset.name;
            
            if (confirm(`Apakah Anda yakin ingin menghapus kategori "${name}"?`)) {
                deleteBtn.disabled = true;
                deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                fetch(`/admin/fund-allocation/settings/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        const rowToRemove = deleteBtn.closest('.row[data-id]');
                        rowToRemove.remove();
                        calculateTotalPercentage(); // Hitung ulang total
                        if (settingsList.children.length === 0) {
                            noSettingsMessage.style.display = 'block';
                        }
                    } else {
                        showAlert(data.message, 'danger');
                        deleteBtn.disabled = false;
                        deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Terjadi kesalahan jaringan.', 'danger');
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                });
            }
        }
    });

    // --- Handler untuk Form Update Persentase ---
    updateForm.addEventListener('submit', function(e) {
        const total = parseFloat(totalPercentageBar.getAttribute('aria-valuenow'));
        if (total > 100) {
            e.preventDefault();
            showAlert('Total persentase tidak boleh melebihi 100%. Mohon perbaiki sebelum menyimpan.', 'danger');
            return;
        }

        const btn = document.getElementById('save-changes-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...';
    });

});
</script>
@endpush