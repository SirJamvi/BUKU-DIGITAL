{{-- resources/views/kasir/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard Kasir') - {{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    
    <style>
        :root {
            --kasir-primary: #667eea;
            --kasir-secondary: #764ba2;
            --kasir-accent: #f59e0b;
            --kasir-success: #10b981;
            --kasir-danger: #ef4444;
            --kasir-warning: #f59e0b;
            --kasir-info: #06b6d4;
            --kasir-dark: #1e293b;
            --kasir-light: #f8fafc;
            --kasir-muted: #64748b;
            --kasir-border: #e2e8f0;
            --kasir-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --kasir-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --kasir-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f7f9fc;
            line-height: 1.6;
            color: var(--kasir-dark);
            overflow-x: hidden;
        }

        /* Layout Container */
        .app-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Main Content Area - Adjusted to match sidebar */
        .main-content {
            flex: 1;
            margin-left: 280px; /* Match sidebar width */
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: #f7f9fc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content-wrapper {
            flex: 1;
            padding: 2rem;
            max-width: 100%;
        }

        /* Mobile Overlay - Enhanced */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1054;
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(4px);
        }

        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        /* Enhanced Cards */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
            background-color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .card:hover {
            box-shadow: 0 8px 35px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
        }

        .card-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
            border-radius: 16px 16px 0 0 !important;
            padding: 1.5rem;
            font-weight: 600;
            color: var(--kasir-dark);
        }

        .card-body {
            padding: 2rem;
        }

        /* Mobile Menu Button - Styled to match sidebar theme */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 1056;
            background: var(--kasir-gradient);
            border: none;
            border-radius: 12px;
            padding: 0.875rem;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
            color: white;
            font-size: 1.25rem;
            width: 3.5rem;
            height: 3.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .mobile-menu-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.4);
        }

        .mobile-menu-btn:active {
            transform: scale(0.95);
        }

        /* Enhanced Button Styles */
        .btn {
            border-radius: 10px;
            font-weight: 500;
            padding: 0.875rem 1.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: var(--kasir-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
            color: white;
        }

        /* Enhanced Alerts */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(10px);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
            color: #7f1d1d;
            border-left: 4px solid #ef4444;
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.05));
            color: #78350f;
            border-left: 4px solid #f59e0b;
        }

        .alert-info {
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(8, 145, 178, 0.05));
            color: #0c4a6e;
            border-left: 4px solid #06b6d4;
        }

        /* Enhanced Form Controls */
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 0.875rem 1.25rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            background-color: #fafbfc;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--kasir-primary);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background-color: white;
            transform: translateY(-1px);
        }

        .form-label {
            font-weight: 600;
            color: var(--kasir-dark);
            margin-bottom: 0.75rem;
        }

        /* Tables Enhancement */
        .table {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
        }

        .table thead th {
            background: var(--kasir-gradient);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1.25rem;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
            transform: scale(1.01);
        }

        .table tbody td {
            padding: 1rem 1.25rem;
            border-color: rgba(102, 126, 234, 0.1);
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--kasir-primary);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--kasir-dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--kasir-muted);
            font-size: 1.1rem;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }

            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .content-wrapper {
                padding: 1.5rem;
                padding-top: 6rem; /* Space for mobile menu button */
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 1.75rem;
            }
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                top: 1rem;
                left: 1rem;
                width: 3rem;
                height: 3rem;
                padding: 0.75rem;
            }

            .content-wrapper {
                padding: 1rem;
                padding-top: 5rem;
            }

            .card-body {
                padding: 1.5rem;
            }

            .card-header {
                padding: 1.25rem;
            }

            .page-header {
                padding: 1.25rem;
            }

            .page-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .content-wrapper {
                padding: 0.75rem;
                padding-top: 5rem;
            }

            .card-body {
                padding: 1rem;
            }

            .card-header {
                padding: 1rem;
            }

            .page-header {
                padding: 1rem;
            }
        }

        /* Loading States */
        .loading-spinner {
            display: inline-block;
            width: 1.5rem;
            height: 1.5rem;
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 50%;
            border-top-color: var(--kasir-primary);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Smooth Animations */
        * {
            scroll-behavior: smooth;
        }

        /* Focus States for Accessibility */
        .btn:focus,
        .form-control:focus,
        .form-select:focus,
        a:focus {
            outline: 3px solid rgba(102, 126, 234, 0.3);
            outline-offset: 2px;
        }

        /* Badge Enhancements */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.8rem;
        }

        .badge.bg-primary {
            background: var(--kasir-gradient) !important;
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-up {
            animation: slideUp 0.4s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    @stack('styles')
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn" type="button" title="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="app-container">
        @include('kasir.layouts.sidebar')

        <div class="main-content">
            @if(View::exists('kasir.layouts.header'))
                @include('kasir.layouts.header')
            @endif
            
            <div class="content-wrapper">
                <!-- Success/Error Messages -->
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show slide-up" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Berhasil!</strong> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show slide-up" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Error!</strong> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="alert alert-warning alert-dismissible fade show slide-up" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Peringatan!</strong> {{ session('warning') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if(session('info'))
                    <div class="alert alert-info alert-dismissible fade show slide-up" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Info!</strong> {{ session('info') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <main class="fade-in">
                    @yield('content')
                </main>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Enhanced Mobile Menu Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            function toggleSidebar() {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
                
                // Enhanced icon animation
                const icon = mobileMenuBtn.querySelector('i');
                if (sidebar.classList.contains('show')) {
                    icon.className = 'fas fa-times';
                    mobileMenuBtn.style.transform = 'rotate(90deg)';
                } else {
                    icon.className = 'fas fa-bars';
                    mobileMenuBtn.style.transform = 'rotate(0deg)';
                }
            }
            
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', toggleSidebar);
            }
            
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', toggleSidebar);
            }
            
            // Close sidebar when clicking on a link (mobile)
            if (sidebar) {
                const sidebarLinks = sidebar.querySelectorAll('a:not(.dropdown-toggle)');
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 992) {
                            setTimeout(() => {
                                toggleSidebar();
                            }, 100);
                        }
                    });
                });
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 992) {
                    if (sidebar) sidebar.classList.remove('show');
                    if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                    if (mobileMenuBtn) {
                        const icon = mobileMenuBtn.querySelector('i');
                        if (icon) icon.className = 'fas fa-bars';
                        mobileMenuBtn.style.transform = 'rotate(0deg)';
                    }
                }
            });

            // Enhanced keyboard navigation
            document.addEventListener('keydown', function(e) {
                // Close sidebar with Escape key
                if (e.key === 'Escape' && sidebar && sidebar.classList.contains('show')) {
                    toggleSidebar();
                }
                
                // Toggle sidebar with Ctrl/Cmd + M
                if ((e.ctrlKey || e.metaKey) && e.key === 'm') {
                    e.preventDefault();
                    toggleSidebar();
                }
            });
        });

        // Enhanced Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(function(alert, index) {
                setTimeout(function() {
                    if (bootstrap && bootstrap.Alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        if (bsAlert) {
                            alert.style.transform = 'translateX(100%)';
                            alert.style.opacity = '0';
                            setTimeout(() => {
                                bsAlert.close();
                            }, 300);
                        }
                    }
                }, 5000 + (index * 500)); // Stagger the hiding
            });
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Enhanced button interactions
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.addEventListener('mousedown', function() {
                    this.style.transform = 'scale(0.95)';
                });
                
                btn.addEventListener('mouseup', function() {
                    this.style.transform = '';
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                });
            });
        });

        // Add loading state to forms
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
                        submitBtn.disabled = true;
                        
                        // Re-enable after 10 seconds as fallback
                        setTimeout(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 10000);
                    }
                });
            });
        });
    </script>

    @stack('scripts')
</body>
</html>