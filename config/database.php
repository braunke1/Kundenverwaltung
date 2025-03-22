<?php
// Datenbankkonfiguration
define('DB_HOST', 'localhost');  // Datenbankhost
define('DB_USER', 'DB-User');       // Datenbankbenutzer
define('DB_PASS', 'DB-Pass');           // Datenbankpasswort
define('DB_NAME', 'DB-Name'); // Datenbankname

// Datenbankverbindung erstellen
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Überprüfen der Verbindung
    if ($conn->connect_error) {
        die("Verbindung zur Datenbank fehlgeschlagen: " . $conn->connect_error);
    }
    
    // Zeichensatz auf UTF-8 setzen
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

// Hilfsfunktion zum Bereinigen von Eingabedaten
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Hilfsfunktion zum Umwandeln von MySQL-Datum in deutsches Format
function formatDate($date, $withTime = false) {
    if (empty($date)) return '';
    
    $timestamp = strtotime($date);
    
    if ($withTime) {
        return date('d.m.Y H:i', $timestamp);
    } else {
        return date('d.m.Y', $timestamp);
    }
}

// Hilfsfunktion zum Umwandeln von deutschem Datum in MySQL-Format
function formatDateForMysql($date) {
    if (empty($date)) return null;
    
    $parts = explode('.', $date);
    if (count($parts) === 3) {
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    
    return $date;
}

// Diese Funktion am Anfang der config/database.php hinzufügen, damit sie überall verfügbar ist
/**
 * Zuverlässige Weiterleitung nach Formularverarbeitung
 * Diese Methode kombiniert mehrere Ansätze und sollte auf den meisten Serverumgebungen funktionieren
 * 
 * @param string $url Die Ziel-URL für die Weiterleitung
 * @param string $message Optionale Erfolgsmeldung
 */
function redirectTo($url, $message = '') {
    // Erfolgsmeldung in Session speichern, falls vorhanden
    if (!empty($message)) {
        $_SESSION['success_message'] = $message;
    }
    
    // Sicherstellen, dass keine Ausgabe gesendet wurde (falls möglich)
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Zuerst versuchen, mit PHP-Header weiterzuleiten
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    }
    
    // Falls das nicht klappt, mit JavaScript weiterleiten
    echo '<script>window.location.href = "' . $url . '";</script>';
    
    // Als Fallback eine Seite mit einem Link anzeigen
    echo '<meta http-equiv="refresh" content="0;url=' . $url . '">';
    echo '<p>Klicken Sie <a href="' . $url . '">hier</a>, wenn Sie nicht automatisch weitergeleitet werden.</p>';
    exit;
}

?>
