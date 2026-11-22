<?php

return [
    'name'    => 'Welcome Party 2026',
    'tagline' => 'The Penthouse of Freedom',

    // Password awal akun Panitia. Jika kosong, seeder membuat password acak.
    'admin_password' => env('ADMIN_PASSWORD'),

    // Paksa URL https:// (untuk di belakang reverse proxy Nginx/Railway/Render)
    'force_https' => (bool) env('FORCE_HTTPS', false),
];
