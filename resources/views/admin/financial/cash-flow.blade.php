{{-- resources/views/admin/financial/cash-flow.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Laporan Arus Kas (Cash Flow)')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item active" aria-current="page">Arus Kas</li>
@endsection

@section('content')
    {{-- Form Filter Arus Kas --}}
    <x-card title="Filter Buku Kas" class="mb-4">
        <form method="GET" action="{{ route('admin.financial.cash-flow') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6">
                    <x-input type="date" name="start_date" label="Dari Tanggal" :value="request('start_date')" />
                </div>
                <div class="col-md-3 col-sm-6">
                    <x-input type="date" name="end_date" label="Sampai Tanggal" :value="request('end_date')" />
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-bold">Jenis Aliran (Flow Type)</label>
                    <select name="flow_type" class="form-select form-select-sm">
                        <option value="">-- Semua Aliran --</option>
                        <option value="sale_direct" {{ request('flow_type') === 'sale_direct' ? 'selected' : '' }}>Penjualan Langsung</option>
                        <option value="debt_settlement" {{ request('flow_type') === 'debt_settlement' ? 'selected' : '' }}>Pelunasan Kasbon</option>
                        <option value="expense" {{ request('flow_type') === 'expense' ? 'selected' : '' }}>Beban Operasional</option>
                        <option value="stock_purchase" {{ request('flow_type') === 'stock_purchase' ? 'selected' : '' }}>Pembelian Stok (HPP)</option>
                        <option value="salary_external" {{ request('flow_type') === 'salary_external' ? 'selected' : '' }}>Gaji Karyawan (Uang Bos)</option>
                        <option value="cash_handover" {{ request('flow_type') === 'cash_handover' ? 'selected' : '' }}>Setoran ke Owner/Bank</option>
                        <option value="cash_refund" {{ request('flow_type') === 'cash_refund' ? 'selected' : '' }}>Refund / Void Nota</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label small fw-bold">Sumber Dana</label>
                    <select name="funding_source" class="form-select form-select-sm">
                        <option value="">-- Semua Sumber --</option>
                        <option value="drawer" {{ request('funding_source') === 'drawer' ? 'selected' : '' }}>Laci Kasir</option>
                        <option value="owner" {{ request('funding_source') === 'owner' ? 'selected' : '' }}>Rekening Owner</option>
                        <option value="external" {{ request('funding_source') === 'external' ? 'selected' : '' }}>Dana Eksternal/Bos</option>
                    </select>
                </div>
                <div class="col-md-2 col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                    <a href="{{ route('admin.financial.cash-flow') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-redo"></i></a>
                </div>
            </div>
        </form>
    </x-card>

    <x-card title="Buku Arus Kas (Cash Flow)">
        @slot('headerActions')
            {{-- Tombol Ekspor PDF jika diperlukan --}}
            <a href="{{ route('admin.reports.financial.export.pdf', request()->query()) }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-print me-2"></i>Ekspor PDF
            </a>
        @endslot

        @include('components.alert')

        <div class="table-responsive">
            <x-table>
                @slot('thead')
                    <tr>
                        <th style="width: 12%;">Tanggal</th>
                        <th style="width: 18%;">Jenis Aliran & Kategori</th>
                        <th style="width: 16%;" class="text-center">Sumber & Laci</th>
                        <th style="width: 14%;" class="text-center">Status P&L</th>
                        <th style="width: 22%;">Deskripsi</th>
                        <th style="width: 18%;" class="text-end">Jumlah</th>
                    </tr>
                @endslot

                @forelse ($cashFlows as $flow)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ \Carbon\Carbon::parse($flow->date)->format('d/m/Y') }}</span>
                            <br><small class="text-muted font-monospace">#{{ $flow->id }} • {{ $flow->createdBy->name ?? 'Sistem' }}</small>
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <span class="badge {{ $flow->flow_type_badge_class }}" style="font-size: 0.72rem;">
                                    {{ $flow->flow_type_label }}
                                </span>
                                @if($flow->category)
                                <small class="text-muted" style="font-size: 0.72rem;">
                                    {{ $flow->category->name }} ({{ $flow->category->category_type ?? $flow->category->type ?? 'Operasional' }})
                                </small>
                                @endif
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $flow->funding_source_badge_class }}" style="font-size: 0.72rem;">
                                {{ $flow->funding_source_label }}
                            </span>
                            <br><small class="text-muted font-monospace" style="font-size: 0.68rem;">Metode: {{ strtoupper(str_replace('-', ' ', $flow->payment_method ?? 'Cash')) }}</small>
                        </td>
                        <td class="text-center">
                            {!! $flow->profit_loss_indicator_badge !!}
                        </td>
                        <td>
                            <span class="small text-dark">{{ $flow->description }}</span>
                        </td>
                        <td class="text-end">
                            <span class="{{ $flow->type == 'income' ? 'text-success' : 'text-danger' }} fw-bold">
                                {{ $flow->type == 'expense' ? '-' : '+' }} Rp {{ number_format($flow->amount, 0, ',', '.') }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-3x mb-2 d-block opacity-50"></i>
                            Tidak ada data arus kas yang cocok dengan filter yang dipilih.
                        </td>
                    </tr>
                @endforelse
            </x-table>
        </div>

        {{-- Pagination --}}
        @if ($cashFlows->hasPages())
            <div class="mt-3">
                {{ $cashFlows->links('components.pagination') }}
            </div>
        @endif
    </x-card>
@endsection