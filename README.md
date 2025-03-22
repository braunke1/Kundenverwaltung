ERP/CRM-System

Dieses ERP/CRM-System ermöglicht die Verwaltung von Kunden, Terminen und die Erstellung von Abrechnungen.

Funktionen

Kundenverwaltung: Privat- und Geschäftskunden erfassen und verwalten
Terminverwaltung: Termine planen, in einer Kalender- oder Listenansicht anzeigen
Leistungserfassung: Erfassen von Leistungen während eines Termins
Abrechnungsübersicht: Zusammenfassung der abrechenbaren Leistungen
Import/Export: Daten im CSV- oder JSON-Format importieren und exportieren
Backup: Automatische und manuelle Backups der Datenbank
Installation

Übertragen Sie alle Dateien auf Ihren Webserver
Konfigurieren Sie die Datenbankverbindung in der Datei config/database.php
Importieren Sie das Datenbankschema aus der SQL-Datei
Stellen Sie sicher, dass PHP Schreibrechte für die Verzeichnisse import-export/imports, import-export/exports und import-export/backups hat
Datenbank-Konfiguration

Passen Sie die Datenbankverbindung in der Datei config/database.php an Ihre Umgebung an:

// Datenbankkonfiguration
define('DB_HOST', 'localhost');  // Datenbankhost
define('DB_USER', 'username');   // Datenbankbenutzer
define('DB_PASS', 'password');   // Datenbankpasswort
define('DB_NAME', 'erp_crm_system'); // Datenbankname
Automatisches Backup einrichten

Um automatische Backups einzurichten, fügen Sie einen Cronjob für das Skript scripts/auto_backup.php hinzu. Hier ist ein Beispiel für ein tägliches Backup um 3 Uhr morgens:

0 3 * * * php /pfad/zu/scripts/auto_backup.php
Ersetzen Sie /pfad/zu/ durch den tatsächlichen Pfad zu Ihrem Webserver-Verzeichnis.

Hinweise zum Backup

Das automatische Backup behält die Dateien der letzten 7 Tage
Die Backup-Dateien werden im Verzeichnis import-export/backups/auto gespeichert
Manuelle Backups können über die Benutzeroberfläche erstellt werden
Import/Export von Daten

Export

Gehen Sie zur Seite "Import/Export" in der Navigation
Wählen Sie aus, welche Daten exportiert werden sollen (Kunden, Termine, Leistungen)
Wählen Sie das Format (CSV oder JSON)
Klicken Sie auf "Exportieren"
Import

Gehen Sie zur Seite "Import/Export" in der Navigation
Wählen Sie aus, welche Art von Daten importiert werden soll
Wählen Sie die zu importierende Datei (CSV oder JSON)
Klicken Sie auf "Importieren"
Sicherheitshinweise

Stellen Sie sicher, dass der Zugriff auf das Verzeichnis import-export/backups durch Webserver-Regeln beschränkt ist
Regelmäßige Backups sollten auf einem externen Speicher gesichert werden
Die automatische Backup-Funktion ersetzt keine vollständige Server-Sicherung
Systemanforderungen

PHP 7.4 oder höher
MySQL 5.7 oder höher
Webserver (Apache, Nginx, etc.)
Lizenz

Dieses ERP/CRM-System ist für Ihre private Nutzung erstellt worden und darf nicht ohne Erlaubnis verbreitet werden.
