<div class="bg-dark border-right" id="sidebar-wrapper">
    <div class="sidebar-heading text-white text-center py-4">
        <i class="fas fa-cogs"></i> {{ config('app.name', 'BD') }}
    </div>
    <div class="list-group list-group-flush">
        <a href="{{ route('admin.dashboard') }}" class="list-group-item list-group-item-action bg-dark text-white {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard
        </a>
        
        @permission('products.read')
        <a href="{{ route('admin.products.index') }}" class="list-group-item list-group-item-action bg-dark text-white {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
            <i class="fas fa-box-open fa-fw me-2"></i>Produk
        </a>
        @endpermission

        @permission('categories.read')
        <a href="{{ route('admin.categories.index') }}" class="list-group-item list-group-item-action bg-dark text-white {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
            <i class="fas fa-tags fa-fw me-2"></i>Kategori
        </a>
        @endpermission
        
        @permission('inventory.read')
        <a href="{{ route('admin.inventory.index') }}" class="list-group-item list-group-item-action bg-dark text-white {{ request()->routeIs('admin.inventory.*') ? 'active' : '' }}">
            <i class="fas fa-warehouse fa-fw me-2"></i>Inventaris
        </a>
        @endpermission
        
        @permission('transactions.read')
        <a href="#" class="list-group-item list-group-item-action bg-dark text-white">
            <i class="fas fa-cash-register fa-fw me-2"></i>Transaksi
        </a>
        @endpermission

        @permission('customers.read')
        <a href="#" class="list-group-item list-group-item-action bg-dark text-white">
            <i class="fas fa-users fa-fw me-2"></i>Pelanggan
        </a>
        @endpermission

        @permission('financial.view_all')
        <a href="{{ route('admin.financial.index') }}" class="list-group-item list-group-item-action bg-dark text-white {{ request()->routeIs('admin.financial.*') ? 'active' : '' }}">
            <i class="fas fa-file-invoice-dollar fa-fw me-2"></i>Finansial
        </a>
        @endpermission

        @permission('reports.view_all')
        <a href="{{ route('admin.reports.index') }}" class="list-group-item list-group-item-action bg-dark text-white {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
            <i class="fas fa-chart-pie fa-fw me-2"></i>Laporan
        </a>
        @endpermission
        
        <hr class="text-secondary">

        @permission('users.read')
        <a href="{{ route('admin.users.index') }}" class="list-group-item list-group-item-action bg-dark text-white {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <i class="fas fa-user-cog fa-fw me-2"></i>Manajemen User
        </a>
        @endpermission
        
        @permission('settings.view')
        <a href="{{ route('admin.settings.index') }}" class="list-group-item list-group-item-action bg-dark text-white {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
            <i class="fas fa-cogs fa-fw me-2"></i>Pengaturan
        </a>
        @endpermission
    </div>
</div>