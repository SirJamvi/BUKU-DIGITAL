<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} - Sistem Bisnis Komprehensif</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f7f6;
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://images.unsplash.com/photo-1556742502-ec7c0e9f34b1?q=80&w=1974&auto=format&fit=crop') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 120px 0;
            text-align: center;
        }
        .hero-section h1 {
            font-weight: 700;
            font-size: 3.5rem;
        }
        .hero-section p {
            font-size: 1.25rem;
            max-width: 700px;
            margin: 0 auto 30px auto;
        }
        .feature-card {
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-10px);
        }
        .feature-icon {
            font-size: 2.5rem;
            color: #0d6efd;
        }
        footer {
            background-color: #343a40;
            color: white;
        }
    </style>
</head>
<body class="antialiased">

    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-cogs text-primary"></i>
                {{ config('app.name', 'Business Dashboard') }}
            </a>
            <div>
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/redirect-dashboard') }}" class="btn btn-outline-primary">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary">Login</a>
                    @endauth
                @endif
            </div>
        </div>
    </nav>

    <header class="hero-section">
        <div class="container">
            <h1>Transformasi Bisnis Anda</h1>
            <p>Satu platform untuk mengelola penjualan, inventaris, keuangan, dan analisis bisnis secara komprehensif dan efisien.</p>
            <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Mulai Sekarang</a>
        </div>
    </header>

    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Fitur Unggulan Kami</h2>
                <p class="lead text-muted">Semua yang Anda butuhkan untuk mengembangkan bisnis Anda.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 feature-card text-center p-4">
                        <div class="feature-icon mb-3 mx-auto">
                           <i class="fas fa-cash-register"></i>
                        </div>
                        <h5 class="card-title">Manajemen Penjualan (POS)</h5>
                        <p class="card-text">Sistem Point of Sale yang cepat dan intuitif untuk memperlancar setiap transaksi.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 feature-card text-center p-4">
                        <div class="feature-icon mb-3 mx-auto">
                            <i class="fas fa-warehouse"></i>
                        </div>
                        <h5 class="card-title">Kontrol Inventaris</h5>
                        <p class="card-text">Lacak stok secara real-time, dapatkan notifikasi stok menipis, dan kelola opname.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 feature-card text-center p-4">
                        <div class="feature-icon mb-3 mx-auto">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h5 class="card-title">Analisis Keuangan</h5>
                        <p class="card-text">Pantau arus kas, hitung ROI, dan dapatkan laporan keuangan yang akurat untuk keputusan strategis.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; {{ date('Y') }} {{ config('app.name', 'Business Dashboard') }}. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>