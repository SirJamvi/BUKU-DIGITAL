<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Business;
use App\Models\UserSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Services\Admin\FundAllocationService;

class AuthService
{
   /**
    * Mencoba untuk mengotentikasi pengguna.
    *
    * @param array $credentials
    * @param bool $remember
    * @return User|null
    */
   public function attemptLogin(array $credentials, bool $remember = false): ?User
   {
       if (Auth::attempt($credentials, $remember)) {
           /** @var User $user */
           $user = Auth::user();

           // Pastikan hanya user aktif yang bisa login
           if (!$user->is_active) {
               Auth::logout();
               return null;
           }

           // Catat sesi login
           $user->sessions()->create([
               'role' => $user->role,
               'ip_address' => request()->ip(),
               'login_time' => now(),
               'last_activity' => now(),
           ]);

           return $user;
       }

       return null;
   }

   /**
    * Membuat pengguna baru beserta bisnisnya (untuk admin).
    *
    * @param array $data
    * @return User
    */
   public function createUserAndBusiness(array $data): User
   {
       return DB::transaction(function () use ($data) {
           // 1. Buat user baru sebagai owner
           $user = User::create([
               'name' => $data['name'],
               'email' => $data['email'],
               'password' => Hash::make($data['password']),
               'role' => 'admin',
               'is_active' => true,
           ]);
           
           // 2. Buat bisnis baru dengan user ini sebagai pemiliknya
           $business = Business::create([
               'name' => $data['business_name'],
               'owner_id' => $user->id,
           ]);
           
           // 3. Update user agar terikat dengan bisnis barunya
           $user->business_id = $business->id;
           $user->save();
           
           // 4. Buat pengaturan alokasi default untuk bisnis baru
           $fundAllocationService = app(FundAllocationService::class);
           $fundAllocationService->createDefaultAllocationSettingsForNewBusiness($business->id, $user->id);
           
           return $user;
       });
   }

   /**
    * Membuat pengguna baru.
    *
    * @param array $data
    * @param string $defaultRole
    * @return User
    */
   public function createUser(array $data, string $defaultRole = 'kasir'): User
   {
       return User::create([
           'name' => $data['name'],
           'email' => $data['email'],
           'password' => Hash::make($data['password']),
           'role' => $data['role'] ?? $defaultRole,
           'is_active' => $data['is_active'] ?? true,
       ]);
   }

   /**
    * Melakukan logout pengguna.
    *
    * @return void
    */
   public function logout(): void
   {
       /** @var User|null $user */
       $user = Auth::user();

       if ($user) {
           // Update waktu logout di sesi terakhir
           $latestSession = $user->sessions()
                ->whereNull('logout_time')
                ->latest('login_time')
                ->first();
                
           if ($latestSession instanceof UserSession) {
               $latestSession->update(['logout_time' => now()]);
           }
       }

       Auth::logout();
   }
}