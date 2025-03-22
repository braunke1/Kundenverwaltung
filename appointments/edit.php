<?php
require_once '../includes/header.php';

// Überprüfen, ob eine ID übergeben wurde
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectTo("/appointments/", "Keine gültige Termin-ID angegeben.");
}

$appointmentId = (int)$_GET['id'];

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Termin aus der Datenbank abrufen
$stmt = $conn->prepare("
    SELECT a.*, 
    c.customer_type,
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
    $stmt->close();
    $conn->close();
    redirectTo("/appointments/", "Termin nicht gefunden.");
}

$appointment = $result->fetch_assoc();
$stmt->close();

// Kundenliste abrufen
$customerSql = "SELECT customer_id, 
                CASE 
                    WHEN customer_type = 'business' THEN company_name 
                    ELSE CONCAT(first_name, ' ', last_name) 
                END as customer_name,
                customer_type
                FROM customers 
                ORDER BY customer_name";
                
$customerResult = $conn->query($customerSql);
$customers = [];
while ($customerRow = $customerResult->fetch_assoc()) {
    $customers[] = $customerRow;
}

// Ansprechpartner abrufen, wenn es ein Geschäftskunde ist
$contacts = [];
if ($appointment['customer_type'] === 'business') {
    $contactStmt = $conn->prepare("SELECT * FROM contacts WHERE customer_id = ? ORDER BY is_primary DESC, last_name, first_name");
    $contactStmt->bind_param("i", $appointment['customer_id']);
    $contactStmt->execute();
    $contactResult = $contactStmt->get_result();
    while ($contact = $contactResult->fetch_assoc()) {
        $contacts[] = $contact;
    }
    $contactStmt->close();
}

// Bereits erfasste Leistungen abrufen
$servicesSql = "SELECT * FROM services WHERE appointment_id = ? ORDER BY service_id";
$servicesStmt = $conn->prepare($servicesSql);
$servicesStmt->bind_param("i", $appointmentId);
$servicesStmt->execute();
$servicesResult = $servicesStmt->get_result();
$services = [];
while ($service = $servicesResult->fetch_assoc()) {
    $services[] = $service;
}
$servicesStmt->close();

// Initialisieren von Fehlermeldungen und Erfolgsbenachrichtigungen
$errors = [];
$success = '';

// Erfolgs- oder Fehlermeldung aus der Session holen und anzeigen
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $errors[] = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Verarbeiten des Formulars beim Absenden
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kontaktaktionen werden separat behandelt
    if (isset($_POST['service_action'])) {
        $serviceAction = $_POST['service_action'];
        
        // Neue Leistung hinzufügen
        if ($serviceAction === 'add' && isset($_POST['new_service_description'])) {
            $serviceDescription = sanitizeInput($_POST['new_service_description']);
            $serviceDuration = !empty($_POST['new_service_duration']) ? (int)$_POST['new_service_duration'] : null;
            
            if (!empty($serviceDescription)) {
                $insertStmt = $conn->prepare("INSERT INTO services 
                    (appointment_id, description, duration) 
                    VALUES (?, ?, ?)");
                    
                $insertStmt->bind_param(
                    "isi", 
                    $appointmentId, $serviceDescription, $serviceDuration
                );
                
                if ($insertStmt->execute()) {
                    $success = "Leistung erfolgreich hinzugefügt!";
                    
                    // Leistungen neu laden
                    $servicesStmt = $conn->prepare("SELECT * FROM services WHERE appointment_id = ? ORDER BY service_id");
                    $servicesStmt->bind_param("i", $appointmentId);
                    $servicesStmt->execute();
                    $servicesResult = $servicesStmt->get_result();
                    $services = [];
                    while ($service = $servicesResult->fetch_assoc()) {
                        $services[] = $service;
                    }
                    $servicesStmt->close();
                } else {
                    $errors[] = "Fehler beim Hinzufügen der Leistung: " . $conn->error;
                }
                
                $insertStmt->close();
            } else {
                $errors[] = "Beschreibung ist für Leistungen erforderlich.";
            }
        }
        
        // Leistung bearbeiten
        else if ($serviceAction === 'edit' && isset($_POST['edit_service_id'], $_POST['edit_service_description'])) {
            $serviceId = (int)$_POST['edit_service_id'];
            $serviceDescription = sanitizeInput($_POST['edit_service_description']);
            $serviceDuration = !empty($_POST['edit_service_duration']) ? (int)$_POST['edit_service_duration'] : null;
            
            if (!empty($serviceDescription)) {
                $updateStmt = $conn->prepare("UPDATE services SET 
                    description = ?, duration = ? 
                    WHERE service_id = ? AND appointment_id = ?");
                    
                $updateStmt->bind_param(
                    "siii", 
                    $serviceDescription, $serviceDuration, $serviceId, $appointmentId
                );
                
                if ($updateStmt->execute()) {
                    $success = "Leistung erfolgreich aktualisiert!";
                    
                    // Leistungen neu laden
                    $servicesStmt = $conn->prepare("SELECT * FROM services WHERE appointment_id = ? ORDER BY service_id");
                    $servicesStmt->bind_param("i", $appointmentId);
                    $servicesStmt->execute();
                    $servicesResult = $servicesStmt->get_result();
                    $services = [];
                    while ($service = $servicesResult->fetch_assoc()) {
                        $services[] = $service;
                    }
                    $servicesStmt->close();
                } else {
                    $errors[] = "Fehler beim Aktualisieren der Leistung: " . $conn->error;
                }
                
                $updateStmt->close();
            } else {
                $errors[] = "Beschreibung ist für Leistungen erforderlich.";
            }
        }
        
        // Leistung löschen
        else if ($serviceAction === 'delete' && isset($_POST['delete_service_id'])) {
            $serviceId = (int)$_POST['delete_service_id'];
            
            $deleteStmt = $conn->prepare("DELETE FROM services WHERE service_id = ? AND appointment_id = ?");
            $deleteStmt->bind_param("ii", $serviceId, $appointmentId);
            
            if ($deleteStmt->execute()) {
                $success = "Leistung erfolgreich gelöscht!";
                
                // Leistungen neu laden
                $servicesStmt = $conn->prepare("SELECT * FROM services WHERE appointment_id = ? ORDER BY service_id");
                $servicesStmt->bind_param("i", $appointmentId);
                $servicesStmt->execute();
                $servicesResult = $servicesStmt->get_result();
                $services = [];
                while ($service = $servicesResult->fetch_assoc()) {
                    $services[] = $service;
                }
                $servicesStmt->close();
            } else {
                $errors[] = "Fehler beim Löschen der Leistung: " . $conn->error;
            }
            
            $deleteStmt->close();
        }
    } else {
        // Termindaten sammeln
        $customerId = (int)$_POST['customer_id'];
        $contactId = !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null;
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $location = sanitizeInput($_POST['location']);
        
        // Datum und Zeit verarbeiten
        $date = sanitizeInput($_POST['date']);
        $startTime = sanitizeInput($_POST['start_time']);
        $endTime = sanitizeInput($_POST['end_time']);
        
        // Status und Dauer
        $status = sanitizeInput($_POST['status']);
        $plannedDuration = (int)$_POST['planned_duration'];
        $actualDuration = !empty($_POST['actual_duration']) ? (int)$_POST['actual_duration'] : null;
        $billingStatus = sanitizeInput($_POST['billing_status']);
        
        // Validierung
        if (empty($customerId)) {
            $errors[] = "Bitte wählen Sie einen Kunden aus.";
        }
        if (empty($title)) {
            $errors[] = "Bitte geben Sie eine Terminbezeichnung ein.";
        }
        if (empty($date)) {
            $errors[] = "Bitte geben Sie ein Datum ein.";
        }
        if (empty($startTime)) {
            $errors[] = "Bitte geben Sie eine Startzeit ein.";
        }
        if (empty($endTime)) {
            $errors[] = "Bitte geben Sie eine Endzeit ein.";
        }
        
        // Datum in MySQL-Format umwandeln
        $dateFormatted = formatDateForMysql($date);
        
        // Timestamp für Start- und Endzeit erstellen
        $startDateTime = $dateFormatted . ' ' . $startTime . ':00';
        $endDateTime = $dateFormatted . ' ' . $endTime . ':00';
        
        // Wenn keine Fehler, in die Datenbank aktualisieren
        if (empty($errors)) {
            $conn->begin_transaction();
            
            try {
                // SQL-Abfrage je nach Status (durchgeführt oder nicht)
                if ($status === 'durchgeführt' && $actualDuration !== null) {
                    $sql = "UPDATE appointments SET 
                                customer_id = ?, 
                                contact_id = ?, 
                                title = ?, 
                                description = ?, 
                                location = ?, 
                                start_time = ?, 
                                end_time = ?, 
                                planned_duration = ?, 
                                actual_duration = ?, 
                                rounded_duration = CEILING(? / 15) * 15,
                                status = ?,
                                billing_status = ?
                            WHERE appointment_id = ?";
                    
                    $stmt = $conn->prepare($sql);
                    
                    if ($contactId) {
                        $stmt->bind_param(
                            "iisssssiiissi", 
                            $customerId, $contactId, $title, $description, $location, 
                            $startDateTime, $endDateTime, $plannedDuration, $actualDuration, 
                            $actualDuration, $status, $billingStatus, $appointmentId
                        );
                    } else {
                        $nullContact = null;
                        $stmt->bind_param(
                            "iisssssiiissi", 
                            $customerId, $nullContact, $title, $description, $location, 
                            $startDateTime, $endDateTime, $plannedDuration, $actualDuration, 
                            $actualDuration, $status, $billingStatus, $appointmentId
                        );
                    }
                } else {
                    $sql = "UPDATE appointments SET 
                                customer_id = ?, 
                                contact_id = ?, 
                                title = ?, 
                                description = ?, 
                                location = ?, 
                                start_time = ?, 
                                end_time = ?, 
                                planned_duration = ?, 
                                status = ?,
                                billing_status = ?
                            WHERE appointment_id = ?";
                    
                    $stmt = $conn->prepare($sql);
                    
                    if ($contactId) {
                        $stmt->bind_param(
                            "iisssssissi", 
                            $customerId, $contactId, $title, $description, $location, 
                            $startDateTime, $endDateTime, $plannedDuration, 
                            $status, $billingStatus, $appointmentId
                        );
                    } else {
                        $nullContact = null;
                        $stmt->bind_param(
                            "iisssssissi", 
                            $customerId, $nullContact, $title, $description, $location, 
                            $startDateTime, $endDateTime, $plannedDuration, 
                            $status, $billingStatus, $appointmentId
                        );
                    }
                }
                
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                
                // Zurück zur Terminliste, wenn gewünscht
                if (isset($_POST['save_and_return'])) {
                    redirectTo("/appointments/", "Termin erfolgreich aktualisiert.");
                } else {
                    // Bearbeitungsseite neu laden mit Erfolgsmeldung
                    redirectTo("/appointments/edit.php?id=" . $appointmentId, "Termin erfolgreich aktualisiert.");
                }
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "Fehler beim Aktualisieren des Termins: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-edit"></i> Termin bearbeiten</h1>
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
            <div class="card">
                <div class="card-body">
                    <form action="" method="POST">
                        <!-- Kunde auswählen -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="customer_id" class="form-label">Kunde *</label>
                                <select class="form-select" id="customer_id" name="customer_id" required onchange="loadContacts(this.value)">
                                    <option value="">-- Kunden auswählen --</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['customer_id']; ?>" 
                                                data-type="<?php echo $customer['customer_type']; ?>"
                                                <?php echo ($appointment['customer_id'] == $customer['customer_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($customer['customer_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6" id="contact_container" style="display: <?php echo ($appointment['customer_type'] === 'business') ? 'block' : 'none'; ?>;">
                                <label for="contact_id" class="form-label">Ansprechpartner</label>
                                <select class="form-select" id="contact_id" name="contact_id">
                                    <option value="">-- Ansprechpartner auswählen --</option>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?php echo $contact['contact_id']; ?>" <?php echo ($appointment['contact_id'] == $contact['contact_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?>
                                            <?php if (!empty($contact['position'])): ?> (<?php echo htmlspecialchars($contact['position']); ?>)<?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Termindetails -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="title" class="form-label">Terminbezeichnung *</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($appointment['title']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="geplant" <?php echo ($appointment['status'] === 'geplant') ? 'selected' : ''; ?>>Geplant</option>
                                    <option value="durchgeführt" <?php echo ($appointment['status'] === 'durchgeführt') ? 'selected' : ''; ?>>Durchgeführt</option>
                                    <option value="abgesagt" <?php echo ($appointment['status'] === 'abgesagt') ? 'selected' : ''; ?>>Abgesagt</option>
                                    <option value="verschoben" <?php echo ($appointment['status'] === 'verschoben') ? 'selected' : ''; ?>>Verschoben</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="description" class="form-label">Beschreibung</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($appointment['description']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="location" class="form-label">Ort</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($appointment['location']); ?>">
                            </div>
                        </div>
                        
                        <!-- Datum und Zeit -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="date" class="form-label">Datum *</label>
                                <input type="text" class="form-control" id="date" name="date" placeholder="TT.MM.JJJJ" value="<?php echo date('d.m.Y', strtotime($appointment['start_time'])); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="start_time" class="form-label">Startzeit *</label>
                                <input type="text" class="form-control" id="start_time" name="start_time" placeholder="HH:MM" value="<?php echo date('H:i', strtotime($appointment['start_time'])); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="end_time" class="form-label">Endzeit *</label>
                                <input type="text" class="form-control" id="end_time" name="end_time" placeholder="HH:MM" value="<?php echo date('H:i', strtotime($appointment['end_time'])); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="planned_duration" class="form-label">Geplante Dauer (Minuten) *</label>
                                <input type="number" class="form-control" id="planned_duration" name="planned_duration" min="1" value="<?php echo $appointment['planned_duration']; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="actual_duration" class="form-label">Tatsächliche Dauer (Minuten)</label>
                                <input type="number" class="form-control" id="actual_duration" name="actual_duration" min="1" value="<?php echo $appointment['actual_duration']; ?>">
                                <div class="form-text">Wird automatisch auf 15-Minuten-Intervalle aufgerundet.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="billing_status" class="form-label">Abrechnungsstatus</label>
                                <select class="form-select" id="billing_status" name="billing_status">
                                    <option value="offen" <?php echo ($appointment['billing_status'] === 'offen') ? 'selected' : ''; ?>>Offen</option>
                                    <option value="abgerechnet" <?php echo ($appointment['billing_status'] === 'abgerechnet') ? 'selected' : ''; ?>>Abgerechnet</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Submit-Buttons -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Speichern
                                        </button>
                                        <button type="submit" class="btn btn-success" name="save_and_return" value="1">
                                            <i class="fas fa-save"></i> Speichern & zurück
                                        </button>
                                    </div>
                                    <a href="/appointments/" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Abbrechen
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Leistungen verwalten -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-tasks"></i> Erbrachte Leistungen</h5>
                </div>
                <div class="card-body">
                    <!-- Vorhandene Leistungen anzeigen -->
                    <?php if (!empty($services)): ?>
                        <div class="table-responsive mb-4">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Beschreibung</th>
                                        <th>Dauer (min)</th>
                                        <th>Abrechnungsstatus</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($services as $service): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($service['description']); ?></td>
                                            <td><?php echo $service['duration']; ?></td>
                                            <td>
                                                <?php if ($service['billing_status'] === 'offen'): ?>
                                                    <span class="badge bg-warning">Offen</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Abgerechnet</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary edit-service-btn" 
                                                            data-service-id="<?php echo $service['service_id']; ?>"
                                                            data-description="<?php echo htmlspecialchars($service['description']); ?>"
                                                            data-duration="<?php echo $service['duration']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-service-btn"
                                                            data-service-id="<?php echo $service['service_id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Keine Leistungen vorhanden.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Neue Leistung hinzufügen -->
                    <button type="button" class="btn btn-success mb-3" id="add-service-btn">
                        <i class="fas fa-plus"></i> Neue Leistung hinzufügen
                    </button>
                    
                    <!-- Formular für neue Leistung (anfangs versteckt) -->
                    <div id="add-service-form" style="display: none;">
                        <h5 class="mb-3">Neue Leistung hinzufügen</h5>
                        <form action="" method="POST">
                            <input type="hidden" name="service_action" value="add">
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="new_service_description" class="form-label">Beschreibung *</label>
                                    <input type="text" class="form-control" id="new_service_description" name="new_service_description" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="new_service_duration" class="form-label">Dauer (Minuten)</label>
                                    <input type="number" class="form-control" id="new_service_duration" name="new_service_duration" min="1">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Speichern
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="cancel-add-service">
                                        <i class="fas fa-times"></i> Abbrechen
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Formular für Leistung bearbeiten (wird per JS gefüllt) -->
                    <div id="edit-service-form" style="display: none;">
                        <h5 class="mb-3">Leistung bearbeiten</h5>
                        <form action="" method="POST">
                            <input type="hidden" name="service_action" value="edit">
                            <input type="hidden" name="edit_service_id" id="edit_service_id">
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="edit_service_description" class="form-label">Beschreibung *</label>
                                    <input type="text" class="form-control" id="edit_service_description" name="edit_service_description" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="edit_service_duration" class="form-label">Dauer (Minuten)</label>
                                    <input type="number" class="form-control" id="edit_service_duration" name="edit_service_duration" min="1">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Speichern
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="cancel-edit-service">
                                        <i class="fas fa-times"></i> Abbrechen
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Formular für Leistung löschen -->
                    <form id="delete-service-form" action="" method="POST" style="display: none;">
                        <input type="hidden" name="service_action" value="delete">
                        <input type="hidden" name="delete_service_id" id="delete_service_id">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Funktion zum Laden der Ansprechpartner eines Geschäftskunden per AJAX
function loadContacts(customerId) {
    const customerSelect = document.getElementById('customer_id');
    const selectedOption = customerSelect.options[customerSelect.selectedIndex];
    const customerType = selectedOption.getAttribute('data-type');
    const contactContainer = document.getElementById('contact_container');
    const contactSelect = document.getElementById('contact_id');
    
    // Container nur anzeigen, wenn ein Geschäftskunde ausgewählt wurde
    if (customerType === 'business' && customerId > 0) {
        // Ansprechpartner-Dropdown zurücksetzen
        contactSelect.innerHTML = '<option value="">-- Ansprechpartner auswählen --</option>';
        
        // Container anzeigen
        contactContainer.style.display = 'block';
        
        // AJAX-Anfrage zum Laden der Ansprechpartner
        fetch(`/api/get_contacts.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Fehler beim Laden der Ansprechpartner:', data.error);
                    return;
                }
                
                // Ansprechpartner zur Dropdown-Liste hinzufügen
                data.forEach(contact => {
                    const option = document.createElement('option');
                    option.value = contact.id;
                    option.textContent = contact.name;
                    contactSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Fehler bei der API-Anfrage:', error);
            });
    } else {
        // Container ausblenden
        contactContainer.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Automatische Berechnung der Dauer beim Ändern der Start- oder Endzeit
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    const durationInput = document.getElementById('planned_duration');
    
    function updateDuration() {
        const startTime = startTimeInput.value;
        const endTime = endTimeInput.value;
        
        if (startTime && endTime) {
            // Parsen der Zeiten
            const [startHours, startMinutes] = startTime.split(':').map(Number);
            const [endHours, endMinutes] = endTime.split(':').map(Number);
            
            // Umrechnung in Minuten
            const startTotalMinutes = startHours * 60 + startMinutes;
            const endTotalMinutes = endHours * 60 + endMinutes;
            
            // Berechnung der Differenz
            let duration = endTotalMinutes - startTotalMinutes;
            if (duration < 0) {
                duration += 24 * 60; // Über Mitternacht hinweg
            }
            
            // Aktualisieren des Dauer-Felds
            durationInput.value = duration;
        }
    }
    
    startTimeInput.addEventListener('change', updateDuration);
    endTimeInput.addEventListener('change', updateDuration);

    // Leistungsverwaltung
    const addServiceBtn = document.getElementById('add-service-btn');
    const addServiceForm = document.getElementById('add-service-form');
    const cancelAddServiceBtn = document.getElementById('cancel-add-service');
    
    if (addServiceBtn) {
        addServiceBtn.addEventListener('click', function() {
            addServiceForm.style.display = 'block';
            this.style.display = 'none';
            document.getElementById('edit-service-form').style.display = 'none';
        });
    }
    
    if (cancelAddServiceBtn) {
        cancelAddServiceBtn.addEventListener('click', function() {
            addServiceForm.style.display = 'none';
            addServiceBtn.style.display = 'block';
        });
    }
    
    // Leistung bearbeiten
    const editServiceBtns = document.querySelectorAll('.edit-service-btn');
    const editServiceForm = document.getElementById('edit-service-form');
    const cancelEditServiceBtn = document.getElementById('cancel-edit-service');
    
    editServiceBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const serviceId = this.getAttribute('data-service-id');
            const description = this.getAttribute('data-description');
            const duration = this.getAttribute('data-duration');
            
            document.getElementById('edit_service_id').value = serviceId;
            document.getElementById('edit_service_description').value = description;
            document.getElementById('edit_service_duration').value = duration;
            
            editServiceForm.style.display = 'block';
            addServiceForm.style.display = 'none';
            addServiceBtn.style.display = 'block';
        });
    });
    
    if (cancelEditServiceBtn) {
        cancelEditServiceBtn.addEventListener('click', function() {
            editServiceForm.style.display = 'none';
        });
    }
    
    // Leistung löschen
    const deleteServiceBtns = document.querySelectorAll('.delete-service-btn');
    const deleteServiceForm = document.getElementById('delete-service-form');
    
    deleteServiceBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const serviceId = this.getAttribute('data-service-id');
            
            if (confirm('Möchten Sie diese Leistung wirklich löschen?')) {
                document.getElementById('delete_service_id').value = serviceId;
                deleteServiceForm.submit();
            }
        });
    });
});
</script>

<?php
// Verbindung schließen
$conn->close();

require_once '../includes/footer.php';
?>