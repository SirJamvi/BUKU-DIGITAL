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

            {{-- Master Data Group --}}
            <div class="sidebar-group">
                <div class="sidebar-group-header">
                    <i class="fas fa-database"></i>
                    <span>Master Data</span>
                </div>
                
                @permission('products.read')
                <a href="{{ route('admin.products.index') }}" class="sidebar-item sub-item {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                    <i class="fas fa-box-open"></i>
                    <span>Produk</span>
                </a>
                @endpermission

                @permission('categories.read')
                <a href="{{ route('admin.categories.index') }}" class="sidebar-item sub-item {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                    <i class="fas fa-tags"></i>
                    <span>Kategori</span>
                </a>
                @endpermission

                @permission('suppliers.read')
                <a href="{{ route('admin.suppliers.index') }}" class="sidebar-item sub-item {{ request()->routeIs('admin.suppliers.*') ? 'active' : '' }}">
                    <i class="fas fa-truck"></i>
                    <span>Supplier</span>
                </a>
                @endpermission

                @permission('expense_categories.read')
                <a href="{{ route('admin.expense_categories.index') }}"
                class="sidebar-item sub-item {{ request()->routeIs('admin.expense_categories.*') ? 'active' : '' }}">
                    <i class="fas fa-list-ul"></i>
                    <span>Kategori Biaya</span>
                </a>
                @endpermission
            </div>

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

            {{-- Analisis & Laporan Group --}}
            <div class="sidebar-group">
                <div class="sidebar-group-header">
                    <i class="fas fa-chart-line"></i>
                    <span>Analisis & Laporan</span>
                </div>

                @permission('reports.view_all')
                <a href="{{ route('admin.reports.index') }}" class="sidebar-item sub-item {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                    <i class="fas fa-chart-pie"></i>
                    <span>Laporan</span>
                </a>
                @endpermission

                @permission('business_intelligence.view')
                <a href="{{ route('admin.bi.index') }}" class="sidebar-item sub-item {{ request()->routeIs('admin.bi.*') ? 'active' : '' }}">
                    <i class="fas fa-chart-line"></i>
                    <span>Business Intelligence</span>
                </a>
                @endpermission

                {{-- Tambahkan link untuk Owner Profit di sini jika controllernya sudah dibuat --}}
            </div>

            @permission('fund_allocation.view')
            <a href="{{ route('admin.fund-allocation.index') }}" class="sidebar-item {{ request()->routeIs('admin.fund-allocation.*') ? 'active' : '' }}">
                <i class="fas fa-money-bill-wave"></i>
                <span>Alokasi Dana</span>
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

        .sidebar-group {
            margin-bottom: 1rem;
        }

        .sidebar-group-header {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--luxury-gold);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 0.5rem;
        }

        .sidebar-group-header i {
            margin-right: 0.5rem;
            color: var(--luxury-gold);
        }

        .sidebar-item.sub-item {
            padding-left: 3rem;
            font-size: 0.9rem;
        }

        .sidebar-item.sub-item:before {
            content: '';
            position: absolute;
            left: 2rem;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 4px;
            background: rgba(255,255,255,0.5);
            border-radius: 50%;
        }

        .sidebar-item.sub-item.active:before {
            background: var(--luxury-gold);
        }
    </style>