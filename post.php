<?php
// post.php : afficher un post + images + fichiers + commentaires + votes
session_start();
require_once __DIR__ . '/config.php';

// Protection : seulement membres + admins
if (
    empty($_SESSION['user_id']) ||
    empty($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['membre', 'admin'], true)
) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole   = $_SESSION['role'] ?? '';

if (empty($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    echo "ID de post invalide.";
    exit;
}

$postId = (int)$_GET['id'];

// ---------------------
// Gestion des actions POST
// ---------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1) Ajout d'un commentaire (ou d'une réponse)
    if ($action === 'add_comment') {
        $commentContent = trim($_POST['comment_content'] ?? '');
        $parentId = null;
        if (!empty($_POST['parent_id']) && ctype_digit($_POST['parent_id'])) {
            $parentId = (int)$_POST['parent_id'];
        }

        if ($commentContent !== '') {
            $stmtC = $pdo->prepare("
                INSERT INTO comments (post_id, parent_comment_id, author_id, content)
                VALUES (:post_id, :parent_id, :author_id, :content)
            ");
            $stmtC->execute([
                'post_id'    => $postId,
                'parent_id'  => $parentId,
                'author_id'  => $currentUserId,
                'content'    => $commentContent,
            ]);
        }

        header('Location: post.php?id=' . $postId . '#comments');
        exit;
    }

    // 2) Vote sur un commentaire
    if ($action === 'vote') {
        $commentId = isset($_POST['comment_id']) && ctype_digit($_POST['comment_id'])
            ? (int)$_POST['comment_id'] : 0;
        $direction = $_POST['direction'] ?? '';

        if ($commentId > 0 && in_array($direction, ['up', 'down'], true)) {
            $voteValue = $direction === 'up' ? 1 : -1;

            $stmtCheck = $pdo->prepare("
                SELECT vote FROM comment_votes
                WHERE comment_id = :cid AND user_id = :uid
            ");
            $stmtCheck->execute([
                'cid' => $commentId,
                'uid' => $currentUserId,
            ]);
            $existing = $stmtCheck->fetchColumn();

            if ($existing === false) {
                $stmtIns = $pdo->prepare("
                    INSERT INTO comment_votes (comment_id, user_id, vote)
                    VALUES (:cid, :uid, :vote)
                ");
                $stmtIns->execute([
                    'cid'  => $commentId,
                    'uid'  => $currentUserId,
                    'vote' => $voteValue,
                ]);
            } else {
                $existing = (int)$existing;

                if ($existing === $voteValue) {
                    $stmtDel = $pdo->prepare("
                        DELETE FROM comment_votes
                        WHERE comment_id = :cid AND user_id = :uid
                    ");
                    $stmtDel->execute([
                        'cid' => $commentId,
                        'uid' => $currentUserId,
                    ]);
                } else {
                    $stmtUpd = $pdo->prepare("
                        UPDATE comment_votes
                        SET vote = :vote
                        WHERE comment_id = :cid AND user_id = :uid
                    ");
                    $stmtUpd->execute([
                        'vote' => $voteValue,
                        'cid'  => $commentId,
                        'uid'  => $currentUserId,
                    ]);
                }
            }
        }

        header('Location: post.php?id=' . $postId . '#comments');
        exit;
    }

    // 3) Vote sur le post (up/down)
    if ($action === 'post_vote') {
        $direction = $_POST['direction'] ?? '';

        if (in_array($direction, ['up', 'down'], true)) {
            $voteValue = $direction === 'up' ? 1 : -1;

            $stmtCheck = $pdo->prepare("
                SELECT vote FROM post_votes
                WHERE post_id = :pid AND user_id = :uid
            ");
            $stmtCheck->execute([
                'pid' => $postId,
                'uid' => $currentUserId,
            ]);
            $existing = $stmtCheck->fetchColumn();

            if ($existing === false) {
                $stmtIns = $pdo->prepare("
                    INSERT INTO post_votes (post_id, user_id, vote)
                    VALUES (:pid, :uid, :vote)
                ");
                $stmtIns->execute([
                    'pid'  => $postId,
                    'uid'  => $currentUserId,
                    'vote' => $voteValue,
                ]);
            } else {
                $existing = (int)$existing;

                if ($existing === $voteValue) {
                    $stmtDel = $pdo->prepare("
                        DELETE FROM post_votes
                        WHERE post_id = :pid AND user_id = :uid
                    ");
                    $stmtDel->execute([
                        'pid' => $postId,
                        'uid' => $currentUserId,
                    ]);
                } else {
                    $stmtUpd = $pdo->prepare("
                        UPDATE post_votes
                        SET vote = :vote
                        WHERE post_id = :pid AND user_id = :uid
                    ");
                    $stmtUpd->execute([
                        'vote' => $voteValue,
                        'pid'  => $postId,
                        'uid'  => $currentUserId,
                    ]);
                }
            }
        }

        header('Location: post.php?id=' . $postId);
        exit;
    }

    // 4) Suppression d'un commentaire (admin OU autrice du commentaire)
    if ($action === 'delete_comment') {
        $commentId = isset($_POST['comment_id']) && ctype_digit($_POST['comment_id'])
            ? (int)$_POST['comment_id'] : 0;

        if ($commentId > 0) {
            // Récupérer l'autrice du commentaire
            $stmt = $pdo->prepare("
                SELECT author_id 
                FROM comments 
                WHERE id = :cid AND post_id = :pid
            ");
            $stmt->execute([
                'cid' => $commentId,
                'pid' => $postId,
            ]);
            $comment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($comment && (
                $currentRole === 'admin' ||
                (int)$comment['author_id'] === $currentUserId
            )) {
                // Les clés étrangères (comment_votes, parent_comment_id) devraient être en cascade.
                $del = $pdo->prepare("DELETE FROM comments WHERE id = :cid");
                $del->execute(['cid' => $commentId]);
            }
        }

        header('Location: post.php?id=' . $postId . '#comments');
        exit;
    }
}

// --------------------------------------
// Récupération du post + auteur + votes
// --------------------------------------
$stmt = $pdo->prepare("
    SELECT 
        p.*,
        u.pseudo AS author_pseudo,
        COALESCE(SUM(CASE WHEN pv.vote = 1 THEN 1 ELSE 0 END), 0)  AS post_upvotes,
        COALESCE(SUM(CASE WHEN pv.vote = -1 THEN 1 ELSE 0 END), 0) AS post_downvotes,
        (
            SELECT pv2.vote
            FROM post_votes pv2
            WHERE pv2.post_id = p.id AND pv2.user_id = :uid
            LIMIT 1
        ) AS my_post_vote
    FROM posts p
    JOIN users u ON p.author_id = u.id
    LEFT JOIN post_votes pv ON pv.post_id = p.id
    WHERE p.id = :id
    GROUP BY p.id, p.title, p.content, p.tag, p.is_nsfw, p.is_pinned, 
             p.created_at, p.author_id, u.pseudo
");
$stmt->execute([
    'id'  => $postId,
    'uid' => $currentUserId,
]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    echo "Post introuvable.";
    exit;
}

// Images du post
$imgStmt = $pdo->prepare("
    SELECT image_path
    FROM post_images
    WHERE post_id = :id
");
$imgStmt->execute(['id' => $postId]);
$images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

// Fichiers joints du post
$fileStmt = $pdo->prepare("
    SELECT id, file_path, original_name, mime_type
    FROM post_files
    WHERE post_id = :id
");
$fileStmt->execute(['id' => $postId]);
$files = $fileStmt->fetchAll(PDO::FETCH_ASSOC);

// Commentaires + votes (avec parent_comment_id)
$stmtComments = $pdo->prepare("
    SELECT 
        c.id,
        c.post_id,
        c.parent_comment_id,
        c.author_id,
        c.content,
        c.created_at,
        u.pseudo AS author_pseudo,
        COALESCE(SUM(CASE WHEN cv.vote = 1 THEN 1 ELSE 0 END), 0)  AS upvotes,
        COALESCE(SUM(CASE WHEN cv.vote = -1 THEN 1 ELSE 0 END), 0) AS downvotes,
        (
            SELECT cv2.vote
            FROM comment_votes cv2
            WHERE cv2.comment_id = c.id AND cv2.user_id = :uid
            LIMIT 1
        ) AS my_vote
    FROM comments c
    JOIN users u ON u.id = c.author_id
    LEFT JOIN comment_votes cv ON cv.comment_id = c.id
    WHERE c.post_id = :post_id
    GROUP BY c.id, c.post_id, c.parent_comment_id, c.author_id, c.content, c.created_at, u.pseudo
    ORDER BY c.created_at ASC
");
$stmtComments->execute([
    'uid'     => $currentUserId,
    'post_id' => $postId,
]);
$allComments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);

// Organiser les commentaires par parent (fil simple : parent -> réponses)
$commentsByParent = [];
foreach ($allComments as $c) {
    $parent = $c['parent_comment_id'] ?? null;
    $commentsByParent[$parent][] = $c;
}

/**
 * Mini rendu Markdown + tags custom
 */
function renderMarkdown(string $md): string {
    if (trim($md) === '') {
        return '';
    }

    $html = htmlspecialchars($md, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Titres
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $html);

    // Listes
    $html = preg_replace('/^(?:-|\*) (.+)$/m', '<li>$1</li>', $html);
    $html = preg_replace_callback(
        '/(?:<li>.*?<\/li>\s*)+/s',
        fn($m) => '<ul>' . $m[0] . '</ul>',
        $html
    );

    // Gras / italique
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.+?)\*/s',     '<em>$1</em>',         $html);

    // Centrage
    $html = preg_replace('/\[center](.+?)\[\/center]/s', '<span class="md-center">$1</span>', $html);

    // Couleur
    $html = preg_replace(
        '/\[color=([#0-9a-zA-Z]+)](.+?)\[\/color]/s',
        '<span style="color:$1">$2</span>',
        $html
    );

    // Souligné / barré
    $html = preg_replace('/\[u](.+?)\[\/u]/s', '<span class="md-underline">$1</span>', $html);
    $html = preg_replace('/\[s](.+?)\[\/s]/s', '<span class="md-strike">$1</span>',    $html);

    // Liens markdown [texte](url)
    $html = preg_replace(
        '/\[([^\]]+)]\((https?:\/\/[^\s)]+)\)/i',
        '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
        $html
    );

    // Liens simples
    $html = preg_replace(
        '/(https?:\/\/[^\s<]+)/i',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
        $html
    );

    // Paragraphes
    $html = preg_replace("/\r\n|\r/", "\n", $html);
    $html = preg_replace("/\n{2,}/", "</p><p>", $html);
    $html = '<p>' . str_replace("\n", "<br>", $html) . '</p>';

    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?> — Transfem Era</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="CSS/index.css">
    <style>
        .post-page {
            max-width: 980px;
            margin: 110px auto 40px;
            padding: 1.6rem 1.8rem 2rem;
            border-radius: 22px;
            background: rgba(255,255,255,0.98);
            box-shadow: 0 18px 45px rgba(15,23,42,0.20);
            backdrop-filter: blur(10px);
        }

        .post-header-block {
            position: relative;
            margin-bottom: 1.2rem;
            padding-right: 130px;
        }

        .post-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: .2rem;
        }

        .post-meta {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: .4rem;
        }

        .tag-pill {
            display: inline-block;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            font-size: 0.75rem;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #4b5563;
            margin-right: .25rem;
        }
        .tag-nsfw { border-color:#fecaca; background:#fef2f2; color:#b91c1c; }
        .tag-pinned { border-color:#fcd34d; background:#fffbeb; color:#92400e; }

        .post-votes-container {
            position: absolute;
            top: 0;
            right: 0;
            text-align: right;
        }

        .vote-btn {
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            padding: 0.15rem 0.55rem;
            font-size: 0.80rem;
            cursor: pointer;
        }
        .vote-btn.active-up {
            background: #dcfce7;
            border-color: #22c55e;
        }
        .vote-btn.active-down {
            background: #fee2e2;
            border-color: #ef4444;
        }

        .post-votes-count {
            font-size: 0.75rem;
            color: #6b7280;
            display: block;
            margin-top: 0.15rem;
        }

        @media (max-width: 640px) {
            .post-header-block {
                padding-right: 0;
            }
            .post-votes-container {
                position: static;
                margin-top: 0.4rem;
            }
        }

        .post-body-block {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .post-footer-nav {
            margin-top: 1.4rem;
            padding-top: 0.8rem;
            border-top: 1px dashed #e5e7eb;
        }

        .post-content {
            font-size: 0.95rem;
            line-height: 1.6;
            color: #111827;
            margin-bottom: 1.3rem;
        }
        .post-content h1,
        .post-content h2,
        .post-content h3 {
            margin: 0.4rem 0 0.2rem;
            font-weight: 600;
        }
        .post-content h1 { font-size: 1.4rem; }
        .post-content h2 { font-size: 1.2rem; }
        .post-content h3 { font-size: 1.05rem; }

        .post-content ul {
            margin: 0.2rem 0 0.4rem 1.2rem;
            padding-left: 0.8rem;
            list-style: disc;
        }
        .post-content a {
            color: #2563eb;
            text-decoration: underline;
        }

        .post-content .md-center    { display:block; text-align:center; }
        .post-content .md-underline { text-decoration: underline; }
        .post-content .md-strike    { text-decoration: line-through; }

        .post-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill,minmax(160px,1fr));
            gap: .6rem;
        }
        .post-gallery img {
            width: 100%;
            border-radius: 14px;
            object-fit: cover;
            max-height: 260px;
        }

        /* Fichiers joints */
        .post-files-block {
            margin-top: 1.4rem;
            margin-bottom: 1.2rem;
        }
        .post-files-block h2 {
            font-size: 1rem;
            margin-bottom: 0.4rem;
            color: #0f172a;
        }
        .post-files-list {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        .post-files-list li {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        .post-files-list a {
            text-decoration: none;
            color: #2563eb;
        }
        .post-files-list a:hover {
            text-decoration: underline;
        }
        .post-files-list .file-meta {
            font-size: 0.75rem;
            color: #6b7280;
            margin-left: 0.4rem;
        }

        .delete-btn {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #b91c1c;
            padding: 0.45rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .delete-btn:hover { background: #fecaca; }

        .back-link {
            font-size: .85rem;
            color: #6b7280;
            text-decoration: none;
        }
        .back-link:hover { text-decoration:underline; color:#111827; }

        /* Commentaires */
        .comments-block {
            margin-top: 1.8rem;
            padding-top: 1.3rem;
            border-top: 1px solid #e5e7eb;
        }
        .comments-block h2 {
            font-size: 1.1rem;
            margin-bottom: 0.8rem;
        }
        .comment-item {
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            padding: 0.7rem 0.9rem;
            margin-bottom: 0.6rem;
            background: #f9fafb;
        }
        .comment-reply {
            margin-left: 2.0rem;
            margin-top: 0.4rem;
        }
        .comment-meta {
            font-size: 0.78rem;
            color: #6b7280;
            margin-bottom: 0.3rem;
            display: flex;
            justify-content: space-between;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .comment-content {
            font-size: 0.9rem;
            color: #111827;
            margin-bottom: 0.3rem;
        }
        .comment-content .md-center    { display:block; text-align:center; }
        .comment-content .md-underline { text-decoration: underline; }
        .comment-content .md-strike    { text-decoration: line-through; }

        .comment-votes {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.8rem;
        }
        .comment-counts-admin {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .comment-actions {
            display: flex;
            gap: 0.4rem;
            align-items: center;
            font-size: 0.8rem;
        }
        .comment-actions button,
        .comment-actions .link-btn {
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            padding: 0.15rem 0.65rem;
            font-size: 0.78rem;
            cursor: pointer;
        }
        .comment-actions .link-btn {
            text-decoration: none;
            color: #4b5563;
        }
        .comment-actions button:hover,
        .comment-actions .link-btn:hover {
            background: #f3f4f6;
        }

        .new-comment-form textarea,
        .reply-form textarea {
            width: 100%;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 0.45rem 0.6rem;
            font-size: 0.9rem;
            resize: vertical;
            min-height: 90px;
        }
        .new-comment-form button,
        .reply-form button {
            margin-top: 0.4rem;
            border-radius: 999px;
            border: 1px solid rgba(191,219,254,1);
            background: #eff6ff;
            font-size: 0.85rem;
            padding: 0.35rem 0.9rem;
            cursor: pointer;
        }
        .new-comment-form button:hover,
        .reply-form button:hover {
            background: #dbeafe;
        }

        .reply-form {
            margin-top: 0.4rem;
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
    <?php if (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['membre', 'admin'], true)): ?>
    <a href="posts.php">Posts</a>
    <?php endif; ?>

    <?php if (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['membre', 'admin'], true)): ?>
        <a href="tdor.php">TDoR</a>
    <?php endif; ?>
</nav>
        <div class="header-right">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="profile-menu">
                    <button class="profile-trigger" type="button">
                        <img
                            src="<?= htmlspecialchars($_SESSION['avatar_path'] ?? 'IMG/profile_default.png') ?>"
                            class="profile-pic <?= htmlspecialchars($_SESSION['avatar_shape'] ?? 'circle') ?>"
                            alt="Profil"
                        >
                    </button>
                    <div class="profile-dropdown">
                        <a href="profil.php">Mon profil</a>
                        <a href="logout.php" class="logout-link">Se déconnecter</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="login-btn">Se connecter</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main>
    <section class="post-page">

        <!-- En-tête du post -->
        <div class="post-header-block">
            <h1 class="post-title"><?= htmlspecialchars($post['title']) ?></h1>

            <!-- Votes du post en haut à droite -->
            <div class="post-votes-container">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="post_vote">
                    <input type="hidden" name="direction" value="up">
                    <button type="submit"
                            class="vote-btn <?= (int)$post['my_post_vote'] === 1 ? 'active-up' : '' ?>">
                        ▲
                    </button>
                </form>

                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="post_vote">
                    <input type="hidden" name="direction" value="down">
                    <button type="submit"
                            class="vote-btn <?= (int)$post['my_post_vote'] === -1 ? 'active-down' : '' ?>">
                        ▼
                    </button>
                </form>

                <?php if ($currentRole === 'admin'): ?>
                    <span class="post-votes-count">
                        <?= (int)$post['post_upvotes'] ?> ▲ / <?= (int)$post['post_downvotes'] ?> ▼
                    </span>
                <?php endif; ?>
            </div>

            <div class="post-meta">
                Par <strong><?= htmlspecialchars($post['author_pseudo']) ?></strong>
                — publié le <?= date('d/m/Y à H:i', strtotime($post['created_at'])) ?>
            </div>

            <div>
                <?php if (!empty($post['tag'])): ?>
                    <span class="tag-pill"><?= htmlspecialchars($post['tag']) ?></span>
                <?php endif; ?>
                <?php if (!empty($post['is_pinned'])): ?>
                    <span class="tag-pill tag-pinned">Épinglé</span>
                <?php endif; ?>
                <?php if (!empty($post['is_nsfw'])): ?>
                    <span class="tag-pill tag-nsfw">NSFW</span>
                <?php endif; ?>
            </div>

            <?php if ($currentRole === 'admin' || $currentUserId === (int)$post['author_id']): ?>
                <div style="margin-top:0.8rem;">
                    <form action="delete_post.php" method="POST"
                          onsubmit="return confirm('Voulez-vous vraiment supprimer ce post ? Cette action est irréversible.');">
                        <input type="hidden" name="id" value="<?= (int)$postId ?>">
                        <button type="submit" class="delete-btn">🗑️ Supprimer ce post</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Contenu + galerie + fichiers + commentaires -->
        <div class="post-body-block">
            <div class="post-content">
                <?= renderMarkdown($post['content']); ?>
            </div>

            <?php if (!empty($images)): ?>
                <h2 style="font-size:1rem;margin-bottom:.4rem;color:#ec4899;">Galerie</h2>
                <div class="post-gallery">
                    <?php foreach ($images as $img): ?>
                        <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="Image du post">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($files)): ?>
                <div class="post-files-block">
                    <h2>Fichiers joints</h2>
                    <ul class="post-files-list">
                        <?php foreach ($files as $f): ?>
                            <li>
                                📎
                                <a href="<?= htmlspecialchars($f['file_path']) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= htmlspecialchars($f['original_name']) ?>
                                </a>
                                <?php if (!empty($f['mime_type'])): ?>
                                    <span class="file-meta">
                                        (<?= htmlspecialchars($f['mime_type']) ?>)
                                    </span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Commentaires -->
            <div class="comments-block" id="comments">
                <h2>Commentaires</h2>

                <?php if (empty($commentsByParent[null] ?? [])): ?>
                    <p style="font-size:0.85rem;color:#6b7280;">
                        Aucun commentaire pour le moment. Sois la première à réagir 💬
                    </p>
                <?php else: ?>
                    <?php
                    // Fonction d'affichage récursive (un seul niveau de fil dans la pratique)
                    function renderCommentThread(array $comment, array $commentsByParent, string $currentRole, int $currentUserId) {
                        $id = (int)$comment['id'];
                        ?>
                        <article class="comment-item" id="comment-<?= $id ?>">
                            <div class="comment-meta">
                                <span>
                                    <strong><?= htmlspecialchars($comment['author_pseudo']) ?></strong>
                                    — <?= date('d/m/Y à H:i', strtotime($comment['created_at'])) ?>
                                </span>

                                <div class="comment-actions">
                                    <div class="comment-votes">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="vote">
                                            <input type="hidden" name="comment_id" value="<?= $id ?>">
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit"
                                                    class="vote-btn <?= (int)$comment['my_vote'] === 1 ? 'active-up' : '' ?>">
                                                ▲
                                            </button>
                                        </form>

                                        <form method="POST">
                                            <input type="hidden" name="action" value="vote">
                                            <input type="hidden" name="comment_id" value="<?= $id ?>">
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit"
                                                    class="vote-btn <?= (int)$comment['my_vote'] === -1 ? 'active-down' : '' ?>">
                                                ▼
                                            </button>
                                        </form>

                                        <?php if ($currentRole === 'admin'): ?>
                                            <span class="comment-counts-admin">
                                                (<?= (int)$comment['upvotes'] ?> ▲ / <?= (int)$comment['downvotes'] ?> ▼)
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <button type="button"
                                            class="link-btn"
                                            data-reply-target="reply-to-<?= $id ?>">
                                        Répondre
                                    </button>

                                    <?php if ($currentRole === 'admin' || (int)$comment['author_id'] === $currentUserId): ?>
                                        <form method="POST" onsubmit="return confirm('Supprimer ce commentaire et ses réponses ?');">
                                            <input type="hidden" name="action" value="delete_comment">
                                            <input type="hidden" name="comment_id" value="<?= $id ?>">
                                            <button type="submit">Supprimer</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="comment-content">
                                <?= renderMarkdown($comment['content']); ?>
                            </div>

                            <!-- Formulaire de réponse caché -->
                            <div class="reply-form" id="reply-to-<?= $id ?>" style="display:none;">
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_comment">
                                    <input type="hidden" name="parent_id" value="<?= $id ?>">
                                    <textarea name="comment_content"
                                              placeholder="Ta réponse (Markdown léger accepté)…"></textarea>
                                    <button type="submit">Publier la réponse</button>
                                </form>
                            </div>

                            <?php if (!empty($commentsByParent[$id] ?? [])): ?>
                                <?php foreach ($commentsByParent[$id] as $reply): ?>
                                    <div class="comment-reply">
                                        <?php renderCommentThread($reply, $commentsByParent, $currentRole, $currentUserId); ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </article>
                        <?php
                    }
                    ?>

                    <?php foreach ($commentsByParent[null] as $c): ?>
                        <?php renderCommentThread($c, $commentsByParent, $currentRole, $currentUserId); ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Nouveau commentaire (top-level) -->
                <div class="new-comment-form" style="margin-top:1rem;">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_comment">
                        <label for="comment_content" style="font-size:0.85rem;color:#4b5563;">
                            Ajouter un commentaire
                        </label>
                        <textarea id="comment_content" name="comment_content"
                                  placeholder="Ton commentaire (Markdown léger accepté : **gras**, *italique*, [lien](https://...), [center]...[/center], [color=#e11d48]...[/color], etc.)"></textarea>
                        <button type="submit">Publier le commentaire</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Retour aux posts -->
        <div class="post-footer-nav">
            <a href="posts.php" class="back-link">← Retour aux posts</a>
        </div>

    </section>
</main>

<footer>
    © <span class="footer-year"><?= date("Y"); ?></span> — Transfem Era
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.fog.min.js"></script>
<script src="SCRIPTS/vanta.js"></script>
<script src="SCRIPTS/accederprofil.js"></script>

<script>
// Affichage / masquage des formulaires de réponse
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-reply-target]').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-reply-target');
            const form = document.getElementById(targetId);
            if (!form) return;
            form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'block' : 'none';
        });
    });
});
</script>
</body>
</html>
