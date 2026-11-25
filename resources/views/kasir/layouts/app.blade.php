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
            /* Brand Identity (Source of Truth) */
            --kasir-primary: #667eea;
            --kasir-secondary: #764ba2;
            --kasir-accent: #f59e0b;
            --kasir-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

            /* Backgrounds & Surfaces */
            --kasir-bg-dominant: #f7f9fc;
            --kasir-bg-secondary: #ffffff;
            --kasir-surface: #ffffff;
            --kasir-surface-subtle: #f8fafc;
            --kasir-light: #f8fafc;
            --kasir-border: #e2e8f0;
            --kasir-border-strong: #cbd5e1;

            /* Typography */
            --kasir-dark: #1e293b;
            --kasir-text: #1e293b;
            --kasir-muted: #64748b;
            --kasir-text-muted: #64748b;

            /* Semantic Status + Soft Surfaces (WCAG AA Contrast) */
            --kasir-success: #047857;
            --kasir-success-soft: #ecfdf5;
            --kasir-success-border: #a7f3d0;
            --kasir-danger: #b91c1c;
            --kasir-danger-soft: #fef2f2;
            --kasir-danger-border: #fecaca;
            --kasir-warning: #92400e;
            --kasir-warning-soft: #fffbeb;
            --kasir-warning-border: #fde68a;
            --kasir-info: #0369a1;
            --kasir-info-soft: #f0f9ff;
            --kasir-info-border: #bae6fd;
            --kasir-brand-soft: rgba(102, 126, 234, 0.08);
            --kasir-brand-border: rgba(102, 126, 234, 0.24);

            /* Spacing Scale (4/8px) */
            --kasir-space-1: 0.25rem;
            --kasir-space-2: 0.5rem;
            --kasir-space-3: 0.75rem;
            --kasir-space-4: 1rem;
            --kasir-space-5: 1.25rem;
            --kasir-space-6: 1.5rem;
            --kasir-space-8: 2rem;

            /* Shape & Elevation */
            --kasir-radius-card: 14px;
            --kasir-radius-btn: 10px;
            --kasir-radius-badge: 999px;
            --kasir-shadow: 0 2px 10px rgba(15, 23, 42, 0.06), 0 1px 3px rgba(15, 23, 42, 0.04);
            --kasir-shadow-lg: 0 10px 24px -4px rgba(15, 23, 42, 0.12), 0 4px 10px -2px rgba(15, 23, 42, 0.06);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            overflow-x: hidden;
            max-width: 100%;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--kasir-bg-dominant);
            line-height: 1.55;
            color: var(--kasir-text);
        }

        /* Layout Container */
        .app-container {
            display: flex;
            min-height: 100vh;
            position: relative;
            width: 100%;
            overflow-x: hidden;
        }

        .main-content {
            flex: 1;
            margin-left: 0;
            width: 100%;
            max-width: 100%;
            transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1), width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--kasir-bg-dominant);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .content-wrapper {
            flex: 1;
            padding: var(--kasir-space-6);
            max-width: 100%;
            min-width: 0;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.55) !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            z-index: 1054 !important;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .sidebar-overlay.show {
            display: block;
            opacity: 1;
        }

        /* Cards & Shared .kasir-card Component */
        .card,
        .kasir-card {
            border: 1px solid var(--kasir-border);
            border-radius: var(--kasir-radius-card);
            box-shadow: var(--kasir-shadow);
            background-color: var(--kasir-surface);
            transition: box-shadow 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
            overflow: hidden;
            min-width: 0;
        }

        /* Hover lift ONLY for opt-in interactive cards */
        .kasir-card-interactive:hover {
            box-shadow: var(--kasir-shadow-lg);
            border-color: var(--kasir-brand-border);
            transform: translateY(-3px);
        }

        .card-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-bottom: 1px solid var(--kasir-border);
            border-radius: var(--kasir-radius-card) var(--kasir-radius-card) 0 0 !important;
            padding: var(--kasir-space-4) var(--kasir-space-5);
            font-weight: 600;
            color: var(--kasir-text);
        }

        .card-body {
            padding: var(--kasir-space-5);
            min-width: 0;
        }

        /* Shared Section Header */
        .kasir-section-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: var(--kasir-space-3);
            padding: var(--kasir-space-4) var(--kasir-space-5);
            background: var(--kasir-surface);
            border-bottom: 1px solid var(--kasir-border);
        }

        .kasir-section-header--brand {
            border-left: 4px solid var(--kasir-primary);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.03) 100%);
        }

        .kasir-section-header--danger {
            border-left: 4px solid var(--kasir-danger);
            background: linear-gradient(135deg, rgba(185, 28, 28, 0.04) 0%, rgba(254, 242, 242, 0.65) 100%);
        }

        .kasir-section-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--kasir-text);
            margin-bottom: 0.15rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--kasir-space-2);
        }

        .kasir-section-subtitle {
            font-size: 0.84rem;
            color: var(--kasir-text-muted);
            margin-bottom: 0;
        }

        /* Shared KPI Card */
        .kasir-kpi {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .kasir-kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .kasir-kpi-icon--success {
            background-color: var(--kasir-success-soft);
            color: var(--kasir-success);
        }

        .kasir-kpi-icon--primary {
            background-color: var(--kasir-brand-soft);
            color: var(--kasir-primary);
        }

        .kasir-kpi-icon--accent {
            background-color: var(--kasir-warning-soft);
            color: var(--kasir-warning);
        }

        .kasir-kpi-value {
            font-size: 1.45rem;
            font-weight: 700;
            color: var(--kasir-text);
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }

        .kasir-num {
            font-variant-numeric: tabular-nums;
        }

        /* Header Hamburger Button (Integrated inside header, never overlaps breadcrumb) */
        .mobile-menu-btn {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: var(--kasir-gradient);
            border: none;
            border-radius: var(--kasir-radius-btn);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.28);
            color: #ffffff;
            font-size: 1.1rem;
            width: 42px;
            height: 42px;
            min-width: 42px;
            min-height: 42px;
            transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
            cursor: pointer;
        }

        /* Desktop specific: Sembunyikan overlay & buat konten menyusut dinamis */
        @media (min-width: 992px) {
            .sidebar-overlay {
                display: none !important;
            }

            .main-content.sidebar-open {
                margin-left: 280px !important;
                width: calc(100% - 280px) !important;
                max-width: calc(100% - 280px) !important;
            }
        }

        .mobile-menu-btn:hover {
            filter: brightness(1.06);
            box-shadow: 0 5px 14px rgba(102, 126, 234, 0.36);
        }

        .mobile-menu-btn:active {
            transform: scale(0.96);
        }

        /* Buttons */
        .btn {
            border-radius: var(--kasir-radius-btn);
            font-weight: 600;
            padding: 0.6rem 1.15rem;
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: all 0.18s ease;
            border: 1px solid transparent;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-sm {
            min-height: 38px;
            padding: 0.4rem 0.85rem !important;
            font-size: 0.82rem !important;
            border-radius: 8px !important;
        }

        .btn-xs {
            min-height: 32px;
            padding: 0.25rem 0.65rem;
            font-size: 0.78rem;
            border-radius: 7px;
        }

        .btn-lg {
            min-height: 46px;
            padding: 0.75rem 1.5rem !important;
            font-size: 0.98rem !important;
        }

        .btn-primary,
        .btn-custom-primary {
            background: var(--kasir-gradient);
            color: #ffffff !important;
            border-color: transparent;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.28);
        }

        .btn-primary:hover,
        .btn-primary:focus,
        .btn-custom-primary:hover,
        .btn-custom-primary:focus {
            filter: brightness(1.05);
            box-shadow: 0 5px 14px rgba(102, 126, 234, 0.36);
            color: #ffffff !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #047857);
            color: #ffffff;
            box-shadow: 0 3px 10px rgba(16, 185, 129, 0.25);
        }

        .btn-success:hover {
            filter: brightness(1.05);
            color: #ffffff;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            color: #ffffff;
            box-shadow: 0 3px 10px rgba(239, 68, 68, 0.25);
        }

        .btn-danger:hover {
            filter: brightness(1.05);
            color: #ffffff;
        }

        /* Kelompok tombol aksi di dalam tabel */
        td .btn + .btn,
        td a.btn + a.btn,
        td a.btn + .btn {
            margin-left: 0.35rem;
        }

        .btn-group .btn + .btn {
            margin-left: -1px;
        }

        /* Alerts */
        .alert {
            border-radius: 12px;
            border: 1px solid transparent;
            padding: 0.95rem 1.25rem;
            box-shadow: var(--kasir-shadow);
        }

        .alert-success {
            background: var(--kasir-success-soft);
            color: var(--kasir-success);
            border-color: var(--kasir-success-border);
            border-left: 4px solid var(--kasir-success);
        }

        .alert-danger {
            background: var(--kasir-danger-soft);
            color: var(--kasir-danger);
            border-color: var(--kasir-danger-border);
            border-left: 4px solid var(--kasir-danger);
        }

        .alert-warning {
            background: var(--kasir-warning-soft);
            color: var(--kasir-warning);
            border-color: var(--kasir-warning-border);
            border-left: 4px solid var(--kasir-warning);
        }

        .alert-info {
            background: var(--kasir-info-soft);
            color: var(--kasir-info);
            border-color: var(--kasir-info-border);
            border-left: 4px solid var(--kasir-info);
        }

        /* Form Controls */
        .form-control,
        .form-select {
            border-radius: var(--kasir-radius-btn);
            border: 1.5px solid var(--kasir-border);
            padding: 0.65rem 1rem;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
            font-size: 0.92rem;
            background-color: var(--kasir-surface);
            color: var(--kasir-text);
        }

        .form-control-sm,
        .form-select-sm {
            padding: 0.4rem 0.75rem;
            font-size: 0.85rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--kasir-primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
            background-color: var(--kasir-surface);
        }

        .form-label {
            font-weight: 600;
            color: var(--kasir-text);
            margin-bottom: 0.5rem;
        }

        /* Tables & Shared .kasir-table (Keep Brand Gradient Header) */
        .table-responsive {
            border-radius: 12px;
            box-shadow: var(--kasir-shadow);
            -webkit-overflow-scrolling: touch;
        }

        .card .table-responsive,
        .kasir-card .table-responsive,
        .modal .table-responsive {
            box-shadow: none;
            border-radius: 0;
        }

        .table,
        .kasir-table {
            margin-bottom: 0;
            color: var(--kasir-text);
        }

        .table thead th,
        .kasir-table thead th {
            background: var(--kasir-gradient);
            color: #ffffff;
            border: none;
            font-weight: 600;
            font-size: 0.82rem;
            padding: 0.85rem 1rem;
            white-space: nowrap;
        }

        .kasir-table tbody tr:nth-child(even) {
            background-color: rgba(248, 250, 252, 0.75);
        }

        .table tbody tr,
        .kasir-table tbody tr {
            transition: background-color 0.15s ease;
        }

        .table tbody tr:hover,
        .kasir-table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.06);
        }

        .table tbody td,
        .kasir-table tbody td {
            padding: 0.85rem 1rem;
            border-color: var(--kasir-border);
            vertical-align: middle;
        }

        /* Shared Unified Badge System (.kasir-badge-*) */
        .kasir-badge-success,
        .kasir-badge-warning,
        .kasir-badge-danger,
        .kasir-badge-info,
        .kasir-badge-muted {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.32rem 0.7rem;
            border-radius: var(--kasir-radius-badge);
            font-weight: 600;
            font-size: 0.76rem;
            line-height: 1.25;
            white-space: nowrap;
        }

        .kasir-badge-success {
            background-color: var(--kasir-success-soft);
            color: var(--kasir-success);
            border: 1px solid var(--kasir-success-border);
        }

        .kasir-badge-warning {
            background-color: var(--kasir-warning-soft);
            color: var(--kasir-warning);
            border: 1px solid var(--kasir-warning-border);
        }

        .kasir-badge-danger {
            background-color: var(--kasir-danger-soft);
            color: var(--kasir-danger);
            border: 1px solid var(--kasir-danger-border);
        }

        .kasir-badge-info {
            background-color: var(--kasir-info-soft);
            color: var(--kasir-info);
            border: 1px solid var(--kasir-info-border);
        }

        .kasir-badge-muted {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid var(--kasir-border);
        }

        /* Page Header */
        .page-header {
            background: var(--kasir-surface);
            border-radius: var(--kasir-radius-card);
            padding: var(--kasir-space-6);
            margin-bottom: var(--kasir-space-6);
            box-shadow: var(--kasir-shadow);
            border: 1px solid var(--kasir-border);
            border-left: 4px solid var(--kasir-primary);
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--kasir-text);
            margin-bottom: 0.35rem;
        }

        .page-subtitle {
            color: var(--kasir-text-muted);
            font-size: 0.98rem;
        }

        /* ===================== RESPONSIVE ===================== */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }

            .content-wrapper {
                padding: var(--kasir-space-4);
            }

            .page-header {
                padding: var(--kasir-space-5);
            }

            .page-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: var(--kasir-space-3);
            }

            .card-body {
                padding: var(--kasir-space-4);
            }

            .card-header,
            .kasir-section-header {
                padding: var(--kasir-space-3) var(--kasir-space-4);
            }

            .page-header {
                padding: var(--kasir-space-4);
            }

            .page-title {
                font-size: 1.35rem;
            }

            .table thead th,
            .kasir-table thead th {
                padding: 0.65rem 0.75rem;
                font-size: 0.78rem;
            }

            .table tbody td,
            .kasir-table tbody td {
                padding: 0.65rem 0.75rem;
                font-size: 0.84rem;
            }

            .table .btn-sm {
                padding: 0.35rem 0.65rem !important;
                font-size: 0.78rem !important;
            }

            .table td.text-center .btn,
            .table td.text-center a.btn {
                margin-bottom: 0.25rem;
            }
        }

        @media (max-width: 576px) {
            .content-wrapper {
                padding: 0.75rem;
            }

            .card-body {
                padding: 1rem;
            }

            .card-header,
            .kasir-section-header {
                padding: 0.85rem 1rem;
            }

            .page-header {
                padding: 1rem;
            }

            .table td.text-center {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 0.35rem;
            }

            .table td.text-center .btn + .btn {
                margin-left: 0;
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
            to {
                transform: rotate(360deg);
            }
        }

        /* Focus States */
        .btn:focus-visible,
        .form-control:focus,
        .form-select:focus,
        a:focus-visible {
            outline: 3px solid rgba(102, 126, 234, 0.3);
            outline-offset: 2px;
        }

        /* Badges (Backward-compatible for existing pages) */
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: var(--kasir-radius-badge);
            font-weight: 500;
            font-size: 0.78rem;
        }

        .badge.bg-primary:not([class*="badge-soft-"]):not([class*="bg-opacity-"]) {
            background: var(--kasir-gradient) !important;
        }

        /* Soft Badges & High-Contrast Utility Classes */
        .badge-soft-danger,
        .badge.bg-danger.bg-opacity-10,
        .badge.bg-danger.bg-opacity-25 {
            background-color: var(--kasir-danger-soft) !important;
            color: var(--kasir-danger) !important;
            border: 1px solid var(--kasir-danger-border) !important;
        }

        .badge-soft-success,
        .badge.bg-success.bg-opacity-10,
        .badge.bg-success.bg-opacity-25 {
            background-color: var(--kasir-success-soft) !important;
            color: var(--kasir-success) !important;
            border: 1px solid var(--kasir-success-border) !important;
        }

        .badge-soft-info,
        .badge.bg-info.bg-opacity-10,
        .badge.bg-info.bg-opacity-25 {
            background-color: var(--kasir-info-soft) !important;
            color: var(--kasir-info) !important;
            border: 1px solid var(--kasir-info-border) !important;
        }

        .badge-soft-warning,
        .badge.bg-warning.bg-opacity-10,
        .badge.bg-warning.bg-opacity-25 {
            background-color: var(--kasir-warning-soft) !important;
            color: var(--kasir-warning) !important;
            border: 1px solid var(--kasir-warning-border) !important;
        }

        .badge-soft-primary,
        .badge.bg-primary.bg-opacity-10,
        .badge.bg-primary.bg-opacity-25 {
            background-color: #eff6ff !important;
            color: #1d4ed8 !important;
            border: 1px solid #bfdbfe !important;
        }

        .badge-soft-secondary,
        .badge.bg-secondary.bg-opacity-10,
        .badge.bg-secondary.bg-opacity-25 {
            background-color: #f1f5f9 !important;
            color: #475569 !important;
            border: 1px solid #cbd5e1 !important;
        }

        /* Solid Badges High-Contrast Safeguards */
        .badge.bg-danger:not([class*="badge-soft-"]):not([class*="bg-opacity-"]) { color: #ffffff !important; }
        .badge.bg-success:not([class*="badge-soft-"]):not([class*="bg-opacity-"]) { color: #ffffff !important; }
        .badge.bg-primary:not([class*="badge-soft-"]):not([class*="bg-opacity-"]) { color: #ffffff !important; }
        .badge.bg-dark:not([class*="badge-soft-"]):not([class*="bg-opacity-"]) { color: #ffffff !important; }
        .badge.bg-info:not([class*="badge-soft-"]):not([class*="bg-opacity-"]) { color: #1e293b !important; }
        .badge.bg-warning:not([class*="badge-soft-"]):not([class*="bg-opacity-"]) { color: #1e293b !important; }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.25s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-up {
            animation: slideUp 0.2s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(10px);
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
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="app-container">
        @include('kasir.layouts.sidebar')

        <div class="main-content">
            @if(View::exists('kasir.layouts.header'))
            @include('kasir.layouts.header')
            @endif

            <div class="content-wrapper">
                <!-- Validation Errors -->
                @if (isset($errors) && $errors->any())
                <div class="alert alert-danger alert-dismissible fade show alert-permanent slide-up" role="alert">
                    <div class="fw-bold mb-1"><i class="fas fa-exclamation-triangle me-2"></i>Perhatian:</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif

                <!-- Success/Error Flash Messages (Auto-hide 5s) -->
                @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show slide-up kasir-flash-alert" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Berhasil!</strong> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif

                @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show slide-up kasir-flash-alert" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Error!</strong> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif

                @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show slide-up kasir-flash-alert" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Peringatan!</strong> {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                @endif

                @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show slide-up kasir-flash-alert" role="alert">
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Unified Sidebar Toggle & Responsive Sync
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarClose = document.getElementById('sidebarClose');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const mainContent = document.querySelector('.main-content');

            function syncSidebar(isOpen) {
                if (!sidebar) return;
                sidebar.classList.toggle('show', isOpen);
                const isDesktop = window.innerWidth >= 992;

                if (mainContent) {
                    mainContent.classList.toggle('sidebar-open', isOpen && isDesktop);
                }
                if (sidebarOverlay) {
                    sidebarOverlay.classList.toggle('show', isOpen && !isDesktop);
                }
                if (mobileMenuBtn) {
                    const icon = mobileMenuBtn.querySelector('i');
                    if (icon) icon.className = isOpen ? 'fas fa-times' : 'fas fa-bars';
                    mobileMenuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                }
            }

            function toggleSidebar() {
                if (!sidebar) return;
                syncSidebar(!sidebar.classList.contains('show'));
            }

            if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', toggleSidebar);
            if (sidebarClose) sidebarClose.addEventListener('click', () => syncSidebar(false));
            if (sidebarOverlay) sidebarOverlay.addEventListener('click', () => syncSidebar(false));

            if (sidebar) {
                const sidebarLinks = sidebar.querySelectorAll('a:not(.dropdown-toggle)');
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 992) {
                            setTimeout(() => syncSidebar(false), 100);
                        }
                    });
                });
            }

            window.addEventListener('resize', function() {
                if (sidebar) syncSidebar(sidebar.classList.contains('show'));
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar && sidebar.classList.contains('show')) {
                    syncSidebar(false);
                }
                if ((e.ctrlKey || e.metaKey) && e.key === 'm') {
                    e.preventDefault();
                    toggleSidebar();
                }
            });
        });

        // Auto-hide ONLY session flash alerts (.kasir-flash-alert:not(.alert-permanent))
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert.kasir-flash-alert:not(.alert-permanent)');
            alerts.forEach(function(alert, index) {
                setTimeout(function() {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                        const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                        if (bsAlert) {
                            alert.style.transition = 'transform 0.2s ease, opacity 0.2s ease';
                            alert.style.transform = 'translateX(30px)';
                            alert.style.opacity = '0';
                            setTimeout(() => bsAlert.close(), 200);
                        }
                    }
                }, 5000 + (index * 500));
            });
        });

        // Smooth scroll for anchor links (safely ignore empty href="#")
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (!href || href === '#') return;
                try {
                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                } catch (_) {}
            });
        });

        // Add loading state to forms (skip if prevented by custom JS validation)
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (e.defaultPrevented) return;
                    const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
                        submitBtn.disabled = true;

                        setTimeout(() => {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }, 10000);
                    }
                });
            });
        });

        // Auto Keep-Alive: Menjaga koneksi kasir aktif selama tab browser terbuka (mencegah timeout sesi)
        setInterval(function() {
            fetch('{{ url("/up") }}', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(res) {
                if (!res.ok) return null;
                return res.text();
            })
            .catch(function() {});
        }, 10 * 60 * 1000);
    </script>

    @stack('scripts')
</body>

</html>