{{-- resources/views/admin/users/index.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'Manajemen Pengguna')

@section('breadcrumb')
<li class="breadcrumb-item active" aria-current="page">Pengguna</li>
@endsection

@section('content')
<x-card>
    @slot('title')
    Daftar Semua Pengguna
    @endslot
    @slot('headerActions')
    <x-button href="{{ route('admin.users.create') }}" variant="primary">
        <i class="fas fa-plus me-2"></i>Tambah User Baru
    </x-button>
    @endslot

    @include('components.alert')

    <x-table>
        @slot('thead')
        <tr>
            <th>No</th>
            <th>Nama</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Tanggal Bergabung</th>
            <th class="text-center">Aksi</th>
        </tr>
        @endslot

        @forelse ($users as $user)
        <tr>
            <td>{{ $users->firstItem() + $loop->index }}</td>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>
                @if ($user->role == 'admin')
                <span class="badge bg-danger">{{ ucfirst($user->role) }}</span>
                @else
                <span class="badge bg-info">{{ ucfirst($user->role) }}</span>
                @endif
            </td>
            <td>
                {{-- Mencegah user menonaktifkan dirinya sendiri --}}
                @if (Auth::id() !== $user->id)
                <div class="form-check form-switch">
                    <input class="form-check-input toggle-status" type="checkbox"
                        data-id="{{ $user->id }}"
                        {{ $user->is_active ? 'checked' : '' }}>
                    <label class="form-check-label status-label-{{ $user->id }}">
                        @if ($user->is_active)
                        <span class="badge bg-success">Aktif</span>
                        @else
                        <span class="badge bg-secondary">Nonaktif</span>
                        @endif
                    </label>
                </div>
                @else
                <span class="badge bg-success">Aktif (Anda)</span>
                @endif
            </td>
            <td>{{ $user->created_at->isoFormat('D MMMM YYYY') }}</td>
            <td class="text-center">
                <x-button href="{{ route('admin.users.edit', $user->id) }}" variant="warning" class="btn-sm">
                    <i class="fas fa-edit"></i>
                </x-button>
                {{-- Mencegah admin menghapus dirinya sendiri --}}
                @if (Auth::id() !== $user->id)
                <x-button type="button" variant="danger" class="btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $user->id }}">
                    <i class="fas fa-trash"></i>
                </x-button>
                @endif

                {{-- Modal Konfirmasi Hapus --}}
                <x-modal id="deleteModal-{{ $user->id }}" title="Konfirmasi Hapus Pengguna">
                    <p>Apakah Anda yakin ingin menghapus pengguna <strong>{{ $user->name }}</strong>? Semua data terkait pengguna ini mungkin akan hilang.</p>
                    @slot('footer')
                    <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <x-button type="button" variant="secondary" data-bs-dismiss="modal">Batal</x-button>
                        <x-button type="submit" variant="danger">Ya, Hapus</x-button>
                    </form>
                    @endslot
                </x-modal>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="7" class="text-center">Tidak ada data pengguna ditemukan.</td>
        </tr>
        @endforelse
    </x-table>

    @if ($users->hasPages())
    <div class="mt-3">
        {{ $users->links('components.pagination') }}
    </div>
    @endif
</x-card>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleButtons = document.querySelectorAll('.toggle-status');
        // Template URL, nanti angka 0 diganti dengan ID user asli
        const urlTemplate = "{{ route('admin.users.toggle-status', ['user' => 0]) }}";

        toggleButtons.forEach(button => {
            button.addEventListener('change', function() {
                const userId = this.getAttribute('data-id');
                const isChecked = this.checked;
                const label = document.querySelector(`.status-label-${userId}`);
                const url = urlTemplate.replace('/0', `/${userId}`);

                fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (data.is_active) {
                                label.innerHTML = '<span class="badge bg-success">Aktif</span>';
                            } else {
                                label.innerHTML = '<span class="badge bg-secondary">Nonaktif</span>';
                            }
                        } else {
                            this.checked = !isChecked;
                            alert('Gagal merubah status: ' + data.message);
                        }
                    })
                    .catch(() => {
                        this.checked = !isChecked;
                        alert('Terjadi kesalahan sistem.');
                    });
            });
        });
    });
</script>
@endpush