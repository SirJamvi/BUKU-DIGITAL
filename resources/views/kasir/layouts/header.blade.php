{{-- resources/views/kasir/layouts/header.blade.php --}}
<nav class="navbar navbar-expand-lg navbar-light bg-white rounded shadow-sm p-3 mb-4">
    <div class="container-fluid">
        {{-- Tombol Breadcrumb/Judul Halaman --}}
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('kasir.dashboard') }}">Kasir</a></li>
                @yield('breadcrumb')
            </ol>
        </nav>

        {{-- Dropdown Profil Pengguna --}}
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user-circle fa-2x me-2" style="color: var(--kasir-accent);"></i>
                <strong>{{ Auth::user()->name }}</strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-end text-small shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="#">Profil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </li>
            </ul>
        </div>
    </div>
</nav>