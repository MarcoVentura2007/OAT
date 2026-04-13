<?php
/**
 * OTA — Configurazione Globale
 * Rinominare in config.php e NON committare su git (.gitignore)
 */

// ── Database ──────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_PORT',    3306);
define('DB_NAME',    'ota_db');
define('DB_USER',    'user');       // utente MySQL dedicato (non root)
define('DB_PASS',    'OTA_Users_2026'); // password sicura
define('DB_CHARSET', 'utf8mb4');

// ── Applicazione ──────────────────────────────────────────
define('APP_NAME',    'OTA Admin');
define('APP_URL',     'http://localhost/CircoloTennis');  // URL pubblico senza slash finale
define('ADMIN_URL',   APP_URL . '/admin_ota_2026');
define('API_URL',     APP_URL . '/api');

// ── Upload foto ───────────────────────────────────────────
define('UPLOAD_DIR',      __DIR__ . '/../public/uploads/');
define('UPLOAD_URL',      APP_URL . '/public/uploads/');
define('MAX_FILE_SIZE',   8 * 1024 * 1024);  // 8 MB
define('ALLOWED_TYPES',   ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('ALLOWED_EXT',     ['jpg', 'jpeg', 'png', 'webp', 'gif']);
define('THUMB_WIDTH',     400);  // larghezza miniatura (px)

// ── Sessione / Token ──────────────────────────────────────
define('TOKEN_TTL',       8 * 3600);     // 8 ore in secondi
define('TOKEN_HEADER',    'X-OTA-Token');

// ── CORS (origini permesse per le API) ───────────────────
define('CORS_ORIGINS', [
    'https://tuodominio.it',
    'http://localhost',
    'http://127.0.0.1',
]);

// ── Ambiente ──────────────────────────────────────────────
define('APP_ENV',  'production');   // 'development' | 'production'
define('APP_DEBUG', APP_ENV === 'development');

// Mostra errori solo in development
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
