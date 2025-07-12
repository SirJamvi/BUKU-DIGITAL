{{-- resources/views/kasir/layouts/sidebar.blade.php --}}
<nav id="sidebar" style="background-color: var(--kasir-bg-dominant); color: var(--kasir-text);">
    <div class="sidebar-header p-4 text-center">
        <h3 class="fw-bold">
            <a href="{{ route('kasir.dashboard') }}" class="text-decoration-none" style="color: var(--kasir-text);">
                <i class="fas fa-cash-register me-2" style="color: var(--kasir-accent);"></i>Kasir
            </a>
        </h3>
    </div>

    <ul class="list-unstyled components p-2">
        <li class="{{ request()->routeIs('kasir.dashboard*') ? 'active' : '' }}">
            <a href="{{ route('kasir.dashboard') }}">
                <i class="fas fa-th-large fa-fw me-2"></i>Dashboard
            </a>
        </li>
        <li class="{{ request()->routeIs('kasir.pos*') ? 'active' : '' }}">
            <a href="{{ route('kasir.pos.index') }}">
                <i class="fas fa-desktop fa-fw me-2"></i>Point of Sale (POS)
            </a>
        </li>
        <li class="{{ request()->routeIs('kasir.transactions*') ? 'active' : '' }}">
            <a href="{{ route('kasir.transactions.index') }}">
                <i class="fas fa-history fa-fw me-2"></i>Riwayat Transaksi
            </a>
        </li>
        <li class="{{ request()->routeIs('kasir.customers*') ? 'active' : '' }}">
            <a href="{{ route('kasir.customers.index') }}">
                <i class="fas fa-users fa-fw me-2"></i>Manajemen Pelanggan
            </a>
        </li>
         <li class="{{ request()->routeIs('kasir.products*') ? 'active' : '' }}">
            <a href="{{ route('kasir.products.index') }}">
                <i class="fas fa-box-open fa-fw me-2"></i>Lihat Produk
            </a>
        </li>
        <li class="{{ request()->routeIs('kasir.reports*') ? 'active' : '' }}">
            <a href="{{ route('kasir.reports.index') }}">
                <i class="fas fa-chart-line fa-fw me-2"></i>Laporan Penjualan
            </a>
        </li>
    </ul>
</nav>

<style>
    #sidebar {
        min-width: 250px;
        max-width: 250px;
        min-height: 100vh;
        box-shadow: 2px 0 15px rgba(0,0,0,0.1);
        transition: all 0.3s;
    }

    #sidebar.active {
        margin-left: -250px;
    }

    #sidebar .sidebar-header {
        border-bottom: 1px solid #eee;
    }

    #sidebar ul.components {
        padding: 20px 0;
    }

    #sidebar ul li a {
        padding: 12px 20px;
        font-size: 1rem;
        display: block;
        color: var(--kasir-text);
        text-decoration: none;
        border-radius: 8px;
        margin: 0 10px 5px 10px;
        transition: all 0.2s ease-in-out;
    }

    #sidebar ul li a:hover,
    #sidebar ul li.active > a {
        background-color: var(--kasir-bg-secondary);
        color: var(--kasir-text);
        font-weight: 500;
    }

    #sidebar ul li.active > a {
        box-shadow: 0 0 10px var(--kasir-bg-secondary);
    }

    #sidebar ul li a i {
        color: var(--kasir-accent);
        min-width: 25px;
        text-align: center;
    }
</style>