@extends('admin.layouts.app')

@section('title', 'Business Intelligence')

@section('breadcrumb')
    <li class="breadcrumb-item active">Business Intelligence</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12 mb-4">
            <h2 class="fw-bold">Wawasan Bisnis</h2>
            <p class="text-muted">Analisis data otomatis untuk membantu pengambilan keputusan strategis Anda.</p>
        </div>
    </div>

    <div class="row g-4">
        {{-- Produk Terlaris --}}
        <div class="col-lg-4 col-md-6">
            <x-card>
                @slot('title')
                    <i class="fas fa-trophy me-2" style="color: var(--admin-accent);"></i> Produk Terlaris
                @endslot
                
                @if($insights['top_selling_products']->isEmpty())
                    <p class="text-muted text-center">Belum ada data penjualan.</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($insights['top_selling_products'] as $product)
                            <li class="list-group-item d-flex justify-content-between align-items-center" style="background-color: var(--admin-secondary);">
                                {{ $product->name }}
                                <span class="badge rounded-pill" style="background-color: var(--admin-accent); color: white;">{{ $product->total_sold }} terjual</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        {{-- Pelanggan Teratas --}}
        <div class="col-lg-4 col-md-6">
            <x-card>
                @slot('title')
                    <i class="fas fa-users me-2" style="color: var(--admin-accent);"></i> Pelanggan Teratas
                @endslot

                @if($insights['top_customers']->isEmpty())
                     <p class="text-muted text-center">Belum ada data pelanggan.</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($insights['top_customers'] as $customer)
                            <li class="list-group-item d-flex justify-content-between align-items-center" style="background-color: var(--admin-secondary);">
                                {{ $customer->name }}
                                <span class="badge bg-secondary rounded-pill">Rp {{ number_format($customer->total_purchases) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        {{-- Margin Tertinggi --}}
        <div class="col-lg-4 col-md-6">
            <x-card>
                @slot('title')
                     <i class="fas fa-percentage me-2" style="color: var(--admin-accent);"></i> Margin Produk Tertinggi
                @endslot

                @if($insights['highest_margin_products']->isEmpty())
                    <p class="text-muted text-center">Belum ada data produk.</p>
                @else
                     <ul class="list-group list-group-flush">
                        @foreach($insights['highest_margin_products'] as $product)
                             <li class="list-group-item d-flex justify-content-between align-items-center" style="background-color: var(--admin-secondary);">
                                {{ $product->name }}
                                <span class="badge rounded-pill" style="background-color: var(--admin-dominant); color: white;">{{ round($product->margin, 2) }}%</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
@endsection