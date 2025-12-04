<?php
// new_post.php : création d'un nouveau post (membres + admins uniquement)
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

$errors  = [];
$success = false;

$title     = '';
$tag       = '';
$content   = '';
$is_nsfw   = 0;
$is_pinned = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $tag     = trim($_POST['tag'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $is_nsfw = isset($_POST['is_nsfw']) ? 1 : 0;

    // Option : seuls les admins peuvent épingler
    if (isset($_POST['is_pinned']) && ($_SESSION['role'] ?? '') === 'admin') {
        $is_pinned = 1;
    }

    // --- validations ---
    if ($title === '') {
        $errors[] = "Merci d'indiquer un titre pour ton post.";
    } elseif (mb_strlen($title) > 255) {
        $errors[] = "Le titre est trop long (255 caractères max).";
    }

    if ($content === '') {
        $errors[] = "Ton post est vide, ajoute un peu de contenu.";
    }

    if ($tag !== '' && mb_strlen($tag) > 100) {
        $errors[] = "Le tag est trop long (100 caractères max).";
    }

    // Si pas d'erreurs -> insertion
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO posts (title, content, tag, is_nsfw, is_pinned, author_id)
                VALUES (:title, :content, :tag, :is_nsfw, :is_pinned, :author_id)
            ");

            $stmt->execute([
                'title'     => $title,
                'content'   => $content, // Markdown brut
                'tag'       => $tag !== '' ? $tag : null,
                'is_nsfw'   => $is_nsfw,
                'is_pinned' => $is_pinned,
                'author_id' => $_SESSION['user_id'],
            ]);

            $postId = (int)$pdo->lastInsertId();

            // --- Upload des images éventuelles ---
            if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
                $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $uploadDirImages   = __DIR__ . '/uploads/posts/';

                if (!is_dir($uploadDirImages)) {
                    mkdir($uploadDirImages, 0777, true);
                }

                $insertImg = $pdo->prepare("
                    INSERT INTO post_images (post_id, image_path) 
                    VALUES (:post_id, :image_path)
                ");

                foreach ($_FILES['images']['name'] as $index => $name) {

                    if ($_FILES['images']['error'][$index] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    if ($_FILES['images']['error'][$index] !== UPLOAD_ERR_OK) {
                        $errors[] = "Une des images n'a pas pu être envoyée correctement.";
                        continue;
                    }

                    $tmpName = $_FILES['images']['tmp_name'][$index];

                    $mime = mime_content_type($tmpName);
                    if (!in_array($mime, $allowedImageMimes, true)) {
                        $errors[] = "Une image a un format non accepté (JPG, PNG ou WebP uniquement).";
                        continue;
                    }

                    $extension = pathinfo($name, PATHINFO_EXTENSION);
                    $safeExt   = $extension ?: 'bin';
                    $filename  = 'post_' . $postId . '_' . time() . '_' . $index . '.' . $safeExt;
                    $destPath  = $uploadDirImages . $filename;

                    if (!move_uploaded_file($tmpName, $destPath)) {
                        $errors[] = "Impossible d'enregistrer une des images.";
                        continue;
                    }

                    $relativePath = 'uploads/posts/' . $filename;

                    $insertImg->execute([
                        'post_id'    => $postId,
                        'image_path' => $relativePath,
                    ]);
                }
            }

            // --- Upload des fichiers joints éventuels (PDF, docs, etc.) ---
            if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {

                $uploadDirFiles = __DIR__ . '/uploads/post_files/';

                if (!is_dir($uploadDirFiles)) {
                    mkdir($uploadDirFiles, 0777, true);
                }

                $insertFile = $pdo->prepare("
                    INSERT INTO post_files (post_id, file_path, original_name, mime_type)
                    VALUES (:post_id, :file_path, :original_name, :mime_type)
                ");

                foreach ($_FILES['files']['name'] as $index => $name) {

                    if ($_FILES['files']['error'][$index] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    if ($_FILES['files']['error'][$index] !== UPLOAD_ERR_OK) {
                        $errors[] = "Un des fichiers n'a pas pu être envoyé correctement.";
                        continue;
                    }

                    $tmpName = $_FILES['files']['tmp_name'][$index];

                    // On accepte "tous" les types, mais on enregistre le mime type
                    $mime = mime_content_type($tmpName) ?: 'application/octet-stream';

                    $extension   = pathinfo($name, PATHINFO_EXTENSION);
                    $safeExt     = $extension ?: 'bin';
                    $safeBase    = pathinfo($name, PATHINFO_FILENAME);
                    // on nettoie un peu le nom de base
                    $safeBase    = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $safeBase);
                    $filename    = 'file_' . $postId . '_' . time() . '_' . $index . '_' . $safeBase . '.' . $safeExt;
                    $destPath    = $uploadDirFiles . $filename;

                    if (!move_uploaded_file($tmpName, $destPath)) {
                        $errors[] = "Impossible d'enregistrer un des fichiers.";
                        continue;
                    }

                    $relativePath = 'uploads/post_files/' . $filename;

                    $insertFile->execute([
                        'post_id'       => $postId,
                        'file_path'     => $relativePath,
                        'original_name' => $name,
                        'mime_type'     => $mime,
                    ]);
                }
            }

            $pdo->commit();
            $success = true;

            header('Location: post.php?id=' . $postId);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Erreur lors de la création du post. Réessaie plus tard.";
            // En prod : log de l'erreur $e->getMessage()
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau post — Transfem Era</title>

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

        .posts-page h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 1rem;
        }

        .alert-box {
            padding: 0.7rem 0.9rem;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
        }

        .new-post-form {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(0, 1.2fr);
            gap: 1.3rem;
        }

        @media (max-width: 900px) {
            .new-post-form {
                grid-template-columns: minmax(0,1fr);
            }
        }

        .new-post-left label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #4b5563;
            margin-bottom: 0.2rem;
        }

        .new-post-left input[type="text"],
        .new-post-left textarea {
            width: 100%;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            padding: 0.5rem 0.7rem;
            font-size: 0.9rem;
        }

        .new-post-left textarea {
            min-height: 230px;
            resize: vertical;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .field-inline {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            margin-bottom: 0.7rem;
        }

        .field-inline > div {
            flex: 1 1 160px;
        }

        .new-post-options {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
            margin: 0.4rem 0 0.8rem;
            font-size: 0.85rem;
            color: #4b5563;
        }

        .new-post-options label {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            cursor: pointer;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.55rem 1.3rem;
            border: 1px solid rgba(245,169,184,0.9);
            background: linear-gradient(90deg, #5bcffb1f, #f5a9b81f);
            color: #e56c9b;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: linear-gradient(90deg, #5bcffb3a, #f5a9b83a);
        }

        .new-post-right {
            border-radius: 18px;
            background: #f9fafb;
            padding: 0.8rem 0.9rem;
            border: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
            max-height: 520px;
            overflow: hidden;
        }

        .preview-box {
            flex: 1;
            overflow: auto;
            border-radius: 12px;
            border: 1px dashed #e5e7eb;
            padding: 0.6rem 0.7rem;
            background: #ffffff;
            font-size: 0.9rem;
            color: #374151;
        }

        .preview-box h3 {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
            color: #111827;
        }

        .preview-content {
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .preview-content a {
            color: #2563eb;
            text-decoration: underline;
        }

        /* Styles custom dans l'aperçu */
        .preview-content .md-center {
            display: block;
            text-align: center;
        }
        .preview-content .md-underline {
            text-decoration: underline;
        }
        .preview-content .md-strike {
            text-decoration: line-through;
        }

        .image-upload-block label,
        .file-upload-block label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: #4b5563;
            margin-bottom: 0.2rem;
        }

        .image-upload-block input[type="file"],
        .file-upload-block input[type="file"] {
            width: 100%;
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        .preview-hint {
            font-size: 0.75rem;
            color: #6b7280;
        }

        /* --- Barre d’outils Markdown --- */
        .editor-toolbar {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 0.25rem;
            margin-bottom: 0.3rem;
        }

        .editor-toolbar button {
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            font-size: 0.78rem;
            padding: 0.15rem 0.6rem;
            cursor: pointer;
        }

        .editor-toolbar button:hover {
            background: #eef2ff;
            border-color: #c7d2fe;
        }

        .editor-toolbar .sep {
            width: 1px;
            height: 18px;
            background: #e5e7eb;
            align-self: center;
        }

        .add-image-btn,
        .add-file-btn {
            margin-top: 0.4rem;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            font-size: 0.8rem;
            padding: 0.25rem 0.7rem;
            cursor: pointer;
        }

        .add-image-btn:hover,
        .add-file-btn:hover {
            background: #eef2ff;
            border-color: #c7d2fe;
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
            <a href="posts.php">Posts</a>
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
        <h2>Nouveau post</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert-box alert-error">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- confirmation JS sur tout l'envoi -->
        <form action="" method="POST" enctype="multipart/form-data" class="new-post-form"
              onsubmit="return confirm('Publier ce post maintenant ?');">
            <div class="new-post-left">
                <div class="field-inline">
                    <div>
                        <label for="title">Titre *</label>
                        <input type="text" id="title" name="title"
                               value="<?= htmlspecialchars($title) ?>" required>
                    </div>
                    <div>
                        <label for="tag">Tag (facultatif)</label>
                        <input type="text" id="tag" name="tag"
                               placeholder="Ex : Annonce, Discussion…"
                               value="<?= htmlspecialchars($tag) ?>">
                    </div>
                </div>

                <label for="content">Contenu *</label>

                <div class="editor-toolbar">
                    <!-- Groupe titres -->
                    <button type="button" data-md="# "  title="Titre niveau 1">H1</button>
                    <button type="button" data-md="## " title="Titre niveau 2">H2</button>
                    <button type="button" data-md="### " title="Titre niveau 3">H3</button>

                    <span class="sep"></span>

                    <!-- Groupe mise en forme -->
                    <button type="button" data-wrap="**" title="Gras">B</button>
                    <button type="button" data-wrap="*"  title="Italique">I</button>
                    <button type="button" data-wrap-start="[u]" data-wrap-end="[/u]" title="Souligné">
                        U
                    </button>
                    <button type="button" data-wrap-start="[s]" data-wrap-end="[/s]" title="Barré">
                        S
                    </button>

                    <span class="sep"></span>

                    <!-- Groupe structure / liens -->
                    <button type="button" data-md="- "  title="Liste à puces">• Liste</button>
                    <button type="button" data-link title="Lien">🔗</button>

                    <span class="sep"></span>

                    <!-- Groupe alignement / couleur -->
                    <button type="button" data-wrap-start="[center]" data-wrap-end="[/center]" title="Centrer">
                        ⫷⫸
                    </button>

                    <input type="color" id="md-color-picker" value="#e11d48"
                           style="width:32px;height:24px;padding:0;border:none;cursor:pointer;">
                    <button type="button" data-color title="Appliquer la couleur choisie">
                        🎨
                    </button>
                </div>

                <textarea id="content" name="content" required
                          placeholder="Markdown type README GitHub. Tu peux aussi utiliser [center]...[/center], [color=#e11d48]...[/color], [u]...[/u], [s]...[/s]."><?= htmlspecialchars($content) ?></textarea>

                <div class="new-post-options">
                    <label>
                        <input type="checkbox" name="is_nsfw" value="1" <?= $is_nsfw ? 'checked' : '' ?>>
                        <span>Contenu sensible / NSFW</span>
                    </label>

                    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                        <label>
                            <input type="checkbox" name="is_pinned" value="1" <?= $is_pinned ? 'checked' : '' ?>>
                            <span>Épingler ce post</span>
                        </label>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-primary">Publier le post</button>
            </div>

            <div class="new-post-right">
                <div class="preview-box">
                    <h3>Aperçu</h3>
                    <div class="preview-content" id="preview-content">
                        Commence à écrire pour voir l’aperçu ici 💖
                    </div>
                    <div class="preview-hint">
                        Markdown : <code>#</code>, <code>##</code>, <code>**gras**</code>, <code>*italique*</code>, <code>- liste</code>, <code>[texte](https://lien)</code>.<br>
                        Tags custom : <code>[center]centré[/center]</code>, <code>[color=#e11d48]texte coloré[/color]</code>,
                        <code>[u]souligné[/u]</code>, <code>[s]barré[/s]</code>.
                    </div>
                </div>

                <div class="image-upload-block">
                    <label for="images">Images (facultatif, plusieurs fichiers possibles)</label>

                    <div id="image-inputs">
                        <input type="file" name="images[]" accept="image/png, image/jpeg, image/webp">
                    </div>

                    <button type="button" id="add-image-input" class="add-image-btn">
                        + Ajouter une autre image
                    </button>

                    <div class="preview-hint">
                        Tu peux ajouter plusieurs images, elles seront affichées sous ton post (galerie).
                    </div>
                </div>

                <div class="file-upload-block">
                    <label for="files">Fichiers joints (PDF, docs, etc.)</label>

                    <div id="file-inputs">
                        <input type="file" name="files[]">
                    </div>

                    <button type="button" id="add-file-input" class="add-file-btn">
                        + Ajouter un autre fichier
                    </button>

                    <div class="preview-hint">
                        Ces fichiers seront listés séparément de la galerie d’images (ex. PDFs, documents).
                    </div>
                </div>
            </div>
        </form>

        <script src="SCRIPTS/new_posts.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.fog.min.js"></script>
        <script src="SCRIPTS/vanta.js"></script>
        <script src="SCRIPTS/accederprofil.js"></script>

        <script>
        // Ajout dynamique de champs fichier pour les images
        document.addEventListener('DOMContentLoaded', function () {
            const imgContainer = document.getElementById('image-inputs');
            const addImgBtn    = document.getElementById('add-image-input');
            if (imgContainer && addImgBtn) {
                addImgBtn.addEventListener('click', function () {
                    const input = document.createElement('input');
                    input.type  = 'file';
                    input.name  = 'images[]';
                    input.accept = 'image/png, image/jpeg, image/webp';
                    imgContainer.appendChild(input);
                });
            }

            // Ajout dynamique de champs fichier pour les fichiers joints
            const fileContainer = document.getElementById('file-inputs');
            const addFileBtn    = document.getElementById('add-file-input');
            if (fileContainer && addFileBtn) {
                addFileBtn.addEventListener('click', function () {
                    const input = document.createElement('input');
                    input.type  = 'file';
                    input.name  = 'files[]';
                    fileContainer.appendChild(input);
                });
            }
        });
        </script>

    </section>
</main>

<footer>
    © <span class="footer-year"><?= date("Y"); ?></span> — Transfem Era
</footer>
</body>
</html>
