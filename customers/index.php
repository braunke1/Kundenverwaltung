<?php
require_once '../includes/header.php';

// Datenbankverbindung herstellen
$conn = getDbConnection();

// Suchfilter
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$customerType = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';

// SQL-Abfrage vorbereiten
$sql = "SELECT * FROM customers WHERE 1=1";

// Suchfilter hinzufügen
if (!empty($searchTerm)) {
    $searchTerm = '%' . $searchTerm . '%';
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR company_name LIKE ? OR email LIKE ?)";
}

if (!empty($customerType)) {
    $sql .= " AND customer_type = ?";
}

$sql .= " ORDER BY customer_type, COALESCE(company_name, CONCAT(first_name, ' ', last_name))";

// Vorbereiten und ausführen der Abfrage
$stmt = $conn->prepare($sql);

// Parameter binden
if (!empty($searchTerm) && !empty($customerType)) {
    $stmt->bind_param("sssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $customerType);
} elseif (!empty($searchTerm)) {
    $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
} elseif (!empty($customerType)) {
    $stmt->bind_param("s", $customerType);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-users"></i> Kundenverwaltung</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="/customers/add.php" class="btn btn-sm btn-primary">
                <i class="fas fa-user-plus"></i> Neuer Kunde
            </a>
        </div>
    </div>
    
    <!-- Suchfilter -->
    <div class="row mb-4">
        <div class="col-12">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Suche nach Name, Firma, E-Mail..." name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">Alle Kundentypen</option>
                        <option value="private" <?php echo ($customerType === 'private') ? 'selected' : ''; ?>>Privatkunden</option>
                        <option value="business" <?php echo ($customerType === 'business') ? 'selected' : ''; ?>>Geschäftskunden</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <a href="/customers/" class="btn btn-outline-secondary w-100">Zurücksetzen</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Kundenliste -->
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Typ</th>
                    <th>Name/Firma</th>
                    <th>E-Mail</th>
                    <th>Telefon</th>
                    <th>Ort</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['customer_id']; ?></td>
                            <td>
                                <?php if ($row['customer_type'] === 'private'): ?>
                                    <span class="badge bg-info">Privat</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Geschäft</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['customer_type'] === 'business'): ?>
                                    <?php echo htmlspecialchars($row['company_name']); ?>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td><?php echo htmlspecialchars($row['city']); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="/customers/edit.php?id=<?php echo $row['customer_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="/appointments/add.php?customer_id=<?php echo $row['customer_id']; ?>" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-calendar-plus"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete(<?php echo $row['customer_id']; ?>, '<?php echo ($row['customer_type'] === 'business') ? addslashes($row['company_name']) : addslashes($row['first_name'] . ' ' . $row['last_name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">Keine Kunden gefunden</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    if (confirm('Möchten Sie ' + name + ' wirklich löschen?')) {
        window.location.href = '/customers/delete.php?id=' + id;
    }
}
</script>

<?php
// Verbindung schließen
$stmt->close();
$conn->close();

require_once '../includes/footer.php';
?>