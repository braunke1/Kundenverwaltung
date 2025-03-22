<?php
/**
 * Automatisches Backup-Skript
 * Dieses Skript kann per Cronjob regelmäßig ausgeführt werden, um ein Datenbank-Backup zu erstellen.
 * 
 * Beispiel für einen täglichen Cronjob um 3 Uhr morgens:
 * 0 3 * * * php /pfad/zu/auto_backup.php
 */

// Konfiguration laden
$scriptDir = dirname(__FILE__);
require_once $scriptDir . '/../config/database.php';

// Backup-Verzeichnis einrichten
$backupDir = $scriptDir . '/../import-export/backups/auto';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Datum und Uhrzeit für den Dateinamen
$date = date('Y-m-d_H-i-s');
$backupFile = $backupDir . "/db_backup_{$date}.sql";

// Datenbank-Server-Konfiguration aus der Config-Datei auslesen
$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPass = DB_PASS;
$dbName = DB_NAME;

// Backup-Befehl für mysqldump erstellen
$command = "mysqldump --opt --host={$dbHost} --user={$dbUser} --password={$dbPass} {$dbName} > {$backupFile}";

// Backup ausführen
system($command, $returnValue);

if ($returnValue === 0) {
    // ZIP-Datei erstellen
    $zipFile = $backupDir . "/db_backup_{$date}.zip";
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($backupFile, basename($backupFile));
        $zip->close();
        
        // SQL-Datei löschen, da wir nur die ZIP-Datei behalten wollen
        unlink($backupFile);
        
        echo "Backup erfolgreich erstellt: " . basename($zipFile) . PHP_EOL;
        
        // Alte Backups löschen (mehr als 7 Tage alt)
        cleanupOldBackups($backupDir, 7);
    } else {
        echo "Fehler beim Erstellen der ZIP-Datei." . PHP_EOL;
    }
} else {
    echo "Fehler beim Erstellen des Backups." . PHP_EOL;
}

/**
 * Löscht Backup-Dateien, die älter als eine bestimmte Anzahl von Tagen sind
 * 
 * @param string $backupDir Verzeichnis mit den Backup-Dateien
 * @param int $daysToKeep Anzahl der Tage, die Backups behalten werden sollen
 */
function cleanupOldBackups($backupDir, $daysToKeep) {
    $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
    
    $files = glob($backupDir . "/*.zip");
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < $cutoffTime) {
            unlink($file);
            echo "Altes Backup gelöscht: " . basename($file) . PHP_EOL;
        }
    }
}
?>