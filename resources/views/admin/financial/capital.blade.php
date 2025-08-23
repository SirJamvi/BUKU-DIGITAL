@extends('admin.layouts.app')
@section('title', 'Manajemen Modal')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.financial.index') }}">Finansial</a></li>
    <li class="breadcrumb-item active">Modal</li>
@endsection
@section('content')
    <x-card title="Input Modal Awal Investasi">
        <form action="{{ route('admin.financial.capital.store') }}" method="POST">
            @csrf
            <p class="text-muted">Masukkan total modal awal yang Anda investasikan ke dalam bisnis. Angka ini akan menjadi dasar perhitungan ROI.</p>
            <x-input type="number" name="initial_capital" label="Total Modal Awal (Rp)" :value="$capital->initial_capital ?? 0" required />
            <div class="mt-3">
                <x-button type="submit" variant="primary">Simpan Modal</x-button>
            </div>
        </form>
    </x-card>
@endsection