<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use App\Models\User;

class ViewServiceProvider extends ServiceProvider
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
        // Menggunakan View Composer untuk berbagi data notifikasi
        // ke layout header admin.
        View::composer('admin.layouts.header', function ($view) {
            /** @var User|null $user */
            $user = Auth::user();
            
            if ($user) {
                $notifications = $user->unreadNotifications()->take(5)->get();
                $notificationCount = $user->unreadNotifications()->count();
            } else {
                $notifications = collect();
                $notificationCount = 0;
            }

            $view->with([
                'notifications' => $notifications,
                'notificationCount' => $notificationCount,
            ]);
        });

        // View composer untuk semua layout yang membutuhkan data user
        View::composer(['admin.layouts.*', 'layouts.admin'], function ($view) {
            /** @var User|null $user */
            $user = Auth::user();
            
            $view->with([
                'currentUser' => $user,
                'isAuthenticated' => Auth::check(),
            ]);
        });

        // View composer untuk sidebar menu (jika ada)
        View::composer('admin.layouts.sidebar', function ($view) {
            /** @var User|null $user */
            $user = Auth::user();
            
            if ($user) {
                $view->with([
                    'userRole' => $user->role ?? 'guest',
                    'userPermissions' => $user->permissions ?? [],
                ]);
            } else {
                $view->with([
                    'userRole' => 'guest',
                    'userPermissions' => [],
                ]);
            }
        });

        // View composer untuk layout kasir agar statistik harian selalu sinkron real-time (WIB)
        View::composer(['kasir.layouts.header', 'kasir.layouts.sidebar', 'kasir.layouts.app'], function ($view) {
            $user = Auth::user();
            if ($user && $user->role === 'kasir') {
                $today = \Carbon\Carbon::today('Asia/Jakarta');
                $businessId = $user->business_id;

                $todayQuery = \App\Models\Transaction::where('business_id', $businessId)
                    ->where('created_by', $user->id)
                    ->where('type', 'sale')
                    ->where(function ($q) use ($today) {
                        $q->whereDate('transaction_date', $today->toDateString())
                          ->orWhereBetween('transaction_date', [
                              $today->copy()->startOfDay(),
                              $today->copy()->endOfDay()
                          ]);
                    });

                $todaySales = (float) $todayQuery->sum('total_amount');
                $todayCount = $todayQuery->count();

                $view->with([
                    'headerTodaySales' => 'Rp ' . number_format($todaySales, 0, ',', '.'),
                    'headerTodayTransactions' => (string) $todayCount,
                ]);
            }
        });
    }
}