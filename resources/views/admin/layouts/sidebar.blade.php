<div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="d-flex align-items-center justify-content-center">
                <i class="fas fa-crown me-2" style="color: var(--luxury-gold);"></i>
                <h4 class="mb-0">Buku Digital Aza</h4>
            </div>
        </div>

        <div class="sidebar-menu">
            <a href="{{ route('admin.dashboard') }}" class="sidebar-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>

            @permission('products.read')
            <a href="{{ route('admin.products.index') }}" class="sidebar-item {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                <i class="fas fa-box-open"></i>
                <span>Produk</span>
            </a>
            @endpermission

            @permission('categories.read')
            <a href="{{ route('admin.categories.index') }}" class="sidebar-item {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                <i class="fas fa-tags"></i>
                <span>Kategori</span>
            </a>
            @endpermission

            @permission('inventory.read')
            <a href="{{ route('admin.inventory.index') }}" class="sidebar-item {{ request()->routeIs('admin.inventory.*') ? 'active' : '' }}">
                <i class="fas fa-warehouse"></i>
                <span>Inventaris</span>
            </a>
            @endpermission

            @permission('transactions.read')
            <a href="{{ route('admin.transactions.index') }}" class="sidebar-item {{ request()->routeIs('admin.transactions.*') ? 'active' : '' }}">
                <i class="fas fa-cash-register"></i>
                <span>Transaksi</span>
            </a>
            @endpermission

            @permission('customers.read')
            <a href="{{ route('admin.customers.index') }}" class="sidebar-item {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
                <i class="fas fa-users"></i>
                <span>Pelanggan</span>
            </a>
            @endpermission

            @permission('financial.view_all')
            <a href="{{ route('admin.financial.index') }}" class="sidebar-item {{ request()->routeIs('admin.financial.*') ? 'active' : '' }}">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Finansial</span>
            </a>
            @endpermission

            @permission('reports.view_all')
            <a href="{{ route('admin.reports.index') }}" class="sidebar-item {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                <i class="fas fa-chart-pie"></i>
                <span>Laporan</span>
            </a>
            @endpermission

            <!-- FITUR YANG HILANG - TAMBAHKAN DI SINI -->
            @permission('fund_allocation.view')
            <a href="{{ route('admin.fund-allocation.index') }}" class="sidebar-item {{ request()->routeIs('admin.fund-allocation.*') ? 'active' : '' }}">
                <i class="fas fa-money-bill-wave"></i>
                <span>Alokasi Dana</span>
            </a>
            @endpermission

            @permission('business_intelligence.view')
            <a href="{{ route('admin.bi.index') }}" class="sidebar-item {{ request()->routeIs('admin.bi.*') ? 'active' : '' }}">
                <i class="fas fa-chart-line"></i>
                <span>Business Intelligence</span>
            </a>
            @endpermission

            <div class="sidebar-divider"></div>

            @permission('users.read')
            <a href="{{ route('admin.users.index') }}" class="sidebar-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <i class="fas fa-user-cog"></i>
                <span>Manajemen User</span>
            </a>
            @endpermission

            @permission('settings.view')
            <a href="{{ route('admin.settings.index') }}" class="sidebar-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                <i class="fas fa-cogs"></i>
                <span>Pengaturan</span>
            </a>
            @endpermission

            <div class="sidebar-divider"></div>

            <!-- Quick Stats or Additional Info -->
            <div class="sidebar-item" style="pointer-events: none; opacity: 0.7;">
                <i class="fas fa-info-circle"></i>
                <span>Info Sistem</span>
            </div>
            
            <div class="px-3 py-2">
                <div class="small text-light opacity-75">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Storage</span>
                        <span>75%</span>
                    </div>
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar" style="width: 75%; background: var(--gold-gradient);"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .progress {
            background-color: rgba(255,255,255,0.1);
            border-radius: 2px;
        }
        
        .progress-bar {
            border-radius: 2px;
        }
        
        .sidebar-item span {
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .sidebar-item.active span {
            font-weight: 600;
            color: var(--gold-light);
        }
        
        .sidebar-item:hover span {
            color: var(--gold-light);
        }
        
        .sidebar-item i {
            color: rgba(255,255,255,0.8);
        }
        
        .sidebar-item.active i {
            color: var(--luxury-gold);
        }
        
        .sidebar-item:hover i {
            color: var(--luxury-gold);
        }
    </style>