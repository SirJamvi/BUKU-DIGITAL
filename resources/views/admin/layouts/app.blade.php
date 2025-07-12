<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin Dashboard') - {{ config('app.name', 'Laravel') }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <style>
        :root {
            --deep-navy: #1a2332;        /* Deep Navy */
            --warm-white: #fafafa;        /* Warm White */
            --luxury-gold: #d4af37;       /* Luxury Gold */
            --gold-dark: #b8951f;         /* Darker Gold */
            --gold-light: #f9e076;        /* Lighter Gold */
            --sidebar-width: 280px;
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
        }

        .admin-wrapper {
            min-height: 100vh;
            display: flex;
            position: relative;
            width: 100%;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--deep-navy) 0%, #0d121b 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: 1050;
            box-shadow: 2px 0 15px rgba(0,0,0,0.2);
            flex-shrink: 0;
            border-right: 1px solid rgba(212, 175, 55, 0.2);
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            text-align: center;
        }

        .sidebar-header h4 {
            font-weight: 600;
            font-size: 1.3rem;
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
            display: block;
            padding: 0.9rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
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
        }

        .sidebar-item:hover i {
            transform: scale(1.1);
        }

        .sidebar-divider {
            height: 1px;
            background: rgba(212, 175, 55, 0.2);
            margin: 1rem 0;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 0;
            transition: margin-left 0.3s ease;
            width: 100%;
            min-width: 0;
            background-color: var(--warm-white);
        }

        .main-content.sidebar-open {
            margin-left: var(--sidebar-width);
        }

        /* Header Styles */
        .main-header {
            background: var(--deep-navy);
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.15);
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            position: relative;
            width: 100%;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar-toggle {
            background: rgba(212, 175, 55, 0.15);
            border: none;
            color: var(--luxury-gold);
            padding: 0.6rem 0.9rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
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
        }

        .breadcrumb-item a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: color 0.3s;
        }

        .breadcrumb-item a:hover {
            color: var(--luxury-gold);
        }

        /* Dropdown Styles */
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

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        /* Content Area */
        .content-area {
            padding: 1.5rem;
            min-height: calc(100vh - 80px);
            width: 100%;
            max-width: 100%;
            background-color: var(--warm-white);
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
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Responsive Design */
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
        }

        @media (max-width: 991.98px) {
            .breadcrumb {
                display: none;
            }
            
            .content-area {
                padding: 1rem;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .main-header {
                padding: 1rem;
            }
            
            .content-area {
                padding: 0.75rem;
            }
        }

        /* Additional fixes for form and table layouts */
        .row {
            margin-right: 0;
            margin-left: 0;
        }

        .col, .col-1, .col-2, .col-3, .col-4, .col-5, .col-6, 
        .col-7, .col-8, .col-9, .col-10, .col-11, .col-12,
        .col-sm, .col-sm-1, .col-sm-2, .col-sm-3, .col-sm-4, .col-sm-5, .col-sm-6,
        .col-sm-7, .col-sm-8, .col-sm-9, .col-sm-10, .col-sm-11, .col-sm-12,
        .col-md, .col-md-1, .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6,
        .col-md-7, .col-md-8, .col-md-9, .col-md-10, .col-md-11, .col-md-12,
        .col-lg, .col-lg-1, .col-lg-2, .col-lg-3, .col-lg-4, .col-lg-5, .col-lg-6,
        .col-lg-7, .col-lg-8, .col-lg-9, .col-lg-10, .col-lg-11, .col-lg-12,
        .col-xl, .col-xl-1, .col-xl-2, .col-xl-3, .col-xl-4, .col-xl-5, .col-xl-6,
        .col-xl-7, .col-xl-8, .col-xl-9, .col-xl-10, .col-xl-11, .col-xl-12 {
            padding-right: 0.75rem;
            padding-left: 0.75rem;
        }

        /* Ensure tables are responsive */
        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }

        /* Fix for action buttons */
        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        /* Ensure cards have proper spacing */
        .card {
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: none;
            border-radius: 10px;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 1.25rem;
            font-weight: 600;
            color: var(--deep-navy);
        }

        .card-body {
            padding: 1.25rem;
        }
        
        /* Gold accent elements */
        .btn-primary {
            background: var(--gold-gradient);
            border: none;
            color: var(--deep-navy);
            font-weight: 600;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--gold-dark) 0%, #e8d068 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(212, 175, 55, 0.3);
            color: var(--deep-navy);
        }
        
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
        // Sidebar Toggle Functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');

        function toggleSidebar() {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
            
            if (window.innerWidth >= 992) {
                mainContent.classList.toggle('sidebar-open');
            }
        }

        function closeSidebar() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            
            if (window.innerWidth >= 992) {
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

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992) {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                mainContent.classList.add('sidebar-open');
            } else {
                mainContent.classList.remove('sidebar-open');
            }
        });

        // Initialize sidebar state
        if (window.innerWidth >= 992) {
            mainContent.classList.add('sidebar-open');
        }

        // Fix for responsive tables
        document.addEventListener('DOMContentLoaded', function() {
            const tables = document.querySelectorAll('table:not(.table-responsive table)');
            tables.forEach(table => {
                if (!table.parentElement.classList.contains('table-responsive')) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'table-responsive';
                    table.parentNode.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }
            });
        });
    </script>

    <script src="{{ asset('js/admin/app.js') }}"></script>
    
    @stack('scripts')
</body>
</html>