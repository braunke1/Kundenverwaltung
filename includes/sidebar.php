<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <?php
        // Aktuelle Seite bestimmen
        $currentPage = basename($_SERVER['PHP_SELF']);
        $currentDir = basename(dirname($_SERVER['PHP_SELF']));
        
        // Menüs definieren
        $menus = [
            'customers' => [
                'icon' => 'fas fa-users',
                'title' => 'Kundenverwaltung',
                'items' => [
                    ['url' => '/customers/', 'title' => 'Kundenliste', 'icon' => 'fas fa-list'],
                    ['url' => '/customers/add.php', 'title' => 'Neuer Kunde', 'icon' => 'fas fa-user-plus']
                ]
            ],
            'appointments' => [
                'icon' => 'fas fa-calendar-alt',
                'title' => 'Terminverwaltung',
                'items' => [
                    ['url' => '/appointments/calendar.php', 'title' => 'Kalender', 'icon' => 'fas fa-calendar-week'],
                    ['url' => '/appointments/', 'title' => 'Terminliste', 'icon' => 'fas fa-list'],
                    ['url' => '/appointments/add.php', 'title' => 'Neuer Termin', 'icon' => 'fas fa-plus-circle']
                ]
            ],
            'billing' => [
                'icon' => 'fas fa-file-invoice',
                'title' => 'Abrechnung',
                'items' => [
                    ['url' => '/billing/', 'title' => 'Abrechnungsübersicht', 'icon' => 'fas fa-list-alt']
                ]
            ],
            'import-export' => [
                'icon' => 'fas fa-exchange-alt',
                'title' => 'Daten & Backup',
                'items' => [
                    ['url' => '/import-export/', 'title' => 'Import/Export', 'icon' => 'fas fa-sync-alt'],
                    ['url' => '/import-export/?backup=true', 'title' => 'Backup', 'icon' => 'fas fa-database']
                ]
            ]
        ];
        
        // Anzeigen der Menüs
        foreach ($menus as $menuKey => $menu):
            $isActive = ($currentDir === $menuKey);
        ?>
        <div class="sidebar-menu mb-3">
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                <span><i class="<?php echo $menu['icon']; ?>"></i> <?php echo $menu['title']; ?></span>
            </h6>
            <ul class="nav flex-column">
                <?php foreach ($menu['items'] as $item): 
                    $isItemActive = ($currentPage === basename($item['url']));
                ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $isItemActive ? 'active' : ''; ?>" href="<?php echo $item['url']; ?>">
                        <i class="<?php echo $item['icon']; ?>"></i> <?php echo $item['title']; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </div>
</div>