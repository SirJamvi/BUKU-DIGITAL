@extends('admin.layouts.app')

@section('title', 'Laporan Pengeluaran')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pengeluaran</li>
@endsection

@section('content')
    {{-- Form Filter --}}
    <x-card title="Filter Laporan">
        <form method="GET" action="{{ route('admin.financial.expenses') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <x-input type="date" name="start_date" label="Dari Tanggal" :value="request('start_date')" />
                </div>
                <div class="col-md-4">
                    <x-input type="date" name="end_date" label="Sampai Tanggal" :value="request('end_date')" />
                </div>
                <div class="col-md-4 d-flex">
                    <x-button type="submit" variant="primary" class="me-2">
                        <i class="fas fa-filter me-1"></i> Terapkan
                    </x-button>
                    <a href="{{ route('admin.financial.expenses') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </x-card>

    <div class="mt-4"></div>

    <x-card title="Rincian Data Pengeluaran">
        @slot('headerActions')
            <div class="d-flex align-items-center">
                {{-- Tombol Ekspor Dropdown --}}
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-file-export me-2"></i>Ekspor
                    </button>
                    <ul class="dropdown-menu">
                        {{-- request()->query() akan meneruskan parameter filter (start_date, end_date) ke URL ekspor --}}
                        <li><a class="dropdown-item" href="{{ route('admin.financial.expenses.export.excel', request()->query()) }}">Excel (.xlsx)</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.financial.expenses.export.pdf', request()->query()) }}">PDF (.pdf)</a></li>
                    </ul>
                </div>

                <x-button type="button" variant="info" class="me-2" data-bs-toggle="modal" data-bs-target="#categoriesModal">
                    <i class="fas fa-tags me-2"></i>Kelola Kategori
                </x-button>
                <x-button href="{{ route('admin.financial.expenses.create') }}" variant="primary">
                    <i class="fas fa-plus me-2"></i>Catat Pengeluaran Baru
                </x-button>
            </div>
        @endslot

        @include('components.alert')

        <x-table>
            @slot('thead')
                <tr>
                    <th>Tanggal</th>
                    <th>Kategori</th>
                    <th>Deskripsi</th>
                    <th class="text-end">Jumlah</th>
                    <th>Dicatat oleh</th>
                    <th class="text-center">Aksi</th>
                </tr>
            @endslot
            @forelse ($expenses as $expense)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($expense->date)->isoFormat('D MMM YYYY') }}</td>
                    <td><span class="badge bg-secondary">{{ $expense->category->name ?? 'N/A' }}</span></td>
                    <td>{{ $expense->description }}</td>
                    <td class="text-end text-danger">Rp {{ number_format($expense->amount, 0, ',', '.') }}</td>
                    <td>{{ $expense->createdBy->name ?? 'Sistem' }}</td>
                    <td class="text-center">
                        <x-button href="{{ route('admin.financial.expenses.edit', $expense->id) }}" variant="warning" class="btn-sm"><i class="fas fa-edit"></i></x-button>
                        <x-button type="button" variant="danger" class="btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $expense->id }}"><i class="fas fa-trash"></i></x-button>
                        
                        <x-modal id="deleteModal-{{ $expense->id }}" title="Konfirmasi Hapus">
                            <p>Apakah Anda yakin ingin menghapus pengeluaran ini?</p>
                            <p><strong>Deskripsi:</strong> {{ $expense->description }}</p>
                            @slot('footer')
                                <form action="{{ route('admin.financial.expenses.destroy', $expense->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="button" variant="secondary" data-bs-dismiss="modal">Batal</x-button>
                                    <x-button type="submit" variant="danger">Ya, Hapus</x-button>
                                </form>
                            @endslot
                        </x-modal>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Tidak ada data pengeluaran yang ditemukan.</td></tr>
            @endforelse
        </x-table>

        @if ($expenses->hasPages())
            {{-- `appends(request()->query())` penting agar filter tetap aktif saat pindah halaman --}}
            <div class="mt-3">{{ $expenses->appends(request()->query())->links('components.pagination') }}</div>
        @endif
    </x-card>

    {{-- Modal untuk Kelola Kategori (tidak berubah) --}}
    <x-modal id="categoriesModal" title="Kelola Kategori Pengeluaran">
        <h5>Tambah Kategori Baru</h5>
        <form action="{{ route('admin.financial.expense_categories.store') }}" method="POST" class="mb-4">
            @csrf
            <div class="input-group">
                <input type="text" name="name" class="form-control" placeholder="Nama Kategori Baru" required>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
        <hr>
        <h5>Daftar Kategori</h5>
        <ul class="list-group">
            @forelse ($categories as $category)
                <li class="list-group-item">{{ $category->name }}</li>
            @empty
                <li class="list-group-item">Belum ada kategori.</li>
            @endforelse
        </ul>
    </x-modal>
@endsection