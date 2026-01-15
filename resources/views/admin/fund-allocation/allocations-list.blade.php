{{-- resources/views/admin/fund-allocation/partials/allocations-list.blade.php --}}
@forelse($settings as $setting)
    <div class="card mb-3 allocation-item">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <h6 class="mb-1">{{ $setting->allocation_name }}</h6>
                    @if($setting->description)
                        <small class="text-muted">{{ $setting->description }}</small>
                    @endif
                </div>
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <input type="number" 
                               class="form-control quick-percentage" 
                               data-id="{{ $setting->id }}"
                               value="{{ $setting->percentage }}" 
                               min="0" 
                               max="100" 
                               step="0.01">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-primary me-2">
                        Rp {{ number_format((10000000 * $setting->percentage) / 100, 0, ',', '.') }}
                    </span>
                    <button type="button" 
                            class="btn btn-sm btn-warning btn-edit-allocation" 
                            data-id="{{ $setting->id }}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" 
                            class="btn btn-sm btn-danger btn-delete-allocation" 
                            data-id="{{ $setting->id }}"
                            data-name="{{ $setting->allocation_name }}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="alert alert-info text-center">
        <i class="fas fa-info-circle me-2"></i>
        Belum ada alokasi dana. Klik tombol "Tambah Alokasi" untuk memulai.
    </div>
@endforelse