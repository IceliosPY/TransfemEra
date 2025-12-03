<?php
// admin_validate.php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connectée.']);
    exit;
}

// On vérifie que l’utilisatrice actuelle est bien admin
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current || $current['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
    exit;
}

$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID invalide.']);
    exit;
}

// On passe le compte en "membre" seulement s’il est encore "visiteur"
$upd = $pdo->prepare('
    UPDATE users
    SET role = "membre"
    WHERE id = :id AND role = "visiteur"
');
$upd->execute(['id' => $userId]);

if ($upd->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Compte déjà validé ou introuvable.']);
    exit;
}

// On renvoie le nouveau nombre d’inscriptions en attente pour MAJ de l’onglet
$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = "visiteur"');
$stmt->execute();
$pendingCount = (int)$stmt->fetchColumn();

echo json_encode(['success' => true, 'pendingCount' => $pendingCount]);
