<?php
/**
 * Datenbank initialisieren — einmalig ausführen
 */
require_once __DIR__ . '/config.php';

// Verzeichnis erstellen
$dir = dirname(DB_PATH);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$db = new SQLite3(DB_PATH);

$db->exec("
    CREATE TABLE IF NOT EXISTS anfragen (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        anliegen TEXT NOT NULL,
        vorname TEXT NOT NULL,
        nachname TEXT NOT NULL,
        email TEXT NOT NULL,
        unternehmen TEXT DEFAULT '',
        position TEXT DEFAULT '',
        budget TEXT DEFAULT '',
        zeitrahmen TEXT DEFAULT '',
        nachricht TEXT DEFAULT '',
        ip_adresse TEXT DEFAULT '',
        user_agent TEXT DEFAULT '',
        erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
        gelesen INTEGER DEFAULT 0,
        notizen TEXT DEFAULT '',
        status TEXT DEFAULT 'neu'
    );

    CREATE TABLE IF NOT EXISTS rate_limits (
        ip TEXT NOT NULL,
        timestamp INTEGER NOT NULL
    );

    CREATE INDEX IF NOT EXISTS idx_anfragen_status ON anfragen(status);
    CREATE INDEX IF NOT EXISTS idx_anfragen_erstellt ON anfragen(erstellt_am);
    CREATE INDEX IF NOT EXISTS idx_rate_ip ON rate_limits(ip);
");

echo "Datenbank erfolgreich erstellt: " . DB_PATH . "\n";
echo "Tabellen: anfragen, rate_limits\n";

$db->close();
