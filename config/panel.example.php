<?php
/**
 * Blog panel yapılandırması — ŞABLON.
 *
 * Kullanımı:
 *   1. Bu dosyayı `config/panel.php` adıyla kopyalayın.
 *   2. `password_hash` değerini kendi parolanızın hash'iyle değiştirin:
 *        php -r "echo password_hash('PAROLANIZ', PASSWORD_DEFAULT), PHP_EOL;"
 *      (Panel, config dosyası yokken açtığınızda bu hash'i sizin için de üretir.)
 *   3. `config/panel.php` dosyasını sunucuda `public_html/config/` altına yükleyin.
 *
 * `config/panel.php` .gitignore'dadır: parola hash'i asla depoya girmez.
 * `config/` dizinine HTTP erişimi kök .htaccess ile zaten kapalıdır.
 */

return [
    // password_hash() çıktısı. Düz metin parola KOYMAYIN.
    'password_hash' => '$2y$10$ORNEK_HASH_BURAYA_GELECEK_DEGISTIRIN',

    // Oturum çerezinin adı. Sitedeki diğer çerezlerle çakışmamalı.
    'session_name' => 'drnpanel',

    // Bu kadar başarısız denemeden sonra giriş kilitlenir.
    'max_attempts' => 5,

    // Kilit süresi (saniye).
    'lockout_seconds' => 900,

    // Bu kadar hareketsizlikten sonra oturum düşer (saniye).
    'idle_seconds' => 7200,
];
