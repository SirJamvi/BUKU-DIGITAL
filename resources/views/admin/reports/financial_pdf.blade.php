<!DOCTYPE html>
<html>
<head>
    <title>Laporan Keuangan</title>
    <style>
        body { font-family: 'sans-serif'; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #dddddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h1, h4 { text-align: center; }
        .summary { display: table; width: 100%; margin-bottom: 20px; }
        .summary-item { display: table-cell; width: 25%; text-align: center; border: 1px solid #eee; padding: 10px; }
        .text-end { text-align: right; }
        .text-success { color: green; }
        .text-danger { color: red; }
    </style>
</head>
<body>
    <h1>Laporan Keuangan</h1>

    {{-- ======================= BAGIAN YANG DIPERBAIKI ======================= --}}
    @if (isset($reportData['filters']['start_date']) && !empty($reportData['filters']['start_date']))
        <h4>Periode: {{ \Carbon\Carbon::parse($reportData['filters']['start_date'])->isoFormat('D MMM YYYY') }} - {{ \Carbon\Carbon::parse($reportData['filters']['end_date'])->isoFormat('D MMM YYYY') }}</h4>
    @else
        <h4>Periode: Semua Waktu</h4>
    @endif
    {{-- ====================================================================== --}}

    <div class="summary">
        <div class="summary-item">
            <p>Total Pemasukan</p>
            <strong>Rp {{ number_format($reportData['total_income'] ?? 0, 0, ',', '.') }}</strong>
        </div>
        <div class="summary-item">
            <p>Keuntungan Kotor</p>
            <strong>Rp {{ number_format($reportData['total_gross_profit'] ?? 0, 0, ',', '.') }}</strong>
        </div>
        <div class="summary-item">
            <p>Total Pengeluaran</p>
            <strong>Rp {{ number_format($reportData['total_expense'] ?? 0, 0, ',', '.') }}</strong>
        </div>
        <div class="summary-item">
            <p>Keuntungan Bersih</p>
            <strong>Rp {{ number_format($reportData['net_profit'] ?? 0, 0, ',', '.') }}</strong>
        </div>
    </div>

    <h4>Rincian Arus Kas</h4>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Deskripsi</th>
                <th>Kategori</th>
                <th class="text-end">Pemasukan</th>
                <th class="text-end">Pengeluaran</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reportData['cash_flows'] as $flow)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($flow->date)->isoFormat('D MMM YYYY') }}</td>
                    <td>{{ $flow->description }}</td>
                    <td>{{ $flow->category->name ?? 'N/A' }}</td>
                    <td class="text-end text-success">
                        {{ $flow->type == 'income' ? 'Rp ' . number_format($flow->amount, 0, ',', '.') : '-' }}
                    </td>
                    <td class="text-end text-danger">
                        {{ $flow->type == 'expense' ? 'Rp ' . number_format($flow->amount, 0, ',', '.') : '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center;">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>