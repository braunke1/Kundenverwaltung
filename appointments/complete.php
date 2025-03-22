<?php
require_once '../includes/header.php';

// Überprüfen, ob eine ID übergeben wurde
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectTo("/appointments/", "Termin erfolgreich erstellt.");
}

$appointmentId = (int)$_GET['id'];

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Termin aus der Datenbank abrufen
$stmt = $conn->prepare("
    SELECT a.*, 
    CASE 
        WHEN c.customer_type = 'business' THEN c.company_name 
        ELSE CONCAT(c.first_name, ' ', c.last_name) 
    END as customer_name
    FROM appointments a
    JOIN customers c ON a.customer_id = c.customer_id
    WHERE a.appointment_id = ?
");
$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$result = $stmt->get_result();

// Prüfen, ob der Termin existiert
if ($result->num_rows === 0) {
    redirectTo("/appointments/", "Termin erfolgreich erstellt.");
}

$appointment = $result->fetch_assoc();
$stmt->close();

// Prüfen, ob der Termin bereits abgeschlossen ist
if ($appointment['status'] !== 'geplant') {
    $_SESSION['error_message'] = "Dieser Termin ist bereits " . $appointment['status'] . " und kann nicht mehr abgeschlossen werden.";
    redirectTo("/appointments/", "Termin erfolgreich erstellt.");
}

// Initialisieren von Fehlermeldungen und Erfolgsbenachrichtigungen
$errors = [];
$success = '';

// Leistungskatalog für Vorschläge abrufen
$catalogResult = $conn->query("SELECT * FROM service_catalog WHERE is_active = 1 ORDER BY name");
$serviceCatalog = [];
while ($catalogRow = $catalogResult->fetch_assoc()) {
    $serviceCatalog[] = $catalogRow;
}

// Verarbeiten des Formulars beim Absenden
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Formulardaten sammeln
    $actualDuration = (int)$_POST['actual_duration'];
    $status = sanitizeInput($_POST['status']);
    $serviceDescriptions = isset($_POST['service_description']) ? $_POST['service_description'] : [];
    $serviceDurations = isset($_POST['service_duration']) ? $_POST['service_duration'] : [];
    
    // Validierung
    if (empty($actualDuration) || $actualDuration <= 0) {
        $errors[] = "Bitte geben Sie die tatsächliche Dauer an.";
    }
    
    if (empty($status)) {
        $errors[] = "Bitte wählen Sie einen Status aus.";
    }
    
    // Wenn keine Fehler, in die Datenbank speichern
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Termin aktualisieren
            $updateStmt = $conn->prepare("
                UPDATE appointments 
                SET status = ?, 
                    actual_duration = ?, 
                    rounded_duration = CEILING(? / 15) * 15
                WHERE appointment_id = ?
            ");
            $updateStmt->bind_param("siii", $status, $actualDuration, $actualDuration, $appointmentId);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Leistungen speichern, wenn vorhanden
            if (!empty($serviceDescriptions)) {
                $insertServiceStmt = $conn->prepare("
                    INSERT INTO services (appointment_id, description, duration)
                    VALUES (?, ?, ?)
                ");
                
                foreach ($serviceDescriptions as $key => $description) {
                    if (!empty($description)) {
                        $duration = isset($serviceDurations[$key]) && !empty($serviceDurations[$key]) ? (int)$serviceDurations[$key] : null;
                        $insertServiceStmt->bind_param("isi", $appointmentId, $description, $duration);
                        $insertServiceStmt->execute();
                    }
                }
                
                $insertServiceStmt->close();
            }
            
            $conn->commit();
            $success = "Termin erfolgreich abgeschlossen!";
            
            // Zurück zur Terminliste, wenn gewünscht
            if (!isset($_POST['stay_on_page'])) {
                redirectTo("/appointments/", "Termin erfolgreich erstellt.");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Fehler beim Speichern: " . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-check-circle"></i> Termin abschließen</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="/appointments/" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Zurück zur Liste
            </a>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Termindetails</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Kunde:</strong> <?php echo htmlspecialchars($appointment['customer_name']); ?></p>
                            <p><strong>Terminbezeichnung:</strong> <?php echo htmlspecialchars($appointment['title']); ?></p>
                            <p><strong>Beschreibung:</strong> <?php echo htmlspecialchars($appointment['description']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Datum:</strong> <?php echo date('d.m.Y', strtotime($appointment['start_time'])); ?></p>
                            <p><strong>Zeit:</strong> <?php echo date('H:i', strtotime($appointment['start_time'])); ?> - <?php echo date('H:i', strtotime($appointment['end_time'])); ?></p>
                            <p><strong>Geplante Dauer:</strong> <?php echo $appointment['planned_duration']; ?> Minuten</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Termin abschließen</h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="actual_duration" class="form-label">Tatsächliche Dauer (Minuten) *</label>
                                <input type="number" class="form-control" id="actual_duration" name="actual_duration" min="1" value="<?php echo $appointment['planned_duration']; ?>" required>
                                <div class="form-text">Die Dauer wird automatisch auf 15-Minuten-Intervalle aufgerundet.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="durchgeführt" selected>Durchgeführt</option>
                                    <option value="abgesagt">Abgesagt</option>
                                    <option value="verschoben">Verschoben</option>
                                </select>
                            </div>
                        </div>
                        
                        <h5 class="mt-4">Erbrachte Leistungen</h5>
                        <div id="services-container">
                            <div class="row mb-3 service-row">
                                <div class="col-md-8">
                                    <label class="form-label">Leistungsbeschreibung</label>
                                    <input type="text" class="form-control" name="service_description[]" placeholder="z.B. Beratung, Installation, Wartung...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Dauer (Min.)</label>
                                    <input type="number" class="form-control" name="service_duration[]" min="1">
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="button" class="btn btn-danger remove-service" style="display:none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary" id="add-service">
                                <i class="fas fa-plus"></i> Weitere Leistung hinzufügen
                            </button>
                        </div>
                        
                        <?php if (!empty($serviceCatalog)): ?>
                        <div class="mb-3">
                            <label class="form-label">Aus Leistungskatalog auswählen:</label>
                            <div class="row">
                                <?php foreach ($serviceCatalog as $service): ?>
                                <div class="col-md-4 mb-2">
                                    <button type="button" class="btn btn-outline-secondary w-100 text-start catalog-service" 
                                            data-name="<?php echo htmlspecialchars($service['name']); ?>"
                                            data-description="<?php echo htmlspecialchars($service['description']); ?>"
                                            data-duration="<?php echo $service['default_duration']; ?>">
                                        <?php echo htmlspecialchars($service['name']); ?>
                                        <?php if (!empty($service['default_duration'])): ?>
                                        <span class="badge bg-info float-end"><?php echo $service['default_duration']; ?> Min</span>
                                        <?php endif; ?>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Speichern
                                </button>
                            </div>
                            <a href="/appointments/" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Abbrechen
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const servicesContainer = document.getElementById('services-container');
    const addServiceButton = document.getElementById('add-service');
    
    // Event-Listener für "Weitere Leistung hinzufügen"-Button
    addServiceButton.addEventListener('click', function() {
        addServiceRow();
    });
    
    // Event-Delegation für "Leistung entfernen"-Buttons
    servicesContainer.addEventListener('click', function(e) {
        if (e.target.closest('.remove-service')) {
            e.target.closest('.service-row').remove();
            updateRemoveButtons();
        }
    });
    
    // Event-Listener für Leistungskatalog-Buttons
    document.querySelectorAll('.catalog-service').forEach(button => {
        button.addEventListener('click', function() {
            const name = this.getAttribute('data-name');
            const description = this.getAttribute('data-description');
            const duration = this.getAttribute('data-duration');
            
            // Leistung zur Liste hinzufügen
            addServiceRow(name + (description ? ': ' + description : ''), duration);
        });
    });
    
    // Funktion zum Hinzufügen einer neuen Leistungszeile
    function addServiceRow(description = '', duration = '') {
        const newRow = document.createElement('div');
        newRow.className = 'row mb-3 service-row';
        newRow.innerHTML = `
            <div class="col-md-8">
                <label class="form-label">Leistungsbeschreibung</label>
                <input type="text" class="form-control" name="service_description[]" placeholder="z.B. Beratung, Installation, Wartung..." value="${description}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Dauer (Min.)</label>
                <input type="number" class="form-control" name="service_duration[]" min="1" value="${duration}">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-danger remove-service">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        servicesContainer.appendChild(newRow);
        updateRemoveButtons();
    }
    
    // Funktion zum Aktualisieren der Entfernen-Buttons
    function updateRemoveButtons() {
        const serviceRows = document.querySelectorAll('.service-row');
        
        if (serviceRows.length <= 1) {
            serviceRows[0].querySelector('.remove-service').style.display = 'none';
        } else {
            serviceRows.forEach(row => {
                row.querySelector('.remove-service').style.display = 'block';
            });
        }
    }
});
</script>

<?php
// Verbindung schließen
$conn->close();

require_once '../includes/footer.php';
?>