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
                        @if ($user->is_active)
                            <span class="badge bg-success">Aktif</span>
                        @else
                            <span class="badge bg-secondary">Nonaktif</span>
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