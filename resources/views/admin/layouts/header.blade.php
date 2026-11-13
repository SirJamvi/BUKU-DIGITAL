<div class="main-header">
    <div class="d-flex justify-content-between align-items-center w-100">
        <div class="header-left d-flex align-items-center">
            <!-- Tombol Burger dipaksa selalu aktif di semua ukuran layar -->
            <button class="sidebar-toggle me-3" id="sidebarToggle" style="display: flex !important; align-items: center; justify-content: center;">
                <i class="fas fa-bars"></i>
            </button>

            <nav aria-label="breadcrumb" class="d-none d-md-block">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}">
                            <i class="fas fa-home me-1"></i>Admin
                        </a>
                    </li>
                    @yield('breadcrumb')
                </ol>
            </nav>
        </div>

        <div class="header-right">
            <div class="d-flex align-items-center gap-3">
                {{-- Search Bar (Optional) --}}
                <div class="d-none d-lg-block">
                    <div class="input-group" style="width: 300px;">
                        <input type="text" class="form-control" placeholder="Cari..." style="border-radius: 20px 0 0 20px; border: 1px solid rgba(212, 175, 55, 0.3); background: rgba(255,255,255,0.1); color: white;">
                        <button class="btn btn-outline-secondary" type="button" style="border-radius: 0 20px 20px 0; border: 1px solid rgba(212, 175, 55, 0.3); background: rgba(212, 175, 55, 0.1); color: var(--luxury-gold);">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

                {{-- Mode Cabang / Indikator & Switcher Cepat --}}
                <div class="d-flex align-items-center me-2">
                    @if(app(\App\Support\BranchContext::class)->isConsolidated() || session('active_branch_id') === 'all')
                        <span class="badge bg-primary px-3 py-2 me-2 shadow-sm" style="font-size: 0.85rem;">
                            <i class="fas fa-network-wired me-1"></i> Pusat (Konsolidasi)
                        </span>
                    @else
                        <span class="badge bg-success px-3 py-2 me-2 shadow-sm" style="font-size: 0.85rem;">
                            <i class="fas fa-store me-1"></i> Cabang: {{ session('active_branch_name', 'Cabang Aktif') }}
                        </span>
                    @endif

                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-warning dropdown-toggle d-flex align-items-center gap-1" type="button" id="branchSwitchDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="border-color: rgba(212, 175, 55, 0.5); color: var(--luxury-gold); background: rgba(212, 175, 55, 0.1);">
                            <i class="fas fa-exchange-alt me-1"></i>
                            <span class="d-none d-sm-inline">Pindah Cabang</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="branchSwitchDropdown" style="min-width: 270px;">
                            <li class="dropdown-header text-gold">Mode Navigasi</li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center justify-content-between {{ (session('active_branch_id') === 'all' || !session('active_branch_id')) ? 'active fw-bold' : '' }}" href="{{ route('branch.switch', 'all') }}">
                                    <span><i class="fas fa-network-wired me-2 text-primary"></i> Pusat (Semua Cabang)</span>
                                    @if(session('active_branch_id') === 'all' || !session('active_branch_id'))
                                        <i class="fas fa-check text-success"></i>
                                    @endif
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li class="dropdown-header text-gold">Monitoring Cabang</li>
                            @if(isset($available_branches) && $available_branches->isNotEmpty())
                                @foreach($available_branches as $ab)
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center justify-content-between {{ (string)session('active_branch_id') === (string)$ab->id ? 'active fw-bold' : '' }}" href="{{ route('branch.switch', $ab->id) }}">
                                            <div>
                                                <i class="fas fa-store me-2 text-gold"></i>
                                                {{ $ab->name }}
                                                <small class="text-muted">({{ $ab->code }})</small>
                                                @if(!$ab->is_active)
                                                    <span class="badge bg-secondary ms-1">Nonaktif</span>
                                                @endif
                                            </div>
                                            @if((string)session('active_branch_id') === (string)$ab->id)
                                                <i class="fas fa-check text-success"></i>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            @else
                                <li><span class="dropdown-item text-muted small">Tidak ada cabang aktif</span></li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-muted small" href="{{ route('admin.branches.index') }}">
                                    <i class="fas fa-cog me-2"></i>Kelola Cabang
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                {{-- Notifications --}}
                <div class="dropdown">
                    <a class="btn btn-link text-decoration-none position-relative" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell fs-5" style="color: var(--luxury-gold);"></i>
                        @if(isset($notificationCount) && $notificationCount > 0)
                            <span class="notification-badge">{{ $notificationCount > 99 ? '99+' : $notificationCount }}</span>
                        @endif
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown" style="width: 350px;">
                        <li class="dropdown-header d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-gold">Notifikasi</span>
                            @if(isset($notificationCount) && $notificationCount > 0)
                                <span class="badge rounded-pill" style="background: var(--gold-gradient);">{{ $notificationCount }}</span>
                            @endif
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        
                        @if(isset($notifications) && $notifications->count() > 0)
                            @foreach($notifications->take(5) as $notification)
                                <li>
                                    <a class="dropdown-item py-2" href="{{ $notification->data['url'] ?? '#' }}">
                                        <div class="d-flex align-items-start">
                                            <div class="flex-shrink-0 me-2">
                                                <i class="fas fa-circle text-gold" style="font-size: 0.6rem;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-medium text-gold">{{ $notification->data['title'] ?? 'Notifikasi' }}</div>
                                                <div class="small">{{ $notification->data['message'] }}</div>
                                                <div class="text-muted small">{{ $notification->created_at->diffForHumans() }}</div>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                            
                            @if($notifications->count() > 5)
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-center text-gold" href="#">
                                        <i class="fas fa-eye me-1"></i>Lihat Semua Notifikasi
                                    </a>
                                </li>
                            @endif
                        @else
                            <li>
                                <div class="dropdown-item-text text-center text-muted py-3">
                                    <i class="fas fa-bell-slash fs-4 d-block mb-2 text-gold"></i>
                                    Tidak ada notifikasi baru
                                </div>
                            </li>
                        @endif
                    </ul>
                </div>

                {{-- User Profile --}}
                <div class="dropdown">
                    <a class="btn btn-link text-decoration-none d-flex align-items-center" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar me-2">
                                @if(Auth::user()->avatar)
                                    <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="{{ Auth::user()->name }}" class="rounded-circle" width="32" height="32" style="border: 1px solid var(--luxury-gold);">
                                @else
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: var(--gold-gradient); color: var(--deep-navy); font-weight: 600;">
                                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                    </div>
                                @endif
                            </div>
                            <div class="d-none d-md-block">
                                <div class="fw-medium" style="color: var(--luxury-gold);">{{ Auth::user()->name }}</div>
                                <div class="text-muted small" style="color: rgba(255,255,255,0.7);">{{ Auth::user()->email }}</div>
                            </div>
                            <i class="fas fa-chevron-down ms-2" style="color: var(--luxury-gold); font-size: 0.8rem;"></i>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li class="dropdown-header d-md-none">
                            <div class="fw-bold text-gold">{{ Auth::user()->name }}</div>
                            <div class="text-muted small" style="color: rgba(255,255,255,0.7);">{{ Auth::user()->email }}</div>
                        </li>
                        <li class="d-md-none"><hr class="dropdown-divider"></li>
                        
                        <li>
                            <a class="dropdown-item" href="{{ route('admin.settings.profile') }}">
                                <i class="fas fa-user-circle me-2 text-gold"></i>Profil Saya
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('admin.settings.index') }}">
                                <i class="fas fa-cog me-2 text-gold"></i>Pengaturan
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#">
                                <i class="fas fa-question-circle me-2 text-gold"></i>Bantuan
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-gold" href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        @csrf
    </form>
</div>

<style>
    .header-right .input-group .form-control:focus {
        border-color: var(--luxury-gold);
        box-shadow: 0 0 0 0.2rem rgba(212,175,55,0.25);
        background: rgba(255,255,255,0.15);
        color: white;
    }
    
    .header-right .btn-outline-secondary {
        border-color: rgba(212, 175, 55, 0.3);
        color: var(--luxury-gold);
    }
    
    .header-right .btn-outline-secondary:hover {
        background: var(--luxury-gold);
        border-color: var(--luxury-gold);
        color: var(--deep-navy);
    }
    
    .notification-badge {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    .dropdown-menu {
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        border-radius: 8px;
        background-color: var(--deep-navy);
        border: 1px solid rgba(212, 175, 55, 0.2);
    }
    
    .dropdown-item {
        padding: 0.7rem 1.2rem;
        transition: all 0.3s ease;
        color: rgba(255,255,255,0.8);
    }
    
    .dropdown-item:hover {
        background: rgba(212, 175, 55, 0.1);
        color: var(--gold-light);
    }
    
    .dropdown-header {
        color: var(--luxury-gold);
        font-weight: 600;
    }
    
    .user-avatar img {
        object-fit: cover;
        border: 1px solid var(--luxury-gold);
    }
    
    .text-gold {
        color: var(--luxury-gold);
    }
</style>