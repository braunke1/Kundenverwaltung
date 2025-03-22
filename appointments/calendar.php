<?php
require_once '../includes/header.php';

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Monat und Jahr aus den GET-Parametern ermitteln oder aktuelles Datum verwenden
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Sicherstellen, dass Monat und Jahr gültig sind
if ($month < 1 || $month > 12) {
    $month = (int)date('m');
}

// Datum für den ersten Tag des Monats erstellen
$firstDayOfMonth = new DateTime("$year-$month-01");
$lastDayOfMonth = clone $firstDayOfMonth;
$lastDayOfMonth->modify('last day of this month');

// Vorheriger und nächster Monat für die Navigation
$prevMonth = clone $firstDayOfMonth;
$prevMonth->modify('-1 month');
$nextMonth = clone $firstDayOfMonth;
$nextMonth->modify('+1 month');

// Namen der Monate und Wochentage auf Deutsch
$monthNames = [
    1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
];

$dayNames = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

// Termine für den aktuellen Monat abrufen
$startDate = $firstDayOfMonth->format('Y-m-d');
$endDate = $lastDayOfMonth->format('Y-m-d');

$sql = "SELECT a.*, 
        CASE 
            WHEN c.customer_type = 'business' THEN c.company_name 
            ELSE CONCAT(c.first_name, ' ', c.last_name) 
        END as customer_name
        FROM appointments a
        JOIN customers c ON a.customer_id = c.customer_id
        WHERE a.start_time BETWEEN ? AND ? 
        ORDER BY a.start_time ASC";

$stmt = $conn->prepare($sql);
$startDateTime = $startDate . ' 00:00:00';
$endDateTime = $endDate . ' 23:59:59';
$stmt->bind_param("ss", $startDateTime, $endDateTime);
$stmt->execute();
$result = $stmt->get_result();

// Termine nach Tagen gruppieren
$appointments = [];
while ($row = $result->fetch_assoc()) {
    $date = date('Y-m-d', strtotime($row['start_time']));
    if (!isset($appointments[$date])) {
        $appointments[$date] = [];
    }
    $appointments[$date][] = $row;
}

// Kalender erstellen
$calendar = [];
$currentDay = clone $firstDayOfMonth;

// Erster Wochentag im Monat (1 = Montag, ..., 7 = Sonntag)
$firstDayOfWeek = (int)$firstDayOfMonth->format('N');

// Füge leere Zellen für die Tage vor dem ersten Tag des Monats hinzu
// Da unser Kalender mit Montag beginnt, müssen wir $firstDayOfWeek - 1 leere Zellen hinzufügen
for ($i = 1; $i < $firstDayOfWeek; $i++) {
    $calendar[] = [
        'day' => null,
        'isCurrentMonth' => false,
        'date' => null
    ];
}

// Füge die Tage des aktuellen Monats hinzu
$daysInMonth = (int)$lastDayOfMonth->format('d');
for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
    $calendar[] = [
        'day' => $day,
        'isCurrentMonth' => true,
        'date' => $date,
        'appointments' => isset($appointments[$date]) ? $appointments[$date] : []
    ];
}

// Füge leere Zellen für die Tage nach dem letzten Tag des Monats hinzu, um die Woche zu vervollständigen
$lastDayOfWeek = (int)$lastDayOfMonth->format('N');
if ($lastDayOfWeek < 7) {
    for ($i = $lastDayOfWeek + 1; $i <= 7; $i++) {
        $calendar[] = [
            'day' => null,
            'isCurrentMonth' => false,
            'date' => null
        ];
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-calendar-alt"></i> Kalenderansicht</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="/appointments/add.php" class="btn btn-sm btn-primary me-2">
                <i class="fas fa-plus-circle"></i> Neuer Termin
            </a>
            <a href="/appointments/" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-list"></i> Listenansicht
            </a>
        </div>
    </div>
    
    <!-- Monatsnavigation -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="?month=<?php echo $prevMonth->format('m'); ?>&year=<?php echo $prevMonth->format('Y'); ?>" class="btn btn-outline-primary">
                            <i class="fas fa-chevron-left"></i> <?php echo $monthNames[(int)$prevMonth->format('m')]; ?>
                        </a>
                        <h3 class="mb-0"><?php echo $monthNames[$month] . ' ' . $year; ?></h3>
                        <a href="?month=<?php echo $nextMonth->format('m'); ?>&year=<?php echo $nextMonth->format('Y'); ?>" class="btn btn-outline-primary">
                            <?php echo $monthNames[(int)$nextMonth->format('m')]; ?> <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Kalender -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="calendar">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <?php foreach ($dayNames as $dayName): ?>
                                        <th class="text-center"><?php echo $dayName; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $days = array_chunk($calendar, 7);
                                foreach ($days as $week): 
                                ?>
                                <tr>
                                    <?php foreach ($week as $day): ?>
                                        <td class="<?php echo $day['isCurrentMonth'] ? '' : 'bg-light'; ?>" style="height: 120px; width: 14.28%; vertical-align: top;">
                                            <?php if ($day['day']): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="fw-bold"><?php echo $day['day']; ?></span>
                                                    <a href="/appointments/add.php?date=<?php echo date('d.m.Y', strtotime($day['date'])); ?>" class="btn btn-sm btn-outline-primary rounded-circle">
                                                        <i class="fas fa-plus"></i>
                                                    </a>
                                                </div>
                                                
                                                <?php if (!empty($day['appointments'])): ?>
                                                    <div class="appointments">
                                                        <?php foreach ($day['appointments'] as $appointment): ?>
                                                            <?php
                                                            $statusClass = '';
                                                            switch ($appointment['status']) {
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
                                                            <div class="appointment mb-1 p-1 rounded <?php echo $statusClass; ?> text-white">
                                                                <small>
                                                                    <a href="/appointments/edit.php?id=<?php echo $appointment['appointment_id']; ?>" class="text-white text-decoration-none">
                                                                        <?php echo date('H:i', strtotime($appointment['start_time'])); ?> - 
                                                                        <?php echo htmlspecialchars(substr($appointment['title'], 0, 20)); ?>
                                                                        <?php if (strlen($appointment['title']) > 20): ?>...<?php endif; ?>
                                                                    </a>
                                                                </small>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.calendar th {
    background-color: #f8f9fa;
}

.appointment {
    font-size: 0.8rem;
}

.appointment:hover {
    opacity: 0.9;
    cursor: pointer;
}
</style>

<?php
// Verbindung schließen
$stmt->close();
$conn->close();

require_once '../includes/footer.php';
?>