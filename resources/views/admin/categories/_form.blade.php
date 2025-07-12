{{-- resources/views/admin/categories/_form.blade.php --}}

{{-- Menampilkan semua error validasi di bagian atas form --}}
@if ($errors->any())
    <div class="alert alert-danger mb-4">
        <h5 class="alert-heading">Terjadi Kesalahan Validasi</h5>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<x-input 
    name="name" 
    label="Nama Kategori" 
    :value="old('name', $category->name ?? '')"
    placeholder="Contoh: Makanan Utama"
    required 
/>

<x-select 
    name="parent_id" 
    label="Kategori Induk (Opsional)"
    :options="$parentCategories->pluck('name', 'id')"
    :selected="old('parent_id', $category->parent_id ?? '')"
    placeholder="-- Tidak Ada --"
/>

<x-input 
    type="textarea"
    name="description"
    label="Deskripsi (Opsional)"
    :value="old('description', $category->description ?? '')"
    placeholder="Penjelasan singkat mengenai kategori ini"
/>

<div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">
        Aktifkan kategori ini
    </label>
</div>  