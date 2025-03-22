<?php
require_once '../includes/header.php';

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Vorgewählter Kunde (z.B. aus der Kundenliste)
$preselectedCustomerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$preselectedDate = isset($_GET['date']) ? sanitizeInput($_GET['date']) : '';

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
    
    // Dauer berechnen
    $plannedDuration = (int)$_POST['planned_duration'];
    
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
    
    // Wenn keine Fehler, in die Datenbank einfügen
    if (empty($errors)) {
        $sql = "INSERT INTO appointments (
                    customer_id, contact_id, title, description, location, 
                    start_time, end_time, planned_duration, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'geplant')";
        
        $stmt = $conn->prepare($sql);
        
        if ($contactId) {
            $stmt->bind_param(
                "iisssssi", 
                $customerId, $contactId, $title, $description, $location, 
                $startDateTime, $endDateTime, $plannedDuration
            );
        } else {
            $nullContact = null;
            $stmt->bind_param(
                "iisssssi", 
                $customerId, $nullContact, $title, $description, $location, 
                $startDateTime, $endDateTime, $plannedDuration
            );
        }
        
        if ($stmt->execute()) {
            $appointmentId = $conn->insert_id;
            
            if (isset($_POST['save_and_new'])) {
                // Formularfelder zurücksetzen und auf derselben Seite bleiben
                redirectTo("/appointments/add.php", "Termin erfolgreich erstellt. Sie können jetzt einen weiteren Termin anlegen.");
            } else {
                // Zurück zur Terminliste
                redirectTo("/appointments/", "Termin erfolgreich erstellt.");
            }
        } else {
            $errors[] = "Fehler beim Erstellen des Termins: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Standard-Werte für neue Termine
$defaultTime = date('H:i');
$defaultEndTime = date('H:i', strtotime('+1 hour'));
$defaultDate = !empty($preselectedDate) ? $preselectedDate : date('d.m.Y');
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-calendar-plus"></i> Neuen Termin anlegen</h1>
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
                                                <?php echo ($preselectedCustomerId == $customer['customer_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($customer['customer_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6" id="contact_container" style="display: none;">
                                <label for="contact_id" class="form-label">Ansprechpartner</label>
                                <select class="form-select" id="contact_id" name="contact_id">
                                    <option value="">-- Ansprechpartner auswählen --</option>
                                    <!-- Wird per AJAX befüllt -->
                                </select>
                            </div>
                        </div>
                        
                        <!-- Termindetails -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="title" class="form-label">Terminbezeichnung *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="description" class="form-label">Beschreibung</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="location" class="form-label">Ort</label>
                                <input type="text" class="form-control" id="location" name="location">
                            </div>
                        </div>
                        
                        <!-- Datum und Zeit -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="date" class="form-label">Datum *</label>
                                <input type="text" class="form-control" id="date" name="date" placeholder="TT.MM.JJJJ" value="<?php echo $defaultDate; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="start_time" class="form-label">Startzeit *</label>
                                <input type="text" class="form-control" id="start_time" name="start_time" placeholder="HH:MM" value="<?php echo $defaultTime; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="end_time" class="form-label">Endzeit *</label>
                                <input type="text" class="form-control" id="end_time" name="end_time" placeholder="HH:MM" value="<?php echo $defaultEndTime; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="planned_duration" class="form-label">Geplante Dauer (Minuten) *</label>
                                <input type="number" class="form-control" id="planned_duration" name="planned_duration" min="1" value="60" required>
                            </div>
                        </div>
                        
                        <!-- Submit-Buttons -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="submit" class="btn btn-primary" name="save" value="1">
                                            <i class="fas fa-save"></i> Speichern
                                        </button>
                                        <button type="submit" class="btn btn-success" name="save_and_new" value="1">
                                            <i class="fas fa-save"></i> Speichern & weiterer Termin
                                        </button>
                                    </div>
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="fas fa-undo"></i> Zurücksetzen
                                    </button>
                                </div>
                            </div>
                        </div>
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
    // Initialisierung
    const customerSelect = document.getElementById('customer_id');
    if (customerSelect.value) {
        loadContacts(customerSelect.value);
    }
    
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
});
</script>

<?php
// Verbindung schließen
$conn->close();

require_once '../includes/footer.php';
?>