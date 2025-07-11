<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SettingsController extends Controller
{
    protected SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
        // Middleware sudah didaftarkan di route file
    }

    public function index(): View
    {
        return view('admin.settings.index');
    }

    public function profile(): View
    {
        /** @var User $user */
        $user = Auth::user();
        return view('admin.settings.profile', compact('user'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $this->settingsService->updateUserProfile($user, $request->all());
            
            return redirect()->route('admin.settings.profile')->with('success', 'Profil berhasil diperbarui.');
        } catch (\Exception $e) {
            logger()->error('Error updating profile: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui profil.');
        }
    }

    public function system(): View
    {
        $settings = $this->settingsService->getSystemSettings();
        return view('admin.settings.system', compact('settings'));
    }

    public function updateSystem(Request $request): RedirectResponse
    {
        try {
            $this->settingsService->updateSystemSettings($request->all());
            return redirect()->route('admin.settings.system')->with('success', 'Pengaturan sistem berhasil diperbarui.');
        } catch (\Exception $e) {
            logger()->error('Error updating system settings: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui pengaturan sistem.');
        }
    }
}