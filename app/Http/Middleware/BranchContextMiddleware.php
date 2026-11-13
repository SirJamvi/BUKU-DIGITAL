<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class BranchContextMiddleware
{
    public function __construct(
        protected BranchContext $branchContext
    ) {}

    /**
     * Menangani konteks cabang aktif untuk setiap request pengguna terotentikasi.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Tamu (Guest) -> mode NONE (fail-closed)
        if (!Auth::check()) {
            $this->branchContext->setNone();
            return $next($request);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->branchContext->setNone();

        // 2. CASE A: REQUEST API (Stateless / Sanctum)
        if ($request->is('api/*') || $request->expectsJson()) {
            $headerVal = $request->header('X-Branch-Id');

            if ($user->isAdmin()) {
                // Admin via API:
                if ($headerVal === 'all' || empty($headerVal)) {
                    $this->branchContext->setConsolidated($user->business_id);
                } else {
                    $branchId = (int) $headerVal;
                    $valid = Branch::where('id', $branchId)
                        ->where('business_id', $user->business_id)
                        ->where('is_active', true)
                        ->exists();

                    if (!$valid) {
                        return response()->json([
                            'message' => "Cabang ID {$branchId} tidak valid untuk bisnis ini.",
                        ], 403);
                    }

                    $this->branchContext->setBranch($branchId, $user->business_id);
                }
            } else {
                // Staf Cabang (Kasir / Driver) via API
                $activeBranches = $user->activeAccessibleBranches();

                if ($activeBranches->isEmpty()) {
                    return response()->json([
                        'message' => 'Semua cabang penugasan Anda nonaktif atau Anda belum ditugaskan. Hubungi Admin.',
                    ], 403);
                }

                $accessibleIds = $activeBranches->pluck('id')->map(fn($id) => (int) $id)->all();

                if (!empty($headerVal) && $headerVal !== 'all') {
                    $requestedId = (int) $headerVal;
                    if (!in_array($requestedId, $accessibleIds, true)) {
                        return response()->json([
                            'message' => 'Anda tidak memiliki hak akses untuk cabang ini.',
                        ], 403);
                    }
                    $this->branchContext->setBranch($requestedId, $user->business_id);
                } else {
                    // Jika header tidak dikirim:
                    if (count($accessibleIds) === 1) {
                        $this->branchContext->setBranch($accessibleIds[0], $user->business_id);
                    } else {
                        $defaultBranch = $activeBranches->first(fn($b) => (bool) ($b->pivot->is_default ?? false));
                        if ($defaultBranch) {
                            $this->branchContext->setBranch($defaultBranch->id, $user->business_id);
                        } else {
                            return response()->json([
                                'message' => 'Header X-Branch-Id wajib dikirim untuk staf multi-cabang.',
                            ], 422);
                        }
                    }
                }
            }

            return $next($request);
        }

        // 3. CASE B: REQUEST WEB (Session Based)
        if ($user->isAdmin()) {
            $sessionBranchId = session('active_branch_id');

            if ($sessionBranchId && $sessionBranchId !== 'all') {
                $branch = Branch::where('id', $sessionBranchId)
                    ->where('business_id', $user->business_id)
                    ->where('is_active', true)
                    ->first();

                if ($branch) {
                    $this->branchContext->setBranch($branch->id, $user->business_id);
                    session([
                        'active_branch_id'   => $branch->id,
                        'active_branch_name' => $branch->name,
                        'active_branch_code' => $branch->code,
                    ]);
                } else {
                    $this->branchContext->setConsolidated($user->business_id);
                    session([
                        'active_branch_id'   => 'all',
                        'active_branch_name' => 'Semua Cabang (Konsolidasi)',
                        'active_branch_code' => 'ALL',
                    ]);
                }
            } else {
                $this->branchContext->setConsolidated($user->business_id);
                session([
                    'active_branch_id'   => 'all',
                    'active_branch_name' => 'Semua Cabang (Konsolidasi)',
                    'active_branch_code' => 'ALL',
                ]);
            }
        } else {
            // Staf Non-Admin (Kasir / Driver)
            $activeBranches = $user->activeAccessibleBranches();

            if ($activeBranches->isEmpty()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Semua cabang penugasan Anda nonaktif atau Anda belum ditugaskan. Hubungi Admin.',
                ]);
            }

            $accessibleIds = $activeBranches->pluck('id')->map(fn($id) => (int) $id)->all();

            if (count($accessibleIds) === 1) {
                $branch = $activeBranches->first();
                if ($branch) {
                    $this->branchContext->setBranch($branch->id, $user->business_id);
                    session([
                        'active_branch_id'   => $branch->id,
                        'active_branch_name' => $branch->name,
                        'active_branch_code' => $branch->code,
                    ]);
                }
            } else {
                // Staf multi-cabang (>1 cabang)
                $sessionBranchId = session('active_branch_id');

                // Validasi session branch id masih valid dalam accessibleIds
                if ($sessionBranchId && $sessionBranchId !== 'all' && in_array((int)$sessionBranchId, $accessibleIds, true)) {
                    $branch = $activeBranches->firstWhere('id', (int) $sessionBranchId);
                    if ($branch) {
                        $this->branchContext->setBranch($branch->id, $user->business_id);
                        session([
                            'active_branch_id'   => $branch->id,
                            'active_branch_name' => $branch->name,
                            'active_branch_code' => $branch->code,
                        ]);
                    }
                } else {
                    // Izinkan request ke rute pemilihan cabang atau logout
                    if ($request->is('branch/select') || $request->is('branch/switch*') || $request->is('logout')) {
                        return $next($request);
                    }

                    return redirect()->route('branch.select');
                }
            }
        }

        // Bagikan data context ke seluruh Blade View
        View::share('active_branch_id', session('active_branch_id'));
        View::share('active_branch_name', session('active_branch_name'));
        View::share('active_branch_code', session('active_branch_code'));
        View::share('available_branches', $user->activeAccessibleBranches());

        return $next($request);
    }
}
