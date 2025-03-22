<?php
require_once 'includes/header.php';

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Statistiken abrufen
$stats = array(
    'customers' => 0,
    'appointments' => 0,
    'upcoming' => 0,
    'billing' => 0
);

// Anzahl der Kunden
$result = $conn->query("SELECT COUNT(*) as count FROM customers");
if ($result && $row = $result->fetch_assoc()) {
    $stats['customers'] = $row['count'];
}

// Anzahl aller Termine
$result = $conn->query("SELECT COUNT(*) as count FROM appointments");
if ($result && $row = $result->fetch_assoc()) {
    $stats['appointments'] = $row['count'];
}

// Anzahl der bevorstehenden Termine
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE start_time > NOW() AND status = 'geplant'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['upcoming'] = $row['count'];
}

// Anzahl der noch nicht abgerechneten Termine
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'durchgeführt' AND billing_status = 'offen'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['billing'] = $row['count'];
}

// Durchschnittliche Termindauer (tatsächlich)
$result = $conn->query("SELECT AVG(actual_duration) as avg_duration FROM appointments WHERE actual_duration IS NOT NULL");
if ($result && $row = $result->fetch_assoc()) {
    $stats['avg_duration'] = round($row['avg_duration']);
}

// Top 5 Kunden mit den meisten Terminen
$topCustomersResult = $conn->query("
    SELECT c.customer_id, 
           CASE WHEN c.customer_type = 'business' THEN c.company_name ELSE CONCAT(c.first_name, ' ', c.last_name) END as customer_name,
           COUNT(a.appointment_id) as appointment_count
    FROM customers c
    JOIN appointments a ON c.customer_id = a.customer_id
    GROUP BY c.customer_id
    ORDER BY appointment_count DESC
    LIMIT 5
");
$topCustomers = [];
if ($topCustomersResult) {
    while ($row = $topCustomersResult->fetch_assoc()) {
        $topCustomers[] = $row;
    }
}

// Letzte 5 Termine abrufen
$upcomingAppointments = array();
$result = $conn->query("
    SELECT a.*, 
           CASE WHEN c.customer_type = 'business' THEN c.company_name ELSE CONCAT(c.first_name, ' ', c.last_name) END as customer_name
    FROM appointments a
    JOIN customers c ON a.customer_id = c.customer_id
    WHERE a.start_time > NOW() AND a.status = 'geplant'
    ORDER BY a.start_time ASC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $upcomingAppointments[] = $row;
    }
}

// Termine pro Monat für Statistik
$monthlyStatsResult = $conn->query("
    SELECT 
        DATE_FORMAT(start_time, '%Y-%m') as month,
        COUNT(*) as count
    FROM 
        appointments
    WHERE 
        start_time >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY 
        DATE_FORMAT(start_time, '%Y-%m')
    ORDER BY 
        month ASC
");
$monthlyStats = [];
if ($monthlyStatsResult) {
    while ($row = $monthlyStatsResult->fetch_assoc()) {
        $monthlyStats[] = $row;
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
    </div>
    
    <!-- Statistik-Karten -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card dashboard-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Kunden</h6>
                            <h2 class="mt-2 mb-0"><?php echo $stats['customers']; ?></h2>
                        </div>
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <a href="/customers/" class="text-white stretched-link">Alle anzeigen</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Termine gesamt</h6>
                            <h2 class="mt-2 mb-0"><?php echo $stats['appointments']; ?></h2>
                        </div>
                        <i class="fas fa-calendar-alt fa-2x"></i>
                    </div>
                    <a href="/appointments/" class="text-white stretched-link">Alle anzeigen</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Anstehende Termine</h6>
                            <h2 class="mt-2 mb-0"><?php echo $stats['upcoming']; ?></h2>
                        </div>
                        <i class="fas fa-calendar-day fa-2x"></i>
                    </div>
                    <a href="/appointments/" class="text-white stretched-link">Alle anzeigen</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Abzurechnen</h6>
                            <h2 class="mt-2 mb-0"><?php echo $stats['billing']; ?></h2>
                        </div>
                        <i class="fas fa-file-invoice fa-2x"></i>
                    </div>
                    <a href="/billing/" class="text-white stretched-link">Zur Abrechnung</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Erweiterte Statistiken -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Termindaten</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stats-item text-center mb-3">
                                <h3 class="text-primary"><?php echo isset($stats['avg_duration']) ? $stats['avg_duration'] : 0; ?> min</h3>
                                <p class="text-muted">Durchschnittliche Termindauer</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stats-item text-center mb-3">
                                <h3 class="text-success"><?php echo $stats['appointments'] > 0 ? round($stats['billing'] / $stats['appointments'] * 100) : 0; ?>%</h3>
                                <p class="text-muted">Abrechungsrate</p>
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="text-muted mt-3">Termine pro Monat</h6>
                    <div class="progress-group">
                        <?php foreach ($monthlyStats as $month): 
                            $monthName = date('M Y', strtotime($month['month'] . '-01'));
                            $percentage = min(100, $month['count'] * 5); // Skalierung für die Anzeige
                        ?>
                        <div class="mb-2">
                            <span class="text-muted"><?php echo $monthName; ?></span>
                            <span class="float-end"><?php echo $month['count']; ?></span>
                            <div class="progress mt-1" style="height: 6px;">
                                <div class="progress-bar bg-primary" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-trophy"></i> Top Kunden</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($topCustomers)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Kunde</th>
                                        <th class="text-end">Termine</th>
                                        <th class="text-end">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topCustomers as $customer): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                            <td class="text-end"><?php echo $customer['appointment_count']; ?></td>
                                            <td class="text-end">
                                                <a href="/customers/edit.php?id=<?php echo $customer['customer_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-user-edit"></i>
                                                </a>
                                                <a href="/appointments/add.php?customer_id=<?php echo $customer['customer_id']; ?>" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-calendar-plus"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Keine Kunden mit Terminen gefunden.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Anstehende Termine -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calendar-check"></i> Anstehende Termine</h5>
                </div>
                <div class="card-body">
                    <?php if (count($upcomingAppointments) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Uhrzeit</th>
                                        <th>Kunde</th>
                                        <th>Terminbezeichnung</th>
                                        <th>Ort</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcomingAppointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo date("d.m.Y", strtotime($appointment['start_time'])); ?></td>
                                            <td><?php echo date("H:i", strtotime($appointment['start_time'])); ?> - <?php echo date("H:i", strtotime($appointment['end_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['customer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['title']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['location']); ?></td>
                                            <td>
                                                <a href="/appointments/edit.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="/appointments/complete.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> Keine anstehenden Termine.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-muted">
                    <a href="/appointments/add.php" class="btn btn-success btn-sm">
                        <i class="fas fa-plus-circle"></i> Neuen Termin erstellen
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Schnellzugriff-Karten -->
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card h-100 dashboard-card">
                <div class="card-body text-center">
                    <i class="fas fa-user-plus fa-3x mb-3 text-primary"></i>
                    <h5 class="card-title">Neuer Kunde</h5>
                    <p class="card-text">Erstellen Sie einen neuen Kunden im System.</p>
                    <a href="/customers/add.php" class="btn btn-primary">Kunde anlegen</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100 dashboard-card">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-plus fa-3x mb-3 text-success"></i>
                    <h5 class="card-title">Neuer Termin</h5>
                    <p class="card-text">Planen Sie einen neuen Termin mit einem Kunden.</p>
                    <a href="/appointments/add.php" class="btn btn-success">Termin planen</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card h-100 dashboard-card">
                <div class="card-body text-center">
                    <i class="fas fa-file-invoice fa-3x mb-3 text-warning"></i>
                    <h5 class="card-title">Abrechnung</h5>
                    <p class="card-text">Verwalten Sie offene Abrechnungen und Rechnungen.</p>
                    <a href="/billing/" class="btn btn-warning">Zur Abrechnung</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Verbindung schließen
$conn->close();

require_once 'includes/footer.php';
?>