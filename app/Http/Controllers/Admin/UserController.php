<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\Admin\UserService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        // Middleware sudah didaftarkan di route file
    }

    public function index(Request $request): View
    {
        $users = $this->userService->getAllUsersWithPagination();

        $businessId = Auth::user()->business_id;

        // Default rentang tanggal: Awal bulan ini s/d hari ini
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        $selectedDriverId = $request->input('driver_id', 'all');
        $activeTab = $request->input('tab', $request->has('start_date') ? 'driver_recap' : 'users');

        $driversList = $this->userService->getDriversList($businessId);
        $driverRecap = $this->userService->getDriverDeliveryRecap($businessId, $startDate, $endDate, $selectedDriverId);

        return view('admin.users.index', compact(
            'users',
            'driverRecap',
            'driversList',
            'startDate',
            'endDate',
            'selectedDriverId',
            'activeTab'
        ));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        try {
            $this->userService->createUser($request->validated());
            return redirect()->route('admin.users.index')->with('success', 'User berhasil dibuat.');
        } catch (\Exception $e) {
            logger()->error('Error creating user: ' . $e->getMessage());
            return back()->with('error', 'Gagal membuat user. Silakan coba lagi.');
        }
    }

    public function show(User $user): View
    {
        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        try {
            $this->userService->updateUser($user, $request->validated());
            return redirect()->route('admin.users.index')->with('success', 'User berhasil diperbarui.');
        } catch (\Exception $e) {
            logger()->error('Error updating user: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui user. Silakan coba lagi.');
        }
    }

    public function destroy(User $user): RedirectResponse
    {
        try {
            $this->userService->deleteUser($user);
            return redirect()->route('admin.users.index')->with('success', 'User berhasil dihapus.');
        } catch (\Exception $e) {
            logger()->error('Error deleting user: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus user. Silakan coba lagi.');
        }
    }

    /**
     * Toggle status aktif/nonaktif user (dipanggil via AJAX).
     */
    public function toggleStatus(User $user): JsonResponse
    {
        try {
            $user = $this->userService->toggleStatus($user);
            return response()->json([
                'success'   => true,
                'message'   => 'Status pengguna berhasil diperbarui.',
                'is_active' => $user->is_active,
            ]);
        } catch (\Exception $e) {
            logger()->error('Error toggling user status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status.',
            ], 500);
        }
    }
}