@extends('admin.layouts.app')

@section('title', 'Laporan Pengeluaran')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pengeluaran</li>
@endsection

@section('content')
    <x-card title="Rincian Data Pengeluaran">
        @slot('headerActions')
            <div class="d-flex">
                <x-button type="button" variant="info" class="me-2" data-bs-toggle="modal" data-bs-target="#categoriesModal">
                    <i class="fas fa-tags me-2"></i>Kelola Kategori
                </x-button>
                {{-- INI PERBAIKANNYA: Memanggil rute yang benar --}}
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
                </tr>
            @endslot
            @forelse ($expenses as $expense)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($expense->date)->isoFormat('D MMM YYYY') }}</td>
                    <td><span class="badge bg-secondary">{{ $expense->category->name ?? 'N/A' }}</span></td>
                    <td>{{ $expense->description }}</td>
                    <td class="text-end text-danger">Rp {{ number_format($expense->amount, 0, ',', '.') }}</td>
                    <td>{{ $expense->createdBy->name ?? 'Sistem' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center">Tidak ada data pengeluaran yang ditemukan.</td></tr>
            @endforelse
        </x-table>

        @if ($expenses->hasPages())
            <div class="mt-3">{{ $expenses->links('components.pagination') }}</div>
        @endif
    </x-card>

    {{-- Modal untuk Kelola Kategori --}}
    <x-modal id="categoriesModal" title="Kelola Kategori Pengeluaran">
        <h5>Tambah Kategori Baru</h5>
        <form action="{{ route('admin.financial.expense_categories.store') }}" method="POST" class="mb-4">
            @csrf
            <div class="row">
                <div class="col-md-6"><x-input name="name" label="Nama Kategori" required /></div>
                <div class="col-md-6"><x-input name="type" label="Tipe Kategori" value="Operasional" required /></div>
            </div>
            <x-button type="submit" variant="primary">Simpan Kategori</x-button>
        </form>
        <hr>
        <h5>Daftar Kategori yang Sudah Ada</h5>
        <ul class="list-group">
            @forelse ($categories as $category)
                <li class="list-group-item">{{ $category->name }} ({{ $category->type }})</li>
            @empty
                <li class="list-group-item">Belum ada kategori.</li>
            @endforelse
        </ul>
    </x-modal>
@endsection