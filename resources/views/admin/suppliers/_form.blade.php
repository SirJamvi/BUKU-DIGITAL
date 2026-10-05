<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">Nama Supplier / Pabrik <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $supplier->name ?? '') }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    
    <div class="col-md-6 mb-3">
        <label for="contact_person" class="form-label">Contact Person</label>
        <input type="text" name="contact_person" id="contact_person" class="form-control @error('contact_person') is-invalid @enderror" value="{{ old('contact_person', $supplier->contact_person ?? '') }}">
        @error('contact_person') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="phone" class="form-label">No. Telepon / WhatsApp</label>
        <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $supplier->phone ?? '') }}">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $supplier->email ?? '') }}">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-12 mb-3">
        <label for="address" class="form-label">Alamat Lengkap</label>
        <textarea name="address" id="address" rows="2" class="form-control @error('address') is-invalid @enderror">{{ old('address', $supplier->address ?? '') }}</textarea>
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<hr class="my-4">

<!-- BAGIAN DINAMIS: SETTING HARGA PRODUK -->
<h5 class="mb-3">Pengaturan Harga Produk</h5>
<p class="text-muted small">Tambahkan produk es kristal yang disediakan oleh pabrik/supplier ini, lalu tentukan harga beli (modal) khususnya.</p>

<div class="table-responsive mb-3">
    <table class="table table-bordered table-sm" id="product_table">
        <thead class="table-light">
            <tr>
                <th width="50%">Produk</th>
                <th width="40%">Harga Beli / Modal (Rp)</th>
                <th width="10%" class="text-center">Aksi</th>
            </tr>
        </thead>
        <tbody id="product_tbody">
            <!-- Baris produk akan ditambahkan di sini oleh JavaScript -->
        </tbody>
    </table>
    <button type="button" class="btn btn-sm btn-success" onclick="addProductRow()">
        <i class="fas fa-plus"></i> Tambah Baris Produk
    </button>
</div>

<!-- SCRIPT JAVASCRIPT UNTUK FORM DINAMIS -->
<script>
    // Ambil data dari Controller
    const availableProducts = @json($products);
    
    // Siapkan data lama (jika error validasi) atau data pivot saat mode Edit
    const oldProducts = @json(old('products', isset($supplier) ? $supplier->products->map(function($item) {
        return ['id' => $item->id, 'price' =>$item->pivot->price];
    }) : []));

    let rowIndex = 0;

    function addProductRow(productId = '', price = '') {
        const tbody = document.getElementById('product_tbody');
        const tr = document.createElement('tr');
        
        // Buat dropdown pilihan produk
        let optionsHtml = '<option value="" disabled selected>-- Pilih Produk --</option>';
        availableProducts.forEach(prod => {
            let selected = (prod.id == productId) ? 'selected' : '';
            optionsHtml += `<option value="${prod.id}" ${selected}>${prod.name}</option>`;
        });

        tr.innerHTML = `
            <td>
                <select name="products[${rowIndex}][id]" class="form-select form-select-sm" required>
                    ${optionsHtml}
                </select>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Rp</span>
                    <input type="number" name="products[${rowIndex}][price]" class="form-control" value="${price}" min="0" required placeholder="Contoh: 11500">
                </div>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()">
                    <i class="fas fa-trash"></i> Hapus
                </button>
            </td>
        `;
        
        tbody.appendChild(tr);
        rowIndex++;
    }

    // Render baris saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        if (oldProducts && oldProducts.length > 0) {
            oldProducts.forEach(prod => {
                addProductRow(prod.id, prod.price);
            });
        } else {
            // Tambah 1 baris kosong sebagai default jika baru
            addProductRow();
        }
    });
</script>