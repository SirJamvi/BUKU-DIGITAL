{{-- resources/views/kasir/pos/delivery_receipt.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan & Struk Pengiriman #{{ $tripCode }} - BUKU DIGITAL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #212529;
        }

        .action-bar {
            background: #ffffff;
            border-bottom: 1px solid #dee2e6;
            padding: 12px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .slip-container {
            max-width: 850px;
            margin: 25px auto;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            padding: 30px;
        }

        .slip-header {
            border-bottom: 2px dashed #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .receipt-card {
            max-width: 80mm;
            margin: 20px auto;
            background: #fff;
            padding: 15px;
            border: 1px solid #ddd;
            font-family: 'Courier New', Courier, monospace;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }

        @media print {
            .no-print, .action-bar {
                display: none !important;
            }
            body {
                background: #fff;
                margin: 0;
                padding: 0;
            }
            .slip-container {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
                padding: 10px;
                page-break-after: always;
            }
            .receipt-card {
                box-shadow: none;
                border: none;
                page-break-after: always;
                max-width: 80mm;
                margin: 0 auto;
            }
        }
    </style>
</head>

<body>
    @php
        $firstTx = $transactions->first();
        $driverName = $firstTx->driver->name ?? 'Driver';
        $driverPhone = $firstTx->driver->phone ?? '-';
        $kasirName = $firstTx->createdBy->name ?? Auth::user()->name;
        $totalTripAmount = $transactions->sum('total_amount');
        $totalItemsCount = 0;
        foreach($transactions as $t) {
            $totalItemsCount += $t->details->sum('quantity');
        }
    @endphp

    {{-- Top Action Bar --}}
    <div class="action-bar no-print">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('kasir.pos.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke POS
                </a>
                <a href="{{ route('kasir.dashboard') }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-tachometer-alt me-1"></i> Dashboard Kasir
                </a>
                <span class="badge bg-success ms-2 px-3 py-2 fs-6">
                    <i class="fas fa-check-circle me-1"></i> Trip {{ $tripCode }} Berhasil Dibuat
                </span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Cetak Surat Jalan & Struk
                </button>
            </div>
        </div>
    </div>

    <div class="container my-4">
        {{-- ======================================================== --}}
        {{-- 1. SURAT JALAN PENGIRIMAN DRIVER                         --}}
        {{-- ======================================================== --}}
        <div class="slip-container">
            <div class="slip-header text-center">
                <h3 class="fw-bold mb-1 letter-spacing-1">SURAT JALAN PENGIRIMAN DRIVER</h3>
                <h5 class="text-primary fw-bold mb-1">#{{ $tripCode }}</h5>
                <p class="text-muted small mb-0">{{ now()->format('d/m/Y H:i') }} WIB | Kasir: <strong>{{ $kasirName }}</strong></p>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded border">
                        <small class="text-muted d-block text-uppercase fw-bold">Driver Pengantar:</small>
                        <h5 class="fw-bold text-dark mb-1"><i class="fas fa-motorcycle text-primary me-2"></i>{{ $driverName }}</h5>
                        <small class="text-muted"><i class="fas fa-phone me-1"></i>{{ $driverPhone }}</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded border">
                        <small class="text-muted d-block text-uppercase fw-bold">Ringkasan Pengiriman:</small>
                        <div class="d-flex justify-content-between">
                            <span>Jumlah Toko / Customer:</span>
                            <strong>{{ $transactions->count() }} Lokasi</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Total Muatan Es:</span>
                            <strong class="text-primary">{{ $totalItemsCount }} Item / Bal</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Total Nilai Tagihan:</span>
                            <strong class="text-danger">Rp {{ number_format($totalTripAmount, 0, ',', '.') }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <h6 class="fw-bold mb-2"><i class="fas fa-map-marker-alt text-danger me-1"></i> Daftar Rute & Pesanan Pelanggan:</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 40px;">No</th>
                            <th>Pelanggan / Toko</th>
                            <th>Detail Produk</th>
                            <th class="text-end">Tagihan Kasbon</th>
                            <th class="text-center" style="width: 140px;">Tanda Terima</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $idx => $trx)
                        <tr>
                            <td class="text-center fw-bold">{{ $idx + 1 }}</td>
                            <td>
                                <strong class="fs-6">{{ $trx->customer->name ?? 'Pelanggan Umum' }}</strong>
                                @if($trx->customer && $trx->customer->phone)
                                <br><small class="text-muted"><i class="fas fa-phone small me-1"></i>{{ $trx->customer->phone }}</small>
                                @endif
                                @if($trx->customer && $trx->customer->address)
                                <br><small class="text-muted"><i class="fas fa-map-pin small me-1"></i>{{ $trx->customer->address }}</small>
                                @endif
                                <br><span class="badge bg-secondary">Struk #{{ str_pad($trx->id, 6, '0', STR_PAD_LEFT) }}</span>
                            </td>
                            <td>
                                <ul class="list-unstyled mb-0 small">
                                    @foreach($trx->details as $item)
                                    <li>• {{ $item->product->name ?? 'Produk' }}: <strong>{{ $item->quantity }} {{ $item->product->unit ?? 'pcs' }}</strong></li>
                                    @endforeach
                                </ul>
                                @if($trx->notes)
                                <small class="text-muted fst-italic">Ket: {{ $trx->notes }}</small>
                                @endif
                            </td>
                            <td class="text-end fw-bold text-danger fs-6">
                                Rp {{ number_format($trx->total_amount, 0, ',', '.') }}
                            </td>
                            <td class="text-center" style="height: 60px;">
                                <div style="border-bottom: 1px dotted #999; margin-top: 35px;"></div>
                                <small class="text-muted" style="font-size: 10px;">(Nama & TTD Toko)</small>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row text-center mt-5">
                <div class="col-6">
                    <p class="mb-4 text-muted small">Driver Pengantar,</p>
                    <div style="height: 40px;"></div>
                    <strong>( {{ $driverName }} )</strong>
                </div>
                <div class="col-6">
                    <p class="mb-4 text-muted small">Kasir Bertugas,</p>
                    <div style="height: 40px;"></div>
                    <strong>( {{ $kasirName }} )</strong>
                </div>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- 2. STRUK TAGIHAN SATUAN PER PELANGGAN (THERMAL 80MM)     --}}
        {{-- ======================================================== --}}
        <div class="text-center my-4 no-print">
            <hr>
            <h5 class="fw-bold"><i class="fas fa-receipt text-primary me-2"></i>Struk Tagihan Masing-Masing Pelanggan</h5>
            <p class="text-muted small">Struk di bawah ini dapat dicetak untuk dibawa oleh driver dan diserahkan ke masing-masing toko.</p>
        </div>

        <div class="row justify-content-center">
            @foreach($transactions as $trx)
            <div class="col-md-6 col-lg-5 mb-4">
                <div class="receipt-card">
                    <div class="text-center pb-2 border-bottom mb-2">
                        <h6 class="fw-bold mb-0">ADIN CRYSTAL</h6>
                        <small class="text-muted" style="font-size: 9pt;">Pabrik & Distribusi Es Kristal</small><br>
                        <span class="badge bg-danger text-white mt-1" style="font-size: 8pt;">TAGIHAN (BELUM LUNAS)</span>
                    </div>

                    <div style="font-size: 9pt;" class="mb-2">
                        <div class="d-flex justify-content-between">
                            <span>No. Struk:</span>
                            <strong>#{{ str_pad($trx->id, 6, '0', STR_PAD_LEFT) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Trip:</span>
                            <span>{{ $tripCode }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Tanggal:</span>
                            <span>{{ $trx->transaction_date->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Pelanggan:</span>
                            <strong>{{ $trx->customer->name ?? 'Pelanggan Umum' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Driver:</span>
                            <span>{{ $driverName }}</span>
                        </div>
                    </div>

                    <div class="border-top border-bottom py-2 my-2" style="font-size: 9pt;">
                        @foreach($trx->details as $item)
                        <div class="d-flex justify-content-between">
                            <span>{{ $item->product->name ?? 'Item' }} x{{ $item->quantity }}</span>
                            <span>Rp {{ number_format($item->total_price, 0, ',', '.') }}</span>
                        </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-between fw-bold mb-2" style="font-size: 10pt;">
                        <span>TOTAL TAGIHAN:</span>
                        <span class="text-danger">Rp {{ number_format($trx->total_amount, 0, ',', '.') }}</span>
                    </div>

                    <div class="text-center pt-2 border-top text-muted" style="font-size: 8pt;">
                        <p class="mb-1">Pembayaran via Driver saat barang diterima.</p>
                        <p class="mb-0">Terima kasih atas kerja samanya!</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</body>
</html>
