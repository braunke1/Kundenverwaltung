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

// Löschen bestätigen
$confirmDelete = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes';

if ($confirmDelete) {
    $conn->begin_transaction();
    
    try {
        // Zuerst alle Leistungen löschen, die mit diesem Termin verknüpft sind
        $deleteServicesStmt = $conn->prepare("DELETE FROM services WHERE appointment_id = ?");
        $deleteServicesStmt->bind_param("i", $appointmentId);
        $deleteServicesStmt->execute();
        $deleteServicesStmt->close();
        
        // Dann den Termin selbst löschen
        $deleteAppointmentStmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id = ?");
        $deleteAppointmentStmt->bind_param("i", $appointmentId);
        $deleteAppointmentStmt->execute();
        $deleteAppointmentStmt->close();
        
        $conn->commit();
        
        // Erfolgsmeldung setzen und zur Terminliste zurückkehren
        redirectTo("/appointments/", "Termin \"" . htmlspecialchars($appointment['title']) . "\" wurde erfolgreich gelöscht.");
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Fehler beim Löschen des Termins: " . $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-trash"></i> Termin löschen</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="/appointments/" class="btn btn-sm btn-outline-secondary">
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
                    <p class="lead">Möchten Sie den folgenden Termin wirklich löschen?</p>
                    
                    <div class="mb-4">
                        <div class="row mb-2">
                            <div class="col-md-3 fw-bold">Titel:</div>
                            <div class="col-md-9"><?php echo htmlspecialchars($appointment['title']); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3 fw-bold">Kunde:</div>
                            <div class="col-md-9"><?php echo htmlspecialchars($appointment['customer_name']); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3 fw-bold">Datum:</div>
                            <div class="col-md-9"><?php echo date('d.m.Y', strtotime($appointment['start_time'])); ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3 fw-bold">Zeit:</div>
                            <div class="col-md-9">
                                <?php echo date('H:i', strtotime($appointment['start_time'])); ?> - 
                                <?php echo date('H:i', strtotime($appointment['end_time'])); ?>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-3 fw-bold">Status:</div>
                            <div class="col-md-9">
                                <?php
                                $statusClasses = [
                                    'geplant' => 'badge bg-primary',
                                    'durchgeführt' => 'badge bg-success',
                                    'abgesagt' => 'badge bg-danger',
                                    'verschoben' => 'badge bg-warning'
                                ];
                                $statusClass = isset($statusClasses[$appointment['status']]) ? $statusClasses[$appointment['status']] : 'badge bg-secondary';
                                ?>
                                <span class="<?php echo $statusClass; ?>"><?php echo ucfirst($appointment['status']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle"></i>
                        Alle mit diesem Termin verknüpften Daten, einschließlich aller erfassten Leistungen, werden ebenfalls gelöscht!
                    </div>
                    
                    <form action="" method="POST">
                        <input type="hidden" name="confirm_delete" value="yes">
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Termin löschen
                            </button>
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

<?php
// Verbindung schließen
$conn->close();

require_once '../includes/footer.php';
?>