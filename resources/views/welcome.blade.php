<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    </title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #1a2332;        /* Deep Navy */
            --secondary-color: #fafafa;       /* Warm White */
            --accent-color: #d4af37;          /* Luxury Gold */
            --accent-dark: #b8951f;           /* Darker Gold for hover */
            --text-dark: #1a2332;             /* Deep Navy for text */
            --text-light: #7f8c8d;            /* Light Gray */
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 8px 25px rgba(0, 0, 0, 0.15);
            --gold-gradient: linear-gradient(135deg, #d4af37 0%, #f9e076 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--secondary-color);
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* Navigation Styles */
        .navbar {
            background-color: var(--primary-color) !important;
            box-shadow: var(--shadow);
            border-bottom: 2px solid var(--accent-color);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--accent-color) !important;
            transition: color 0.3s ease;
        }

        .navbar-brand:hover {
            color: #f9e076 !important;
        }

        .navbar-brand i {
            color: var(--accent-color);
            margin-right: 0.5rem;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover i {
            transform: rotate(15deg);
        }

        /* Button Styles */
        .btn-primary {
            background: var(--gold-gradient);
            border: none;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            transition: all 0.3s ease;
            color: var(--primary-color);
            box-shadow: 0 4px 10px rgba(212, 175, 55, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #c5a02f 0%, #e8d068 100%);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(212, 175, 55, 0.4);
            color: var(--primary-color);
        }

        .btn-outline-primary {
            color: var(--accent-color);
            border: 2px solid var(--accent-color);
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            transition: all 0.3s ease;
            background: transparent;
        }

        .btn-outline-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            color: var(--primary-color);
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d121b 100%);
            padding: 120px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 10% 20%, rgba(212, 175, 55, 0.1) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(212, 175, 55, 0.1) 0%, transparent 20%);
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-section h1 {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .hero-section h1 span {
            color: var(--accent-color);
            display: block;
        }

        .hero-section p {
            font-size: clamp(1rem, 3vw, 1.25rem);
            color: rgba(250, 250, 250, 0.85);
            max-width: 700px;
            margin: 0 auto 2.5rem auto;
        }

        /* Feature Cards */
        .features-section {
            padding: 100px 0;
            background-color: var(--secondary-color);
            position: relative;
            overflow: hidden;
        }

        .features-section::before {
            content: '';
            position: absolute;
            top: -200px;
            right: -200px;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.08) 0%, transparent 70%);
            z-index: 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 4rem;
            position: relative;
            z-index: 2;
        }

        .section-title h2 {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 2.8rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .section-title .divider {
            width: 80px;
            height: 4px;
            background: var(--gold-gradient);
            margin: 1rem auto;
            border-radius: 2px;
        }

        .section-title p {
            color: var(--text-light);
            font-size: 1.125rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .feature-card {
            background-color: white;
            border: none;
            border-radius: 15px;
            box-shadow: var(--shadow);
            padding: 2.5rem 2rem;
            text-align: center;
            height: 100%;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            z-index: 2;
            border-top: 4px solid transparent;
        }

        .feature-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
            border-top: 4px solid var(--accent-color);
        }

        .feature-icon {
            font-size: 3.5rem;
            background: var(--gold-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 1.5rem;
            transition: transform 0.4s ease;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.15) rotate(5deg);
        }

        .feature-card h5 {
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.4rem;
        }

        .feature-card p {
            color: var(--text-light);
            font-size: 1rem;
            line-height: 1.7;
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d121b 100%);
            color: var(--secondary-color);
            padding: 3rem 0 2rem;
            text-align: center;
            position: relative;
        }

        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gold-gradient);
        }

        .footer-content {
            padding: 2rem 0;
        }

        .footer-logo {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-color);
            margin-bottom: 1.5rem;
        }

        .social-icons {
            margin: 2rem 0;
        }

        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(212, 175, 55, 0.1);
            color: var(--accent-color);
            font-size: 1.2rem;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }

        .social-icons a:hover {
            background: var(--accent-color);
            color: var(--primary-color);
            transform: translateY(-3px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-section {
                padding: 100px 0;
            }
            
            .features-section {
                padding: 80px 0;
            }
            
            .feature-card {
                margin-bottom: 2rem;
            }
            
            .navbar-brand {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 576px) {
            .hero-section {
                padding: 80px 0;
            }
            
            .section-title h2 {
                font-size: 2.2rem;
            }
            
            .feature-card {
                padding: 2rem 1.5rem;
            }
        }

        /* Loading Animation */
        .loading {
            opacity: 0;
            animation: fadeIn 0.6s ease-in-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Additional Luxury Touches */
        .gold-underline {
            display: inline-block;
            position: relative;
        }

        .gold-underline::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--gold-gradient);
            border-radius: 2px;
        }

        .text-gold {
            color: var(--accent-color);
        }
    </style>
</head>
<body class="antialiased">

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">
                <i class="fas fa-crown"></i>
                Buku Digital Aza
            </a>
            
            <div class="navbar-nav ms-auto">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/redirect-dashboard') }}" class="btn btn-outline-primary me-2">
                            <i class="fas fa-tachometer-alt me-1"></i>
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-1"></i>
                            Login
                        </a>
                    @endauth
                @endif
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section">
        <div class="container">
            <div class="hero-content loading">
                <h1><span>Transformasi</span>Bisnis Anda Dengan Buku Bisnis Digital Premium</h1>
                <p>Satu platform eksklusif untuk mengelola penjualan, inventaris, keuangan, dan analisis bisnis secara komprehensif dan efisien.</p>
                <a href="{{ route('login') }}" class="btn btn-primary btn-lg mt-3">
                    <i class="fas fa-gem me-2"></i>
                    Mulai Pengalaman Premium
                </a>
            </div>
        </div>
    </header>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <div class="section-title loading">
                <h2>Fitur <span class="text-gold">Eksklusif</span> Kami</h2>
                <div class="divider"></div>
                <p class="lead">Solusi premium yang dirancang untuk pertumbuhan bisnis Anda</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card loading">
                        <div class="feature-icon">
                            <i class="fas fa-cash-register"></i>
                        </div>
                        <h5 class="card-title gold-underline">Manajemen Penjualan (POS)</h5>
                        <p class="card-text">Sistem Point of Sale premium yang cepat dan intuitif untuk pengalaman transaksi yang mulus dengan laporan real-time.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card loading">
                        <div class="feature-icon">
                            <i class="fas fa-warehouse"></i>
                        </div>
                        <h5 class="card-title gold-underline">Kontrol Inventaris Premium</h5>
                        <p class="card-text">Lacak stok secara real-time dengan presisi tinggi, notifikasi stok menipis, dan manajemen opname terintegrasi.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card loading">
                        <div class="feature-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h5 class="card-title gold-underline">Analisis Keuangan Elite</h5>
                        <p class="card-text">Pantau arus kas dengan presisi, hitung ROI, dan dapatkan laporan keuangan eksklusif untuk keputusan strategis.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Premium Section -->
    <section class="features-section" style="background: linear-gradient(135deg, #0d121b 0%, #1a2332 100%); padding: 100px 0;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <div class="loading">
                        <h2 class="text-white mb-4">Pengalaman <span class="text-gold">Bisnis Premium</span></h2>
                        <p class="text-light mb-4">Platform kami dirancang untuk memberikan pengalaman bisnis kelas atas dengan fitur-fitur eksklusif:</p>
                        <ul class="text-light list-unstyled">
                            <li class="mb-3"><i class="fas fa-check-circle text-gold me-2"></i> Antarmuka mewah dengan navigasi intuitif</li>
                            <li class="mb-3"><i class="fas fa-check-circle text-gold me-2"></i> Keamanan data tingkat enterprise</li>
                            <li class="mb-3"><i class="fas fa-check-circle text-gold me-2"></i> Laporan analitik prediktif</li>
                            <li class="mb-3"><i class="fas fa-check-circle text-gold me-2"></i> Dukungan pelanggan premium 24/7</li>
                            <li><i class="fas fa-check-circle text-gold me-2"></i> Integrasi dengan sistem kelas enterprise</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="loading">
                        <div class="card feature-card h-100" style="border-top: 4px solid var(--accent-color);">
                            <div class="feature-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <h5 class="card-title">Fitur <span class="text-gold">Premium</span></h5>
                            <p class="card-text">Tingkatkan bisnis Anda dengan paket premium kami yang menawarkan:</p>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-crown text-gold me-2"></i> Prioritas akses fitur baru</li>
                                <li class="mb-2"><i class="fas fa-crown text-gold me-2"></i> Penyimpanan data tak terbatas</li>
                                <li class="mb-2"><i class="fas fa-crown text-gold me-2"></i> Pelatihan eksklusif</li>
                                <li class="mb-2"><i class="fas fa-crown text-gold me-2"></i> Konsultasi bisnis premium</li>
                                <li><i class="fas fa-crown text-gold me-2"></i> Laporan kinerja khusus</li>
                            </ul>
                            <a href="#" class="btn btn-primary mt-3">Yukk Cobain!!!</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <i class="fas fa-crown me-2"></i>Buku Digital Aza
                </div>
                <p class="mb-4">Solusi bisnis premium untuk pertumbuhan umkm tak terbatas</p>
                
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
                
                <p class="mb-0">
                    &copy; {{ date('Y') }} Buku Digital Aza.       
                    All Rights Reserved.
                </p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Loading animation trigger
        document.addEventListener('DOMContentLoaded', function() {
            const loadingElements = document.querySelectorAll('.loading');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationDelay = Math.random() * 0.3 + 's';
                        entry.target.classList.add('animate');
                    }
                });
            }, {
                threshold: 0.1
            });
            
            loadingElements.forEach(el => observer.observe(el));
            
            // Gold particles animation for hero section
            const heroSection = document.querySelector('.hero-section');
            if (heroSection) {
                for (let i = 0; i < 20; i++) {
                    createParticle();
                }
            }
            
            function createParticle() {
                const particle = document.createElement('div');
                particle.className = 'gold-particle';
                particle.style.cssText = `
                    position: absolute;
                    width: ${Math.random() * 6 + 2}px;
                    height: ${Math.random() * 6 + 2}px;
                    background: #d4af37;
                    border-radius: 50%;
                    opacity: ${Math.random() * 0.6 + 0.2};
                    top: ${Math.random() * 100}%;
                    left: ${Math.random() * 100}%;
                    z-index: 1;
                `;
                heroSection.appendChild(particle);
                
                animateParticle(particle);
            }
            
            function animateParticle(particle) {
                const duration = Math.random() * 10 + 10;
                const xMovement = (Math.random() - 0.5) * 100;
                const yMovement = (Math.random() - 0.5) * 50;
                
                particle.animate([
                    { transform: 'translate(0, 0)', opacity: particle.style.opacity },
                    { transform: `translate(${xMovement}px, ${yMovement}px)`, opacity: 0 }
                ], {
                    duration: duration * 1000,
                    easing: 'ease-in-out'
                });
                
                setTimeout(() => {
                    particle.remove();
                    createParticle();
                }, duration * 1000);
            }
        });

        // Smooth scrolling for internal links
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
    </script>
</body>
</html>