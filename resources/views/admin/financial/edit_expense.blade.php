@extends('admin.layouts.app')

@section('title', 'Edit Pengeluaran')
@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
<li class="breadcrumb-item"><a href="{{ route('admin.financial.expenses') }}">Pengeluaran</a></li>
<li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<x-card title="Formulir Edit Pengeluaran">

    @if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('admin.financial.expenses.update', $expense->id) }}" method="POST">
        @csrf
        @method('PUT')

        <x-input
            name="category_name"
            label="Kategori Pengeluaran"
            :value="old('category_name', $expense->category->name ?? '')"
            required />

        <x-input type="number" name="amount" label="Jumlah (Rp)" :value="old('amount', $expense->amount)" required />

        <div class="mb-3">
            <label class="form-label d-block fw-bold">Sumber Dana Pengeluaran <span class="text-danger">*</span></label>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="funding_source" id="exp_funding_owner" value="owner" {{ old('funding_source', $expense->funding_source ?? 'owner') === 'owner' ? 'checked' : '' }} onchange="toggleDrawerWarning(this.value)">
                <label class="form-check-label text-primary fw-semibold" for="exp_funding_owner">
                    <i class="fas fa-user-shield me-1"></i> Rekening / Kas Owner (Tidak Potong Laci Kasir)
                </label>
                <div class="small text-muted ms-4">Biaya operasional yang dibayar melalui rekening owner/perusahaan.</div>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="funding_source" id="exp_funding_external" value="external" {{ old('funding_source', $expense->funding_source) === 'external' ? 'checked' : '' }} onchange="toggleDrawerWarning(this.value)">
                <label class="form-check-label text-success fw-semibold" for="exp_funding_external">
                    <i class="fas fa-money-bill-wave me-1"></i> Dana Eksternal / Gaji Bos (Tidak Potong Laci Kasir)
                </label>
                <div class="small text-muted ms-4">Biaya payroll/gaji karyawan atau dana talangan bos luar laci.</div>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="funding_source" id="exp_funding_drawer" value="drawer" {{ old('funding_source', $expense->funding_source) === 'drawer' ? 'checked' : '' }} onchange="toggleDrawerWarning(this.value)">
                <label class="form-check-label text-danger fw-semibold" for="exp_funding_drawer">
                    <i class="fas fa-cash-register me-1"></i> Kas Laci Kasir Cabang (⚠️ Memotong Fisik Uang Laci Kasir)
                </label>
                <div class="small text-muted ms-4">Hanya gunakan jika uang pengeluaran diambil tunai dari laci meja kasir.</div>
            </div>
            <div id="drawer_warning_alert" class="alert alert-warning py-2 px-3 mt-2 d-none" style="font-size: 0.8rem; border-left: 4px solid #f59e0b;">
                <i class="fas fa-exclamation-triangle me-1 text-warning"></i> <strong>Perhatian:</strong> Memilih <em>Kas Laci Kasir</em> akan memotong perhitungan fisik uang kasir saat tutup shift kasir di cabang bersangkutan.
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Metode Pembayaran <span class="text-danger">*</span></label>
            <select name="payment_method" class="form-select" required>
                <option value="">-- Pilih Metode Pembayaran --</option>
                @foreach($paymentMethods as $method)
                <option value="{{ $method->slug }}" {{ (old('payment_method', $expense->payment_method) == $method->slug) ? 'selected' : '' }}>
                    {{ $method->name }}
                </option>
                @endforeach
            </select>
        </div>

        <x-input type="date" name="date" label="Tanggal Pengeluaran" :value="old('date', \Carbon\Carbon::parse($expense->date)->toDateString())" required />

        <x-input type="textarea" name="description" label="Deskripsi" :value="old('description', $expense->description)" required />

        <div class="d-flex justify-content-end mt-4">
            <x-button href="{{ route('admin.financial.expenses') }}" variant="secondary" class="me-2">Batal</x-button>
            <x-button type="submit" variant="primary">Simpan Perubahan</x-button>
        </div>
    </form>
</x-card>

<script>
function toggleDrawerWarning(val) {
    const alertEl = document.getElementById('drawer_warning_alert');
    if (alertEl) {
        if (val === 'drawer') {
            alertEl.classList.remove('d-none');
        } else {
            alertEl.classList.add('d-none');
        }
    }
}
document.addEventListener('DOMContentLoaded', function() {
    const checked = document.querySelector('input[name="funding_source"]:checked');
    if (checked) {
        toggleDrawerWarning(checked.value);
    }
});
</script>
@endsection