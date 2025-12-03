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

// Données profil
$avatarPath   = $user['avatar_path']  ?: 'IMG/profile_default.png';
$avatarShape  = $user['avatar_shape'] ?: 'circle';
$pseudo       = $user['pseudo']       ?? '';
$gender       = $user['gender']       ?? '';
$email        = $user['email']        ?? '';
$createdAt    = $user['created_at']   ?? null;
$role         = $user['role']         ?? 'visiteur';

$errors  = [];
$success = "";

// ------------------------------------------------------
// 1. Actions ADMIN (validation / rétrogradation)
// ------------------------------------------------------
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['admin_action'])
    && $role === 'admin'
) {
    $adminAction = $_POST['admin_action'];
    $targetId    = (int)($_POST['user_id'] ?? 0);

    if ($targetId > 0) {
        if ($adminAction === 'validate_user') {
            // visiteur -> membre
            $stmtVal = $pdo->prepare("
                UPDATE users
                SET role = 'membre'
                WHERE id = :id AND role = 'visiteur'
            ");
            $stmtVal->execute(['id' => $targetId]);
        } elseif ($adminAction === 'demote_visitor') {
            // membre -> visiteur (on évite de se rétrograder soi-même ici)
            if ($targetId !== (int)$user['id']) {
                $stmtDem = $pdo->prepare("
                    UPDATE users
                    SET role = 'visiteur'
                    WHERE id = :id AND role = 'membre'
                ");
                $stmtDem->execute(['id' => $targetId]);
            }
        }
    }

    // On revient sur la page pour éviter le repost
    header('Location: profil.php');
    exit;
}

// ------------------------------------------------------
// 2. Préparation des listes admin (avec pagination)
// ------------------------------------------------------
$allUsers       = [];
$pendingUsers   = [];
$totalUsers     = 0;
$totalPages     = 1;
$currentPage    = 1;
$perPage        = 5;

if ($role === 'admin') {
    // Pagination "comptes"
    $currentPage = max(1, (int)($_GET['page'] ?? 1));

    $countStmt   = $pdo->query('SELECT COUNT(*) FROM users');
    $totalUsers  = (int)$countStmt->fetchColumn();
    $totalPages  = max(1, (int)ceil($totalUsers / $perPage));

    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    $offset = ($currentPage - 1) * $perPage;

    // Tous les comptes (page courante)
    $q = $pdo->prepare('
        SELECT id, pseudo, email, role, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ');
    $q->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $q->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $q->execute();
    $allUsers = $q->fetchAll(PDO::FETCH_ASSOC);

    // Comptes à valider : "visiteur"
    $stmtPending = $pdo->prepare('
        SELECT id, pseudo, email, role, created_at
        FROM users
        WHERE role = :role
        ORDER BY created_at DESC
    ');
    $stmtPending->execute(['role' => 'visiteur']);
    $pendingUsers = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
}

// ------------------------------------------------------
// 3. Traitement du formulaire de profil (texte + avatar)
// ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_action'])) {

    // Champs texte
    $pseudo = trim($_POST['pseudo'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $email  = trim($_POST['email']  ?? '');

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L’adresse mail n’est pas valide.";
    }

    // Upload avatar éventuel
    $newAvatarPath = null;

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Erreur lors de l’envoi de la nouvelle photo de profil.";
        } else {
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $mime    = mime_content_type($_FILES['avatar']['tmp_name']);

            if (!in_array($mime, $allowed, true)) {
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
                   SET pseudo = :pseudo,
                       gender = :gender,
                       email  = :email";

        $params = [
            'pseudo' => $pseudo,
            'gender' => $gender,
            'email'  => $email,
            'id'     => $_SESSION['user_id']
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

    <link rel="stylesheet" href="CSS/profil.css">

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

        <!-- Champs éditables -->
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
                <label for="email">Adresse mail (facultative)</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($email) ?>"
                       placeholder="Adresse visible dans ton profil (optionnelle)">
            </div>
        </div>

        <button type="submit" class="save-btn">Enregistrer les modifications</button>

        <?php if ($role === 'admin'): ?>
            <button type="button" class="admin-main-btn" id="open-users-modal">
                <span>👥</span>
                <span>Ouvrir la gestion des comptes</span>
            </button>
        <?php endif; ?>

    </form>

    <div class="back-links">
        <a href="index.php">← Retour au site</a>
        <a href="logout.php">Se déconnecter</a>
    </div>

</div>

<?php if ($role === 'admin'): ?>
    <!-- MODAL GESTION DES COMPTES + VALIDATION INSCRIPTIONS -->
    <div class="modal-backdrop" id="users-modal-backdrop">
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="users-modal-title">
            <div class="modal-header">
                <div>
                    <div class="modal-title" id="users-modal-title">Gestion des comptes</div>
                    <small><?= (int)$totalUsers ?> compte(s) enregistré(s)</small><br>
                    <small>Page <?= (int)$currentPage ?> / <?= (int)$totalPages ?></small>
                </div>
                <button type="button" class="modal-close-btn" id="close-users-modal" aria-label="Fermer">
                    &times;
                </button>
            </div>

            <!-- Onglets -->
            <div class="modal-tabs">
                <button type="button" class="admin-tab active" data-tab="accounts">
                    Comptes
                </button>
                <button type="button" class="admin-tab" data-tab="pending">
                    Validation des inscriptions (<?= count($pendingUsers) ?>)
                </button>
            </div>

            <div class="modal-body">
                <!-- Onglet 1 : tous les comptes -->
                <div class="admin-tab-panel active" data-tab-panel="accounts">
                    <?php if (empty($allUsers)): ?>
                        <p>Aucun compte n’a encore été créé.</p>
                    <?php else: ?>
                        <table class="accounts-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pseudo</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Création</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($allUsers as $u): ?>
                                <tr>
                                    <td>#<?= (int)$u['id'] ?></td>
                                    <td><?= htmlspecialchars($u['pseudo'] ?: $u['email']) ?></td>
                                    <td class="email-col"><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <span class="role-pill <?= htmlspecialchars($u['role']) ?>">
                                            <?= htmlspecialchars($u['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $u['created_at']
                                            ? htmlspecialchars(date('d/m/Y', strtotime($u['created_at'])))
                                            : '-' ?>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'membre' && (int)$u['id'] !== (int)$user['id']): ?>
                                            <form method="post" class="inline-admin-form">
                                                <input type="hidden" name="admin_action" value="demote_visitor">
                                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <button type="submit" class="demote-user-btn">
                                                    Remettre visiteur
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if ($totalPages > 1): ?>
                            <div class="accounts-pagination">
                                <div>
                                    <?php if ($currentPage > 1): ?>
                                        <a href="profil.php?page=<?= $currentPage - 1 ?>">← Page précédente</a>
                                    <?php endif; ?>
                                </div>
                                <div>Page <?= (int)$currentPage ?> / <?= (int)$totalPages ?></div>
                                <div>
                                    <?php if ($currentPage < $totalPages): ?>
                                        <a href="profil.php?page=<?= $currentPage + 1 ?>">Page suivante →</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>

                <!-- Onglet 2 : inscriptions à valider -->
                <div class="admin-tab-panel" data-tab-panel="pending">
                    <?php if (empty($pendingUsers)): ?>
                        <p>Aucune inscription à valider pour le moment 💖</p>
                    <?php else: ?>
                        <table class="accounts-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pseudo</th>
                                <th>Email</th>
                                <th>Inscrite le</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pendingUsers as $u): ?>
                                <tr>
                                    <td>#<?= (int)$u['id'] ?></td>
                                    <td><?= htmlspecialchars($u['pseudo'] ?: '—') ?></td>
                                    <td class="email-col"><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <?= $u['created_at']
                                            ? htmlspecialchars(date('d/m/Y', strtotime($u['created_at'])))
                                            : '-' ?>
                                    </td>
                                    <td>
                                        <form method="post" class="inline-admin-form">
                                            <input type="hidden" name="admin_action" value="validate_user">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="validate-user-btn">
                                                Valider
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Scripts JS -->
<script src="SCRIPTS/profil.js"></script>

<!-- Vanta FOG -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.fog.min.js"></script>
<script src="SCRIPTS/vanta.js"></script>

</body>
</html>
