@props([
    'name',
    'label' => '',
    'options' => [], // array [value => text]
    'selected' => null,
    'required' => false,
])

<div class="mb-3">
    @if ($label)
        <label for="{{ $name }}" class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
    @endif

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }}
        @if($required) required @endif
    >
        <option value="">-- Pilih salah satu --</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" {{ old($name, $selected) == $value ? 'selected' : '' }}>
                {{ $text }}
            </option>
        @endforeach
    </select>

    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>