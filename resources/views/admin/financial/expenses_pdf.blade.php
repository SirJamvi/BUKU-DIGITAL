<!DOCTYPE html>
<html>
<head>
    <title>Laporan Pengeluaran</title>
    <style>
        body { font-family: 'sans-serif'; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #dddddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h1, h4 { text-align: center; margin: 4px 0; }
        .summary { width: 100%; margin: 15px 0; border: 1px solid #eee; padding: 10px; text-align: center; background: #fafafa; }
        .text-end { text-align: right; }
        .text-danger { color: #dc3545; }
    </style>
</head>
<body>
    <h1>Laporan Pengeluaran</h1>

    @php
        $start = $startDate ?? ($reportData['filters']['start_date'] ?? null);
        $end = $endDate ?? ($reportData['filters']['end_date'] ?? null);
        $items = $expenses ?? ($reportData['cash_flows'] ?? collect());
        $total = isset($totalExpense) ? $totalExpense : (isset($reportData['total_expense']) ? $reportData['total_expense'] : $items->where('type', 'expense')->sum('amount'));
    @endphp

    @if ($start && $end)
        <h4>Periode: {{ \Carbon\Carbon::parse($start)->isoFormat('D MMM YYYY') }} - {{ \Carbon\Carbon::parse($end)->isoFormat('D MMM YYYY') }}</h4>
    @elseif ($start)
        <h4>Periode: Mulai {{ \Carbon\Carbon::parse($start)->isoFormat('D MMM YYYY') }}</h4>
    @elseif ($end)
        <h4>Periode: Sampai {{ \Carbon\Carbon::parse($end)->isoFormat('D MMM YYYY') }}</h4>
    @else
        <h4>Periode: Semua Waktu</h4>
    @endif

    <div class="summary">
        <span style="font-size: 14px; color: #555;">Total Pengeluaran: </span>
        <strong style="font-size: 16px; color: #dc3545;">Rp {{ number_format($total, 0, ',', '.') }}</strong>
    </div>

    <h4>Rincian Pengeluaran</h4>
    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Tanggal</th>
                <th style="width: 25%;">Kategori</th>
                <th style="width: 30%;">Deskripsi</th>
                <th style="width: 15%;" class="text-end">Jumlah</th>
                <th style="width: 15%;">Dicatat Oleh</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($item->date)->isoFormat('D MMM YYYY') }}</td>
                    <td>{{ $item->category->name ?? 'N/A' }}</td>
                    <td>{{ $item->description ?? '-' }}</td>
                    <td class="text-end text-danger">
                        Rp {{ number_format($item->amount, 0, ',', '.') }}
                    </td>
                    <td>{{ $item->createdBy->name ?? 'Sistem' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #888;">Tidak ada data pengeluaran.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($items->isNotEmpty())
        <tfoot>
            <tr style="font-weight: bold; background-color: #f9f9f9;">
                <td colspan="3" class="text-end">Total:</td>
                <td class="text-end text-danger">Rp {{ number_format($total, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>
</body>
</html>
