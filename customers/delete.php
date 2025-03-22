<?php
require_once '../includes/header.php';

// Überprüfen, ob eine ID übergeben wurde
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectTo("/customers/", "Keine gültige Kunden-ID angegeben.");
}

$customerId = (int)$_GET['id'];

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Überprüfen, ob der Kunde existiert
$stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Kunde nicht gefunden
    $stmt->close();
    $conn->close();
    redirectTo("/customers/", "Kunde nicht gefunden.");
}

// Kundendaten holen für die Bestätigungsnachricht
$customer = $result->fetch_assoc();
$customerName = ($customer['customer_type'] === 'business') 
    ? $customer['company_name'] 
    : $customer['first_name'] . ' ' . $customer['last_name'];

// Löschen bestätigen
$confirmDelete = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes';

// Erfolgs- oder Fehlermeldung aus der Session holen und anzeigen
$errors = [];
$success = '';
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $errors[] = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if ($confirmDelete) {
    // Kunde löschen - aber prüfen, ob es verknüpfte Termine gibt
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE customer_id = ?");
    $checkStmt->bind_param("i", $customerId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $row = $checkResult->fetch_assoc();
    $hasAppointments = ($row['count'] > 0);
    $checkStmt->close();
    
    if ($hasAppointments && !isset($_POST['delete_appointments'])) {
        // Es gibt verknüpfte Termine und der Benutzer hat nicht bestätigt, dass auch diese gelöscht werden sollen
        $error = "Dieser Kunde hat zugeordnete Termine. Bitte bestätigen Sie das Löschen aller verknüpften Daten.";
    } else {
        // Kunde und alle verknüpften Daten löschen
        $conn->begin_transaction();
        
        try {
            // Kontakte löschen
            $stmt = $conn->prepare("DELETE FROM contacts WHERE customer_id = ?");
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            
            // Wenn Termine existieren und bestätigt wurde, diese zu löschen
            if ($hasAppointments && isset($_POST['delete_appointments'])) {
                // Termine abrufen, um die appointment_ids zu erhalten
                $appointmentStmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE customer_id = ?");
                $appointmentStmt->bind_param("i", $customerId);
                $appointmentStmt->execute();
                $appointmentResult = $appointmentStmt->get_result();
                
                while ($appointmentRow = $appointmentResult->fetch_assoc()) {
                    $appointmentId = $appointmentRow['appointment_id'];
                    
                    // Leistungen zu diesem Termin löschen
                    $serviceStmt = $conn->prepare("DELETE FROM services WHERE appointment_id = ?");
                    $serviceStmt->bind_param("i", $appointmentId);
                    $serviceStmt->execute();
                    $serviceStmt->close();
                }
                $appointmentStmt->close();
                
                // Alle Termine des Kunden löschen
                $stmt = $conn->prepare("DELETE FROM appointments WHERE customer_id = ?");
                $stmt->bind_param("i", $customerId);
                $stmt->execute();
            }
            
            // Kunden löschen
            $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            
            $conn->commit();
            
            // Erfolgsmeldung setzen und zur Kundenliste zurückkehren
            redirectTo("/customers/", "Kunde \"" . htmlspecialchars($customerName) . "\" wurde erfolgreich gelöscht.");
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Fehler beim Löschen des Kunden: " . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-trash"></i> Kunden löschen</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="/customers/" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Zurück zur Liste
            </a>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
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
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Bestätigung erforderlich</h5>
                </div>
                <div class="card-body">
                    <p class="lead">Möchten Sie den folgenden Kunden wirklich löschen?</p>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($customerName); ?></p>
                    
                    <?php
                    // Überprüfen, ob es verknüpfte Termine gibt
                    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE customer_id = ?");
                    $checkStmt->bind_param("i", $customerId);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    $row = $checkResult->fetch_assoc();
                    $appointmentCount = $row['count'];
                    $checkStmt->close();
                    
                    if ($appointmentCount > 0): 
                    ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle"></i>
                        Dieser Kunde hat <strong><?php echo $appointmentCount; ?> verknüpfte Termine</strong>. 
                        Wenn Sie den Kunden löschen, werden alle zugehörigen Daten ebenfalls gelöscht!
                    </div>
                    <?php endif; ?>
                    
                    <form action="" method="POST">
                        <input type="hidden" name="confirm_delete" value="yes">
                        
                        <?php if ($appointmentCount > 0): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="delete_appointments" id="delete_appointments" value="yes">
                            <label class="form-check-label" for="delete_appointments">
                                Ich bestätige, dass ich alle <?php echo $appointmentCount; ?> Termine und zugehörige Daten dieses Kunden löschen möchte.
                            </label>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Kunden löschen
                            </button>
                            <a href="/customers/" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Abbrechen
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Verbindung schließen
$stmt->close();
$conn->close();

require_once '../includes/footer.php';
?>