<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin Dashboard') - {{ config('app.name', 'Laravel') }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <style>
        :root {
            --deep-navy: #1a2332;
            --warm-white: #fafafa;
            --luxury-gold: #d4af37;
            --gold-dark: #b8951f;
            --gold-light: #f9e076;
            --sidebar-width: 280px;
            --sidebar-width-mobile: 260px;
            --header-height: 70px;
            --gold-gradient: linear-gradient(135deg, var(--luxury-gold) 0%, var(--gold-light) 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--warm-white);
            color: var(--deep-navy);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .admin-wrapper {
            min-height: 100vh;
            display: flex;
            position: relative;
            width: 100%;
        }

        /* Sidebar Styles - Enhanced Responsive */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--deep-navy) 0%, #0d121b 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1050;
            box-shadow: 2px 0 15px rgba(0,0,0,0.2);
            flex-shrink: 0;
            border-right: 1px solid rgba(212, 175, 55, 0.2);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .sidebar-header {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            text-align: center;
            position: sticky;
            top: 0;
            background: var(--deep-navy);
            z-index: 10;
        }

        .sidebar-header h4 {
            font-weight: 600;
            font-size: clamp(1.1rem, 2.5vw, 1.3rem);
            margin-bottom: 0;
            color: var(--luxury-gold);
            font-family: 'Playfair Display', serif;
        }

        .sidebar-menu {
            padding: 1rem 0;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
        }

        .sidebar-menu::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-menu::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background: var(--luxury-gold);
            border-radius: 2px;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            padding: 0.85rem 1.25rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
            font-size: clamp(0.875rem, 2vw, 0.95rem);
        }

        .sidebar-item:hover {
            background: rgba(212, 175, 55, 0.1);
            color: var(--gold-light);
            transform: translateX(5px);
        }

        .sidebar-item.active {
            background: rgba(212, 175, 55, 0.15);
            color: var(--gold-light);
            border-left-color: var(--luxury-gold);
        }

        .sidebar-item i {
            width: 20px;
            margin-right: 0.8rem;
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }

        .sidebar-item:hover i {
            transform: scale(1.1);
        }

        .sidebar-divider {
            height: 1px;
            background: rgba(212, 175, 55, 0.2);
            margin: 1rem 0;
        }

        /* Main Content - Enhanced Responsive */
        .main-content {
            flex: 1;
            margin-left: 0;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            min-width: 0;
            background-color: var(--warm-white);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main-content.sidebar-open {
            margin-left: var(--sidebar-width);
        }

        /* Header Styles - Enhanced Responsive */
        .main-header {
            background: var(--deep-navy);
            padding: 1rem 1.25rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.15);
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            position: sticky;
            top: 0;
            width: 100%;
            z-index: 1000;
            min-height: var(--header-height);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
            min-width: 0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar-toggle {
            background: rgba(212, 175, 55, 0.15);
            border: none;
            color: var(--luxury-gold);
            padding: 0.6rem 0.9rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .sidebar-toggle:hover {
            background: var(--luxury-gold);
            color: var(--deep-navy);
            transform: scale(1.05);
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            flex-wrap: nowrap;
            overflow: hidden;
        }

        .breadcrumb-item {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .breadcrumb-item a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: color 0.3s;
        }

        .breadcrumb-item a:hover {
            color: var(--luxury-gold);
        }

        /* Dropdown Styles - Enhanced */
        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-radius: 8px;
            background-color: var(--deep-navy);
            border: 1px solid rgba(212, 175, 55, 0.2);
            max-width: 90vw;
        }

        .dropdown-item {
            padding: 0.7rem 1.2rem;
            transition: all 0.3s ease;
            color: rgba(255,255,255,0.8);
            font-size: clamp(0.85rem, 2vw, 0.95rem);
        }

        .dropdown-item:hover {
            background: rgba(212, 175, 55, 0.1);
            color: var(--gold-light);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        /* Content Area - Enhanced Responsive */
        .content-area {
            padding: 1.5rem;
            min-height: calc(100vh - var(--header-height));
            width: 100%;
            max-width: 100%;
            background-color: var(--warm-white);
            flex: 1;
        }

        .content-area .container,
        .content-area .container-fluid {
            width: 100%;
            max-width: 100%;
            padding-left: 0;
            padding-right: 0;
        }

        .content-area .card,
        .content-area .table-responsive {
            width: 100%;
            max-width: 100%;
            border: 1px solid rgba(0,0,0,0.05);
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            backdrop-filter: blur(2px);
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Cards - Enhanced Responsive */
        .card {
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: none;
            border-radius: 10px;
            overflow: hidden;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 1.25rem;
            font-weight: 600;
            color: var(--deep-navy);
            font-size: clamp(0.95rem, 2.5vw, 1.1rem);
        }

        .card-body {
            padding: clamp(1rem, 3vw, 1.25rem);
        }

        /* Tables - Enhanced Responsive */
        .table-responsive {
            overflow-x: auto;
            width: 100%;
            -webkit-overflow-scrolling: touch;
            border-radius: 8px;
        }

        .table {
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            margin-bottom: 0;
        }

        .table thead th {
            white-space: nowrap;
            padding: 0.75rem;
        }

        .table tbody td {
            padding: 0.75rem;
            vertical-align: middle;
        }

        /* Buttons - Enhanced Responsive */
        .btn {
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--gold-gradient);
            border: none;
            color: var(--deep-navy);
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--gold-dark) 0%, #e8d068 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(212, 175, 55, 0.3);
            color: var(--deep-navy);
        }

        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        /* Forms - Enhanced Responsive */
        .form-control,
        .form-select {
            font-size: clamp(0.85rem, 2vw, 0.95rem);
            padding: 0.6rem 0.9rem;
            border-radius: 6px;
        }

        .form-label {
            font-size: clamp(0.85rem, 2vw, 0.95rem);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        /* Utility Classes */
        .text-gold {
            color: var(--luxury-gold);
        }
        
        .gold-divider {
            height: 3px;
            background: var(--gold-gradient);
            width: 50px;
            margin: 10px 0;
            border-radius: 2px;
        }

        /* User Avatar - Responsive */
        .user-avatar img,
        .user-avatar > div {
            width: clamp(28px, 5vw, 32px);
            height: clamp(28px, 5vw, 32px);
        }

        /* Responsive Design - Mobile First */
        @media (max-width: 575.98px) {
            .sidebar {
                width: var(--sidebar-width-mobile);
            }

            .main-header {
                padding: 0.75rem 1rem;
            }

            .header-left {
                gap: 0.5rem;
            }

            .header-right {
                gap: 0.25rem;
            }

            .header-right .d-lg-block {
                display: none !important;
            }

            .content-area {
                padding: 1rem;
            }

            .card-body {
                padding: 0.875rem;
            }

            .table {
                font-size: 0.75rem;
            }

            .table thead th,
            .table tbody td {
                padding: 0.5rem 0.35rem;
            }

            .btn {
                padding: 0.4rem 0.75rem;
                font-size: 0.8rem;
            }

            .btn-group {
                flex-direction: column;
                width: 100%;
            }

            .btn-group .btn {
                width: 100%;
            }

            .dropdown-menu {
                font-size: 0.85rem;
            }

            .sidebar-item {
                padding: 0.75rem 1rem;
            }

            /* Hide breadcrumb text on very small screens */
            .breadcrumb-item + .breadcrumb-item::before {
                padding-right: 0.3rem;
                padding-left: 0.3rem;
            }
        }

        /* Tablet Portrait */
        @media (min-width: 576px) and (max-width: 767.98px) {
            .content-area {
                padding: 1.25rem;
            }

            .breadcrumb {
                display: none;
            }

            .table {
                font-size: 0.85rem;
            }
        }

        /* Tablet Landscape */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .content-area {
                padding: 1.5rem;
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }

        /* Desktop */
        @media (min-width: 992px) {
            .sidebar {
                position: fixed;
                transform: translateX(0);
                z-index: 1000;
            }
            
            .main-content {
                margin-left: var(--sidebar-width);
            }
            
            .sidebar-toggle {
                display: none;
            }

            .sidebar-overlay {
                display: none !important;
            }
        }

        /* Large Desktop */
        @media (min-width: 1400px) {
            .content-area {
                padding: 2rem;
            }

            .card-body {
                padding: 1.5rem;
            }
        }

        /* Print Styles */
        @media print {
            .sidebar,
            .main-header,
            .sidebar-overlay {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .content-area {
                padding: 0;
            }

            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }

        /* Additional utility for overflow */
        .row {
            margin-right: 0;
            margin-left: 0;
        }

        [class*="col-"] {
            padding-right: 0.75rem;
            padding-left: 0.75rem;
        }

        /* Touch device optimizations */
        @media (hover: none) and (pointer: coarse) {
            .sidebar-item,
            .dropdown-item,
            .btn {
                min-height: 44px;
                display: flex;
                align-items: center;
            }

            .sidebar-item:active {
                background: rgba(212, 175, 55, 0.2);
            }
        }

        /* Landscape orientation on mobile */
        @media (max-width: 991.98px) and (orientation: landscape) {
            .sidebar {
                width: var(--sidebar-width-mobile);
            }

            .content-area {
                padding: 1rem;
            }

            .main-header {
                padding: 0.5rem 1rem;
                min-height: 60px;
            }
        }

        /* High DPI screens */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .sidebar,
            .main-header,
            .card {
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
        }
    </style>

    <link rel="stylesheet" href="{{ asset('css/admin/app.css') }}">
    
    @stack('styles')
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Sidebar -->
        @include('admin.layouts.sidebar')

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            @include('admin.layouts.header')

            <main class="content-area">
                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Enhanced Sidebar Toggle with better mobile support
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');

        // Check if we're on mobile
        function isMobile() {
            return window.innerWidth < 992;
        }

        function toggleSidebar() {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
            
            // Prevent body scroll when sidebar is open on mobile
            if (isMobile()) {
                document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
            }
            
            if (!isMobile()) {
                mainContent.classList.toggle('sidebar-open');
            }
        }

        function closeSidebar() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
            
            if (!isMobile()) {
                mainContent.classList.remove('sidebar-open');
            }
        }

        // Event Listeners
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }

        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                closeSidebar();
            }
        });

        // Handle window resize with debounce
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (!isMobile()) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                    mainContent.classList.add('sidebar-open');
                    document.body.style.overflow = '';
                } else {
                    mainContent.classList.remove('sidebar-open');
                    if (!sidebar.classList.contains('show')) {
                        document.body.style.overflow = '';
                    }
                }
            }, 250);
        });

        // Initialize sidebar state
        if (!isMobile()) {
            mainContent.classList.add('sidebar-open');
        }

        // Enhanced table responsiveness
        document.addEventListener('DOMContentLoaded', function() {
            // Wrap tables in responsive containers
            const tables = document.querySelectorAll('table:not(.table-responsive table)');
            tables.forEach(table => {
                if (!table.parentElement.classList.contains('table-responsive')) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'table-responsive';
                    table.parentNode.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }
            });

            // Add touch scroll hint for tables on mobile
            if (isMobile()) {
                const tableWrappers = document.querySelectorAll('.table-responsive');
                tableWrappers.forEach(wrapper => {
                    if (wrapper.scrollWidth > wrapper.clientWidth) {
                        wrapper.classList.add('has-scroll');
                        
                        // Add scroll indicator
                        const scrollIndicator = document.createElement('div');
                        scrollIndicator.className = 'scroll-indicator';
                        scrollIndicator.innerHTML = '<i class="fas fa-arrows-alt-h"></i> Geser untuk melihat lebih banyak';
                        scrollIndicator.style.cssText = 'text-align: center; padding: 0.5rem; font-size: 0.75rem; color: var(--luxury-gold); background: rgba(212, 175, 55, 0.1); border-radius: 0 0 8px 8px;';
                        
                        wrapper.parentNode.insertBefore(scrollIndicator, wrapper.nextSibling);
                        
                        // Remove indicator after first scroll
                        wrapper.addEventListener('scroll', function() {
                            if (scrollIndicator) {
                                scrollIndicator.remove();
                            }
                        }, { once: true });
                    }
                });
            }

            // Optimize dropdown positioning on mobile
            const dropdowns = document.querySelectorAll('.dropdown-menu');
            dropdowns.forEach(dropdown => {
                dropdown.addEventListener('show.bs.dropdown', function() {
                    if (isMobile()) {
                        this.style.maxHeight = '80vh';
                        this.style.overflowY = 'auto';
                    }
                });
            });

            // Add loading state for forms
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.disabled = true;
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
                        
                        // Re-enable after 5 seconds as fallback
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }, 5000);
                    }
                });
            });
        });

        // Optimize images loading
        if ('loading' in HTMLImageElement.prototype) {
            const images = document.querySelectorAll('img[loading="lazy"]');
            images.forEach(img => {
                img.loading = 'lazy';
            });
        }

        // Service Worker registration for PWA support (optional)
        if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
            window.addEventListener('load', function() {
                // navigator.serviceWorker.register('/sw.js'); // Uncomment if you have SW
            });
        }
    </script>

    <script src="{{ asset('js/admin/app.js') }}"></script>
    
    @stack('scripts')
</body>
</html>