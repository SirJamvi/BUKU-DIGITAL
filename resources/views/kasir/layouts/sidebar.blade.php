{{-- resources/views/kasir/layouts/sidebar.blade.php --}}
<nav id="sidebar" class="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="logo-container">
            <a href="{{ route('kasir.dashboard') }}" class="logo-link text-decoration-none">
                <div class="logo-icon">
                    <i class="fas fa-cash-register"></i>
                </div>
                <div class="logo-text">
                    <h3 class="logo-title mb-0">KasirPro</h3>
                    <small class="logo-subtitle">Point of Sale</small>
                </div>
            </a>
        </div>
        
        <!-- Close button for mobile -->
        <button class="sidebar-close d-lg-none" type="button" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- User Profile Section (Mobile) -->
    <div class="user-profile d-lg-none">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-details">
                <div class="user-name">{{ Auth::user()->name }}</div>
                <div class="user-role">Kasir</div>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <div class="sidebar-nav-container">
        <ul class="sidebar-nav">
            <li class="nav-item {{ request()->routeIs('kasir.dashboard*') ? 'active' : '' }}">
                <a href="{{ route('kasir.dashboard') }}" class="nav-link">
                    <div class="nav-icon">
                        <i class="fas fa-th-large"></i>
                    </div>
                    <span class="nav-text">Dashboard</span>
                    <div class="nav-indicator"></div>
                </a>
            </li>

            <li class="nav-divider">
                <span class="divider-text">Transaksi</span>
            </li>

            <li class="nav-item {{ request()->routeIs('kasir.pos*') ? 'active' : '' }}">
                <a href="{{ route('kasir.pos.index') }}" class="nav-link">
                    <div class="nav-icon">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <span class="nav-text">Kasir</span>
                    <div class="nav-badge">
                        <span class="badge bg-primary">POS</span>
                    </div>
                    <div class="nav-indicator"></div>
                </a>
            </li>

            <li class="nav-item {{ request()->routeIs('kasir.transactions*') ? 'active' : '' }}">
                <a href="{{ route('kasir.transactions.index') }}" class="nav-link">
                    <div class="nav-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <span class="nav-text">Riwayat Transaksi</span>
                    <div class="nav-indicator"></div>
                </a>
            </li>

            <li class="nav-divider">
                <span class="divider-text">Manajemen</span>
            </li>

            <li class="nav-item {{ request()->routeIs('kasir.customers*') ? 'active' : '' }}">
                <a href="{{ route('kasir.customers.index') }}" class="nav-link">
                    <div class="nav-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="nav-text">Pelanggan</span>
                    <div class="nav-indicator"></div>
                </a>
            </li>

            <li class="nav-item {{ request()->routeIs('kasir.products*') ? 'active' : '' }}">
                <a href="{{ route('kasir.products.index') }}" class="nav-link">
                    <div class="nav-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <span class="nav-text">Produk</span>
                    <div class="nav-indicator"></div>
                </a>
            </li>

            <li class="nav-divider">
                <span class="divider-text">Laporan</span>
            </li>

            <li class="nav-item {{ request()->routeIs('kasir.reports*') ? 'active' : '' }}">
                <a href="{{ route('kasir.reports.index') }}" class="nav-link">
                    <div class="nav-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <span class="nav-text">Laporan Penjualan</span>
                    <div class="nav-indicator"></div>
                </a>
            </li>
        </ul>

        <!-- Quick Stats (Desktop Only) -->
        <div class="sidebar-stats d-none d-lg-block">
            <div class="stats-header">
                <h6 class="mb-3">Statistik Hari Ini</h6>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value">@yield('today-sales', 'Rp 0')</div>
                        <div class="stat-label">Penjualan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value">@yield('today-transactions', '0')</div>
                        <div class="stat-label">Transaksi</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="footer-content">
            <div class="app-version">
                <small class="text-muted">Version 1.0.0</small>
            </div>
            <div class="footer-links">
                <a href="#" class="footer-link" title="Bantuan">
                    <i class="fas fa-question-circle"></i>
                </a>
                <a href="#" class="footer-link" title="Pengaturan">
                    <i class="fas fa-cog"></i>
                </a>
            </div>
        </div>
    </div>
</nav>

<style>
    /* Sidebar Base Styles */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 280px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        z-index: 1050;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        box-shadow: 4px 0 25px rgba(0, 0, 0, 0.15);
        overflow: hidden;
    }

    /* Main content adjustment */
    .main-content {
        margin-left: 280px;
        transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Sidebar Header */
    .sidebar-header {
        padding: 1.5rem 1.25rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        flex-shrink: 0;
        min-height: 80px;
    }

    .logo-container {
        display: flex;
        align-items: center;
        flex: 1;
    }

    .logo-link {
        display: flex;
        align-items: center;
        color: white !important;
        transition: all 0.3s ease;
        text-decoration: none !important;
    }

    .logo-link:hover {
        transform: translateX(5px);
        color: white !important;
    }

    .logo-icon {
        width: 50px;
        height: 50px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        font-size: 1.5rem;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }

    .logo-link:hover .logo-icon {
        background: rgba(255, 255, 255, 0.25);
        transform: scale(1.05);
    }

    .logo-text {
        display: flex;
        flex-direction: column;
    }

    .logo-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0;
        line-height: 1.2;
    }

    .logo-subtitle {
        opacity: 0.8;
        font-size: 0.75rem;
        font-weight: 400;
        margin-top: -2px;
    }

    .sidebar-close {
        background: none;
        border: none;
        color: white;
        font-size: 1.25rem;
        padding: 0.5rem;
        border-radius: 6px;
        transition: all 0.3s ease;
        opacity: 0.7;
        flex-shrink: 0;
    }

    .sidebar-close:hover {
        background: rgba(255, 255, 255, 0.1);
        opacity: 1;
        transform: rotate(90deg);
    }

    /* User Profile (Mobile) */
    .user-profile {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(255, 255, 255, 0.05);
        flex-shrink: 0;
    }

    .user-info {
        display: flex;
        align-items: center;
    }

    .user-avatar {
        font-size: 2.5rem;
        margin-right: 1rem;
        opacity: 0.9;
        flex-shrink: 0;
    }

    .user-details {
        flex: 1;
        min-width: 0;
    }

    .user-name {
        font-weight: 600;
        font-size: 1rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-role {
        font-size: 0.8rem;
        opacity: 0.8;
    }

    /* Navigation Container */
    .sidebar-nav-container {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        overflow-x: hidden;
        padding-bottom: 1rem;
    }

    /* Sidebar Navigation */
    .sidebar-nav {
        list-style: none;
        padding: 1rem 0 0 0;
        margin: 0;
        flex: 1;
    }

    .nav-item {
        margin: 0 1rem 0.5rem 1rem;
        position: relative;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 1rem 1.25rem;
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        border-radius: 12px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        font-weight: 500;
        background: transparent;
        min-height: 60px;
    }

    .nav-link:hover {
        color: white !important;
        background: rgba(255, 255, 255, 0.1);
        transform: translateX(8px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        text-decoration: none;
    }

    .nav-item.active .nav-link {
        color: white !important;
        background: rgba(255, 255, 255, 0.15);
        font-weight: 600;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }

    .nav-icon {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        font-size: 1.1rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }

    .nav-link:hover .nav-icon,
    .nav-item.active .nav-icon {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }

    .nav-text {
        flex: 1;
        font-size: 0.95rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .nav-badge {
        margin-left: auto;
        flex-shrink: 0;
    }

    .nav-badge .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
    }

    .nav-indicator {
        position: absolute;
        left: -1rem;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 0;
        background: white;
        border-radius: 2px;
        transition: all 0.3s ease;
    }

    .nav-item.active .nav-indicator {
        height: 30px;
    }

    /* Navigation Dividers */
    .nav-divider {
        margin: 1.5rem 1rem 1rem 1rem;
        padding: 0 1.25rem;
    }

    .divider-text {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.6;
        position: relative;
        padding-left: 1rem;
    }

    .divider-text::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 4px;
        background: rgba(255, 255, 255, 0.6);
        border-radius: 50%;
    }

    /* Sidebar Stats */
    .sidebar-stats {
        padding: 1rem 1.25rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(255, 255, 255, 0.05);
        margin-top: auto;
        flex-shrink: 0;
    }

    .stats-header h6 {
        color: rgba(255, 255, 255, 0.9);
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 1rem;
    }

    .stats-grid {
        display: grid;
        gap: 0.75rem;
    }

    .stat-card {
        display: flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        padding: 0.875rem;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        background: rgba(255, 255, 255, 0.12);
        transform: translateY(-2px);
    }

    .stat-icon {
        width: 35px;
        height: 35px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.75rem;
        font-size: 0.9rem;
        color: white;
        flex-shrink: 0;
    }

    .stat-info {
        flex: 1;
        min-width: 0;
    }

    .stat-value {
        font-weight: 600;
        font-size: 0.9rem;
        color: white;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .stat-label {
        font-size: 0.7rem;
        opacity: 0.8;
    }

    /* Sidebar Footer */
    .sidebar-footer {
        padding: 1rem 1.25rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(255, 255, 255, 0.05);
        flex-shrink: 0;
        min-height: 60px;
    }

    .footer-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .footer-links {
        display: flex;
        gap: 0.5rem;
    }

    .footer-link {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        color: rgba(255, 255, 255, 0.7);
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .footer-link:hover {
        color: white !important;
        background: rgba(255, 255, 255, 0.1);
        text-decoration: none;
    }

    /* Mobile Responsive */
    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%);
            width: 300px;
            z-index: 1055;
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0;
        }

        .user-profile {
            display: block !important;
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 280px;
        }
    }

    @media (max-width: 576px) {
        .sidebar {
            width: 260px;
        }

        .logo-title {
            font-size: 1.25rem;
        }

        .nav-link {
            padding: 0.875rem 1rem;
        }

        .nav-icon {
            width: 35px;
            height: 35px;
            margin-right: 0.875rem;
        }
    }

    /* Scrollbar Styles */
    .sidebar-nav-container::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar-nav-container::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
    }

    .sidebar-nav-container::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 2px;
    }

    .sidebar-nav-container::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
    }

    /* Animations */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .nav-item {
        animation: slideIn 0.4s ease forwards;
    }

    .nav-item:nth-child(1) { animation-delay: 0.05s; }
    .nav-item:nth-child(2) { animation-delay: 0.1s; }
    .nav-item:nth-child(3) { animation-delay: 0.15s; }
    .nav-item:nth-child(4) { animation-delay: 0.2s; }
    .nav-item:nth-child(5) { animation-delay: 0.25s; }
    .nav-item:nth-child(6) { animation-delay: 0.3s; }
    .nav-item:nth-child(7) { animation-delay: 0.35s; }
    .nav-item:nth-child(8) { animation-delay: 0.4s; }

    /* Focus States for Accessibility */
    .nav-link:focus {
        outline: 2px solid rgba(255, 255, 255, 0.5);
        outline-offset: 2px;
    }

    .footer-link:focus {
        outline: 2px solid rgba(255, 255, 255, 0.5);
        outline-offset: 2px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Close sidebar button functionality
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');

        if (sidebarClose) {
            sidebarClose.addEventListener('click', function() {
                sidebar.classList.remove('show');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('show');
                }
                if (mobileMenuBtn) {
                    mobileMenuBtn.querySelector('i').className = 'fas fa-bars';
                }
            });
        }

        // Add smooth scroll behavior to navigation links
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Add loading state
                const icon = this.querySelector('.nav-icon i');
                if (icon) {
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    
                    // Restore icon after a brief moment
                    setTimeout(() => {
                        icon.className = originalClass;
                    }, 800);
                }
            });
        });

        // Update stats periodically (if elements exist)
        function updateStats() {
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach(stat => {
                stat.style.opacity = '0.7';
                setTimeout(() => {
                    stat.style.opacity = '1';
                }, 300);
            });
        }

        // Update stats every 5 minutes
        setInterval(updateStats, 300000);

        // Handle window resize for responsive behavior
        function handleResize() {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('show');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('show');
                }
                if (mobileMenuBtn) {
                    const icon = mobileMenuBtn.querySelector('i');
                    if (icon) {
                        icon.className = 'fas fa-bars';
                    }
                }
            }
        }

        window.addEventListener('resize', handleResize);

        // Prevent sidebar from closing when clicking inside it
        sidebar.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
</script>