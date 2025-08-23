<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\CashFlow; // <-- Tambahkan ini
use App\Policies\CashFlowPolicy; // <-- Tambahkan ini
use App\Models\User;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Inventory;
use App\Policies\UserPolicy;
use App\Policies\ProductPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\ReportPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Pemetaan model ke policy untuk aplikasi.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Product::class => ProductPolicy::class,
        Transaction::class => TransactionPolicy::class,
        Inventory::class => InventoryPolicy::class,
        Transaction::class => TransactionPolicy::class,
        CashFlow::class => CashFlowPolicy::class,
        // Report tidak terikat pada model, jadi kita akan menggunakan Gate
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        //
    }
}