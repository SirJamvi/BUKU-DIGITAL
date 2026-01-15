{{-- resources/views/kasir/layouts/header.blade.php --}}
<header class="main-header">
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
        <div class="container-fluid">
            <!-- Breadcrumb Section -->
            <div class="breadcrumb-section flex-grow-1">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('kasir.dashboard') }}" class="text-decoration-none">
                                <i class="fas fa-home me-1"></i>
                                <span class="d-none d-sm-inline">Dashboard</span>
                            </a>
                        </li>
                        @yield('breadcrumb')
                    </ol>
                </nav>
                
                <!-- Page Title (Mobile) -->
                <div class="page-title d-lg-none mt-2">
                    <h5 class="mb-0 fw-bold text-dark">@yield('page-title', 'Dashboard')</h5>
                </div>
            </div>

            <!-- User Profile Dropdown -->
            <div class="user-section">
                <div class="dropdown">
                    <a href="#" 
                       class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle user-dropdown" 
                       id="dropdownUser1" 
                       data-bs-toggle="dropdown" 
                       aria-expanded="false">
                        <div class="user-avatar me-2">
                            <i class="fas fa-user-circle fa-2x"></i>
                        </div>
                        <div class="user-info d-none d-md-block">
                            <div class="user-name fw-bold">{{ Auth::user()->name }}</div>
                            <div class="user-role text-muted small">Kasir</div>
                        </div>
                        <i class="fas fa-chevron-down ms-2 d-none d-md-inline"></i>
                    </a>
                    
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="dropdownUser1">
                        <li class="px-3 py-2 border-bottom">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-user-circle fa-2x me-3 text-primary"></i>
                                <div>
                                    <div class="fw-bold">{{ Auth::user()->name }}</div>
                                    <div class="text-muted small">{{ Auth::user()->email }}</div>
                                </div>
                            </div>
                        </li>
                        
                        <li>
                            <a class="dropdown-item py-2" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                                <i class="fas fa-user fa-fw me-2 text-info"></i> 
                                Profil Saya
                            </a>
                        </li>
                        
                        <li>
                            <a class="dropdown-item py-2" href="#">
                                <i class="fas fa-cog fa-fw me-2 text-secondary"></i> 
                                Pengaturan
                            </a>
                        </li>
                        
                        <li>
                            <a class="dropdown-item py-2" href="#">
                                <i class="fas fa-question-circle fa-fw me-2 text-primary"></i> 
                                Bantuan
                            </a>
                        </li>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <li>
                            <a class="dropdown-item py-2 text-danger" 
                               href="#" 
                               onclick="event.preventDefault(); confirmLogout();">
                                <i class="fas fa-sign-out-alt fa-fw me-2"></i> 
                                Keluar
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Quick Actions Bar (Optional) -->
    <div class="quick-actions d-none d-lg-flex">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between py-2">
                <div class="quick-stats d-flex gap-3">
                    <div class="stat-item">
                        <span class="text-muted small">Hari ini:</span>
                        <span class="fw-bold ms-1">@yield('today-sales', 'Rp 0')</span>
                    </div>
                    <div class="stat-item">
                        <span class="text-muted small">Transaksi:</span>
                        <span class="fw-bold ms-1">@yield('today-transactions', '0')</span>
                    </div>
                </div>
                
                <div class="current-time">
                    <small class="text-muted" id="currentDateTime"></small>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="logoutModalLabel">
                    <i class="fas fa-sign-out-alt me-2"></i>Konfirmasi Keluar
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fas fa-question-circle fa-3x text-warning mb-3"></i>
                <h6 class="mb-3">Apakah Anda yakin ingin keluar?</h6>
                <p class="text-muted small">Anda akan keluar dari sistem kasir dan perlu login kembali.</p>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Batal
                </button>
                <button type="button" class="btn btn-danger px-4" onclick="performLogout()">
                    <i class="fas fa-sign-out-alt me-2"></i>Ya, Keluar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Profile Modal (Optional) -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="fas fa-user me-2"></i>Profil Pengguna
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <i class="fas fa-user-circle fa-4x text-primary mb-3"></i>
                    <h6 class="fw-bold">{{ Auth::user()->name }}</h6>
                    <p class="text-muted">{{ Auth::user()->email }}</p>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body p-3">
                                <small class="text-muted">Informasi Akun</small>
                                <div class="mt-2">
                                    <div class="d-flex justify-content-between py-1">
                                        <span>Role:</span>
                                        <span class="fw-bold">Kasir</span>
                                    </div>
                                    <div class="d-flex justify-content-between py-1">
                                        <span>Status:</span>
                                        <span class="badge bg-success">Aktif</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Logout Form -->
<form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
    @csrf
</form>

<style>
    /* Header Styles */
    .main-header {
        background-color: white;
        box-shadow: var(--kasir-shadow);
        position: sticky;
        top: 0;
        z-index: 100;
        border-bottom: 1px solid var(--kasir-border);
    }

    .navbar {
        padding: 1rem 0;
        min-height: 70px;
    }

    /* Breadcrumb Styles */
    .breadcrumb {
        background-color: transparent;
        padding: 0;
        margin: 0;
        font-size: 0.9rem;
    }

    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        font-weight: bold;
        color: var(--kasir-muted);
    }

    .breadcrumb-item a {
        color: var(--kasir-primary);
        transition: all 0.3s ease;
    }

    .breadcrumb-item a:hover {
        color: #1d4ed8;
        text-decoration: underline !important;
    }

    .breadcrumb-item.active {
        color: var(--kasir-muted);
    }

    /* User Dropdown Styles */
    .user-dropdown {
        padding: 0.5rem 1rem;
        border-radius: 0.75rem;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .user-dropdown:hover {
        background-color: var(--kasir-secondary);
        border-color: var(--kasir-border);
        transform: translateY(-1px);
    }

    .user-avatar i {
        color: var(--kasir-primary);
        transition: all 0.3s ease;
    }

    .user-dropdown:hover .user-avatar i {
        color: #1d4ed8;
        transform: scale(1.1);
    }

    .dropdown-menu {
        border-radius: 0.75rem;
        padding: 0.5rem 0;
        min-width: 250px;
        box-shadow: var(--kasir-shadow-lg);
        border: 1px solid var(--kasir-border);
        margin-top: 0.5rem;
    }

    .dropdown-item {
        padding: 0.75rem 1.25rem;
        transition: all 0.3s ease;
        border-radius: 0;
    }

    .dropdown-item:hover {
        background-color: var(--kasir-secondary);
        transform: translateX(5px);
    }

    .dropdown-item i {
        width: 20px;
        text-align: center;
    }

    /* Quick Actions Bar */
    .quick-actions {
        background-color: #f8fafc;
        border-bottom: 1px solid var(--kasir-border);
        font-size: 0.875rem;
    }

    .stat-item {
        padding: 0.25rem 0.75rem;
        background-color: white;
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .navbar {
            padding: 0.75rem 0;
            min-height: 60px;
        }

        .user-info {
            display: none !important;
        }

        .breadcrumb {
            font-size: 0.8rem;
        }

        .dropdown-menu {
            min-width: 200px;
        }

        .page-title {
            border-top: 1px solid var(--kasir-border);
            padding-top: 0.5rem;
        }
    }

    @media (max-width: 576px) {
        .container-fluid {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }

        .breadcrumb-item span {
            display: none;
        }

        .user-dropdown {
            padding: 0.25rem 0.5rem;
        }
    }

    /* Modal Enhancements */
    .modal-content {
        border-radius: 1rem;
        overflow: hidden;
    }

    .modal-header {
        border: none;
        padding: 1.5rem 1.5rem 1rem;
    }

    .modal-body {
        padding: 1rem 1.5rem 1.5rem;
    }

    .modal-footer {
        padding: 1rem 1.5rem 1.5rem;
    }
</style>

<script>
    // Update current date time
    function updateDateTime() {
        const now = new Date();
        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        const dateTimeElement = document.getElementById('currentDateTime');
        if (dateTimeElement) {
            dateTimeElement.textContent = now.toLocaleDateString('id-ID', options);
        }
    }

    // Logout functions
    function confirmLogout() {
        const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
        logoutModal.show();
    }

    function performLogout() {
        // Show loading state
        const logoutBtn = event.target;
        const originalText = logoutBtn.innerHTML;
        logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Keluar...';
        logoutBtn.disabled = true;
        
        // Submit logout form after brief delay
        setTimeout(() => {
            document.getElementById('logout-form').submit();
        }, 1000);
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateDateTime();
        // Update every minute
        setInterval(updateDateTime, 60000);
    });
</script>