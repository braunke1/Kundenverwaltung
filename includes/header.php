<?php
// Session starten
session_start();

// Pfad zur Basiskonfiguration
require_once __DIR__ . '/../config/database.php';

// Überprüfen, ob der Benutzer angemeldet ist (später zu implementieren)
$isLoggedIn = true; // Für die Entwicklung vorübergehend auf true gesetzt
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP/CRM System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- jQuery (wird für Select2 benötigt) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Select2 für erweiterte Dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- Benutzerdefiniertes CSS -->
    <link rel="stylesheet" href="/css/style.css">
    
    <!-- Einige CSS-Anpassungen für Select2 -->
    <style>
    .select2-container--default .select2-results__option {
        padding: 8px 12px;
    }
    .select2-result-customer {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .customer-type {
        font-size: 0.8em;
        color: #666;
        background-color: #f0f0f0;
        padding: 2px 6px;
        border-radius: 3px;
    }
    .select2-container .select2-selection--single {
        height: 38px;
        padding: 5px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
    /* Fix für Select2 in Bootstrap Modal-Dialogen */
    .select2-container {
        z-index: 9999;
    }
    </style>
</head>
<body>
    <!-- Hauptnavigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">ERP/CRM System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/customers/">
                            <i class="fas fa-users"></i> Kunden
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/appointments/calendar.php">
                            <i class="fas fa-calendar-alt"></i> Termine
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/billing/">
                            <i class="fas fa-file-invoice"></i> Abrechnung
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/import-export/">
                            <i class="fas fa-exchange-alt"></i> Import/Export
                        </a>
                    </li>
                </ul>
                <?php if ($isLoggedIn): ?>
                <div class="navbar-nav">
                    <a class="nav-link" href="#"><i class="fas fa-user"></i> Profil</a>
                    <a class="nav-link" href="#"><i class="fas fa-sign-out-alt"></i> Abmelden</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Container für Hauptinhalt -->
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar Navigation -->
            <?php include_once __DIR__ . '/sidebar.php'; ?>
            
            <!-- Hauptinhalt -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">