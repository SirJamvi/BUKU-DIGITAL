<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LoginApiController extends Controller
{
    /**
     * 1. Login Normal (Email & Password)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email atau password salah.'
            ], 401);
        }

        // Buat Token Sanctum (Akan aktif selamanya sampai user klik Logout)
        $branches = $this->getAccessibleBranchesForUser($user);

        if (!$user->isAdmin() && empty($branches)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Semua cabang penugasan Anda nonaktif atau Anda belum ditugaskan. Hubungi Admin.',
            ], 403);
        }

        $token = $user->createToken('MobileAppToken')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil.',
            'data' => [
                'user'             => $user,
                'token'            => $token,
                'branches'         => $branches,
                'active_branch_id' => $user->getActiveBranchId(),
            ]
        ]);
    }

    /**
     * 2. Login menggunakan Google (Mobile ngirim data ke sini)
     */
    public function googleLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'name' => 'required|string',
            'google_id' => 'nullable|string', // Opsional, tergantung implementasi RN Anda
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Cek apakah user dengan email tersebut sudah ada di database
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun Google ini tidak terdaftar sebagai karyawan.'
            ], 401);
        }

        $branches = $this->getAccessibleBranchesForUser($user);

        if (!$user->isAdmin() && empty($branches)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Semua cabang penugasan Anda nonaktif atau Anda belum ditugaskan. Hubungi Admin.',
            ], 403);
        }

        // Jika user ada, langsung buatkan Token Sanctum
        $token = $user->createToken('MobileAppToken')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login Google berhasil.',
            'data' => [
                'user'             => $user,
                'token'            => $token,
                'branches'         => $branches,
                'active_branch_id' => $user->getActiveBranchId(),
            ]
        ]);
    }

    /**
     * Ambil daftar cabang yang dapat diakses user untuk aplikasi mobile.
     */
    protected function getAccessibleBranchesForUser(User $user): array
    {
        return $user->activeAccessibleBranches()->map(function ($b) use ($user) {
            $isDefault = $user->isAdmin()
                ? (bool) ($b->is_main ?? false)
                : (bool) ($b->pivot->is_default ?? false);

            return [
                'id'         => $b->id,
                'name'       => $b->name,
                'code'       => $b->code,
                'is_default' => $isDefault,
            ];
        })->values()->all();
    }

    /**
     * 3. Logout (Menghapus token yang sedang digunakan)
     */
    public function logout(Request $request)
    {
        // Menghapus token saat ini dari database
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil.'
        ]);
    }
}