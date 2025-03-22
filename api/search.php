<?php
// Konfiguration laden
require_once '../config/database.php';

// JSON-Header
header('Content-Type: application/json');

// Prüfen, ob ein Suchbegriff übergeben wurde
if (!isset($_GET['q']) || empty($_GET['q'])) {
    echo json_encode(['error' => 'Kein Suchbegriff angegeben']);
    exit;
}

$searchTerm = sanitizeInput($_GET['q']);
$searchTerm = '%' . $searchTerm . '%';

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Suchergebnisse sammeln
$results = [
    'customers' => [],
    'appointments' => [],
    'services' => []
];

// Kunden durchsuchen
$customerSql = "SELECT 
                    customer_id, 
                    customer_type,
                    CASE 
                        WHEN customer_type = 'business' THEN company_name 
                        ELSE CONCAT(first_name, ' ', last_name) 
                    END as name,
                    email,
                    phone
                FROM customers 
                WHERE 
                    CONCAT(first_name, ' ', last_name) LIKE ? OR
                    company_name LIKE ? OR
                    email LIKE ? OR
                    phone LIKE ?
                LIMIT 10";

$stmt = $conn->prepare($customerSql);
$stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$customerResult = $stmt->get_result();

while ($row = $customerResult->fetch_assoc()) {
    $results['customers'][] = [
        'id' => $row['customer_id'],
        'type' => $row['customer_type'],
        'name' => $row['name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'url' => '/customers/edit.php?id=' . $row['customer_id']
    ];
}
$stmt->close();

// Termine durchsuchen
$appointmentSql = "SELECT 
                      a.appointment_id, 
                      a.title, 
                      a.start_time,
                      a.status,
                      CASE 
                          WHEN c.customer_type = 'business' THEN c.company_name 
                          ELSE CONCAT(c.first_name, ' ', c.last_name) 
                      END as customer_name
                   FROM appointments a
                   JOIN customers c ON a.customer_id = c.customer_id
                   WHERE 
                      a.title LIKE ? OR
                      a.description LIKE ? OR
                      a.location LIKE ?
                   LIMIT 10";

$stmt = $conn->prepare($appointmentSql);
$stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$appointmentResult = $stmt->get_result();

while ($row = $appointmentResult->fetch_assoc()) {
    $results['appointments'][] = [
        'id' => $row['appointment_id'],
        'title' => $row['title'],
        'customer' => $row['customer_name'],
        'date' => date('d.m.Y H:i', strtotime($row['start_time'])),
        'status' => $row['status'],
        'url' => '/appointments/edit.php?id=' . $row['appointment_id']
    ];
}
$stmt->close();

// Leistungen durchsuchen
$serviceSql = "SELECT 
                   s.service_id,
                   s.description,
                   a.appointment_id,
                   a.title as appointment_title,
                   CASE 
                       WHEN c.customer_type = 'business' THEN c.company_name 
                       ELSE CONCAT(c.first_name, ' ', c.last_name) 
                   END as customer_name
                FROM services s
                JOIN appointments a ON s.appointment_id = a.appointment_id
                JOIN customers c ON a.customer_id = c.customer_id
                WHERE 
                   s.description LIKE ?
                LIMIT 10";
       
$stmt = $conn->prepare($serviceSql);
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$serviceResult = $stmt->get_result();

while ($row = $serviceResult->fetch_assoc()) {
    $results['services'][] = [
        'id' => $row['service_id'],
        'description' => $row['description'],
        'appointment_title' => $row['appointment_title'],
        'customer' => $row['customer_name'],
        'url' => '/appointments/edit.php?id=' . $row['appointment_id']
    ];
}
$stmt->close();

// Gesamtergebnisanzahl berechnen
$totalResults = count($results['customers']) + count($results['appointments']) + count($results['services']);

// Antwort zusammenstellen
$response = [
    'success' => true,
    'query' => $_GET['q'],
    'total' => $totalResults,
    'results' => $results
];

// Ergebnis zurückgeben
echo json_encode($response);

// Verbindung schließen
$conn->close();
?>