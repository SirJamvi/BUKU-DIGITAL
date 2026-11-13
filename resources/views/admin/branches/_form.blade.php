<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label fw-semibold">Nama Cabang <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
               value="{{ old('name', $branch->name ?? '') }}" placeholder="Contoh: Cabang Barat, Pabrik Utama" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="code" class="form-label fw-semibold">
            Kode Cabang <span class="text-danger">*</span>
            @if(isset($branch))
                <small class="text-muted fw-normal">(Kode bersifat permanen dan tidak dapat diubah)</small>
            @else
                <small class="text-muted fw-normal">(Format alfanumerik kapital, contoh: CB-01, PUSAT)</small>
            @endif
        </label>
        <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" 
               value="{{ old('code', $branch->code ?? '') }}" 
               placeholder="Contoh: CB-01" 
               {{ isset($branch) ? 'readonly disabled' : 'required' }}
               style="text-transform: uppercase;">
        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="manager_id" class="form-label fw-semibold">Penanggung Jawab / Manajer</label>
        <select name="manager_id" id="manager_id" class="form-select @error('manager_id') is-invalid @enderror">
            <option value="">-- Tanpa Manajer Khusus --</option>
            @foreach($managers as $manager)
                <option value="{{ $manager->id }}" {{ (string) old('manager_id', $branch->manager_id ?? '') === (string) $manager->id ? 'selected' : '' }}>
                    {{ $manager->name }} ({{ $manager->email }})
                </option>
            @endforeach
        </select>
        @error('manager_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="phone" class="form-label fw-semibold">No. Telepon / WhatsApp</label>
        <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" 
               value="{{ old('phone', $branch->phone ?? '') }}" placeholder="Contoh: 081234567890">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-12 mb-3">
        <label for="address" class="form-label fw-semibold">Alamat Cabang</label>
        <textarea name="address" id="address" rows="3" class="form-control @error('address') is-invalid @enderror" 
                  placeholder="Alamat lengkap lokasi fisik cabang atau depo...">{{ old('address', $branch->address ?? '') }}</textarea>
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-12 mb-3">
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" 
                   {{ old('is_active', $branch->is_active ?? true) ? 'checked' : '' }}
                   {{ isset($branch) && $branch->is_main ? 'disabled' : '' }}>
            <label class="form-check-label fw-semibold" for="is_active">
                Cabang Aktif untuk Transaksi dan Operasional
            </label>
            @if(isset($branch) && $branch->is_main)
                <div class="form-text text-info">
                    <i class="fas fa-info-circle me-1"></i> Cabang Utama (Pusat) wajib selalu berstatus aktif.
                </div>
            @endif
        </div>
    </div>
</div>

