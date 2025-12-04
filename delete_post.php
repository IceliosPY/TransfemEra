<?php
session_start();
require_once __DIR__ . '/config.php';

// Vérification admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

// Vérification ID du post
if (empty($_POST['id']) || !ctype_digit($_POST['id'])) {
    http_response_code(400);
    echo "ID de post invalide.";
    exit;
}

$postId = (int)$_POST['id'];

try {
    $pdo->beginTransaction();

    // Supprimer les images liées
    $stmt = $pdo->prepare("SELECT image_path FROM post_images WHERE post_id = :id");
    $stmt->execute(['id' => $postId]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Supprimer les fichiers physiques
    foreach ($images as $img) {
        $file = __DIR__ . '/' . $img['image_path'];
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // Supprimer les entrées dans post_images
    $stmt = $pdo->prepare("DELETE FROM post_images WHERE post_id = :id");
    $stmt->execute(['id' => $postId]);

    // Supprimer le post
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
    $stmt->execute(['id' => $postId]);

    $pdo->commit();

    // Retour aux posts
    header("Location: posts.php?deleted=1");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Erreur lors de la suppression du post.";
    // En prod : log($e->getMessage());
    exit;
}
