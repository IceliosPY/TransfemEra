<?php
session_start();
require_once __DIR__ . '/config.php';

// 1. Vérifier que la personne est connectée (membre ou admin)
if (
    empty($_SESSION['user_id']) ||
    empty($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['membre', 'admin'], true)
) {
    http_response_code(403);
    echo "Accès refusé.";
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$currentRole   = $_SESSION['role'];

// 2. Vérifier l'ID du post (POST obligatoire)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id']) || !ctype_digit($_POST['id'])) {
    http_response_code(400);
    echo "ID de post invalide.";
    exit;
}

$postId = (int)$_POST['id'];

try {
    // 3. Récupérer le post pour connaître son autrice
    $stmt = $pdo->prepare("SELECT author_id FROM posts WHERE id = :id");
    $stmt->execute(['id' => $postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        // Post inexistant ou déjà supprimé
        header("Location: posts.php");
        exit;
    }

    // 4. Vérifier les droits : admin OU autrice du post
    if ($currentRole !== 'admin' && (int)$post['author_id'] !== $currentUserId) {
        http_response_code(403);
        echo "Tu n'as pas l'autorisation de supprimer ce post.";
        exit;
    }

    $pdo->beginTransaction();

    // 5. Supprimer les votes des commentaires liés au post
    $stmt = $pdo->prepare("
        DELETE cv FROM comment_votes cv
        JOIN comments c ON cv.comment_id = c.id
        WHERE c.post_id = :id
    ");
    $stmt->execute(['id' => $postId]);

    // 6. Supprimer les commentaires du post
    $stmt = $pdo->prepare("DELETE FROM comments WHERE post_id = :id");
    $stmt->execute(['id' => $postId]);

    // 7. Supprimer les votes sur le post
    $stmt = $pdo->prepare("DELETE FROM post_votes WHERE post_id = :id");
    $stmt->execute(['id' => $postId]);

    // 8. Récupérer les images pour supprimer les fichiers physiques
    $stmt = $pdo->prepare("SELECT image_path FROM post_images WHERE post_id = :id");
    $stmt->execute(['id' => $postId]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($images as $img) {
        $file = __DIR__ . '/' . $img['image_path'];
        if (is_file($file)) {
            @unlink($file); // on ignore les erreurs si le fichier n'existe plus
        }
    }

    // 9. Supprimer les entrées dans post_images
    $stmt = $pdo->prepare("DELETE FROM post_images WHERE post_id = :id");
    $stmt->execute(['id' => $postId]);

    // 10. Supprimer le post lui-même
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
    $stmt->execute(['id' => $postId]);

    $pdo->commit();

    // Retour à la liste
    header("Location: posts.php?deleted=1");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    // En prod : log($e->getMessage());
    http_response_code(500);
    echo "Erreur lors de la suppression du post.";
    exit;
}
