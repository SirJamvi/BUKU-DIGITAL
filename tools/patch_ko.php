<?php
$root = getcwd();
function patch($file, $old, $new) {
    global $root;
    $path = $root . '/' . $file;
    if (!is_file($path)) { echo "SKIP (tidak ada): $file\n"; return; }
    $src = file_get_contents($path);
    if (strpos($src, $new) !== false) { echo "SUDAH: $file\n"; return; }
    if (strpos($src, $old) === false) { echo "ANCHOR TIDAK KETEMU: $file\n"; return; }
    if (!is_file($path . '.bak')) { copy($path, $path . '.bak'); }
    file_put_contents($path, str_replace($old, $new, $src));
    echo "OK: $file\n";
}

$msg = 'Akun KO tidak dapat masuk ke sistem ini. Silakan gunakan aplikasi ACS.';

// 1-2. Validasi
patch('app/Http/Requests/Admin/StoreUserRequest.php', "'in:admin,kasir,driver'", "'in:admin,kasir,driver,ko'");
patch('app/Http/Requests/Admin/UpdateUserRequest.php', "'in:admin,kasir,driver'", "'in:admin,kasir,driver,ko'");

// 3. Service
patch('app/Services/Admin/UserService.php', "['admin', 'kasir', 'driver']", "['admin', 'kasir', 'driver', 'ko']");

// 4-5. Dropdown
patch('resources/views/admin/users/create.blade.php', "'driver' => 'Driver']", "'driver' => 'Driver', 'ko' => 'KO']");
patch('resources/views/admin/users/edit.blade.php', "'driver' => 'Driver']", "'driver' => 'Driver', 'ko' => 'KO']");

// 6. Badge index
patch('resources/views/admin/users/index.blade.php', '{{ ucfirst($user->role) }}', '{{ $user->role === \'ko\' ? \'KO\' : ucfirst($user->role) }}');

// 7. Login email/password
$old = <<<'X'
$redirectPath = $user->role === 'admin' ? '/admin/dashboard' : '/kasir/dashboard';
X;
$new = "if (\$user->role === 'ko') { \$this->authService->logout(); \$request->session()->invalidate(); \$request->session()->regenerateToken(); return back()->withErrors(['email' => '$msg'])->onlyInput('email'); } " . $old;
patch('app/Http/Controllers/Auth/LoginController.php', $old, $new);

// 8. Login Google (user sudah terhubung)
$old = 'Auth::login($finduser);';
$new = "if (\$finduser->role === 'ko') { return redirect()->route('login')->withErrors(['email' => '$msg']); } " . $old;
patch('app/Http/Controllers/Auth/GoogleController.php', $old, $new);

// 9. Login Google (email sudah ada)
$old = 'if($existingUser) {';
$new = "if(\$existingUser && \$existingUser->role === 'ko') { return redirect()->route('login')->withErrors(['email' => '$msg']); } " . $old;
patch('app/Http/Controllers/Auth/GoogleController.php', $old, $new);

// 10. Penjaga redirect-dashboard (cegah loop)
$old = '$user = Auth::user();';
$new = $old . " if (\$user->role === 'ko') { Auth::logout(); request()->session()->invalidate(); request()->session()->regenerateToken(); return redirect('/login')->withErrors(['email' => '$msg']); }";
patch('routes/web.php', $old, $new);

// 11. File migrasi enum (jika belum ada)
$mig = $root . '/database/migrations/2026_10_04_222111_add_ko_to_users_role_enum.php';
if (!is_file($mig)) {
    file_put_contents($mig, <<<'M'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','kasir','driver','ko') NOT NULL DEFAULT 'kasir'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','kasir','driver') NOT NULL DEFAULT 'kasir'");
    }
};
M);
    echo "OK: migrasi ditulis\n";
} else { echo "SUDAH: migrasi ada\n"; }