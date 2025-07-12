{{-- resources/views/kasir/pos/receipt.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Transaksi #{{ $transaction->id }}</title>
    <style>
        @page {
            size: 80mm auto; /* Ukuran kertas printer termal */
            margin: 5mm;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10pt;
            color: #000;
        }
        .receipt-container {
            width: 100%;
        }
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .header h3 {
            margin: 0;
            font-size: 14pt;
        }
        .info {
            margin-bottom: 10px;
        }
        .info table {
            width: 100%;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th, .items-table td {
            padding: 3px 0;
        }
        .items-table .item-name {
            text-align: left;
        }
        .items-table .item-qty, .items-table .item-price {
            text-align: right;
            white-space: nowrap;
        }
        .totals {
            margin-top: 10px;
            border-top: 1px dashed #000;
            padding-top: 5px;
        }
        .totals table {
            width: 100%;
        }
        .totals td:last-child {
            text-align: right;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 9pt;
        }
    </style>
</head>
<body onload="window.print();">
    <div class="receipt-container">
        <div class="header">
            <h3>{{ config('app.name', 'Toko Anda') }}</h3>
            <p>Alamat Toko Anda di Sini<br>Telp: 08123456789</p>
        </div>

        <div class="info">
            <table>
                <tr>
                    <td>No. Struk</td>
                    <td>: #{{ $transaction->id }}</td>
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

        <table class="items-table">
            <thead>
                <tr>
                    <td colspan="3" style="border-top: 1px dashed #000;"></td>
                </tr>
            </thead>
            <tbody>
                @foreach ($transaction->details as $item)
                    <tr>
                        <td colspan="3" class="item-name">{{ $item->product->name }}</td>
                    </tr>
                    <tr>
                        <td>{{ $item->quantity }} x {{ number_format($item->unit_price) }}</td>
                        <td colspan="2" class="item-price">{{ number_format($item->total_price) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                 <tr>
                    <td colspan="3" style="border-top: 1px dashed #000; padding-top: 5px;"></td>
                </tr>
            </tfoot>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td><strong>Total</strong></td>
                    <td><strong>Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</strong></td>
                </tr>
                 <tr>
                    <td>Metode Bayar</td>
                    <td>{{ $transaction->payment_method }}</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <p>Terima kasih telah berbelanja!</p>
        </div>
    </div>

</body>
</html>