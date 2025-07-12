{{-- Menampilkan error validasi --}}
@include('admin.components.validation-error')

<div class="mb-3">
    <label for="name" class="form-label">Nama Kategori</label>
    <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $category->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="parent_id" class="form-label">Kategori Induk (Opsional)</label>
    <select class="form-select" id="parent_id" name="parent_id">
        <option value="">-- Tidak Ada --</option>
        @foreach ($parentCategories as $parent)
            <option value="{{ $parent->id }}" {{ old('parent_id', $category->parent_id ?? '') == $parent->id ? 'selected' : '' }}>
                {{ $parent->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi (Opsional)</label>
    <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $category->description ?? '') }}</textarea>
</div>

<div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">
        Aktifkan kategori ini
    </label>
</div>