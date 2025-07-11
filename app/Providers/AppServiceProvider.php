<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Menetapkan panjang string default untuk migrasi
        // untuk menghindari error pada versi MySQL yang lebih lama.
        Schema::defaultStringLength(191);
    }
}