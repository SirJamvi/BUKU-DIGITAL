{{-- resources/views/admin/financial/roi-analysis.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Analisis ROI')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item active" aria-current="page">Analisis ROI</li>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row g-4">
            {{-- Kartu ROI --}}
            <div class="col-md-12">
                <div class="card bg-primary text-white text-center">
                    <div class="card-body">
                        <h5 class="card-title">RETURN ON INVESTMENT (ROI)</h5>
                        <h1 class="display-4 fw-bold">{{ $roiData['roi'] ?? 0 }}%</h1>
                        <p class="lead">Persentase keuntungan atau kerugian relatif terhadap jumlah modal yang diinvestasikan.</p>
                    </div>
                </div>
            </div>

            {{-- Kartu Modal Awal --}}
            <div class="col-md-6">
                <x-card title="Total Modal Investasi">
                    <div class="text-center">
                        <i class="fas fa-money-bill-wave fa-3x text-info mb-3"></i>
                        <h3>Rp {{ number_format($roiData['initial_capital'] ?? 0, 0, ',', '.') }}</h3>
                        <p class="text-muted">Jumlah total modal awal dan tambahan modal yang telah diinvestasikan ke dalam bisnis.</p>
                    </div>
                </x-card>
            </div>

            {{-- Kartu Total Keuntungan --}}
            <div class="col-md-6">
                <x-card title="Total Keuntungan Bersih">
                    <div class="text-center">
                        <i class="fas fa-chart-line fa-3x text-success mb-3"></i>
                        <h3>Rp {{ number_format($roiData['total_profit'] ?? 0, 0, ',', '.') }}</h3>
                        <p class="text-muted">Akumulasi keuntungan bersih yang dihasilkan oleh bisnis hingga saat ini.</p>
                    </div>
                </x-card>
            </div>

             {{-- Kartu Penjelasan Rumus --}}
            <div class="col-md-12">
                <x-card title="Bagaimana ROI Dihitung?">
                    <p>ROI dihitung untuk mengukur efisiensi sebuah investasi. Rumus yang digunakan adalah:</p>
                    <div class="bg-light p-3 rounded text-center">
                        <code class="fs-5">ROI = ((Total Keuntungan - Total Modal) / Total Modal) x 100%</code>
                    </div>
                    <hr>
                    <p>
                        <strong>Interpretasi:</strong>
                        <ul>
                            <li><strong>ROI Positif:</strong> Menandakan bahwa keuntungan melebihi biaya investasi (untung).</li>
                            <li><strong>ROI Negatif:</strong> Menandakan bahwa biaya investasi melebihi keuntungan (rugi).</li>
                        </ul>
                    </p>
                </x-card>
            </div>
        </div>
    </div>
@endsection