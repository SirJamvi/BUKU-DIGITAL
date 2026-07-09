@extends('kasir.layouts.app')

@section('title', 'Pecah Ball (Konversi Es) Dinamis')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="font-weight-bold text-dark">Pecah Ball Es Kristal (Dinamis)</h2>
            <p class="text-muted">Konversi stok karung besar menjadi satu atau beberapa kemasan eceran sekaligus.</p>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="{{ route('kasir.inventory.process_break_unit') }}" method="POST" id="breakUnitForm">
                        @csrf

                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <h5 class="font-weight-bold mb-3 text-danger"><i class="fas fa-box-open me-2"></i> Produk Yang Akan Dipecah (Bahan Baku)</h5>
                            <div class="row">
                                <div class="col-md-8 mb-3 mb-md-0">
                                    <label class="form-label">Pilih Produk (Karung/Ball)</label>
                                    <select class="form-select @error('source_product_id') is-invalid @enderror" name="source_product_id" required>
                                        <option value="" disabled selected>-- Pilih Produk Asal --</option>
                                        @foreach($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Jumlah (Qty)</label>
                                    <input type="number" class="form-control" name="source_qty" value="1" min="1" required>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 bg-white rounded-3 mb-4 border border-info">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="font-weight-bold text-success mb-0"><i class="fas fa-boxes me-2"></i> Menjadi (Hasil Pecahan)</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-target">
                                    <i class="fas fa-plus"></i> Tambah Hasil
                                </button>
                            </div>

                            <div id="target-container">
                                <div class="row target-row align-items-end mb-3 pb-3 border-bottom">
                                    <div class="col-md-7">
                                        <label class="form-label small text-muted">Produk Hasil</label>
                                        <select class="form-select" name="targets[0][product_id]" required>
                                            <option value="" disabled selected>-- Pilih Kemasan Hasil --</option>
                                            @foreach($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">Jumlah Dihasilkan</label>
                                        <input type="number" class="form-control" name="targets[0][qty]" min="1" value="1" required>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-danger btn-remove-target" disabled><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-warning btn-lg px-5 rounded-3 text-dark fw-bold">
                                <i class="fas fa-exchange-alt me-2"></i> Proses Pecah Es
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mt-4 mt-lg-0">
            <div class="card border-0 bg-light rounded-4">
                <div class="card-body p-4">
                    <h5 class="font-weight-bold mb-3"><i class="fas fa-info-circle text-info me-2"></i> Info SOP Baru</h5>
                    <ul class="text-muted small ps-3">
                        <li class="mb-2">Sistem kini <strong>dinamis</strong>. Anda bisa memecah 1 jenis barang menjadi beberapa jenis barang sekaligus.</li>
                        <li class="mb-2">Contoh: Memecah <strong>1 Ball 15kg</strong>. Di bagian "Hasil", Anda bisa menambahkan 2 baris: Baris pertama <strong>1x 10kg</strong>, klik 'Tambah Hasil' untuk baris kedua <strong>1x 5kg</strong>.</li>
                        <li>Pastikan jumlah fisik benar-benar sesuai dengan yang di-input agar opname stok valid.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let targetIndex = 1; // Mulai index dari 1 karena 0 sudah ada di HTML default
        const container = document.getElementById('target-container');
        const btnAdd = document.getElementById('btn-add-target');

        btnAdd.addEventListener('click', function() {
            // Clone baris pertama
            const firstRow = container.querySelector('.target-row');
            const newRow = firstRow.cloneNode(true);

            // Hapus value yang tersisa dari kloningan
            newRow.querySelector('select').value = "";
            newRow.querySelector('input').value = "1";

            // Update nama attribute array (targets[1][product_id], targets[2][product_id], dst)
            newRow.querySelector('select').setAttribute('name', `targets[${targetIndex}][product_id]`);
            newRow.querySelector('input').setAttribute('name', `targets[${targetIndex}][qty]`);

            // Aktifkan tombol hapus untuk baris baru
            const btnRemove = newRow.querySelector('.btn-remove-target');
            btnRemove.removeAttribute('disabled');

            // Tambahkan event listener untuk tombol hapus
            btnRemove.addEventListener('click', function() {
                newRow.remove();
            });

            // Masukkan baris baru ke dalam container
            container.appendChild(newRow);
            targetIndex++;
        });
    });
</script>
@endsection