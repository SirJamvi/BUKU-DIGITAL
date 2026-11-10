<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI WhatsApp Order System Configurations (BukuDig - Adin Crystal)
    |--------------------------------------------------------------------------
    |
    | Konfigurasi terpusat untuk debounce, rate-limiting, human-handoff,
    | notifikasi grup admin, dan parameter jendela pesan susulan/ack.
    |
    */

    // Jeda debounce penggabungan bubble chat (detik). Default: 7 detik
    'debounce_seconds' => (int) env('AI_ORDER_DEBOUNCE_SECONDS', 7),

    // Jeda debounce singkat untuk pesan ACK dan BATAL/UBAH (detik). Default: 2 detik
    'debounce_short_seconds' => (int) env('AI_ORDER_DEBOUNCE_SHORT_SECONDS', 2),

    // Menggunakan pemutusan koneksi cepat fastcgi/litespeed_finish_request agar Fonnte tidak timeout
    'after_response' => filter_var(env('AI_ORDER_AFTER_RESPONSE', true), FILTER_VALIDATE_BOOLEAN),

    // Mode dry run (hanya catat ke log, tidak mengirim pesan WhatsApp ke pelanggan sungguhan)
    'dry_run' => filter_var(env('AI_ORDER_DRY_RUN', false), FILTER_VALIDATE_BOOLEAN),

    // Batas maksimal pesan WhatsApp yang diproses per nomor per menit
    'rate_limit' => (int) env('AI_ORDER_RATE_LIMIT', 20),

    // Secret token opsional untuk verifikasi header/query webhook Fonnte
    'webhook_secret' => env('FONNTE_WEBHOOK_SECRET', ''),

    // Jendela waktu pesan susulan (menit) setelah order validated untuk menerima penegasan/perubahan
    'followup_minutes' => (int) env('AI_ORDER_FOLLOWUP_MINUTES', 5),

    // Jendela waktu pengakuan/terima kasih (menit) setelah order validated
    'ack_minutes' => (int) env('AI_ORDER_ACK_MINUTES', 10),

    // Durasi pengalihan human mode / bantuan staf (menit). Default: 30 menit
    'handoff_minutes' => (int) env('AI_ORDER_HANDOFF_MINUTES', 30),

    // Apakah bot merespons pesan terima kasih/oke ("Siap Kak 👍") atau tetap diam
    'ack_reply' => filter_var(env('AI_ORDER_ACK_REPLY', true), FILTER_VALIDATE_BOOLEAN),

    // ID Grup WhatsApp Admin (format ...@g.us) untuk notifikasi internal
    'admin_group_id' => env('AI_ORDER_ADMIN_GROUP_ID', ''),

    // Aktifkan pengiriman notifikasi grup admin
    'admin_notify' => filter_var(env('AI_ORDER_ADMIN_NOTIFY', true), FILTER_VALIDATE_BOOLEAN),
];

