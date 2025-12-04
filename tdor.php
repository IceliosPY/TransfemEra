<?php
// tdor.php : page mémoire TDoR (membres + admins uniquement)
session_start();
require_once __DIR__ . '/config.php';

// Toujours déclarer l'encodage de la page
header('Content-Type: text/html; charset=utf-8');

// Protection : seulement membres + admins
if (
    empty($_SESSION['user_id']) ||
    empty($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['membre', 'admin'], true)
) {
    header('Location: login.php');
    exit;
}

// --- Fonction utilitaire pour lire un CSV d'une année donnée ---
function loadTdorYearFromCsv(int $year): array
{
    // Dossier : TransfemEra/DATA/tdor/2025.csv
    $filePath = __DIR__ . "/DATA/tdor/{$year}.csv";

    if (!is_readable($filePath)) {
        return []; // fichier introuvable ou illisible
    }

    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        return [];
    }

    $rows = [];

    // On lit la première ligne brute pour détecter le séparateur
    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);
        return [];
    }

    // Force en UTF-8 si besoin pour les accents
    if (!mb_detect_encoding($firstLine, 'UTF-8', true)) {
        $firstLine = mb_convert_encoding($firstLine, 'UTF-8', 'Windows-1252,ISO-8859-1');
    }

    $candidates = [';', ',', "\t"];
    $delimiter  = ',';
    $bestCount  = 0;

    foreach ($candidates as $cand) {
        $count = substr_count($firstLine, $cand);
        if ($count > $bestCount) {
            $bestCount = $count;
            $delimiter = $cand;
        }
    }

    // On reparse la 1re ligne comme en-têtes
    $headers = str_getcsv(trim($firstLine), $delimiter);
    if (empty($headers)) {
        fclose($handle);
        return [];
    }

    // Normalisation basique des noms de colonnes
    $normalizedHeaders = array_map(static function ($h) {
        return trim($h);
    }, $headers);

    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        // saute les lignes complètement vides
        if (count(array_filter($data, fn($v) => $v !== '' && $v !== null)) === 0) {
            continue;
        }

        // Conversion de chaque champ en UTF-8 si besoin
        foreach ($data as $i => $val) {
            if (!mb_detect_encoding($val, 'UTF-8', true)) {
                $data[$i] = mb_convert_encoding($val, 'UTF-8', 'Windows-1252,ISO-8859-1');
            }
        }

        // Aligne les valeurs sur les colonnes
        $rowAssoc = [];
        foreach ($data as $idx => $value) {
            $key = $normalizedHeaders[$idx] ?? "col{$idx}";
            $rowAssoc[$key] = $value;
        }

        $rows[] = [
            'date'     => $rowAssoc['Date']     ?? '',
            'name'     => $rowAssoc['Name']     ?? '',
            'age'      => $rowAssoc['Age']      ?? '',
            'location' => $rowAssoc['Location'] ?? '',
            'country'  => $rowAssoc['Country']  ?? '',
            'category' => $rowAssoc['Category'] ?? '',
            'cause'    => $rowAssoc['Cause']    ?? '',
        ];
    }

    fclose($handle);
    return $rows;
}

// Année sélectionnée (pour le tableau en bas de page)
$selectedYear = null;
if (!empty($_GET['year']) && ctype_digit($_GET['year'])) {
    $selectedYear = (int)$_GET['year'];
}

// Années pour lesquelles on prévoit des fichiers CSV (pour la partie tableau / stats détaillées)
// ici tu peux avoir 2025, 2024, 2023, etc. (ou range(2025, 2000, -1) si tu as étendu)
$configuredYears = range(2025, 2000, -1);

// On charge les données uniquement pour l'année demandée
$tdorData = [];
if ($selectedYear !== null && in_array($selectedYear, $configuredYears, true)) {
    $tdorData[$selectedYear] = loadTdorYearFromCsv($selectedYear);
}

// Liste des années disponibles pour les accordéons
$availableYears = $configuredYears;
rsort($availableYears);

// --- Pagination + stats (pour l'année sélectionnée) ---
$perPage = 10;
$page = isset($_GET['page']) && ctype_digit($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$entries = [];
$entriesPage = [];
$total = 0;
$totalPages = 1;
$categoryCounts = [];

if ($selectedYear !== null && in_array($selectedYear, $configuredYears, true)) {
    $entries = $tdorData[$selectedYear] ?? [];
    $total   = count($entries);
    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset  = ($page - 1) * $perPage;
    $entriesPage = array_slice($entries, $offset, $perPage);

    // Stats par catégorie (pour TOUTE l’année sélectionnée)
    foreach ($entries as $e) {
        $cat = trim($e['category'] ?? '');
        if ($cat === '') {
            $cat = 'Inconnu / non rapporté';
        }
        $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
    }
}

// Pour le JS du camembert (section stats par catégorie, année sélectionnée)
$catLabelsJson = !empty($categoryCounts)
    ? json_encode(array_keys($categoryCounts), JSON_UNESCAPED_UNICODE)
    : '[]';
$catValuesJson = !empty($categoryCounts)
    ? json_encode(array_values($categoryCounts))
    : '[]';

// --- Stats globales : année / pays / catégorie + données pour la carte ---
$allYearCounts = [];
$totalReportsAllYears = 0;
$countryCountsAllYears = [];
$categoryCountsAllYears = [];
$allMapPoints = []; // pour la carte (toutes années confondues)

$tdorDir = __DIR__ . '/DATA/tdor';

if (is_dir($tdorDir)) {
    foreach (glob($tdorDir . '/*.csv') as $file) {
        $basename = basename($file);
        // On attend des fichiers de la forme 2025.csv, 2024.csv, etc.
        if (preg_match('/^(\d{4})\.csv$/', $basename, $m)) {
            $year = (int)$m[1];
            $rows = loadTdorYearFromCsv($year);
            $count = count($rows);
            $allYearCounts[$year] = $count;
            $totalReportsAllYears += $count;

            foreach ($rows as $row) {
                // PAYS
                $country = trim($row['country'] ?? '');
                if ($country === '') {
                    $country = 'Inconnu / non rapporté';
                }
                $countryCountsAllYears[$country] = ($countryCountsAllYears[$country] ?? 0) + 1;

                // CATÉGORIE
                $catGlobal = trim($row['category'] ?? '');
                if ($catGlobal === '') {
                    $catGlobal = 'Inconnu / non rapporté';
                }
                $categoryCountsAllYears[$catGlobal] = ($categoryCountsAllYears[$catGlobal] ?? 0) + 1;

                // Données pour la carte : info, coords déduites côté JS via le pays
                $allMapPoints[] = [
                    'year'     => $year,
                    'name'     => $row['name'] ?? '',
                    'date'     => $row['date'] ?? '',
                    'location' => $row['location'] ?? '',
                    'country'  => $row['country'] ?? '',
                    'category' => $row['category'] ?? '',
                    'cause'    => $row['cause'] ?? '',
                ];
            }
        }
    }
    // Trie les années décroissantes
    krsort($allYearCounts);
    // Trie pays / catégories par nombre décroissant
    arsort($countryCountsAllYears);
    arsort($categoryCountsAllYears);
}

// Pour le JS de la carte
$mapPointsJson = !empty($allMapPoints)
    ? json_encode($allMapPoints, JSON_UNESCAPED_UNICODE)
    : '[]';

// --- Bloc "Sources TDoR" : fichiers téléchargeables par année ---
$sourceYears = [2025, 2024, 2023, 2022, 2021];

$downloadSources = [];
foreach ($sourceYears as $y) {
    $downloadSources[$y] = [
        [
            'label' => "TDoR $y — Victimes (PNG)",
            'path'  => "DATA/tdor/tdor_{$y}_victims.png",
        ],
        [
            'label' => "TDoR $y — Slides (PPTX)",
            'path'  => "DATA/tdor/tdor_{$y}_slides.pptx",
        ],
        [
            'label' => "TDoR $y — Liste des noms (PDF)",
            'path'  => "DATA/tdor/tdor_{$y}_namelist.pdf",
        ],
        [
            'label' => "TDoR $y — Carte (PNG)",
            'path'  => "DATA/tdor/tdor_{$y}_map.png",
        ],
        [
            'label' => "TDoR $y — Tableur (CSV)",
            'path'  => "DATA/tdor/{$y}.csv",
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>TDoR — Transfem Era</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="CSS/index.css">

    <!-- Leaflet CSS pour la carte -->
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    />

    <style>
        .tdor-page {
            max-width: 980px;
            margin: 110px auto 40px;
            padding: 1.6rem 1.8rem 2rem;
            border-radius: 22px;
            background: rgba(255,255,255,0.98);
            box-shadow: 0 18px 45px rgba(15,23,42,0.20);
            backdrop-filter: blur(10px);
        }

        .tdor-intro {
            margin-bottom: 1.6rem;
        }
        .tdor-intro h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.4rem;
        }
        .tdor-intro p {
            font-size: 0.95rem;
            color: #374151;
            line-height: 1.6;
        }

        /* Accordéons années */
        .tdor-years {
            margin-top: 1.2rem;
        }
        .tdor-year {
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            margin-bottom: 0.6rem;
            overflow: hidden;
        }
        .tdor-year-header {
            width: 100%;
            padding: 0.6rem 0.9rem;
            border: none;
            background: linear-gradient(90deg, #f5a9b830, #5bcffb20);
            display: flex;
            align-items: center;
            justify-content: space_between;
            font-size: 0.95rem;
            cursor: pointer;
        }
        .tdor-year-header span {
            font-weight: 500;
            color: #111827;
        }
        .tdor-year-header small {
            color: #6b7280;
            font-size: 0.8rem;
        }
        .tdor-year-header .chevron {
            margin-left: 0.6rem;
            font-size: 0.85rem;
        }

        .tdor-year-panel {
            display: none;
            padding: 0.6rem 0.9rem 0.8rem;
            font-size: 0.9rem;
            color: #4b5563;
        }
        .tdor-year-panel p {
            margin-bottom: 0.5rem;
        }

        .tdor-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            border-radius: 999px;
            padding: 0.3rem 0.9rem;
            border: 1px solid rgba(59,130,246,0.5);
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 0.85rem;
            text-decoration: none;
        }
        .tdor-link-btn:hover {
            background: #dbeafe;
        }

        /* Tableau principal */
        .tdor-table-block {
            margin-top: 2rem;
            padding-top: 1.4rem;
            border-top: 1px solid #e5e7eb;
        }
        .tdor-table-block h3 {
            font-size: 1.1rem;
            margin-bottom: 0.6rem;
            color: #111827;
        }
        .tdor-table-description {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 0.6rem;
        }

        .tdor-table-wrapper {
            width: 100%;
            overflow-x: auto;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        table.tdor-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        table.tdor-table thead {
            background: #f3f4f6;
        }
        table.tdor-table th,
        table.tdor-table td {
            padding: 0.45rem 0.7rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            white-space: nowrap;
        }
        table.tdor-table th {
            font-weight: 600;
            color: #111827;
            font-size: 0.8rem;
        }
        table.tdor-table tbody tr:nth-child(even) {
            background: #ffffff;
        }
        table.tdor-table tbody tr:nth-child(odd) {
            background: #fdf2f8;
        }
        table.tdor-table tbody tr:hover {
            background: #fee2e2;
        }

        /* Pagination */
        .pagination {
            margin-top: 0.7rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            font-size: 0.8rem;
        }
        .pagination a,
        .pagination span {
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            text-decoration: none;
            color: #4b5563;
        }
        .pagination a:hover {
            background: #f3f4f6;
        }
        .pagination .active {
            background: #ec4899;
            border-color: #ec4899;
            color: #ffffff;
            font-weight: 600;
        }
        .pagination .disabled {
            opacity: 0.4;
            cursor: default;
        }

        /* BLOC 2 : stats par catégorie (année sélectionnée) */
        .tdor-stats-page {
            margin-top: 0;
        }
        .tdor-stats-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.6rem;
        }
        .tdor-stats-description {
            font-size: 0.9rem;
            color: #4b5563;
            margin-bottom: 1rem;
        }
        .tdor-stats-chart-wrapper {
            max-width: 480px;
            margin: 0 auto 0;
        }

        /* BLOC 3 : Statistiques totales (Année / Pays / Catégorie / Carte) */
        .tdor-total-stats-page {
            margin-top: 0;
        }
        .tdor-total-stats-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.6rem;
        }
        .tdor-total-stats-description {
            font-size: 0.9rem;
            color: #4b5563;
            margin-bottom: 1rem;
        }

        .tdor-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .tdor-tab {
            padding: 0.5rem 1rem;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #f3f4f6;
            cursor: pointer;
            font-size: 0.9rem;
            color: #374151;
            transition: 0.2s;
        }
        .tdor-tab:hover {
            background: #e5e7eb;
        }
        .tdor-tab.active {
            background: #ec4899;
            border-color: #ec4899;
            color: #ffffff;
            font-weight: 600;
        }

        .tdor-tab-content {
            display: none;
            animation: fadeIn 0.2s ease;
        }
        .tdor-tab-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        .tdor-year-counts-title,
        .tdor-country-counts-title,
        .tdor-category-counts-title,
        .tdor-map-title {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: #111827;
        }
        .tdor-year-counts-description,
        .tdor-country-counts-description,
        .tdor-category-counts-description,
        .tdor-map-description {
            font-size: 0.9rem;
            color: #4b5563;
            margin-bottom: 0.7rem;
        }

        .tdor-year-counts-table-wrapper,
        .tdor-country-counts-table-wrapper,
        .tdor-category-counts-table-wrapper {
            max-width: 420px;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            overflow: hidden;
        }
        table.tdor-year-counts-table,
        table.tdor-country-counts-table,
        table.tdor-category-counts-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        table.tdor-year-counts-table th,
        table.tdor-year-counts-table td,
        table.tdor-country-counts-table th,
        table.tdor-country-counts-table td,
        table.tdor-category-counts-table th,
        table.tdor-category-counts-table td {
            padding: 0.4rem 0.7rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        table.tdor-year-counts-table thead,
        table.tdor-country-counts-table thead,
        table.tdor-category-counts-table thead {
            background: #f3f4f6;
        }
        table.tdor-year-counts-table tbody tr:nth-child(even),
        table.tdor-country-counts-table tbody tr:nth-child(even),
        table.tdor-category-counts-table tbody tr:nth-child(even) {
            background: #ffffff;
        }
        table.tdor-year-counts-table tbody tr:nth-child(odd),
        table.tdor-country-counts-table tbody tr:nth-child(odd),
        table.tdor-category-counts-table tbody tr:nth-child(odd) {
            background: #fdf2f8;
        }

        .tdor-year-counts-total {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #111827;
        }

        /* Carte */
        #tdor-map {
            width: 100%;
            height: 480px;
            border-radius: 18px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        /* BLOC 4 : Sources TDoR (téléchargements) */
        .tdor-sources-page {
            margin-top: 0;
        }
        .tdor-sources-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.6rem;
        }
        .tdor-sources-description {
            font-size: 0.9rem;
            color: #4b5563;
            margin-bottom: 1rem;
        }

        .tdor-sources-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .tdor-src-tab {
            padding: 0.45rem 0.9rem;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #f3f4f6;
            cursor: pointer;
            font-size: 0.85rem;
            color: #374151;
            transition: 0.2s;
        }
        .tdor-src-tab:hover {
            background: #e5e7eb;
        }
        .tdor-src-tab.active {
            background: #6366f1;
            border-color: #6366f1;
            color: #ffffff;
            font-weight: 600;
        }

        .tdor-sources-content {
            display: none;
            animation: fadeIn 0.2s ease;
        }
        .tdor-sources-content.active {
            display: block;
        }

        .tdor-sources-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .tdor-sources-list li a {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            font-size: 0.85rem;
            color: #1f2933;
            text-decoration: none;
        }
        .tdor-sources-list li a:hover {
            background: #e5e7eb;
        }
        .tdor-sources-list li span.unavailable {
            font-size: 0.85rem;
            color: #9ca3af;
        }

        @media (max-width: 640px) {
            .tdor-page {
                margin-top: 90px;
                padding: 1.3rem 1.2rem 1.6rem;
            }
            .tdor-year-counts-table-wrapper,
            .tdor-country-counts-table-wrapper,
            .tdor-category-counts-table-wrapper {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div id="vanta-bg"></div>

<header>
    <div id="top-bar">
        <div class="header-left">
            <h1>Transfem Era</h1>
        </div>

        <nav class="main-nav">
            <a href="index.php#accueil">Accueil</a>
            <a href="index.php#valeurs">Nos valeurs</a>
            <a href="index.php#contact">Nous contacter</a>
            <?php if (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['membre', 'admin'], true)): ?>
                <a href="posts.php">Posts</a>
                <a href="tdor.php" class="active">TDoR</a>
            <?php endif; ?>
        </nav>

        <div class="header-right">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="profile-menu">
                    <button class="profile-trigger" type="button"
                            aria-haspopup="true" aria-expanded="false">
                        <img
                            src="<?= htmlspecialchars($_SESSION['avatar_path'] ?? 'IMG/profile_default.png') ?>"
                            class="profile-pic <?= htmlspecialchars($_SESSION['avatar_shape'] ?? 'circle') ?>"
                            alt="Profil"
                        >
                    </button>

                    <div class="profile-dropdown" role="menu">
                        <a href="profil.php" role="menuitem">Mon profil</a>
                        <a href="logout.php" role="menuitem" class="logout-link">Se déconnecter</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="login-btn">Se connecter</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- 🔽 MENU FLOTTANT (même principe que sur index.php) -->
<button id="floating-menu-btn" aria-expanded="false" aria-haspopup="true">
    ☰ Sections TDoR
</button>

<div id="floating-menu" role="menu">
    <a href="#tdor-table" role="menuitem">Tableau</a>
    <a href="#tdor-stats" role="menuitem">Stats par catégorie</a>
    <a href="#tdor-total-stats" role="menuitem">Stats globales</a>
    <a href="#tdor-sources" role="menuitem">Sources</a>
</div>

<main>
    <!-- BLOC 1 : Accordéons + tableaux -->
    <section class="tdor-page">
        <div class="tdor-intro">
            <h2>Trans Day of Remembrance (TDoR)</h2>
            <p>
                Le TDoR est un moment de mémoire pour les personnes trans et
                non-binaires assassinées ou disparues. Cette page regroupe,
                année par année, les noms recensés pendant la période TDoR
                (du 1ᵉʳ octobre au 30 septembre de l’année suivante).
            </p>
            <p>
                Les tableaux ci-dessous sont là pour rendre visibles nos mort·es,
                pour documenter les violences, et pour soutenir les luttes locales.
            </p>
        </div>

        <!-- Accordéons par année -->
        <div class="tdor-years">
            <?php foreach ($availableYears as $year): ?>
                <div class="tdor-year">
                    <button type="button"
                            class="tdor-year-header"
                            data-target="year-panel-<?= $year ?>">
                        <div>
                            <span>TDoR <?= $year ?></span><br>
                            <small>1 Oct <?= $year - 1 ?> – 30 Sep <?= $year ?></small>
                        </div>
                        <div class="chevron">▾</div>
                    </button>

                    <div class="tdor-year-panel" id="year-panel-<?= $year ?>">
                        <p>
                            Tableau récapitulatif des personnes recensées pour le TDoR <?= $year ?>.
                        </p>
                        <a href="tdor.php?year=<?= $year ?>#tdor-table"
                           class="tdor-link-btn">
                            Voir la liste <?= $year ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Bloc tableau -->
        <div class="tdor-table-block" id="tdor-table">
            <?php if ($selectedYear === null || !in_array($selectedYear, $configuredYears, true)): ?>
                <h3>Aucune année sélectionnée</h3>
                <p class="tdor-table-description">
                    Choisis une année dans les blocs ci-dessus pour afficher le tableau
                    correspondant (par exemple TDoR 2025).
                </p>
            <?php else: ?>
                <?php if ($total === 0): ?>
                    <h3>Aucune entrée pour TDoR <?= $selectedYear ?></h3>
                    <p class="tdor-table-description">
                        L’année est configurée, mais aucune ligne n’a été trouvée.
                        Vérifie que ton fichier
                        <code>DATA/tdor/<?= $selectedYear ?>.csv</code>
                        existe bien sur le serveur, qu’il est au format CSV
                        (pas .ods / .xlsx) et qu’il contient des données.
                    </p>
                <?php else: ?>
                    <h3>Liste des rapports — TDoR <?= $selectedYear ?></h3>
                    <p class="tdor-table-description">
                        Données chargées depuis
                        <code>DATA/tdor/<?= $selectedYear ?>.csv</code> —
                        page <?= $page ?> / <?= $totalPages ?> (<?= $total ?> entrées).
                    </p>

                    <div class="tdor-table-wrapper">
                        <table class="tdor-table">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Nom</th>
                                <th>Âge</th>
                                <th>Lieu</th>
                                <th>Pays</th>
                                <th>Catégorie</th>
                                <th>Cause</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($entriesPage as $entry): ?>
                                <tr>
                                    <td><?= htmlspecialchars($entry['date']) ?></td>
                                    <td><?= htmlspecialchars($entry['name']) ?></td>
                                    <td><?= $entry['age'] !== '' ? htmlspecialchars($entry['age']) : '—' ?></td>
                                    <td><?= htmlspecialchars($entry['location']) ?></td>
                                    <td><?= htmlspecialchars($entry['country']) ?></td>
                                    <td><?= htmlspecialchars($entry['category']) ?></td>
                                    <td><?= htmlspecialchars($entry['cause']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php $baseUrl = 'tdor.php?year=' . $selectedYear; ?>

                            <!-- Précédent -->
                            <?php if ($page > 1): ?>
                                <a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>#tdor-table">« Précédent</a>
                            <?php else: ?>
                                <span class="disabled">« Précédent</span>
                            <?php endif; ?>

                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <?php if ($p == $page): ?>
                                    <span class="active"><?= $p ?></span>
                                <?php else: ?>
                                    <a href="<?= $baseUrl ?>&page=<?= $p ?>#tdor-table"><?= $p ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Suivant -->
                            <?php if ($page < $totalPages): ?>
                                <a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>#tdor-table">Suivant »</a>
                            <?php else: ?>
                                <span class="disabled">Suivant »</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </section>

    <!-- BLOC 2 : Statistiques par catégorie (année sélectionnée) -->
    <section class="tdor-page tdor-stats-page" id="tdor-stats">
        <h2 class="tdor-stats-title">Statistiques par catégorie</h2>
        <p class="tdor-stats-description">
            Ce bloc affiche un camembert par catégorie (suicide, violence, garde à vue, etc.) pour
            l’année TDoR sélectionnée.
        </p>

        <?php if ($selectedYear === null || !in_array($selectedYear, $configuredYears, true)): ?>
            <p style="font-size:0.9rem;color:#6b7280;">
                Aucune année sélectionnée. Choisis d’abord une année dans la section TDoR ci-dessus.
            </p>
        <?php elseif ($total === 0): ?>
            <p style="font-size:0.9rem;color:#6b7280;">
                Aucune donnée disponible pour TDoR <?= $selectedYear ?>, impossible de générer des statistiques.
            </p>
        <?php elseif (empty($categoryCounts)): ?>
            <p style="font-size:0.9rem;color:#6b7280;">
                Les données de l’année <?= $selectedYear ?> ne contiennent pas de catégories exploitables.
            </p>
        <?php else: ?>
            <p class="tdor-stats-description">
                Répartition des rapports par catégorie pour TDoR <?= $selectedYear ?>  
                (source : <code>DATA/tdor/<?= $selectedYear ?>.csv</code>).
            </p>

            <div class="tdor-stats-chart-wrapper">
                <canvas id="tdor-category-chart" width="400" height="400"></canvas>
            </div>

            <script>
                const TDOR_CATEGORY_LABELS = <?= $catLabelsJson ?>;
                const TDOR_CATEGORY_VALUES = <?= $catValuesJson ?>;
            </script>
        <?php endif; ?>
    </section>

    <!-- BLOC 3 : Statistiques totales (Année / Pays / Catégorie / Carte) -->
    <section class="tdor-page tdor-total-stats-page" id="tdor-total-stats">
        <h2 class="tdor-total-stats-title">Statistiques totales</h2>
        <p class="tdor-total-stats-description">
            Ce bloc regroupe les statistiques globales construites à partir de tous les fichiers
            CSV présents dans <code>DATA/tdor/</code>.  
            Choisis un onglet pour afficher les statistiques par année, par pays, par catégorie,
            ou la carte mondiale interactive.
        </p>

        <!-- Onglets -->
        <div class="tdor-tabs">
            <button class="tdor-tab active" data-target="stats-years">Par année</button>
            <button class="tdor-tab" data-target="stats-countries">Par pays</button>
            <button class="tdor-tab" data-target="stats-categories">Par catégorie</button>
            <button class="tdor-tab" data-target="stats-map">Carte du monde</button>
        </div>

        <div class="tdor-tabs-content">

            <!-- ONGLET 1 : Par année -->
            <div class="tdor-tab-content active" id="stats-years">
                <h3 class="tdor-year-counts-title">Nombre de signalements par année</h3>
                <p class="tdor-year-counts-description">
                    Comptage du nombre total de signalements pour chaque année TDoR.
                </p>

                <?php if (empty($allYearCounts)): ?>
                    <p style="font-size:0.9rem;color:#6b7280;">
                        Aucun fichier CSV trouvé dans <code>DATA/tdor/</code>.
                    </p>
                <?php else: ?>
                    <div class="tdor-year-counts-table-wrapper">
                        <table class="tdor-year-counts-table">
                            <thead>
                            <tr>
                                <th>Année TDoR</th>
                                <th>Nombre de signalements</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($allYearCounts as $year => $count): ?>
                                <tr>
                                    <td><?= (int)$year ?></td>
                                    <td><?= (int)$count ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="tdor-year-counts-total">
                        Total cumulé : <strong><?= (int)$totalReportsAllYears ?></strong> signalements.
                    </p>
                <?php endif; ?>
            </div>

            <!-- ONGLET 2 : Par pays -->
            <div class="tdor-tab-content" id="stats-countries">
                <h3 class="tdor-country-counts-title">Nombre de signalements par pays</h3>
                <p class="tdor-country-counts-description">
                    Comptage du nombre total de signalements par pays, toutes années confondues.
                </p>

                <?php if (empty($countryCountsAllYears)): ?>
                    <p style="font-size:0.9rem;color:#6b7280;">
                        Aucune donnée disponible sur les pays.
                    </p>
                <?php else: ?>
                    <div class="tdor-country-counts-table-wrapper">
                        <table class="tdor-country-counts-table">
                            <thead>
                            <tr>
                                <th>Pays</th>
                                <th>Signalements</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($countryCountsAllYears as $country => $count): ?>
                                <tr>
                                    <td><?= htmlspecialchars($country) ?></td>
                                    <td><?= (int)$count ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ONGLET 3 : Par catégorie (toutes années confondues) -->
            <div class="tdor-tab-content" id="stats-categories">
                <h3 class="tdor-category-counts-title">Nombre de signalements par catégorie</h3>
                <p class="tdor-category-counts-description">
                    Comptage du nombre total de signalements par catégorie (violence, suicide, garde à vue, etc.),
                    toutes années confondues (tous fichiers CSV de <code>DATA/tdor/</code>).
                </p>

                <?php if (empty($categoryCountsAllYears)): ?>
                    <p style="font-size:0.9rem;color:#6b7280;">
                        Aucune donnée disponible sur les catégories.
                    </p>
                <?php else: ?>
                    <div class="tdor-category-counts-table-wrapper">
                        <table class="tdor-category-counts-table">
                            <thead>
                            <tr>
                                <th>Catégorie</th>
                                <th>Signalements</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($categoryCountsAllYears as $cat => $count): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cat) ?></td>
                                    <td><?= (int)$count ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ONGLET 4 : Carte du monde -->
            <div class="tdor-tab-content" id="stats-map">
                <h3 class="tdor-map-title">Carte mondiale des signalements</h3>
                <p class="tdor-map-description">
                    Chaque marqueur représente une affaire recensée dans les fichiers CSV de
                    <code>DATA/tdor/</code>, positionnée approximativement au centre du pays indiqué.
                    Clique sur un point pour voir le nom, la date, le lieu, le pays, la catégorie et la cause.
                </p>

                <?php if (empty($allMapPoints)): ?>
                    <p style="font-size:0.9rem;color:#6b7280;">
                        Aucune donnée de carte disponible (aucun CSV ou colonnes pays manquantes).
                    </p>
                <?php else: ?>
                    <div id="tdor-map"></div>
                    <script>
                        const TDOR_MAP_POINTS = <?= $mapPointsJson ?>;
                    </script>
                <?php endif; ?>
            </div>

        </div>
    </section>

    <!-- BLOC 4 : Sources TDoR — téléchargements par année -->
    <section class="tdor-page tdor-sources-page" id="tdor-sources">
        <h2 class="tdor-sources-title">Sources TDoR — téléchargements par année</h2>
        <p class="tdor-sources-description">
            Ce bloc permet de télécharger les fichiers sources utilisés pour les recensements TDoR.  
            Sélectionne une année, puis choisis l’un des fichiers (CSV, PDF, images, etc.).
        </p>

        <!-- Onglets sources (2025, 2024, 2023, 2022, 2021) -->
        <div class="tdor-sources-tabs">
            <?php
            $firstSrcYear = $sourceYears[0];
            foreach ($sourceYears as $y):
            ?>
                <button
                    class="tdor-src-tab <?= $y === $firstSrcYear ? 'active' : '' ?>"
                    data-target="sources-<?= $y ?>"
                >
                    <?= $y ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($sourceYears as $y): ?>
            <?php
            $sources = $downloadSources[$y] ?? [];
            ?>
            <div
                class="tdor-sources-content <?= $y === $firstSrcYear ? 'active' : '' ?>"
                id="sources-<?= $y ?>"
            >
                <h3 class="tdor-year-counts-title">Fichiers pour TDoR <?= $y ?></h3>

                <?php if (empty($sources)): ?>
                    <p style="font-size:0.9rem;color:#6b7280;">
                        Aucun fichier configuré pour cette année.
                    </p>
                <?php else: ?>
                    <ul class="tdor-sources-list">
                        <?php foreach ($sources as $src): ?>
                            <?php
                            $label = $src['label'] ?? 'Fichier';
                            $relPath = $src['path'] ?? '';
                            $absPath = $relPath !== '' ? __DIR__ . '/' . $relPath : null;
                            $exists = $absPath && is_readable($absPath);
                            ?>
                            <li>
                                <?php if ($exists): ?>
                                    <a href="<?= htmlspecialchars($relPath) ?>" download>
                                        ⬇
                                        <span><?= htmlspecialchars($label) ?></span>
                                    </a>
                                <?php else: ?>
                                    <span class="unavailable">
                                        <?= htmlspecialchars($label) ?> — non disponible sur le serveur
                                    </span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
</main>

<footer>
    © <span class="footer-year"><?= date("Y"); ?></span> — Transfem Era
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.fog.min.js"></script>
<script src="SCRIPTS/vanta.js"></script>
<script src="SCRIPTS/accederprofil.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="SCRIPTS/tdor.js"></script>
<script src="SCRIPTS/index.js"></script>
<script src="SCRIPTS/accederprofil.js"></script>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
</html>
