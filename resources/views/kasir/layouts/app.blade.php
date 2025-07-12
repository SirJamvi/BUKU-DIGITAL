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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    
    <style>
        :root {
            --kasir-bg-dominant: #fcfcfc;
            --kasir-bg-secondary: #fffae3;
            --kasir-accent: #f7567c;
            --kasir-text: #333;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5; /* A slightly darker bg to make the main content pop */
        }

        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }

        #main-content {
            width: 100%;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        /* Custom card style */
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        .card-header {
            background-color: var(--kasir-bg-dominant);
            border-bottom: 1px solid #eee;
        }

    </style>

    @stack('styles')
</head>
<body>
    <div class="wrapper">
        @include('kasir.layouts.sidebar')

        <div id="main-content">
            @include('kasir.layouts.header')
            
            <main class="mt-4">
                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @stack('scripts')
</body>
</html>