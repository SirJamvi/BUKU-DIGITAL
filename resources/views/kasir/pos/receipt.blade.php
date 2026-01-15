{{-- resources/views/kasir/pos/receipt.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk #{{ $transaction->id }} - Adin Crystal</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 5mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11pt;
            color: #000;
            line-height: 1.4;
        }
        
        .receipt-container {
            width: 100%;
            max-width: 80mm;
            margin: 0 auto;
            padding: 5mm;
        }
        
        /* ========================================= */
        /* HEADER - Lebih Profesional */
        /* ========================================= */
        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }
        
        .header h1 {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 5px;
            letter-spacing: 2px;
        }
        
        .header .store-name {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .header .store-contact {
            font-size: 9pt;
            color: #333;
            margin-top: 3px;
        }
        
        /* ========================================= */
        /* INFO TRANSAKSI */
        /* ========================================= */
        .transaction-info {
            margin-bottom: 12px;
            font-size: 10pt;
        }
        
        .transaction-info table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .transaction-info td {
            padding: 2px 0;
        }
        
        .transaction-info td:first-child {
            width: 35%;
            font-weight: 600;
        }
        
        .transaction-info td:last-child {
            width: 65%;
        }
        
        /* ========================================= */
        /* TABEL ITEMS - Lebih Rapi */
        /* ========================================= */
        .items-section {
            margin: 15px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 8px 0;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .items-table .item-row {
            margin-bottom: 8px;
        }
        
        .item-name {
            font-weight: bold;
            font-size: 10pt;
            margin-bottom: 2px;
        }
        
        .item-detail {
            display: flex;
            justify-content: space-between;
            font-size: 9pt;
            color: #333;
            margin-bottom: 6px;
        }
        
        .item-qty-price {
            text-align: left;
        }
        
        .item-total {
            text-align: right;
            font-weight: 600;
        }
        
        .item-separator {
            height: 1px;
            background: #ddd;
            margin: 6px 0;
        }
        
        /* ========================================= */
        /* TOTALS - Lebih Menonjol */
        /* ========================================= */
        .totals {
            margin-top: 12px;
            padding-top: 8px;
        }
        
        .totals table {
            width: 100%;
            font-size: 11pt;
        }
        
        .totals td {
            padding: 3px 0;
        }
        
        .totals .subtotal-row td {
            font-size: 10pt;
        }
        
        .totals .total-row td {
            font-size: 13pt;
            font-weight: bold;
            padding-top: 6px;
            border-top: 2px solid #000;
        }
        
        .totals .payment-row td {
            font-size: 10pt;
            padding-top: 4px;
            color: #333;
        }
        
        .totals td:last-child {
            text-align: right;
        }
        
        /* ========================================= */
        /* FOOTER */
        /* ========================================= */
        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #000;
        }
        
        .footer-message {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .footer-note {
            font-size: 8pt;
            color: #555;
            font-style: italic;
        }
        
        .footer-date {
            font-size: 8pt;
            color: #777;
            margin-top: 8px;
        }
        
        /* ========================================= */
        /* NO PRINT - Tombol Aksi */
        /* ========================================= */
        .no-print {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .no-print .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 10pt;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        /* ========================================= */
        /* PRINT STYLES */
        /* ========================================= */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            
            .receipt-container {
                padding: 0;
            }
        }
    </style>
</head>
<body onload="window.print();">
    <div class="receipt-container">
        {{-- ========================================= --}}
        {{-- HEADER --}}
        {{-- ========================================= --}}
        <div class="header">
            <h1>ADIN CRYSTAL</h1>
            <div class="store-name">Es Kristal Premium</div>
            <div class="store-contact">
                Telp: 087811192774<br>
                www.adincrystal.com
            </div>
        </div>

        {{-- ========================================= --}}
        {{-- TRANSACTION INFO --}}
        {{-- ========================================= --}}
        <div class="transaction-info">
            <table>
                <tr>
                    <td>No. Struk</td>
                    <td>: #{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}</td>
                </tr>
                <tr>
                    <td>Tanggal</td>
                    <td>: {{ $transaction->transaction_date->format('d/m/Y H:i') }}</td>
                </tr>
                <tr>
                    <td>Kasir</td>
                    <td>: {{ $transaction->createdBy->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Pelanggan</td>
                    <td>: {{ $transaction->customer->name ?? 'Umum' }}</td>
                </tr>
            </table>
        </div>

        {{-- ========================================= --}}
        {{-- ITEMS --}}
        {{-- ========================================= --}}
        <div class="items-section">
            @foreach ($transaction->details as $index => $item)
                <div class="item-row">
                    <div class="item-name">{{ $item->product->name }}</div>
                    <div class="item-detail">
                        <div class="item-qty-price">
                            {{ $item->quantity }} x Rp {{ number_format($item->unit_price, 0, ',', '.') }}
                        </div>
                        <div class="item-total">
                            Rp {{ number_format($item->total_price, 0, ',', '.') }}
                        </div>
                    </div>
                </div>
                @if(!$loop->last)
                    <div class="item-separator"></div>
                @endif
            @endforeach
        </div>

        {{-- ========================================= --}}
        {{-- TOTALS --}}
        {{-- ========================================= --}}
        <div class="totals">
            <table>
                <tr class="subtotal-row">
                    <td>Subtotal</td>
                    <td>Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                </tr>
                <tr class="total-row">
                    <td>TOTAL</td>
                    <td>Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                </tr>
                <tr class="payment-row">
                    <td>Metode Bayar</td>
                    <td>{{ $transaction->payment_method }}</td>
                </tr>
            </table>
        </div>

        {{-- ========================================= --}}
        {{-- FOOTER --}}
        {{-- ========================================= --}}
        <div class="footer">
            <div class="footer-message">Terima Kasih Atas Kunjungan Anda!</div>
            <div class="footer-note">Barang yang sudah dibeli tidak dapat ditukar/dikembalikan</div>
            <div class="footer-date">Dicetak: {{ now()->format('d/m/Y H:i:s') }}</div>
        </div>
    </div>

    {{-- ========================================= --}}
    {{-- TOMBOL AKSI (Tidak Tercetak) --}}
    {{-- ========================================= --}}
    <div class="no-print">
        <a href="{{ route('kasir.pos.index') }}" class="btn btn-primary">
            ← Kembali ke POS
        </a>
        <a href="javascript:window.print()" class="btn btn-secondary">
            🖨️ Cetak Ulang
        </a>
    </div>
</body>
</html>