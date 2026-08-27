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
        $token = $user->createToken('MobileAppToken')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil.',
            'data' => [
                'user' => $user,
                'token' => $token,
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
            // Jika belum ada, Anda bisa menolaknya atau otomatis mendaftarkan (Register).
            // Di sini kita asumsikan Kasir harus didaftarkan manual oleh Admin dulu, 
            // jadi kita tolak jika email belum terdaftar di sistem.
            return response()->json([
                'status' => 'error',
                'message' => 'Akun Google ini tidak terdaftar sebagai karyawan.'
            ], 401);
        }

        // Jika user ada, langsung buatkan Token Sanctum
        $token = $user->createToken('MobileAppToken')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login Google berhasil.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ]
        ]);
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