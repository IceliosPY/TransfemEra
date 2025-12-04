<?php
// posts.php : liste de tous les posts (membres + admins uniquement)
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

// ----- Gestion des votes sur les posts depuis la liste -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'post_vote') {
        $postId = isset($_POST['post_id']) && ctype_digit($_POST['post_id'])
            ? (int)$_POST['post_id'] : 0;
        $direction = $_POST['direction'] ?? '';

        if ($postId > 0 && in_array($direction, ['up', 'down'], true)) {
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

        header('Location: posts.php');
        exit;
    }
}

// ----- Recherche -----
$search = trim($_GET['q'] ?? '');

$params = ['uid' => $currentUserId];
$where  = '';

if ($search !== '') {
    $where = "WHERE (p.title LIKE :search OR p.content LIKE :search OR p.tag LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

// Récupération de tous les posts + votes + nombre de commentaires
$sql = "
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
        ) AS my_post_vote,
        (
            SELECT COUNT(*)
            FROM comments c
            WHERE c.post_id = p.id
        ) AS comment_count
    FROM posts p
    JOIN users u ON u.id = p.author_id
    LEFT JOIN post_votes pv ON pv.post_id = p.id
    $where
    GROUP BY 
        p.id, p.title, p.content, p.tag, p.is_nsfw, p.is_pinned,
        p.created_at, p.author_id, u.pseudo
    ORDER BY p.is_pinned DESC, p.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Petite fonction pour « il y a X … »
function diff_ago(string $datetime): string {
    $ts = strtotime($datetime);
    if (!$ts) return '';

    $diff = time() - $ts;
    if ($diff < 0) $diff = 0;

    $minutes = floor($diff / 60);
    $hours   = floor($minutes / 60);
    $days    = floor($hours / 24);

    if ($minutes < 1)  return "à l’instant";
    if ($minutes < 60) return "il y a {$minutes} min";
    if ($hours   < 24) return "il y a {$hours} h";
    if ($days    < 7)  return "il y a {$days} j";

    $weeks = floor($days / 7);
    if ($weeks < 5) return "il y a {$weeks} sem.";

    $months = floor($days / 30);
    if ($months < 12) return "il y a {$months} mois";

    $years = floor($days / 365);
    return "il y a {$years} an" . ($years > 1 ? 's' : '');
}

/**
 * Rendu Markdown simplifié (copié depuis post.php)
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

    // Couleurs
    $html = preg_replace(
        '/\[color=([#0-9a-zA-Z]+)](.+?)\[\/color]/s',
        '<span style="color:$1">$2</span>',
        $html
    );

    // Souligné / barré
    $html = preg_replace('/\[u](.+?)\[\/u]/s', '<span class="md-underline">$1</span>', $html);
    $html = preg_replace('/\[s](.+?)\[\/s]/s', '<span class="md-strike">$1</span>',    $html);

    // Liens Markdown
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

    // Paragraphes / sauts de ligne
    $html = preg_replace("/\r\n|\r/", "\n", $html);
    $html = preg_replace("/\n{2,}/", "</p><p>", $html);
    $html = '<p>' . str_replace("\n", "<br>", $html) . '</p>';

    return $html;
}

/**
 * Snippet Markdown : tronque puis applique renderMarkdown
 */
function snippet_md(string $text, int $max = 180): string {
    $text = trim($text);
    if (mb_strlen($text) > $max) {
        $text = mb_substr($text, 0, $max - 1) . '…';
    }
    return renderMarkdown($text);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posts — Transfem Era</title>

    <link rel="stylesheet" href="CSS/index.css">

    <style>
        .posts-page {
            max-width: 980px;
            margin: 110px auto 40px;
            padding: 1.6rem 1.8rem 2rem;
            border-radius: 22px;
            background: rgba(255,255,255,0.98);
            box-shadow: 0 18px 45px rgba(15,23,42,0.20);
            backdrop-filter: blur(10px);
        }

        .posts-header-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.8rem;
            margin-bottom: 1.2rem;
        }

        .posts-header-row h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: #111827;
        }

        .posts-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            align-items: center;
        }

        .posts-search-form {
            position: relative;
        }

        .posts-search-form input[type="text"] {
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            padding: 0.35rem 0.9rem;
            font-size: 0.85rem;
            min-width: 180px;
        }

        .btn-new-post {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.45rem 0.85rem;
            border: 1px solid rgba(245,169,184,0.85);
            background: linear-gradient(90deg, #5bcffb1f, #f5a9b81f);
            color: #e56c9b;
            font-size: 0.85rem;
            text-decoration: none;
            font-weight: 500;
            gap: 0.35rem;
        }

        .btn-new-post:hover {
            background: linear-gradient(90deg, #5bcffb3a, #f5a9b83a);
        }

        .posts-list {
            margin-top: 0.8rem;
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
        }

        .post-card {
            border-radius: 18px;
            padding: 0.8rem 1rem;
            background: radial-gradient(circle at top left,
                        rgba(245,169,184,0.18),
                        rgba(91,207,251,0.06));
            border: 1px solid rgba(229,231,235,0.9);
            box-shadow: 0 10px 30px rgba(15,23,42,0.08);
            display: grid;
            grid-template-columns: minmax(0,1fr) auto;
            column-gap: 1rem;
            row-gap: 0.35rem;
            transition: transform 0.12s ease, box-shadow 0.12s ease,
                        border-color 0.12s ease, background 0.12s ease;
        }

        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 40px rgba(15,23,42,0.16);
            border-color: rgba(244,114,182,0.8);
            background: radial-gradient(circle at top left,
                        rgba(245,169,184,0.26),
                        rgba(91,207,251,0.10));
        }

        .post-main-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .post-main {
            min-width: 0;
        }

        .post-title-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.1rem;
        }

        .post-title {
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .post-badges {
            display: flex;
            gap: 0.3rem;
            flex-wrap: wrap;
            font-size: 0.7rem;
        }

        .badge {
            border-radius: 999px;
            padding: 0.1rem 0.5rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-tag {
            background: rgba(91,207,251,0.15);
            color: #0369a1;
        }

        .badge-pinned {
            background: rgba(234,179,8,0.15);
            color: #92400e;
        }

        .badge-nsfw {
            background: rgba(220,38,38,0.15);
            color: #b91c1c;
        }

        .post-snippet {
            font-size: 0.85rem;
            color: #374151;
            margin-bottom: 0.3rem;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        /* on veut que les spans/links du markdown restent inline */
        .post-snippet p {
            display: inline;
        }

        .post-meta {
            font-size: 0.78rem;
            color: #6b7280;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .post-meta .dot {
            width: 3px;
            height: 3px;
            border-radius: 50%;
            background: #d1d5db;
        }

        .post-stats {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            font-size: 0.8rem;
            color: #6b7280;
            min-width: 90px;
            gap: 0.35rem;
        }

        .post-stats form {
            display: inline;
        }

        .vote-btn {
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            padding: 0.15rem 0.45rem;
            font-size: 0.78rem;
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

        .post-votes-small {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .comments-count {
            font-size: 0.8rem;
            color: #4b5563;
            white-space: nowrap;
        }

        @media (max-width: 640px) {
            .posts-page {
                margin-top: 90px;
                padding: 1.3rem 1.2rem 1.6rem;
            }
            .post-card {
                grid-template-columns: minmax(0,1fr);
            }
            .post-stats {
                justify-content: flex-start;
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
            <a href="posts.php" class="active">Posts</a>
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

<main>
    <section class="posts-page">
        <div class="posts-header-row">
            <h2>Posts de la communauté</h2>

            <div class="posts-controls">
                <form class="posts-search-form" method="GET" action="posts.php">
                    <input
                        type="text"
                        name="q"
                        placeholder="Rechercher un post…"
                        value="<?= htmlspecialchars($search) ?>"
                    >
                </form>

                <a href="new_post.php" class="btn-new-post">
                    <span>➕</span>
                    <span>Nouveau post</span>
                </a>
            </div>
        </div>

        <?php if ($search !== ''): ?>
            <p style="font-size:0.8rem;color:#6b7280;margin-bottom:0.4rem;">
                Résultats pour « <?= htmlspecialchars($search) ?> » —
                <?= count($posts) ?> post<?= count($posts) > 1 ? 's' : '' ?> trouvé<?= count($posts) > 1 ? 's' : '' ?>.
            </p>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
            <p style="font-size:0.9rem; color:#6b7280;">
                Aucun post pour le moment<?= $search !== '' ? ' pour cette recherche' : '' ?>.
                Sois la première à en créer un 💖
            </p>
        <?php else: ?>
            <div class="posts-list">
                <?php foreach ($posts as $post): ?>
                    <article class="post-card">
                        <!-- Partie cliquable menant au post -->
                        <a class="post-main-link" href="post.php?id=<?= (int)$post['id'] ?>">
                            <div class="post-main">
                                <div class="post-title-row">
                                    <div class="post-title">
                                        <?= htmlspecialchars($post['title']) ?>
                                    </div>
                                    <div class="post-badges">
                                        <?php if (!empty($post['tag'])): ?>
                                            <span class="badge badge-tag">
                                                <?= htmlspecialchars($post['tag']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($post['is_pinned'])): ?>
                                            <span class="badge badge-pinned">Épinglé</span>
                                        <?php endif; ?>
                                        <?php if (!empty($post['is_nsfw'])): ?>
                                            <span class="badge badge-nsfw">NSFW</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="post-snippet">
                                    <?= snippet_md($post['content']) ?>
                                </div>

                                <div class="post-meta">
                                    <span>Par <strong><?= htmlspecialchars($post['author_pseudo']) ?></strong></span>
                                    <span class="dot"></span>
                                    <span><?= htmlspecialchars(diff_ago($post['created_at'])) ?></span>
                                </div>
                            </div>
                        </a>

                        <!-- Stats (votes + nombre de commentaires) -->
                        <div class="post-stats">
                            <form method="POST">
                                <input type="hidden" name="action" value="post_vote">
                                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                <input type="hidden" name="direction" value="up">
                                <button type="submit"
                                        class="vote-btn <?= (int)$post['my_post_vote'] === 1 ? 'active-up' : '' ?>">
                                    ▲
                                </button>
                            </form>

                            <form method="POST">
                                <input type="hidden" name="action" value="post_vote">
                                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit"
                                        class="vote-btn <?= (int)$post['my_post_vote'] === -1 ? 'active-down' : '' ?>">
                                    ▼
                                </button>
                            </form>

                            <?php if ($currentRole === 'admin'): ?>
                                <span class="post-votes-small">
                                    <?= (int)$post['post_upvotes'] ?> ▲ / <?= (int)$post['post_downvotes'] ?> ▼
                                </span>
                            <?php endif; ?>

                            <span class="comments-count">
                                💬 <?= (int)$post['comment_count'] ?>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer>
    © <span class="footer-year"><?= date("Y"); ?></span> — Transfem Era
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.fog.min.js"></script>
<script src="SCRIPTS/vanta.js"></script>
<script src="SCRIPTS/accederprofil.js"></script>

</body>
</html>
