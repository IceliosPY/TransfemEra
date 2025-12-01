<?php
// profil.php
session_start();
require_once __DIR__ . '/config.php';

// Redirection si non connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupération du profil
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// Données
$avatarPath   = $user['avatar_path']  ?: 'IMG/profile_default.png';
$avatarShape  = $user['avatar_shape'] ?: 'circle';
$pseudo       = $user['pseudo']       ?? '';
$gender       = $user['gender']       ?? '';
$publicEmail  = $user['public_email'] ?? '';
$createdAt    = $user['created_at']   ?? null;
$role         = $user['role']         ?? 'visiteur';

$errors  = [];
$success = "";

// ------------------------------------------------------
// TRAITEMENT DU FORMULAIRE (texte + avatar)
// ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Champs texte
    $pseudo      = trim($_POST['pseudo'] ?? '');
    $gender      = trim($_POST['gender'] ?? '');
    $publicEmail = trim($_POST['public_email'] ?? '');

    if ($publicEmail !== '' && !filter_var($publicEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L’adresse mail facultative n’est pas valide.";
    }

    // Upload avatar éventuel
    $newAvatarPath = null;

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Erreur lors de l’envoi de la nouvelle photo de profil.";
        } else {
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $mime = mime_content_type($_FILES['avatar']['tmp_name']);

            if (!in_array($mime, $allowed)) {
                $errors[] = "Seuls les fichiers JPG, PNG et WEBP sont autorisés pour la photo de profil.";
            } else {
                $destFolder = __DIR__ . "/uploads/avatar/";
                if (!is_dir($destFolder)) {
                    mkdir($destFolder, 0777, true);
                }

                $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename  = "avatar_" . $_SESSION['user_id'] . "_" . time() . "." . $extension;
                $destPath  = $destFolder . $filename;

                if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $destPath)) {
                    $errors[] = "Impossible d’enregistrer la nouvelle photo de profil.";
                } else {
                    $newAvatarPath = "uploads/avatar/" . $filename;
                }
            }
        }
    }

    // Si pas d’erreurs → mise à jour
    if (empty($errors)) {
        $sql = "UPDATE users
                   SET pseudo       = :pseudo,
                       gender       = :gender,
                       public_email = :public_email";

        $params = [
            'pseudo'       => $pseudo,
            'gender'       => $gender,
            'public_email' => $publicEmail,
            'id'           => $_SESSION['user_id']
        ];

        if ($newAvatarPath !== null) {
            $sql .= ", avatar_path = :avatar_path";
            $params['avatar_path'] = $newAvatarPath;
        }

        $sql .= " WHERE id = :id";

        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        if ($newAvatarPath !== null) {
            $avatarPath = $newAvatarPath;
            $_SESSION['avatar_path'] = $newAvatarPath;
        }

        $success = "Ton profil a bien été mis à jour 💖";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil — Transfem Era</title>

    <link rel="stylesheet" href="CSS/login.css">

    <style>
        /* Fond Vanta derrière, contenu au-dessus */
        #vanta-bg {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        body {
            background: transparent !important;
        }

        .profile-container {
            max-width: 520px;
            margin: 40px auto;
            position: relative;
            z-index: 5;
        }

        /* Carte principale compacte */
        .profile-card {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            padding: 1.4rem 1.6rem;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.2rem;
        }

        .profile-avatar-label {
            display: inline-block;
            cursor: pointer;
            position: relative;
            flex-shrink: 0;
        }

        .profile-avatar-label img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .profile-avatar-label::after {
            content: "Changer";
            position: absolute;
            inset: 0;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding-bottom: 6px;
            font-size: 0.7rem;
            color: #fff;
            background: linear-gradient(to top, rgba(0,0,0,0.45), transparent);
            opacity: 0;
            border-radius: 50%;
            transition: opacity 0.2s ease;
        }

        .profile-avatar-label:hover img {
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(0,0,0,0.22);
        }

        .profile-avatar-label:hover::after {
            opacity: 1;
        }

        #avatar-input {
            display: none;
        }

        .profile-header-main {
            flex: 1;
            min-width: 0;
        }

        .profile-header-topline {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.3rem;
            flex-wrap: wrap;
        }

        .profile-username {
            font-size: 1.3rem;
            font-weight: 600;
            color: #111827;
        }

        .profile-email {
            font-size: 0.85rem;
            color: #6b7280;
            word-break: break-all;
        }

        /* Badge rôle */
        .role-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.15rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }

        .role-visiteur {
            background: #e5e7eb;
            color: #111827;
        }
        .role-membre {
            background: rgba(91,207,251,0.15);
            color: #2563eb;
        }
        .role-admin {
            background: linear-gradient(90deg, #f97316, #ec4899);
            color: #ffffff;
        }

        /* Ligne infos rapides */
        .profile-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 0.3rem;
            font-size: 0.8rem;
            color: #6b7280;
        }

        .profile-meta span {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .profile-meta span .dot {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: #9ca3af;
        }

        /* Formulaire compact */
        .profile-fields-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.8rem;
            margin-top: 0.8rem;
        }

        .profile-field label {
            display: block;
            font-size: 0.8rem;
            margin-bottom: 0.2rem;
            color: #6b7280;
        }

        .profile-field input {
            width: 100%;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            font-size: 0.9rem;
        }

        .profile-field input[readonly] {
            background: #f9fafb;
            color: #6b7280;
        }

        .save-btn {
            width: 100%;
            margin-top: 0.8rem;
            padding: 0.55rem;
            border-radius: 999px;
            border: 1px solid rgba(245,169,184,0.9);
            background: linear-gradient(90deg, #5bcffb1f, #f5a9b81f);
            color: #e56c9b;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .save-btn:hover {
            background: linear-gradient(90deg, #5bcffb3a, #f5a9b83a);
        }

        .alert {
            padding: 0.6rem;
            border-radius: 8px;
            margin-bottom: 0.8rem;
            font-size: 0.85rem;
        }
        .alert.error   { background: #ffe6ea; color: #b00020; }
        .alert.success { background: #e6fff3; color: #146c43; }

        .back-links {
            margin-top: 0.9rem;
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            position: relative;
            z-index: 5;
        }

        .back-links a {
            text-decoration: none;
            color: #e56c9b;
        }

        /* Panneau admin repliable */
        .admin-panel-wrapper {
            margin-top: 1rem;
        }

        .admin-toggle-btn {
            width: 100%;
            padding: 0.5rem 0.8rem;
            border-radius: 999px;
            border: 1px solid rgba(236,72,153,0.4);
            background: #fff7fb;
            color: #be185d;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            cursor: pointer;
        }

        .admin-toggle-btn span.icon {
            font-size: 1.1rem;
        }

        .admin-panel {
            margin-top: 0.7rem;
            padding: 0.8rem 0.9rem;
            border-radius: 14px;
            border: 1px solid rgba(236,72,153,0.35);
            background: linear-gradient(135deg, rgba(236,72,153,0.08), rgba(56,189,248,0.06));
            box-shadow: 0 6px 18px rgba(0,0,0,0.05);
            font-size: 0.85rem;
            display: none; /* masqué par défaut */
        }

        .admin-panel.open {
            display: block;
        }

        .admin-panel p {
            margin-bottom: 0.35rem;
            color: #4b5563;
        }

        .admin-panel-actions {
            margin-top: 0.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }

        .admin-chip {
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            font-size: 0.8rem;
            border: 1px solid rgba(190,24,93,0.3);
            background: rgba(255,255,255,0.8);
            color: #be185d;
        }

        @media (min-width: 640px) {
            .profile-fields-grid {
                grid-template-columns: 1fr 1fr;
            }

            .profile-field.full-row {
                grid-column: 1 / -1;
            }
        }
    </style>
</head>
<body>

<div id="vanta-bg"></div>

<div class="login-container profile-container">

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <?php foreach ($errors as $err): ?>
                <div><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="profile-card">

        <!-- Header compact : avatar + pseudo + badge + infos -->
        <div class="profile-header">
            <label for="avatar-input" class="profile-avatar-label">
                <img
                    src="<?= htmlspecialchars($avatarPath) ?>"
                    alt="Avatar"
                    id="avatar-preview"
                    class="<?= htmlspecialchars($avatarShape) ?>"
                >
            </label>
            <input type="file" id="avatar-input" name="avatar"
                   accept="image/png, image/jpeg, image/webp">

            <div class="profile-header-main">
                <div class="profile-header-topline">
                    <div class="profile-username">
                        <?= $pseudo !== '' ? htmlspecialchars($pseudo) : htmlspecialchars($user['email']) ?>
                    </div>
                    <?php
                        $roleClass = 'role-visiteur';
                        $roleLabel = $role;

                        if ($role === 'membre') {
                            $roleClass = 'role-membre';
                            $roleLabel = 'membre';
                        } elseif ($role === 'admin') {
                            $roleClass = 'role-admin';
                            $roleLabel = 'admin';
                        }
                    ?>
                    <span class="role-badge <?= htmlspecialchars($roleClass) ?>">
                        <?= htmlspecialchars($roleLabel) ?>
                    </span>
                </div>

                <?php if (!empty($user['email'])): ?>
                    <div class="profile-email">
                        <?= htmlspecialchars($user['email']) ?>
                    </div>
                <?php endif; ?>

                <div class="profile-meta">
                    <?php if ($createdAt): ?>
                        <span>
                            <span class="dot"></span>
                            Compte créé le
                            <?= htmlspecialchars(date('d/m/Y', strtotime($createdAt))) ?>
                        </span>
                    <?php endif; ?>
                    <span>
                        <span class="dot"></span>
                        ID #<?= (int)$user['id'] ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Champs éditables, organisés en grille -->
        <div class="profile-fields-grid">
            <div class="profile-field">
                <label for="pseudo">Pseudo</label>
                <input type="text" id="pseudo" name="pseudo"
                       value="<?= htmlspecialchars($pseudo) ?>"
                       maxlength="100">
            </div>

            <div class="profile-field">
                <label for="gender">Genre</label>
                <input type="text" id="gender" name="gender"
                       value="<?= htmlspecialchars($gender) ?>"
                       maxlength="50"
                       placeholder="Ex : femme trans, transféminine…">
            </div>

            <div class="profile-field full-row">
                <label for="public_email">Adresse mail (facultative)</label>
                <input type="email" id="public_email" name="public_email"
                       value="<?= htmlspecialchars($publicEmail) ?>"
                       placeholder="Adresse visible dans ton profil (optionnelle)">
            </div>
        </div>

        <button type="submit" class="save-btn">Enregistrer les modifications</button>

        <?php if ($role === 'admin'): ?>
            <div class="admin-panel-wrapper">
                <button type="button" class="admin-toggle-btn" id="admin-toggle">
                    <span class="icon">🛠️</span>
                    <span>Ouvrir / fermer le panneau d’administration</span>
                </button>

                <div class="admin-panel" id="admin-panel">
                    <p>Tu es connectée en tant qu’<strong>administratrice</strong> de Transfem Era.</p>
                    <p>Ces outils sont en préparation ; pour l’instant ils sont indiqués à titre de repères.</p>

                    <div class="admin-panel-actions">
                        <span class="admin-chip">Gestion des comptes (à venir)</span>
                        <span class="admin-chip">Validation des inscriptions (à venir)</span>
                        <span class="admin-chip">Paramètres du site (à venir)</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </form>

    <div class="back-links">
        <a href="index.php">← Retour au site</a>
        <a href="logout.php">Se déconnecter</a>
    </div>

</div>

<script>
    // Preview de l’avatar choisi
    const avatarInput   = document.getElementById('avatar-input');
    const avatarPreview = document.getElementById('avatar-preview');

    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                avatarPreview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    // Panneau admin repliable
    const adminToggle = document.getElementById('admin-toggle');
    const adminPanel  = document.getElementById('admin-panel');

    if (adminToggle && adminPanel) {
        adminToggle.addEventListener('click', () => {
            adminPanel.classList.toggle('open');
        });
    }
</script>

<!-- Vanta FOG (scripts partagés avec index) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.fog.min.js"></script>
<script src="SCRIPTS/vanta.js"></script>

</body>
</html>
