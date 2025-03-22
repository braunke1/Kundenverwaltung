<?php
require_once '../includes/header.php';

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Meldungen initialisieren
$success = '';
$error = '';

// PHP-basierte Backup-Funktion
function createDatabaseBackup($backupDir) {
    $conn = getDbConnection();
    
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $date = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . "/backup_{$date}.sql";
    
    try {
        $tables = array();
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        $output = "-- Database Backup - " . date('Y-m-d H:i:s') . "\n\n";
        
        // Tabellen-Struktur und Daten für jede Tabelle exportieren
        foreach ($tables as $table) {
            // Struktur der Tabelle abrufen
            $result = $conn->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch_row();
            $output .= "\n\n" . $row[1] . ";\n\n";
            
            // Daten der Tabelle abrufen
            $result = $conn->query("SELECT * FROM `$table`");
            $numRows = $result->num_rows;
            
            if ($numRows > 0) {
                $columnNames = array();
                $fields = $result->fetch_fields();
                foreach ($fields as $field) {
                    $columnNames[] = "`" . $field->name . "`";
                }
                
                $output .= "INSERT INTO `$table` (" . implode(", ", $columnNames) . ") VALUES ";
                
                $rowCount = 0;
                $result->data_seek(0); // Zurück zum Anfang
                
                while ($row = $result->fetch_row()) {
                    $rowCount++;
                    $output .= "(";
                    
                    $columnCount = 0;
                    foreach ($row as $value) {
                        $columnCount++;
                        
                        if ($value === null) {
                            $output .= "NULL";
                        } else {
                            $output .= "'" . $conn->real_escape_string($value) . "'";
                        }
                        
                        if ($columnCount < count($row)) {
                            $output .= ", ";
                        }
                    }
                    
                    $output .= ")";
                    
                    if ($rowCount < $numRows) {
                        $output .= ",\n";
                    } else {
                        $output .= ";\n";
                    }
                }
            }
        }
        
        // SQL-Datei speichern
        file_put_contents($backupFile, $output);
        
        // Eine ZIP-Datei erstellen
        $zipFile = $backupDir . "/backup_{$date}.zip";
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($backupFile, basename($backupFile));
            $zip->close();
            
            // SQL-Datei löschen, da wir nur die ZIP-Datei behalten wollen
            unlink($backupFile);
            
            return $zipFile;
        } else {
            return $backupFile;
        }
    } catch (Exception $e) {
        // Bei Fehler die Exception zurückgeben
        return false;
    }
}

// Export-Funktionalität
if (isset($_POST['export']) && !empty($_POST['export_type'])) {
    $exportType = sanitizeInput($_POST['export_type']);
    $exportFormat = isset($_POST['export_format']) ? sanitizeInput($_POST['export_format']) : 'csv';
    
    // Pfad zum Export-Verzeichnis
    $exportDir = __DIR__ . '/exports';
    if (!file_exists($exportDir)) {
        mkdir($exportDir, 0755, true);
    }
    
    // Datum für Dateinamen
    $date = date('Y-m-d_H-i-s');
    
    // SQL-Abfrage je nach Exporttyp
    switch ($exportType) {
        case 'customers':
            $sql = "SELECT * FROM customers";
            $filename = "kunden_export_{$date}.{$exportFormat}";
            break;
        case 'appointments':
            $sql = "SELECT a.*, 
                    CASE 
                        WHEN c.customer_type = 'business' THEN c.company_name 
                        ELSE CONCAT(c.first_name, ' ', c.last_name) 
                    END as customer_name
                    FROM appointments a
                    JOIN customers c ON a.customer_id = c.customer_id";
            $filename = "termine_export_{$date}.{$exportFormat}";
            break;
        case 'services':
            $sql = "SELECT s.*, a.title as appointment_title,
                    CASE 
                        WHEN c.customer_type = 'business' THEN c.company_name 
                        ELSE CONCAT(c.first_name, ' ', c.last_name) 
                    END as customer_name
                    FROM services s
                    JOIN appointments a ON s.appointment_id = a.appointment_id
                    JOIN customers c ON a.customer_id = c.customer_id";
            $filename = "leistungen_export_{$date}.{$exportFormat}";
            break;
        default:
            $error = "Ungültiger Export-Typ.";
            break;
    }
    
    if (empty($error)) {
        // Daten abfragen
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            // Vollständiger Pfad zur Export-Datei
            $filePath = $exportDir . '/' . $filename;
            
            if ($exportFormat === 'csv') {
                // CSV-Export
                $fp = fopen($filePath, 'w');
                
                // Header-Zeile schreiben
                $firstRow = $result->fetch_assoc();
                fputcsv($fp, array_keys($firstRow), ';');
                
                // Erste Zeile wieder zurück in das Ergebnis-Array stellen
                $result->data_seek(0);
                
                // Alle Daten schreiben
                while ($row = $result->fetch_assoc()) {
                    fputcsv($fp, $row, ';');
                }
                
                fclose($fp);
                
                // Datei zum Download anbieten
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                readfile($filePath);
                exit;
            } elseif ($exportFormat === 'json') {
                // JSON-Export
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                
                file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
                
                // Datei zum Download anbieten
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                readfile($filePath);
                exit;
            } else {
                $error = "Ungültiges Export-Format.";
            }
        } else {
            $error = "Keine Daten zum Exportieren gefunden.";
        }
    }
}

// Import-Funktionalität
if (isset($_POST['import']) && !empty($_FILES['import_file']['name'])) {
    $importType = sanitizeInput($_POST['import_type']);
    $fileName = $_FILES['import_file']['name'];
    $fileTmpName = $_FILES['import_file']['tmp_name'];
    $fileSize = $_FILES['import_file']['size'];
    $fileError = $_FILES['import_file']['error'];
    
    // Überprüfen, ob ein Fehler beim Hochladen aufgetreten ist
    if ($fileError === 0) {
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Gültige Dateiformate
        $allowed = ['csv', 'json'];
        
        if (in_array($fileExt, $allowed)) {
            // Datei in temporäres Verzeichnis verschieben
            $importDir = __DIR__ . '/imports';
            if (!file_exists($importDir)) {
                mkdir($importDir, 0755, true);
            }
            
            $newFilePath = $importDir . '/' . $fileName;
            move_uploaded_file($fileTmpName, $newFilePath);
            
            // Daten importieren je nach Dateityp
            if ($fileExt === 'csv') {
                // CSV importieren
                $importData = [];
                if (($handle = fopen($newFilePath, "r")) !== FALSE) {
                    // Header-Zeile lesen
                    $header = fgetcsv($handle, 0, ';');
                    
                    // Daten lesen
                    while (($row = fgetcsv($handle, 0, ';')) !== FALSE) {
                        $data = [];
                        foreach ($header as $i => $key) {
                            if (isset($row[$i])) {
                                $data[$key] = $row[$i];
                            } else {
                                $data[$key] = '';
                            }
                        }
                        $importData[] = $data;
                    }
                    fclose($handle);
                }
            } else if ($fileExt === 'json') {
                // JSON importieren
                $jsonData = file_get_contents($newFilePath);
                $importData = json_decode($jsonData, true);
            }
            
            // Daten in die Datenbank einfügen
            if (!empty($importData)) {
                $importCount = 0;
                $conn->begin_transaction();
                
                try {
                    switch ($importType) {
                        case 'customers':
                            // Felder vorbereiten
                            $requiredFields = ['customer_type'];
                            $optionalFields = [
                                'first_name', 'last_name', 'email', 'phone', 
                                'street', 'postal_code', 'city', 'country', 
                                'company_name', 'tax_id', 'website', 'industry', 'notes'
                            ];
                            
                            // Prüfen und bereinigen
                            foreach ($importData as &$customer) {
                                // customer_type validieren
                                if (isset($customer['customer_type'])) {
                                    $customer['customer_type'] = strtolower(trim($customer['customer_type']));
                                    if ($customer['customer_type'] !== 'private' && $customer['customer_type'] !== 'business') {
                                        $customer['customer_type'] = 'private'; // Standard setzen
                                    }
                                } else {
                                    $customer['customer_type'] = 'private'; // Standard setzen
                                }
                                
                                // Optionale Felder prüfen
                                foreach ($optionalFields as $field) {
                                    if (!isset($customer[$field])) {
                                        $customer[$field] = null;
                                    }
                                }
                            }
                            
                            // SQL zum Einfügen von Kunden
                            $stmt = $conn->prepare("INSERT INTO customers 
                                (customer_type, first_name, last_name, email, phone, 
                                street, postal_code, city, country, 
                                company_name, tax_id, website, industry, notes)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            
                            foreach ($importData as $customer) {
                                $stmt->bind_param("ssssssssssssss", 
                                    $customer['customer_type'], 
                                    $customer['first_name'], 
                                    $customer['last_name'], 
                                    $customer['email'], 
                                    $customer['phone'], 
                                    $customer['street'], 
                                    $customer['postal_code'], 
                                    $customer['city'], 
                                    $customer['country'], 
                                    $customer['company_name'], 
                                    $customer['tax_id'], 
                                    $customer['website'], 
                                    $customer['industry'], 
                                    $customer['notes']);
                                $stmt->execute();
                                $importCount++;
                            }
                            break;
                            
                        // Weitere Importtypen können hier hinzugefügt werden
                            
                        default:
                            throw new Exception("Ungültiger Import-Typ.");
                            break;
                    }
                    
                    $conn->commit();
                    $success = "{$importCount} Datensätze wurden erfolgreich importiert.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Fehler beim Import: " . $e->getMessage();
                }
            } else {
                $error = "Keine gültigen Daten zum Importieren gefunden.";
            }
        } else {
            $error = "Ungültiges Dateiformat. Erlaubt sind: CSV und JSON.";
        }
    } else {
        $error = "Fehler beim Hochladen der Datei.";
    }
}

// Backup-Funktion
if (isset($_POST['create_backup'])) {
    $backupDir = __DIR__ . '/backups';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // PHP-basiertes Backup erstellen
    $backupFile = createDatabaseBackup($backupDir);
    
    if ($backupFile) {
        // ZIP-Datei zum Download anbieten
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($backupFile) . '"');
        header('Content-Length: ' . filesize($backupFile));
        header('Pragma: no-cache');
        readfile($backupFile);
        exit;
    } else {
        $error = "Fehler beim Erstellen des Backups.";
    }
}

// Backup-Wiederherstellung
if (isset($_POST['restore_backup']) && !empty($_FILES['backup_file']['name'])) {
    $fileName = $_FILES['backup_file']['name'];
    $fileTmpName = $_FILES['backup_file']['tmp_name'];
    $fileSize = $_FILES['backup_file']['size'];
    $fileError = $_FILES['backup_file']['error'];
    
    // Überprüfen, ob ein Fehler beim Hochladen aufgetreten ist
    if ($fileError === 0) {
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if ($fileExt === 'zip' || $fileExt === 'sql') {
            // Backup-Verzeichnis
            $backupDir = __DIR__ . '/backups';
            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $uploadedFile = $backupDir . '/' . $fileName;
            move_uploaded_file($fileTmpName, $uploadedFile);
            
            // SQL-Datei extrahieren, wenn es eine ZIP-Datei ist
            $sqlFile = $uploadedFile;
            if ($fileExt === 'zip') {
                $zip = new ZipArchive();
                if ($zip->open($uploadedFile) === TRUE) {
                    $sqlFileName = '';
                    
                    // Erste SQL-Datei im Archiv suchen
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        if (pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
                            $sqlFileName = $filename;
                            break;
                        }
                    }
                    
                    if (!empty($sqlFileName)) {
                        $sqlFile = $backupDir . '/' . basename($sqlFileName);
                        copy("zip://{$uploadedFile}#{$sqlFileName}", $sqlFile);
                        $zip->close();
                    } else {
                        $error = "Keine SQL-Datei im ZIP-Archiv gefunden.";
                        $zip->close();
                        unlink($uploadedFile);
                        $sqlFile = '';
                    }
                } else {
                    $error = "Fehler beim Öffnen der ZIP-Datei.";
                    unlink($uploadedFile);
                    $sqlFile = '';
                }
            }
            
            // Backup wiederherstellen
            if (!empty($sqlFile) && file_exists($sqlFile)) {
                try {
                    // SQL-Datei einlesen
                    $sqlContent = file_get_contents($sqlFile);
                    $sqlStatements = explode(';', $sqlContent);
                    
                    $conn->begin_transaction();
                    
                    foreach ($sqlStatements as $statement) {
                        $statement = trim($statement);
                        if (!empty($statement)) {
                            $conn->query($statement);
                        }
                    }
                    
                    $conn->commit();
                    $success = "Backup wurde erfolgreich wiederhergestellt.";
                    
                    // Temporäre Dateien aufräumen
                    if ($fileExt === 'zip') {
                        unlink($sqlFile);
                    }
                    unlink($uploadedFile);
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Fehler bei der Wiederherstellung des Backups: " . $e->getMessage();
                }
            }
        } else {
            $error = "Ungültiges Dateiformat. Erlaubt sind: ZIP und SQL.";
        }
    } else {
        $error = "Fehler beim Hochladen der Datei.";
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-exchange-alt"></i> Import / Export</h1>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Export-Bereich -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-file-export"></i> Daten exportieren</h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="export_type" class="form-label">Was möchten Sie exportieren?</label>
                            <select class="form-select" id="export_type" name="export_type" required>
                                <option value="">-- Bitte wählen --</option>
                                <option value="customers">Kundendaten</option>
                                <option value="appointments">Termindaten</option>
                                <option value="services">Leistungsdaten</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="export_format" class="form-label">Format</label>
                            <select class="form-select" id="export_format" name="export_format">
                                <option value="csv">CSV</option>
                                <option value="json">JSON</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="export" class="btn btn-primary">
                            <i class="fas fa-download"></i> Exportieren
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Import-Bereich -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-file-import"></i> Daten importieren</h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="import_type" class="form-label">Was möchten Sie importieren?</label>
                            <select class="form-select" id="import_type" name="import_type" required>
                                <option value="">-- Bitte wählen --</option>
                                <option value="customers">Kundendaten</option>
                                <!-- Weitere Importtypen können hier hinzugefügt werden -->
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="import_file" class="form-label">Datei auswählen</label>
                            <input class="form-control" type="file" id="import_file" name="import_file" required>
                            <div class="form-text">Unterstützte Formate: CSV, JSON</div>
                        </div>
                        
                        <button type="submit" name="import" class="btn btn-success">
                            <i class="fas fa-upload"></i> Importieren
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Backup-Bereich -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-database"></i> Datenbank-Backup</h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <p>Erstellen Sie ein vollständiges Backup Ihrer Datenbank.</p>
                        <button type="submit" name="create_backup" class="btn btn-info">
                            <i class="fas fa-download"></i> Backup erstellen
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Backup-Wiederherstellung -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="fas fa-undo"></i> Backup wiederherstellen</h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="backup_file" class="form-label">Backup-Datei auswählen</label>
                            <input class="form-control" type="file" id="backup_file" name="backup_file" required>
                            <div class="form-text">Unterstützte Formate: ZIP, SQL</div>
                        </div>
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Achtung:</strong> Die Wiederherstellung eines Backups überschreibt alle vorhandenen Daten. Dieser Vorgang kann nicht rückgängig gemacht werden.
                        </div>
                        
                        <button type="submit" name="restore_backup" class="btn btn-warning" onclick="return confirm('Sind Sie sicher, dass Sie das Backup wiederherstellen möchten? Alle vorhandenen Daten werden überschrieben.')">
                            <i class="fas fa-undo"></i> Backup wiederherstellen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hinweise -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Hinweise</h5>
                </div>
                <div class="card-body">
                    <h6>Export</h6>
                    <ul>
                        <li>CSV-Dateien können in Excel oder andere Tabellenkalkulationsprogramme importiert werden.</li>
                        <li>JSON-Dateien eignen sich für die Verwendung in anderen Anwendungen oder für Entwickler.</li>
                    </ul>
                    
                    <h6>Import</h6>
                    <ul>
                        <li>Stellen Sie sicher, dass die Importdatei das richtige Format hat und alle erforderlichen Felder enthält.</li>
                        <li>Bei CSV-Dateien muss die erste Zeile die Spaltennamen enthalten.</li>
                        <li>Das Trennzeichen für CSV-Dateien ist das Semikolon (;).</li>
                    </ul>
                    
                    <h6>Backup</h6>
                    <ul>
                        <li>Erstellen Sie regelmäßig Backups Ihrer Datenbank, um Datenverlust zu vermeiden.</li>
                        <li>Backups werden als ZIP-Datei heruntergeladen und enthalten eine SQL-Datei mit allen Datenbanktabellen.</li>
                        <li>Die Wiederherstellung eines Backups überschreibt alle vorhandenen Daten.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Verbindung schließen
$conn->close();

require_once '../includes/footer.php';
?>