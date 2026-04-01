<?php
/**
 * ILDEN KI Consulting — Formular-Endpunkt
 * Empfängt Anfragen, speichert in DB, sendet E-Mail
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ALLOWED_ORIGINS)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Nur POST erlaubt']);
    exit;
}

// --- Rate Limiting ---
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$db = new SQLite3(DB_PATH);

// Alte Einträge löschen
$cutoff = time() - RATE_LIMIT_WINDOW;
$stmt = $db->prepare("DELETE FROM rate_limits WHERE timestamp < :cutoff");
$stmt->bindValue(':cutoff', $cutoff, SQLITE3_INTEGER);
$stmt->execute();

// Anfragen zählen
$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM rate_limits WHERE ip = :ip");
$stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
$result = $stmt->execute()->fetchArray();
if ($result['cnt'] >= RATE_LIMIT_MAX) {
    http_response_code(429);
    echo json_encode(['error' => 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.']);
    $db->close();
    exit;
}

// Rate-Limit Eintrag hinzufügen
$stmt = $db->prepare("INSERT INTO rate_limits (ip, timestamp) VALUES (:ip, :ts)");
$stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
$stmt->bindValue(':ts', time(), SQLITE3_INTEGER);
$stmt->execute();

// --- Daten einlesen ---
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_POST;
}

// --- Validierung ---
$errors = [];

$anliegen = trim($input['anliegen'] ?? '');
$vorname = trim($input['vorname'] ?? '');
$nachname = trim($input['nachname'] ?? '');
$email = trim($input['email'] ?? '');
$unternehmen = trim($input['unternehmen'] ?? '');
$position = trim($input['position'] ?? '');
$budget = trim($input['budget'] ?? '');
$zeitrahmen = trim($input['zeitrahmen'] ?? '');
$nachricht = trim($input['nachricht'] ?? '');

if (empty($vorname)) $errors[] = 'Vorname ist erforderlich';
if (empty($nachname)) $errors[] = 'Nachname ist erforderlich';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Gültige E-Mail-Adresse erforderlich';
if (empty($anliegen)) $errors[] = 'Bitte wählen Sie ein Anliegen';

// Honeypot (falls im Formular ein verstecktes Feld 'website' existiert)
if (!empty($input['website'] ?? '')) {
    // Bot erkannt — still ignorieren
    echo json_encode(['success' => true, 'message' => 'Vielen Dank!']);
    $db->close();
    exit;
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['error' => 'Validierungsfehler', 'details' => $errors]);
    $db->close();
    exit;
}

// --- Sanitize ---
$anliegen = htmlspecialchars($anliegen, ENT_QUOTES, 'UTF-8');
$vorname = htmlspecialchars($vorname, ENT_QUOTES, 'UTF-8');
$nachname = htmlspecialchars($nachname, ENT_QUOTES, 'UTF-8');
$unternehmen = htmlspecialchars($unternehmen, ENT_QUOTES, 'UTF-8');
$position = htmlspecialchars($position, ENT_QUOTES, 'UTF-8');
$budget = htmlspecialchars($budget, ENT_QUOTES, 'UTF-8');
$zeitrahmen = htmlspecialchars($zeitrahmen, ENT_QUOTES, 'UTF-8');
$nachricht = htmlspecialchars($nachricht, ENT_QUOTES, 'UTF-8');
$userAgent = htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? '', ENT_QUOTES, 'UTF-8');

// --- In DB speichern ---
$stmt = $db->prepare("
    INSERT INTO anfragen (anliegen, vorname, nachname, email, unternehmen, position, budget, zeitrahmen, nachricht, ip_adresse, user_agent)
    VALUES (:anliegen, :vorname, :nachname, :email, :unternehmen, :position, :budget, :zeitrahmen, :nachricht, :ip, :ua)
");
$stmt->bindValue(':anliegen', $anliegen, SQLITE3_TEXT);
$stmt->bindValue(':vorname', $vorname, SQLITE3_TEXT);
$stmt->bindValue(':nachname', $nachname, SQLITE3_TEXT);
$stmt->bindValue(':email', $email, SQLITE3_TEXT);
$stmt->bindValue(':unternehmen', $unternehmen, SQLITE3_TEXT);
$stmt->bindValue(':position', $position, SQLITE3_TEXT);
$stmt->bindValue(':budget', $budget, SQLITE3_TEXT);
$stmt->bindValue(':zeitrahmen', $zeitrahmen, SQLITE3_TEXT);
$stmt->bindValue(':nachricht', $nachricht, SQLITE3_TEXT);
$stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
$stmt->bindValue(':ua', $userAgent, SQLITE3_TEXT);
$stmt->execute();

$anfrageId = $db->lastInsertRowID();
$db->close();

// --- E-Mail senden ---
$anliegenMap = [
    'strategie' => 'KI-Strategie',
    'automatisierung' => 'Automatisierung',
    'datenanalyse' => 'Datenanalyse',
    'schulung' => 'KI-Schulung',
    'implementierung' => 'Implementierung',
    'compliance' => 'Compliance & Ethik',
];
$anliegenText = $anliegenMap[$anliegen] ?? $anliegen;

$budgetMap = [
    '10k-25k' => '10.000 – 25.000 EUR',
    '25k-50k' => '25.000 – 50.000 EUR',
    '50k-100k' => '50.000 – 100.000 EUR',
    '100k+' => '100.000+ EUR',
    'unklar' => 'Noch unklar',
];
$budgetText = $budgetMap[$budget] ?? ($budget ?: 'Nicht angegeben');

$zeitrahmenMap = [
    'sofort' => 'So schnell wie möglich',
    '1-3m' => 'In 1–3 Monaten',
    '3-6m' => 'In 3–6 Monaten',
    'explorativ' => 'Noch explorativ',
];
$zeitrahmenText = $zeitrahmenMap[$zeitrahmen] ?? ($zeitrahmen ?: 'Nicht angegeben');

$subject = MAIL_SUBJECT_PREFIX . " $anliegenText — $vorname $nachname";

$body = "
═══════════════════════════════════════
  NEUE ANFRAGE – ILDEN KI Consulting
═══════════════════════════════════════

Anfrage-ID:    #$anfrageId
Eingegangen:   " . date('d.m.Y H:i') . " Uhr
Anliegen:      $anliegenText

───────────────────────────────────────
  KONTAKTDATEN
───────────────────────────────────────

Name:          $vorname $nachname
E-Mail:        $email
Unternehmen:   " . ($unternehmen ?: '–') . "
Position:      " . ($position ?: '–') . "

───────────────────────────────────────
  PROJEKTDETAILS
───────────────────────────────────────

Budget:        $budgetText
Zeitrahmen:    $zeitrahmenText

Nachricht:
$nachricht

───────────────────────────────────────
IP: $ip
───────────────────────────────────────
";

$headers = "From: ILDEN KI Consulting <" . MAIL_FROM . ">\r\n";
$headers .= "Reply-To: $vorname $nachname <$email>\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "X-Anfrage-ID: $anfrageId\r\n";

$mailSent = @mail(MAIL_TO, $subject, $body, $headers);

// --- Response ---
echo json_encode([
    'success' => true,
    'message' => 'Vielen Dank! Wir melden uns innerhalb von 24 Stunden bei Ihnen.',
    'id' => $anfrageId,
    'email_sent' => $mailSent,
]);
