<?php
/**
 * ILDEN KI Consulting — Backend-Konfiguration
 */

// ===== E-MAIL EINSTELLUNGEN =====
define('MAIL_TO', 'info@ilden-consulting.de');
define('MAIL_FROM', 'noreply@ilden-consulting.de');
define('MAIL_SUBJECT_PREFIX', '[ILDEN Anfrage]');

// ===== DATENBANK =====
define('DB_PATH', __DIR__ . '/data/anfragen.sqlite');

// ===== SICHERHEIT =====
// CSRF-Token Secret (bitte ändern!)
define('CSRF_SECRET', 'ilden_csrf_' . md5(__DIR__));

// Rate Limiting: Max Anfragen pro IP pro Stunde
define('RATE_LIMIT_MAX', 5);
define('RATE_LIMIT_WINDOW', 3600);

// Erlaubte Origins (für CORS)
define('ALLOWED_ORIGINS', [
    'http://localhost',
    'http://127.0.0.1',
    'https://ilden-consulting.de',
    'https://www.ilden-consulting.de',
]);

// ===== ADMIN =====
// Admin-Passwort (bitte ändern!)
define('ADMIN_PASSWORD', 'ILDEN2026!admin');
