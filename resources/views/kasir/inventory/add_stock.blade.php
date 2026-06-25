@extends('kasir.layouts.app') {{-- Sesuaikan dengan nama layout kasir Anda --}}

@section('title', 'Terima Stok Supplier')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="font-weight-bold text-dark">Penerimaan Stok dari Supplier</h2>
            <p class="text-muted">Gunakan form ini saat truk supplier datang untuk langsung mengupdate stok aktual.</p>
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
        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form action="{{ route('kasir.inventory.store_stock') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="product_id" class="form-label font-weight-bold">Pilih Produk Es Kristal</label>
                        <select class="form-select form-select-lg @error('product_id') is-invalid @enderror" id="product_id" name="product_id" required>
                            <option value="" disabled selected>-- Pilih Produk --</option>
                            @foreach($products as $product)
                            <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                {{ $product->name }} (Saat ini: {{ $product->inventory->current_stock ?? 0 }} {{ $product->unit }})
                            </option>
                            @endforeach
                        </select>
                        @error('product_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="quantity" class="form-label font-weight-bold">Jumlah Masuk (Ball/Pcs)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="fas fa-plus"></i></span>
                            <input type="number" class="form-control @error('quantity') is-invalid @enderror" id="quantity" name="quantity" value="{{ old('quantity') }}" min="1" required placeholder="Contoh: 30">
                            @error('quantity')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="notes" class="form-label font-weight-bold">Catatan (Opsional)</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" placeholder="Contoh: Pengiriman pagi jam 08:00 oleh driver Budi">{{ old('notes') }}</textarea>
                    @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-lg px-5 rounded-3">
                        <i class="fas fa-save me-2"></i> Simpan Stok
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection