<?php
require_once '../includes/header.php';

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Filter-Parameter
$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// SQL-Abfrage vorbereiten
$sql = "SELECT a.*, 
        CASE 
            WHEN c.customer_type = 'business' THEN c.company_name 
            ELSE CONCAT(c.first_name, ' ', c.last_name) 
        END as customer_name
        FROM appointments a
        JOIN customers c ON a.customer_id = c.customer_id
        WHERE 1=1";

$params = [];
$types = "";

// Filter anwenden
if ($customerId > 0) {
    $sql .= " AND a.customer_id = ?";
    $params[] = $customerId;
    $types .= "i";
}

if (!empty($status)) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($dateFrom)) {
    $dateFromFormatted = formatDateForMysql($dateFrom);
    $sql .= " AND a.start_time >= ?";
    $params[] = $dateFromFormatted . " 00:00:00";
    $types .= "s";
}

if (!empty($dateTo)) {
    $dateToFormatted = formatDateForMysql($dateTo);
    $sql .= " AND a.start_time <= ?";
    $params[] = $dateToFormatted . " 23:59:59";
    $types .= "s";
}

// Sortierung
$sql .= " ORDER BY a.start_time DESC";

// Statement vorbereiten
$stmt = $conn->prepare($sql);

// Parameter binden, wenn vorhanden
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Kundenliste für Filter abrufen
$customerSql = "SELECT customer_id, 
                CASE 
                    WHEN customer_type = 'business' THEN company_name 
                    ELSE CONCAT(first_name, ' ', last_name) 
                END as customer_name
                FROM customers 
                ORDER BY customer_name";
                
$customerResult = $conn->query($customerSql);
$customers = [];
while ($customerRow = $customerResult->fetch_assoc()) {
    $customers[] = $customerRow;
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-calendar-alt"></i> Terminverwaltung</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="/appointments/add.php" class="btn btn-sm btn-primary me-2">
                <i class="fas fa-plus-circle"></i> Neuer Termin
            </a>
            <a href="/appointments/calendar.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-calendar-week"></i> Kalenderansicht
            </a>
        </div>
    </div>
    
    <!-- Suchfilter -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-filter"></i> Filter</h5>
        </div>
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="customer_id" class="form-label">Kunde</label>
                    <select name="customer_id" id="customer_id" class="form-select">
                        <option value="">Alle Kunden</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['customer_id']; ?>" <?php echo ($customerId == $customer['customer_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['customer_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">Alle Status</option>
                        <option value="geplant" <?php echo ($status === 'geplant') ? 'selected' : ''; ?>>Geplant</option>
                        <option value="durchgeführt" <?php echo ($status === 'durchgeführt') ? 'selected' : ''; ?>>Durchgeführt</option>
                        <option value="abgesagt" <?php echo ($status === 'abgesagt') ? 'selected' : ''; ?>>Abgesagt</option>
                        <option value="verschoben" <?php echo ($status === 'verschoben') ? 'selected' : ''; ?>>Verschoben</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Datum von</label>
                    <input type="text" class="form-control" id="date_from" name="date_from" placeholder="TT.MM.JJJJ" value="<?php echo $dateFrom; ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Datum bis</label>
                    <input type="text" class="form-control" id="date_to" name="date_to" placeholder="TT.MM.JJJJ" value="<?php echo $dateTo; ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Suchen
                    </button>
                    <a href="/appointments/" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Zurücksetzen
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Ergebnisliste -->
    <div class="card">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list"></i> Terminliste</h5>
                <span class="badge bg-primary"><?php echo $result->num_rows; ?> Termine gefunden</span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Uhrzeit</th>
                                <th>Kunde</th>
                                <th>Terminbezeichnung</th>
                                <th>Status</th>
                                <th>Ort</th>
                                <th>Dauer (min)</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d.m.Y', strtotime($row['start_time'])); ?></td>
                                    <td>
                                        <?php echo date('H:i', strtotime($row['start_time'])); ?> - 
                                        <?php echo date('H:i', strtotime($row['end_time'])); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        switch ($row['status']) {
                                            case 'geplant':
                                                $statusClass = 'bg-primary';
                                                break;
                                            case 'durchgeführt':
                                                $statusClass = 'bg-success';
                                                break;
                                            case 'abgesagt':
                                                $statusClass = 'bg-danger';
                                                break;
                                            case 'verschoben':
                                                $statusClass = 'bg-warning';
                                                break;
                                            default:
                                                $statusClass = 'bg-secondary';
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                        <?php if ($row['billing_status'] === 'offen' && $row['status'] === 'durchgeführt'): ?>
                                            <span class="badge bg-warning">Abr. offen</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['location']); ?></td>
                                    <td>
                                        <?php 
                                        if ($row['status'] === 'durchgeführt' && !empty($row['actual_duration'])) {
                                            echo $row['actual_duration'];
                                            if (!empty($row['rounded_duration']) && $row['rounded_duration'] != $row['actual_duration']) {
                                                echo ' <span class="text-muted">(' . $row['rounded_duration'] . ')</span>';
                                            }
                                        } else {
                                            echo $row['planned_duration'];
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="/appointments/edit.php?id=<?php echo $row['appointment_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($row['status'] === 'geplant'): ?>
                                                <a href="/appointments/complete.php?id=<?php echo $row['appointment_id']; ?>" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="confirmDelete(<?php echo $row['appointment_id']; ?>, '<?php echo addslashes($row['title']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Keine Termine gefunden.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, title) {
    if (confirm('Möchten Sie den Termin "' + title + '" wirklich löschen?')) {
        window.location.href = '/appointments/delete.php?id=' + id;
    }
}

// Datumspicker für die Filterfelder initialisieren (kann mit einer JS-Bibliothek ergänzt werden)
document.addEventListener('DOMContentLoaded', function() {
    // Hier könnte ein Datepicker initialisiert werden, z.B. mit jQuery UI oder bootstrap-datepicker
    // Für diese einfache Version akzeptieren wir manuelle Eingaben im Format TT.MM.JJJJ
});
</script>

<?php
// Verbindung schließen
$stmt->close();
$conn->close();

require_once '../includes/footer.php';
?>