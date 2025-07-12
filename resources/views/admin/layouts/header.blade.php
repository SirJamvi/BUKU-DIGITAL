<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
    <div class="container-fluid">
        <button class="btn btn-primary" id="menu-toggle"><i class="fas fa-bars"></i></button>

        <nav aria-label="breadcrumb" class="ms-3 d-none d-md-block">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                @yield('breadcrumb')
            </ol>
        </nav>

        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                {{-- Notifikasi --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownNotification" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        @if(isset($notificationCount) && $notificationCount > 0)
                            <span class="badge rounded-pill bg-danger">{{ $notificationCount }}</span>
                        @endif
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownNotification">
                        @if(isset($notifications) && $notifications->count() > 0)
                            @foreach($notifications as $notification)
                                <li>
                                    <a class="dropdown-item" href="{{ $notification->data['url'] ?? '#' }}">
                                        {{ $notification->data['message'] }}
                                        <br>
                                        <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                                    </a>
                                </li>
                            @endforeach
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="#">Lihat Semua Notifikasi</a></li>
                        @else
                            <li><a class="dropdown-item text-center" href="#">Tidak ada notifikasi baru</a></li>
                        @endif
                    </ul>
                </li>
                {{-- Profil Pengguna --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-user-circle"></i> {{ Auth::user()->name }}
                    </a>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <a class="dropdown-item" href="{{ route('admin.settings.profile') }}">Profil Saya</a>
                        <a class="dropdown-item" href="{{ route('admin.settings.index') }}">Pengaturan</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            Logout
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>