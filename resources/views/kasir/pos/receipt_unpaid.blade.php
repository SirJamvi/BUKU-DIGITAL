{{-- resources/views/kasir/pos/receipt_unpaid.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Tagihan #{{ $transaction->id }} - Adin Crystal</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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
            background-color: #f4f6f9;
        }

        .receipt-container {
            width: 100%;
            max-width: 80mm;
            margin: 20px auto;
            padding: 5mm;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        /* ========================================= */
        /* HEADER */
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
        /* BADGE KASBON DI HEADER */
        /* ========================================= */
        .kasbon-badge {
            text-align: center;
            background-color: #dc3545;
            color: #fff;
            font-weight: bold;
            font-size: 10pt;
            padding: 6px 0;
            letter-spacing: 1px;
            margin-bottom: 12px;
            border-radius: 3px;
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
        /* TABEL ITEMS */
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
        /* TOTALS */
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

        .totals td:last-child {
            text-align: right;
        }

        .status-box {
            border: 1px dashed #dc3545;
            padding: 8px;
            margin-top: 10px;
            font-weight: bold;
            text-align: center;
            color: #dc3545;
            font-size: 10pt;
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
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .no-print .btn {
            display: inline-block;
            padding: 10px 15px;
            margin: 5px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 10pt;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            font-family: inherit;
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

        .btn-success {
            background: #25D366;
            color: white;
        }

        .btn-success:hover {
            background: #128C7E;
        }

        /* ========================================= */
        /* PRINT STYLES */
        /* ========================================= */
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background-color: #fff;
                margin: 0;
                padding: 0;
            }

            .receipt-container {
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
    <div class="receipt-container" id="receipt-area">
        {{-- ========================================= --}}
        {{-- HEADER --}}
        {{-- ========================================= --}}
        <div class="header">
            <h1>ADIN CRYSTAL</h1>
            <div class="store-name">Es Kristal Premium</div>
            <div class="store-contact">
                Telp: 087811192774<br>
                www.eskristalkarawang.com
            </div>
        </div>

        {{-- Badge Kasbon --}}
        <div class="kasbon-badge">
            ⚠️ STRUK TAGIHAN (KASBON)
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
        {{-- TOTALS (versi Belum Lunas) --}}
        {{-- ========================================= --}}
        <div class="totals">
            <table>
                <tr class="subtotal-row">
                    <td>Subtotal</td>
                    <td>Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                </tr>
                <tr class="total-row" style="color: #dc3545;">
                    <td>TOTAL TAGIHAN</td>
                    <td>Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                </tr>
            </table>
            <div class="status-box">
                ⚠️ STATUS: BELUM LUNAS
            </div>
        </div>

        {{-- ========================================= --}}
        {{-- FOOTER --}}
        {{-- ========================================= --}}
        <div class="footer">
            <div class="footer-message">Mohon segera lakukan pelunasan</div>
            <div class="footer-note">Simpan struk ini sebagai bukti tagihan yang sah</div>
            <div class="footer-date">Dicetak: {{ now()->format('d/m/Y H:i:s') }}</div>
        </div>
    </div>

    {{-- ========================================= --}}
    {{-- TOMBOL AKSI (Tidak Tercetak) --}}
    {{-- ========================================= --}}
    <div class="no-print">
        <div style="margin-bottom: 15px;">
            <a href="{{ route('kasir.pos.index') }}" class="btn btn-primary">
                ← Kembali ke POS
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                🖨️ Cetak Struk
            </button>
        </div>

        <div class="wa-input-container" style="display: flex; flex-direction: column; gap: 10px; align-items: center;">
            <button type="button" onclick="shareNative()" class="btn btn-success" id="btnShareNative" style="width: 100%; max-width: 300px; padding: 12px; font-size: 11pt; border-radius: 8px;">
                <i class="fas fa-share-alt"></i> 📱 Bagikan Struk Tagihan (WhatsApp)
            </button>
            <small style="color: #666; text-align: center;">*Klik tombol di atas untuk membagikan struk tagihan langsung ke aplikasi pilihan Anda (WA/Telegram/dll).</small>
        </div>
    </div>

    <!-- Panggil Library HTML2Canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        function shareNative() {
            const btn = document.getElementById('btnShareNative');
            const originalText = btn.innerHTML;

            btn.innerHTML = '⏳ Menyiapkan Struk...';
            btn.disabled = true;

            const receiptArea = document.getElementById('receipt-area');
            const transactionId = '{{ $transaction->id }}';

            html2canvas(receiptArea, {
                scale: 2,
                backgroundColor: "#ffffff",
                logging: false
            }).then(canvas => {
                canvas.toBlob(function(blob) {
                    const file = new File([blob], `Tagihan_AdinCrystal_${transactionId}.png`, {
                        type: 'image/png'
                    });

                    if (navigator.canShare && navigator.canShare({
                            files: [file]
                        })) {
                        navigator.share({
                            files: [file],
                            title: 'Struk Tagihan Adin Crystal',
                            text: 'Berikut struk tagihan (kasbon) atas nama {{ $transaction->customer->name ?? "Pelanggan" }}. Mohon segera dilunasi. Terima kasih.'
                        }).then(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }).catch((error) => {
                            console.log('Share dibatalkan atau gagal:', error);
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        });
                    } else {
                        alert('Browser Anda tidak mendukung Share Langsung. Gambar akan didownload otomatis.');

                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `Tagihan_AdinCrystal_${transactionId}.png`;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);

                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                }, 'image/png');

            }).catch(err => {
                console.error("Error generating receipt:", err);
                alert('Gagal memproses struk.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    </script>
</body>

</html>