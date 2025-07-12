{{-- resources/views/auth/guest.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Color Palette */
        :root {
            --deep-navy: #1a2332;
            --darker-navy: #243247;
            --warm-white: #fafafa;
            --luxury-gold: #d4af37;
            --gold-hover: #c5a230;
            --gold-light: rgba(212, 175, 55, 0.1);
            --gold-shadow: rgba(212, 175, 55, 0.25);
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--darker-navy) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--warm-white);
        }

        /* Auth Container */
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        /* Auth Card */
        .auth-card {
            max-width: 450px;
            width: 100%;
            background: rgba(36, 50, 71, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 
                0 10px 30px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Typography */
        h2, h5, .card-title {
            color: var(--warm-white) !important;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.5rem;
            text-align: center;
            position: relative;
        }

        .card-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, var(--luxury-gold), var(--gold-hover));
            border-radius: 2px;
        }

        /* Form Elements */
        .form-label {
            color: var(--warm-white) !important;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: var(--warm-white) !important;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.12);
            border-color: var(--luxury-gold);
            box-shadow: 0 0 0 0.25rem var(--gold-shadow);
            color: var(--warm-white) !important;
        }

        .form-control::placeholder {
            color: rgba(250, 250, 250, 0.6);
        }

        .form-control[readonly] {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
            color: rgba(250, 250, 250, 0.8) !important;
        }

        /* Checkbox */
        .form-check-input {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .form-check-input:checked {
            background-color: var(--luxury-gold);
            border-color: var(--luxury-gold);
        }

        .form-check-input:focus {
            border-color: var(--luxury-gold);
            box-shadow: 0 0 0 0.25rem var(--gold-shadow);
        }

        .form-check-label {
            color: var(--warm-white) !important;
            font-size: 0.95rem;
        }

        /* Buttons */
        .btn-primary,
        .btn-custom-primary {
            background: linear-gradient(135deg, var(--luxury-gold), var(--gold-hover)) !important;
            border: none !important;
            color: var(--deep-navy) !important;
            font-weight: 600 !important;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover,
        .btn-custom-primary:hover {
            background: linear-gradient(135deg, var(--gold-hover), #b8952a) !important;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
            color: var(--deep-navy) !important;
        }

        .btn-primary:active,
        .btn-custom-primary:active {
            transform: translateY(0);
            color: var(--deep-navy) !important;
        }

        .btn-primary:focus,
        .btn-custom-primary:focus {
            box-shadow: 0 0 0 0.25rem var(--gold-shadow);
            color: var(--deep-navy) !important;
        }

        /* Untuk link yang menggunakan class btn */
        a.btn-primary,
        a.btn-custom-primary {
            color: var(--deep-navy) !important;
            text-decoration: none !important;
        }

        a.btn-primary:hover,
        a.btn-custom-primary:hover {
            color: var(--deep-navy) !important;
            text-decoration: none !important;
        }

        /* Override Bootstrap defaults yang mungkin konflik */
        .btn-primary *,
        .btn-custom-primary * {
            color: inherit !important;
        }

        /* Specific untuk icon dalam button */
        .btn-primary i,
        .btn-custom-primary i {
            color: var(--deep-navy) !important;
        }

        /* Links */
        a {
            color: var(--luxury-gold) !important;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        a:hover {
            color: var(--gold-hover) !important;
            text-decoration: underline;
        }

        /* Alerts */
        .alert {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: var(--warm-white);
            margin-bottom: 1.5rem;
            padding: 1rem;
            font-size: 0.95rem;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border-color: rgba(40, 167, 69, 0.3);
            color: #d4edda;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border-color: rgba(220, 53, 69, 0.3);
            color: #f8d7da;
        }

        /* Form Validation */
        .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }

        .invalid-feedback {
            display: block;
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Text Utilities */
        .text-muted {
            color: rgba(250, 250, 250, 0.7) !important;
        }

        .text-center {
            text-align: center;
        }

        /* Spacing */
        .mb-4 {
            margin-bottom: 1.5rem !important;
        }

        .mt-3 {
            margin-top: 1rem !important;
        }

        .my-3 {
            margin-top: 1rem !important;
            margin-bottom: 1rem !important;
        }

        /* Icons */
        .fas, .fab, .far {
            color: var(--luxury-gold);
        }

        /* Responsive Design */
        @media (max-width: 576px) {
            .auth-card {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }
            
            .card-title {
                font-size: 1.25rem;
            }
        }

        /* Loading Animation */
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Brand Logo/Icon Area */
        .auth-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-brand i {
            font-size: 3rem;
            color: var(--luxury-gold);
            margin-bottom: 1rem;
        }

        /* Enhanced Form Group */
        .form-group {
            margin-bottom: 1.5rem;
        }

        /* Remember Me & Forgot Password Row */
        .auth-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 480px) {
            .auth-options {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }

        /* Forgot Password & Reset Password Specific Styles */
        .forgot-password-icon,
        .reset-password-icon {
            margin-bottom: 1rem;
        }

        .help-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .help-section ul li {
            margin-bottom: 0.5rem;
            padding-left: 0.5rem;
        }

        /* Password Requirements */
        .password-requirements {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .password-requirements h6 {
            color: var(--warm-white) !important;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .password-requirements ul li {
            padding: 0.25rem 0;
            color: rgba(250, 250, 250, 0.8) !important;
        }

        .password-requirements .fas.fa-check {
            color: #28a745 !important;
            font-size: 0.875rem;
        }

        /* Terms & Privacy Notice */
        .text-center p a.text-decoration-underline {
            border-bottom: 1px solid currentColor;
            text-decoration: none !important;
        }

        .text-center p a.text-decoration-underline:hover {
            border-bottom-color: var(--gold-hover);
        }

        /* Success States */
        .text-success {
            color: #28a745 !important;
        }

        /* Enhanced Icons */
        .fa-3x {
            font-size: 2.5rem !important;
        }

        /* Page Specific Animations */
        .forgot-password-icon i,
        .reset-password-icon i {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 0.8; }
            50% { opacity: 1; }
            100% { opacity: 0.8; }
        }

        /* Enhanced list styling */
        .list-unstyled {
            padding-left: 0;
            list-style: none;
        }

        .list-unstyled li {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            {{-- Brand/Logo Section --}}
            <div class="auth-brand">
                <i class="fas fa-shield-alt"></i>
            </div>
            
            {{-- Content Section --}}
            @yield('content')
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>