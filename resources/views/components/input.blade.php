@props([
    'type' => 'text',
    'name',
    'label' => '',
    'value' => '',
    'placeholder' => '',
    'required' => false,
])

<div class="mb-3">
    @if ($label)
        <label for="{{ $name }}" class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
    @endif

    <input 
        type="{{ $type }}" 
        id="{{ $name }}" 
        name="{{ $name }}" 
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }}
        @if($required) required @endif
    >

    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- Cara pakai:
<x-input type="email" name="email" label="Alamat Email" required />
--}}