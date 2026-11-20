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
        // Dioptimalkan dengan memoization request & single SQL aggregate query untuk mencegah full table scan
        View::composer(['kasir.layouts.header', 'kasir.layouts.sidebar', 'kasir.layouts.app'], function ($view) {
            static $kasirMetrics = null;

            $user = Auth::user();
            if ($user && $user->hasRole('kasir')) {
                if ($kasirMetrics === null) {
                    $todayStart = \Carbon\Carbon::today('Asia/Jakarta')->startOfDay();
                    $todayEnd = \Carbon\Carbon::today('Asia/Jakarta')->endOfDay();
                    $businessId = $user->business_id;
                    $branchId = app(\App\Support\BranchContext::class)->getBranchId();

                    $todayQuery = \App\Models\Transaction::where('business_id', $businessId)
                        ->where('created_by', $user->id)
                        ->where('type', 'sale')
                        ->whereBetween('transaction_date', [$todayStart, $todayEnd]);

                    if ($branchId) {
                        $todayQuery->where('branch_id', $branchId);
                    }

                    // Gabungkan sum dan count dalam 1 query via selectRaw
                    $stats = $todayQuery->selectRaw('COALESCE(SUM(total_amount), 0) as total_sales, COUNT(*) as total_count')->first();

                    $todaySales = (float) ($stats->total_sales ?? 0);
                    $todayCount = (int) ($stats->total_count ?? 0);

                    $kasirMetrics = [
                        'headerTodaySales' => 'Rp ' . number_format($todaySales, 0, ',', '.'),
                        'headerTodayTransactions' => (string) $todayCount,
                    ];
                }

                $view->with($kasirMetrics);
            }
        });
    }
}