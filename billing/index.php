<?php
require_once '../includes/header.php';

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Filter-Parameter
$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// SQL für die Abrechnungsübersicht
$sql = "SELECT 
            c.customer_id,
            c.customer_type,
            CASE 
                WHEN c.customer_type = 'business' THEN c.company_name 
                ELSE CONCAT(c.first_name, ' ', c.last_name) 
            END as customer_name,
            a.appointment_id,
            a.title,
            a.start_time,
            a.actual_duration,
            a.rounded_duration,
            a.status,
            a.billing_status,
            GROUP_CONCAT(s.description SEPARATOR '; ') as services_provided
        FROM 
            customers c
        JOIN 
            appointments a ON c.customer_id = a.customer_id
        LEFT JOIN 
            services s ON a.appointment_id = s.appointment_id
        WHERE 
            a.status = 'durchgeführt' 
            AND a.billing_status = 'offen'";

$params = [];
$types = "";

// Filter anwenden
if ($customerId > 0) {
    $sql .= " AND c.customer_id = ?";
    $params[] = $customerId;
    $types .= "i";
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

$sql .= " GROUP BY c.customer_id, a.appointment_id
          ORDER BY c.customer_id, a.start_time";

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

// Ergebnisse nach Kunden gruppieren
$billingData = [];
while ($row = $result->fetch_assoc()) {
    $customerId = $row['customer_id'];
    if (!isset($billingData[$customerId])) {
        $billingData[$customerId] = [
            'customer_name' => $row['customer_name'],
            'customer_type' => $row['customer_type'],
            'appointments' => [],
            'total_duration' => 0,
            'total_rounded_duration' => 0
        ];
    }
    
    $billingData[$customerId]['appointments'][] = $row;
    $billingData[$customerId]['total_duration'] += $row['actual_duration'];
    $billingData[$customerId]['total_rounded_duration'] += $row['rounded_duration'];
}

// Funktion zum Markieren von Terminen als abgerechnet
if (isset($_POST['mark_billed']) && isset($_POST['customer_id'])) {
    $billCustomerId = (int)$_POST['customer_id'];
    $appointmentIds = isset($_POST['appointment_ids']) ? $_POST['appointment_ids'] : [];
    
    if (!empty($appointmentIds) && is_array($appointmentIds)) {
        $conn->begin_transaction();
        
        try {
            // Termine als abgerechnet markieren
            $updateAppointmentStmt = $conn->prepare("
                UPDATE appointments 
                SET billing_status = 'abgerechnet'
                WHERE appointment_id = ? AND customer_id = ?
            ");
            
            // Leistungen als abgerechnet markieren
            $updateServiceStmt = $conn->prepare("
                UPDATE services 
                SET billing_status = 'abgerechnet'
                WHERE appointment_id = ?
            ");
            
            foreach ($appointmentIds as $appointmentId) {
                $appointmentId = (int)$appointmentId;
                
                $updateAppointmentStmt->bind_param("ii", $appointmentId, $billCustomerId);
                $updateAppointmentStmt->execute();
                
                $updateServiceStmt->bind_param("i", $appointmentId);
                $updateServiceStmt->execute();
            }
            
            $updateAppointmentStmt->close();
            $updateServiceStmt->close();
            
            $conn->commit();
            
            // Seite neu laden, um die aktualisierten Daten anzuzeigen
            header("Location: /billing/");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Fehler beim Aktualisieren des Abrechnungsstatus: " . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-file-invoice"></i> Abrechnungsübersicht</h1>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-filter"></i> Filter</h5>
        </div>
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-4">
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
                    <label for="date_from" class="form-label">Datum von</label>
                    <input type="text" class="form-control" id="date_from" name="date_from" placeholder="TT.MM.JJJJ" value="<?php echo $dateFrom; ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Datum bis</label>
                    <input type="text" class="form-control" id="date_to" name="date_to" placeholder="TT.MM.JJJJ" value="<?php echo $dateTo; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filtern
                    </button>
                </div>
                <div class="col-12">
                    <a href="/billing/" class="btn btn-outline-secondary">
                        <i class="fas fa-undo"></i> Filter zurücksetzen
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Ergebnisliste -->
    <?php if (!empty($billingData)): ?>
        <?php foreach ($billingData as $customerId => $customerData): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo htmlspecialchars($customerData['customer_name']); ?></h5>
                        <div>
                            <span class="badge bg-light text-dark">
                                Gesamt: <?php echo $customerData['total_duration']; ?> Min. 
                                (gerundet: <?php echo $customerData['total_rounded_duration']; ?> Min.)
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="select-all-<?php echo $customerId; ?>" onchange="toggleAllCheckboxes(this, <?php echo $customerId; ?>)"></th>
                                        <th>Datum</th>
                                        <th>Termin</th>
                                        <th>Dauer (Min.)</th>
                                        <th>Dauer gerundet (Min.)</th>
                                        <th>Erbrachte Leistungen</th>
                                        <th>Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customerData['appointments'] as $appointment): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="appointment_ids[]" value="<?php echo $appointment['appointment_id']; ?>" class="appointment-checkbox-<?php echo $customerId; ?>">
                                            </td>
                                            <td><?php echo date('d.m.Y', strtotime($appointment['start_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['title']); ?></td>
                                            <td><?php echo $appointment['actual_duration']; ?></td>
                                            <td><?php echo $appointment['rounded_duration']; ?></td>
                                            <td><?php echo htmlspecialchars($appointment['services_provided'] ?: 'Keine Leistungen erfasst'); ?></td>
                                            <td>
                                                <a href="/appointments/edit.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-primary">
                                        <th colspan="3" class="text-end">Gesamtdauer:</th>
                                        <th><?php echo $customerData['total_duration']; ?> Min.</th>
                                        <th><?php echo $customerData['total_rounded_duration']; ?> Min.</th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-3">
                            <button type="submit" name="mark_billed" class="btn btn-success" onclick="return confirm('Ausgewählte Termine als abgerechnet markieren?')">
                                <i class="fas fa-check"></i> Ausgewählte Termine als abgerechnet markieren
                            </button>
                            <div>
                                <button type="button" class="btn btn-primary" onclick="printBillingTable(<?php echo $customerId; ?>)">
                                    <i class="fas fa-print"></i> Drucken
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="copyToClipboard(<?php echo $customerId; ?>)">
                                    <i class="fas fa-copy"></i> In Zwischenablage kopieren
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Keine offenen Abrechnungen gefunden.
        </div>
    <?php endif; ?>
</div>

<script>
// Funktion zum Auswählen aller Checkboxen einer Kundengruppe
function toggleAllCheckboxes(selectAllCheckbox, customerId) {
    const checkboxes = document.querySelectorAll('.appointment-checkbox-' + customerId);
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

// Funktion zum Drucken einer Abrechnungstabelle
function printBillingTable(customerId) {
    const customerCard = document.querySelector(`[name="customer_id"][value="${customerId}"]`).closest('.card');
    const customerName = customerCard.querySelector('.card-header h5').textContent;
    
    let printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Abrechnung - ${customerName}</title>
            <style>
                body { font-family: Arial, sans-serif; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .text-end { text-align: right; }
                .footer { margin-top: 20px; }
            </style>
        </head>
        <body>
            <h1>Abrechnung - ${customerName}</h1>
            <p>Datum: ${new Date().toLocaleDateString()}</p>
    `);
    
    const table = customerCard.querySelector('table').cloneNode(true);
    
    // Entferne die erste Spalte (Checkboxen) und die Aktionen-Spalte
    for (let i = 0; i < table.rows.length; i++) {
        table.rows[i].deleteCell(table.rows[i].cells.length - 1); // Aktionen-Spalte
        table.rows[i].deleteCell(0); // Checkbox-Spalte
    }
    
    printWindow.document.write(table.outerHTML);
    
    printWindow.document.write(`
            <div class="footer">
                <p>Gesamtdauer (gerundet): ${customerCard.querySelector('.badge').textContent.trim()}</p>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// Funktion zum Kopieren der Tabellendaten in die Zwischenablage
function copyToClipboard(customerId) {
    const customerCard = document.querySelector(`[name="customer_id"][value="${customerId}"]`).closest('.card');
    const customerName = customerCard.querySelector('.card-header h5').textContent;
    const table = customerCard.querySelector('table');
    
    let text = `Abrechnung - ${customerName}\n\n`;
    
    // Überschriften hinzufügen (ohne Checkbox und Aktionen)
    text += 'Datum\tTermin\tDauer (Min.)\tDauer gerundet (Min.)\tErbrachte Leistungen\n';
    
    // Zeilendaten hinzufügen
    for (let i = 1; i < table.rows.length - 1; i++) { // Überspringe Überschrift und Fußzeile
        const row = table.rows[i];
        // Überspringe Checkbox-Spalte und Aktionen-Spalte
        text += `${row.cells[1].textContent}\t${row.cells[2].textContent}\t${row.cells[3].textContent}\t${row.cells[4].textContent}\t${row.cells[5].textContent}\n`;
    }
    
    // Gesamtdauer hinzufügen
    const totalRow = table.rows[table.rows.length - 1];
    text += `\nGesamtdauer: ${totalRow.cells[3].textContent} (gerundet: ${totalRow.cells[4].textContent})`;
    
    // In die Zwischenablage kopieren
    navigator.clipboard.writeText(text)
        .then(() => {
            alert('Abrechnungsdaten wurden in die Zwischenablage kopiert!');
        })
        .catch(err => {
            console.error('Fehler beim Kopieren in die Zwischenablage: ', err);
            alert('Fehler beim Kopieren in die Zwischenablage. Bitte manuell kopieren.');
        });
}
</script>

<?php
// Verbindung schließen
$stmt->close();
$conn->close();

require_once '../includes/footer.php';
?>