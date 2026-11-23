<?php

namespace App\Providers;

use App\Models\Transaction;
use App\Models\User;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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

            $notifications = collect();
            $notificationCount = 0;

            if ($user) {
                try {
                    $notifications = $user->unreadNotifications()->latest()->take(5)->get();
                    $notificationCount = $user->unreadNotifications()->count();
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $view->with([
                'notifications' => $notifications,
                'notificationCount' => $notificationCount,
            ]);
        });

        // View composer untuk semua layout yang membutuhkan data user (admin & kasir)
        View::composer(['admin.layouts.*', 'layouts.admin', 'kasir.layouts.*', 'layouts.kasir', 'kasir.layouts.app'], function ($view) {
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
                    'userRole' => $user->role ?? ($user->allRoles()[0] ?? 'guest'),
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
            static $cachedMetrics = [];

            $user = Auth::user();

            $kasirMetrics = [
                'headerTodaySales' => 'Rp 0',
                'headerTodayTransactions' => '0',
            ];

            if ($user && $user->hasRole('kasir')) {
                $branchId = app(BranchContext::class)->getBranchId();
                $cacheKey = $user->id . '_' . ($branchId ?? 'all');

                if (isset($cachedMetrics[$cacheKey])) {
                    $kasirMetrics = $cachedMetrics[$cacheKey];
                } else {
                    try {
                        $todayStart = Carbon::today('Asia/Jakarta')->startOfDay();
                        $todayEnd = Carbon::today('Asia/Jakarta')->endOfDay();
                        $businessId = $user->business_id;

                        $todayQuery = Transaction::where('business_id', $businessId)
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

                        $cachedMetrics[$cacheKey] = $kasirMetrics;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }

            $view->with($kasirMetrics);
        });
    }
}
