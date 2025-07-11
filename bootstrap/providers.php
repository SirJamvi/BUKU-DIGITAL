<?php

return [
    App\Providers\AppServiceProvider::class,
    // Daftarkan provider kustom sesuai SOP
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
    App\Providers\PermissionServiceProvider::class, // Jika Anda membuatnya
    App\Providers\ViewServiceProvider::class,       // Jika Anda membuatnya
];