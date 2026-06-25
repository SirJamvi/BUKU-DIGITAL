@extends('kasir.layouts.app') {{-- Sesuaikan dengan nama layout kasir Anda --}}

@section('title', 'Pecah Ball (Konversi Es)')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="font-weight-bold text-dark">Pecah Ball Es Kristal</h2>
            <p class="text-muted">Konversi stok karung besar menjadi kemasan eceran (repackaging).</p>
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
                    <form action="{{ route('kasir.inventory.process_break_unit') }}" method="POST">
                        @csrf

                        <div class="mb-4">
                            <label for="conversion_id" class="form-label font-weight-bold">Pilih Tipe Pecahan</label>
                            @if($conversions->isEmpty())
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i> Admin belum mengatur aturan konversi (Misal: 1 Ball 20kg -> 4 Pcs 5rb). Silakan hubungi Admin.
                            </div>
                            @else
                            <select class="form-select form-select-lg @error('conversion_id') is-invalid @enderror" id="conversion_id" name="conversion_id" required>
                                <option value="" disabled selected>-- Pilih Aturan Pecah --</option>
                                @foreach($conversions as $conv)
                                <option value="{{ $conv->id }}">
                                    PECAH: {{ $conv->quantity_to_break }} {{ $conv->fromProduct->name }}
                                    &#10145;
                                    MENJADI: {{ $conv->yield_amount }} {{ $conv->toProduct->name }}
                                </option>
                                @endforeach
                            </select>
                            @endif
                            @error('conversion_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="multiplier" class="form-label font-weight-bold">Berapa Kali Pecah?</label>
                            <div class="input-group input-group-lg">
                                <input type="number" class="form-control @error('multiplier') is-invalid @enderror" id="multiplier" name="multiplier" value="1" min="1" required>
                                <span class="input-group-text bg-light text-muted">Kali (Pengali)</span>
                            </div>
                            <small class="text-muted mt-1 d-block">
                                Jika diisi <strong>2</strong> dan aturannya adalah memecah 1 ball menjadi 4 eceran, maka sistem akan mengurangi <strong>2 ball</strong> dan menambah <strong>8 eceran</strong>.
                            </small>
                            @error('multiplier')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-warning btn-lg px-5 rounded-3 text-dark fw-bold" {{ $conversions->isEmpty() ? 'disabled' : '' }}>
                                <i class="fas fa-boxes me-2"></i> Proses Pecah Es
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mt-4 mt-lg-0">
            <div class="card border-0 bg-light rounded-4">
                <div class="card-body p-4">
                    <h5 class="font-weight-bold mb-3"><i class="fas fa-info-circle text-info me-2"></i> Info SOP</h5>
                    <ul class="text-muted small ps-3">
                        <li class="mb-2">Lakukan input "Pecah Ball" <strong>segera setelah</strong> fisik es dipecah di kulkas.</li>
                        <li class="mb-2">Jangan menunggu sampai akhir shift agar stok di POS tidak minus.</li>
                        <li>Pastikan jumlah plastik eceran yang dihasilkan sesuai dengan SOP (Misal: 1 Ball 20kg harus jadi 4-5 plastik 5rb).</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection