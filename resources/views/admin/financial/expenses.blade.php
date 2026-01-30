@extends('admin.layouts.app')

@section('title', 'Laporan Pengeluaran')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pengeluaran</li>
@endsection

@push('styles')
<style>
    /* Responsive Header Actions */
    .header-actions-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
    }

    /* Mobile: Stack buttons vertically */
    @media (max-width: 575.98px) {
        .header-actions-wrapper {
            width: 100%;
            flex-direction: column;
        }

        .header-actions-wrapper .btn,
        .header-actions-wrapper .btn-group {
            width: 100%;
        }

        .header-actions-wrapper .dropdown-toggle {
            width: 100%;
            text-align: left;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Make buttons more touch-friendly */
        .btn {
            padding: 0.6rem 1rem;
            min-height: 44px;
        }

        /* Filter form buttons */
        .filter-actions {
            width: 100%;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-actions .btn {
            width: 100%;
        }
    }

    /* Tablet: 2 columns */
    @media (min-width: 576px) and (max-width: 767.98px) {
        .header-actions-wrapper {
            width: 100%;
        }

        .header-actions-wrapper .btn-group,
        .header-actions-wrapper .btn {
            flex: 1 1 calc(50% - 0.25rem);
            min-width: 0;
        }
    }

    /* Desktop: Keep original layout */
    @media (min-width: 768px) {
        .header-actions-wrapper {
            justify-content: flex-end;
        }
    }

    /* Table responsiveness enhancements */
    @media (max-width: 767.98px) {
        .table {
            font-size: 0.75rem;
        }

        .table th,
        .table td {
            padding: 0.5rem 0.25rem;
            white-space: nowrap;
        }

        /* Stack action buttons vertically in mobile */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .action-buttons .btn {
            width: 100%;
            min-width: auto;
        }

        /* Adjust badge and text sizes */
        .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }

        /* Make modal more mobile-friendly */
        .modal-dialog {
            margin: 0.5rem;
        }

        .modal-body {
            padding: 1rem;
        }
    }

    /* Better dropdown menu on mobile */
    @media (max-width: 575.98px) {
        .dropdown-menu {
            width: 100%;
            left: 0 !important;
            right: 0 !important;
            transform: none !important;
        }
    }

    /* Scroll hint for tables */
    .table-scroll-hint {
        display: none;
        text-align: center;
        padding: 0.5rem;
        background: rgba(212, 175, 55, 0.1);
        color: var(--luxury-gold);
        font-size: 0.75rem;
        border-radius: 0 0 8px 8px;
        margin-top: -1px;
    }

    @media (max-width: 991.98px) {
        .table-scroll-hint.show {
            display: block;
        }
    }

    /* Loading state */
    .btn-loading {
        position: relative;
        pointer-events: none;
        opacity: 0.7;
    }

    .btn-loading::after {
        content: '';
        position: absolute;
        width: 16px;
        height: 16px;
        top: 50%;
        left: 50%;
        margin-left: -8px;
        margin-top: -8px;
        border: 2px solid #ffffff;
        border-radius: 50%;
        border-top-color: transparent;
        animation: spinner 0.6s linear infinite;
    }

    @keyframes spinner {
        to { transform: rotate(360deg); }
    }

    /* Better spacing for cards on mobile */
    @media (max-width: 575.98px) {
        .card {
            margin-bottom: 1rem;
        }

        .card-header {
            padding: 0.875rem;
            font-size: 0.95rem;
        }

        .card-body {
            padding: 0.875rem;
        }
    }
</style>
@endpush

@section('content')
    {{-- Form Filter --}}
    <x-card title="Filter Laporan">
        <form method="GET" action="{{ route('admin.financial.expenses') }}" id="filterForm">
            <div class="row g-3">
                {{-- 1. Filter Tanggal Mulai --}}
                <div class="col-md-3 col-sm-6">
                    <x-input type="date" name="start_date" label="Dari Tanggal" :value="request('start_date')" />
                </div>

                {{-- 2. Filter Tanggal Sampai --}}
                <div class="col-md-3 col-sm-6">
                    <x-input type="date" name="end_date" label="Sampai Tanggal" :value="request('end_date')" />
                </div>

                {{-- 3. Filter Kategori (INI YANG BARU DITAMBAHKAN) --}}
                <div class="col-md-3 col-sm-6">
                    <label class="form-label">Kategori</label>
                    <select name="category_id" class="form-select">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $cat)
                            {{-- PERHATIKAN BAGIAN VALUE DAN LOGIKA SELECTED INI --}}
                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- 4. Tombol Aksi --}}
                <div class="col-md-3 col-12">
                    <label class="form-label d-none d-md-block">&nbsp;</label>
                    <div class="d-flex filter-actions">
                        <x-button type="submit" variant="primary" class="me-2 w-100">
                            <i class="fas fa-filter me-1"></i> Terapkan
                        </x-button>
                        <a href="{{ route('admin.financial.expenses') }}" class="btn btn-secondary w-100">
                            <i class="fas fa-redo me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </x-card>

    <div class="mt-3 mt-md-4"></div>

    <x-card title="Rincian Data Pengeluaran">
        @slot('headerActions')
            <div class="header-actions-wrapper">
                {{-- Tombol Ekspor Dropdown --}}
                <div class="btn-group">
                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-file-export me-2"></i>Ekspor
                        <span class="d-none d-md-inline">Data</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="{{ route('admin.financial.expenses.export.excel', request()->query()) }}">
                                <i class="fas fa-file-excel me-2 text-success"></i>Excel (.xlsx)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('admin.financial.expenses.export.pdf', request()->query()) }}">
                                <i class="fas fa-file-pdf me-2 text-danger"></i>PDF (.pdf)
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- Tombol Kelola Kategori --}}
                <x-button type="button" variant="info" data-bs-toggle="modal" data-bs-target="#categoriesModal">
                    <i class="fas fa-tags me-2"></i>
                    <span class="d-none d-sm-inline">Kelola </span>Kategori
                </x-button>

                {{-- Tombol Catat Pengeluaran Baru --}}
                <x-button href="{{ route('admin.financial.expenses.create') }}" variant="primary">
                    <i class="fas fa-plus me-2"></i>
                    <span class="d-none d-sm-inline">Catat </span>Pengeluaran<span class="d-none d-sm-inline"> Baru</span>
                </x-button>
            </div>
        @endslot

        @include('components.alert')

        <div class="table-responsive">
            <x-table>
                @slot('thead')
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th class="d-none d-md-table-cell">Deskripsi</th>
                        <th class="text-end">Jumlah</th>
                        <th class="d-none d-lg-table-cell">Dicatat oleh</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                @endslot
                @forelse ($expenses as $expense)
                    <tr>
                        <td data-label="Tanggal">
                            <span class="d-none d-md-inline">{{ \Carbon\Carbon::parse($expense->date)->isoFormat('D MMM YYYY') }}</span>
                            <span class="d-md-none">{{ \Carbon\Carbon::parse($expense->date)->isoFormat('D/M/YY') }}</span>
                        </td>
                        <td data-label="Kategori">
                            <span class="badge bg-secondary">{{ $expense->category->name ?? 'N/A' }}</span>
                        </td>
                        <td data-label="Deskripsi" class="d-none d-md-table-cell">
                            {{ Str::limit($expense->description, 50) }}
                        </td>
                        <td data-label="Jumlah" class="text-end text-danger fw-bold">
                            <span class="d-none d-sm-inline">Rp </span>{{ number_format($expense->amount, 0, ',', '.') }}
                        </td>
                        <td data-label="Dicatat oleh" class="d-none d-lg-table-cell">
                            {{ $expense->createdBy->name ?? 'Sistem' }}
                        </td>
                        <td data-label="Aksi" class="text-center">
                            <div class="action-buttons d-inline-flex d-md-inline">
                                {{-- Tombol Detail untuk Mobile --}}
                                <x-button 
                                    type="button" 
                                    variant="info" 
                                    class="btn-sm d-md-none me-1" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#detailModal-{{ $expense->id }}"
                                    title="Detail">
                                    <i class="fas fa-eye"></i>
                                </x-button>

                                {{-- Tombol Edit --}}
                                <x-button 
                                    href="{{ route('admin.financial.expenses.edit', $expense->id) }}" 
                                    variant="warning" 
                                    class="btn-sm me-1"
                                    title="Edit">
                                    <i class="fas fa-edit"></i>
                                    <span class="d-none d-xl-inline ms-1">Edit</span>
                                </x-button>

                                {{-- Tombol Hapus --}}
                                <x-button 
                                    type="button" 
                                    variant="danger" 
                                    class="btn-sm" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#deleteModal-{{ $expense->id }}"
                                    title="Hapus">
                                    <i class="fas fa-trash"></i>
                                    <span class="d-none d-xl-inline ms-1">Hapus</span>
                                </x-button>
                            </div>

                            {{-- Modal Detail (untuk mobile) --}}
                            <x-modal id="detailModal-{{ $expense->id }}" title="Detail Pengeluaran">
                                <div class="mb-3">
                                    <strong>Tanggal:</strong><br>
                                    {{ \Carbon\Carbon::parse($expense->date)->isoFormat('D MMMM YYYY') }}
                                </div>
                                <div class="mb-3">
                                    <strong>Kategori:</strong><br>
                                    <span class="badge bg-secondary">{{ $expense->category->name ?? 'N/A' }}</span>
                                </div>
                                <div class="mb-3">
                                    <strong>Deskripsi:</strong><br>
                                    {{ $expense->description }}
                                </div>
                                <div class="mb-3">
                                    <strong>Jumlah:</strong><br>
                                    <span class="text-danger fw-bold fs-5">Rp {{ number_format($expense->amount, 0, ',', '.') }}</span>
                                </div>
                                <div class="mb-3">
                                    <strong>Dicatat oleh:</strong><br>
                                    {{ $expense->createdBy->name ?? 'Sistem' }}
                                </div>
                                @slot('footer')
                                    <x-button type="button" variant="secondary" data-bs-dismiss="modal">Tutup</x-button>
                                @endslot
                            </x-modal>

                            {{-- Modal Delete --}}
                            <x-modal id="deleteModal-{{ $expense->id }}" title="Konfirmasi Hapus">
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Tindakan ini tidak dapat dibatalkan!
                                </div>
                                <p>Apakah Anda yakin ingin menghapus pengeluaran ini?</p>
                                <div class="bg-light p-3 rounded">
                                    <strong>Deskripsi:</strong> {{ $expense->description }}<br>
                                    <strong>Jumlah:</strong> <span class="text-danger">Rp {{ number_format($expense->amount, 0, ',', '.') }}</span>
                                </div>
                                @slot('footer')
                                    <form action="{{ route('admin.financial.expenses.destroy', $expense->id) }}" method="POST" class="delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="button" variant="secondary" data-bs-dismiss="modal">Batal</x-button>
                                        <x-button type="submit" variant="danger" class="delete-btn">
                                            <i class="fas fa-trash me-1"></i> Ya, Hapus
                                        </x-button>
                                    </form>
                                @endslot
                            </x-modal>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                            <p class="text-muted mb-0">Tidak ada data pengeluaran yang ditemukan.</p>
                            @if(request()->has('start_date') || request()->has('end_date'))
                                <a href="{{ route('admin.financial.expenses') }}" class="btn btn-sm btn-link mt-2">
                                    <i class="fas fa-redo me-1"></i> Reset Filter
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </div>

        {{-- Scroll Hint --}}
        <div class="table-scroll-hint" id="scrollHint">
            <i class="fas fa-arrows-alt-h me-2"></i>Geser tabel ke kiri/kanan untuk melihat lebih banyak
        </div>

        @if ($expenses->hasPages())
            <div class="mt-3">
                {{ $expenses->appends(request()->query())->links('components.pagination') }}
            </div>
        @endif
    </x-card>

    {{-- Modal untuk Kelola Kategori --}}
    <x-modal id="categoriesModal" title="Kelola Kategori Pengeluaran" size="lg">
        <div class="mb-4">
            <h5 class="mb-3">
                <i class="fas fa-plus-circle text-primary me-2"></i>Tambah Kategori Baru
            </h5>
            <form action="{{ route('admin.financial.expense_categories.store') }}" method="POST" id="categoryForm">
                @csrf
                <div class="input-group">
                    <input 
                        type="text" 
                        name="name" 
                        class="form-control" 
                        placeholder="Nama Kategori Baru" 
                        required
                        minlength="3"
                        maxlength="50">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>

        <hr>

        <div>
            <h5 class="mb-3">
                <i class="fas fa-list text-info me-2"></i>Daftar Kategorii
            </h5>
            <div class="list-group">
                @forelse ($categories as $category)
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas fa-tag text-secondary me-2"></i>
                            {{ $category->name }}
                        </span>
                        <span class="badge bg-primary rounded-pill">
                            {{ $category->expenses_count ?? 0 }} pengeluaran
                        </span>
                    </div>
                @empty
                    <div class="list-group-item text-center text-muted">
                        <i class="fas fa-folder-open fa-2x mb-2 d-block"></i>
                        Belum ada kategori.
                    </div>
                @endforelse
            </div>
        </div>

        @slot('footer')
            <x-button type="button" variant="secondary" data-bs-dismiss="modal">Tutup</x-button>
        @endslot
    </x-modal>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if table needs scroll hint
    const tableResponsive = document.querySelector('.table-responsive');
    const scrollHint = document.getElementById('scrollHint');
    
    if (tableResponsive && scrollHint && window.innerWidth < 992) {
        if (tableResponsive.scrollWidth > tableResponsive.clientWidth) {
            scrollHint.classList.add('show');
            
            // Hide hint after first scroll
            tableResponsive.addEventListener('scroll', function() {
                scrollHint.classList.remove('show');
            }, { once: true });
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                scrollHint.classList.remove('show');
            }, 5000);
        }
    }

    // Add loading state to delete buttons
    const deleteForms = document.querySelectorAll('.delete-form');
    deleteForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const btn = this.querySelector('.delete-btn');
            if (btn) {
                btn.classList.add('btn-loading');
                btn.disabled = true;
            }
        });
    });

    // Add loading state to filter form
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('btn-loading');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Memuat...';
            }
        });
    }

    // Add loading state to category form
    const categoryForm = document.getElementById('categoryForm');
    if (categoryForm) {
        categoryForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...';
            }
        });
    }

    // Better dropdown positioning on mobile
    if (window.innerWidth < 576) {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(dropdown => {
            dropdown.classList.add('dropdown-menu-end');
        });
    }

    // Touch feedback for buttons on mobile
    if ('ontouchstart' in window) {
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(btn => {
            btn.addEventListener('touchstart', function() {
                this.style.opacity = '0.7';
            });
            btn.addEventListener('touchend', function() {
                this.style.opacity = '1';
            });
        });
    }

    // Auto-close mobile dropdowns after selection
    const dropdownItems = document.querySelectorAll('.dropdown-item');
    dropdownItems.forEach(item => {
        item.addEventListener('click', function() {
            const dropdown = this.closest('.dropdown');
            if (dropdown) {
                const toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
                if (toggle) {
                    bootstrap.Dropdown.getInstance(toggle)?.hide();
                }
            }
        });
    });

    // Enhance modal scrolling on mobile
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            if (window.innerWidth < 576) {
                this.querySelector('.modal-body').style.maxHeight = '60vh';
                this.querySelector('.modal-body').style.overflowY = 'auto';
            }
        });
    });
});

// Prevent double form submission
(function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (this.classList.contains('form-submitted')) {
                e.preventDefault();
                return false;
            }
            this.classList.add('form-submitted');
        });
    });
})();
</script>
@endpush