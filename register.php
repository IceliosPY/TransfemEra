<?php
// register.php
require_once __DIR__ . '/config.php';

$errors  = [];
$success = false;

// Valeurs par défaut pour le repop du formulaire
$pseudo      = '';
$first_name  = '';
$gender      = '';
$birthdate   = '';
$email       = '';
$avatarPath  = null; // chemin éventuel de l'avatar

// --- petite fonction simple pour normaliser la date en YYYY-MM-DD ---
function normalize_birthdate(string $birthdate): ?string
{
    $birthdate = trim($birthdate);

    // 1) Format standard des <input type="date"> : 2025-12-16
    if (preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $birthdate, $m)) {
        $y   = (int)$m[1];
        $mon = (int)$m[2];
        $d   = (int)$m[3];

        if (checkdate($mon, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mon, $d);
        }
        return null;
    }

    // 2) Format 09/11/2020
    if (preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $birthdate, $m)) {
        $d   = (int)$m[1];
        $mon = (int)$m[2];
        $y   = (int)$m[3];

        if (checkdate($mon, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mon, $d);
        }
        return null;
    }

    // 3) Format 09-11-2020
    if (preg_match('~^(\d{2})-(\d{2})-(\d{4})$~', $birthdate, $m)) {
        $d   = (int)$m[1];
        $mon = (int)$m[2];
        $y   = (int)$m[3];

        if (checkdate($mon, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mon, $d);
        }
        return null;
    }

    // Rien ne correspond
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Récupération et nettoyage basique
    $pseudo        = trim($_POST['pseudo'] ?? '');
    $first_name    = trim($_POST['first_name'] ?? '');
    $gender        = trim($_POST['gender'] ?? '');
    $birthdate     = trim($_POST['birthdate'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $password_conf = $_POST['password_confirm'] ?? '';
    $accept_rules  = isset($_POST['accept_rules']);

    // --- Upload de la photo de profil (facultatif) ----
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {

            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $mime = mime_content_type($_FILES['avatar']['tmp_name']);

            if (in_array($mime, $allowed, true)) {

                $destFolder = __DIR__ . "/uploads/avatar/";
                if (!is_dir($destFolder)) {
                    mkdir($destFolder, 0777, true);
                }

                $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename  = "avatar_" . time() . "_" . rand(1000, 9999) . "." . $extension;
                $destPath  = $destFolder . $filename;

                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destPath)) {
                    $avatarPath = "uploads/avatar/" . $filename;
                }

            } else {
                $errors[] = "Format d’image non accepté (JPG, PNG ou WebP uniquement).";
            }
        } else {
            $errors[] = "Erreur lors de l’envoi de la photo de profil.";
        }
    }

    // --- Validation ---

    // Pseudo obligatoire
    if ($pseudo === '') {
        $errors[] = "Merci d'indiquer un pseudo.";
    } elseif (mb_strlen($pseudo) > 100) {
        $errors[] = "Le pseudo est trop long (100 caractères max).";
    }

    // Prénom facultatif -> on ne fait qu'une limite de taille
    if ($first_name !== '' && mb_strlen($first_name) > 100) {
        $errors[] = "Le prénom est trop long (100 caractères max).";
    }

    // Genre facultatif -> limite simple
    if ($gender !== '' && mb_strlen($gender) > 50) {
        $errors[] = "Le champ genre est trop long (50 caractères max).";
    }

    // Date de naissance (obligatoire)
    if ($birthdate === '') {
        $errors[] = "Merci d’indiquer ta date de naissance.";
    } else {
        $birthdate = trim($birthdate);
        $normalized = normalize_birthdate($birthdate);

        if ($normalized === null) {
            $errors[] = "La date de naissance n’est pas valide.";
        } else {
            $birthdate = $normalized; // version propre pour MySQL
        }
    }

    // Email facultatif
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Merci d'indiquer une adresse email valide (ou laisse le champ vide).";
    }

    // Mot de passe
    if (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    if ($password !== $password_conf) {
        $errors[] = "Les deux mots de passe ne correspondent pas.";
    }

    // Acceptation du règlement / statuts
    if (!$accept_rules) {
        $errors[] = "Tu dois accepter le règlement / les statuts de l’association pour créer un compte.";
    }

    // Si pas d'erreurs pour l'instant, on vérifie si le pseudo ou l'email existent déjà
    if (empty($errors)) {

        // Vérif pseudo unique
        $stmt = $pdo->prepare("SELECT id FROM users WHERE pseudo = :pseudo");
        $stmt->execute(['pseudo' => $pseudo]);
        $existingPseudo = $stmt->fetch();

        if ($existingPseudo) {
            $errors[] = "Ce pseudo est déjà utilisé. Merci d’en choisir un autre.";
        }

        // Vérif email uniquement si non vide
        if ($email !== '') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $existingEmail = $stmt->fetch();

            if ($existingEmail) {
                $errors[] = "Un compte existe déjà avec cette adresse email.";
            }
        }
    }

    // Si toujours pas d'erreur -> on insère
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $insert = $pdo->prepare("
            INSERT INTO users (pseudo, first_name, gender, birthdate, email, password_hash, role, avatar_path)
            VALUES (:pseudo, :first_name, :gender, :birthdate, :email, :password_hash, 'visiteur', :avatar_path)
        ");

        try {
            $insert->execute([
                'pseudo'        => $pseudo,
                'first_name'    => $first_name !== '' ? $first_name : null,
                'gender'        => $gender     !== '' ? $gender     : null,
                'birthdate'     => $birthdate  !== '' ? $birthdate  : null,
                'email'         => $email      !== '' ? $email      : null,
                'password_hash' => $hash,
                'avatar_path'   => $avatarPath
            ]);

            $success = true;

            // On vide les champs pour ne pas réafficher les infos
            $pseudo      = '';
            $first_name  = '';
            $gender      = '';
            $birthdate   = '';
            $email       = '';
            $avatarPath  = null;

        } catch (PDOException $e) {
            // En prod, on loguerait l'erreur serveur sans l'afficher
            $errors[] = "Erreur lors de la création du compte. Réessaie plus tard.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un compte — Transfem Era</title>

    <link rel="stylesheet" href="CSS/register.css">
</head>
<body>

<div id="vanta-bg"></div>

<div class="register-page">
    <div class="register-card">
        <h2>Créer un compte</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert-box alert-error">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-box alert-success">
                Ton compte a bien été créé 💖<br>
                Il sera d’abord considéré comme <strong>visiteur</strong>, puis pourra être validé
                par l’équipe de Transfem Era.<br>
                Tu peux maintenant <a href="login.php">te connecter</a>.
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" class="register-form">

            <!-- PHOTO DE PROFIL -->
            <label for="avatar" class="avatar-label">Photo de profil (facultatif)</label>

            <div class="avatar-upload">
                <label for="avatar" class="avatar-wrapper">
                    <img
                        id="avatar-preview"
                        src="IMG/default.png"
                        alt="Aperçu"
                    >
                    <div class="avatar-hover">
                        <span>Changer</span>
                    </div>
                </label>

                <input type="file"
                       id="avatar"
                       name="avatar"
                       accept="image/png, image/jpeg, image/webp">
            </div>

            <!-- PSEUDO -->
            <label for="pseudo">Pseudo *</label>
            <input type="text" id="pseudo" name="pseudo" required maxlength="100"
                   value="<?= htmlspecialchars($pseudo) ?>">

            <!-- PRENOM -->
            <label for="first_name">Prénom (facultatif)</label>
            <input type="text" id="first_name" name="first_name" maxlength="100"
                   value="<?= htmlspecialchars($first_name) ?>">

            <!-- GENRE -->
            <label for="gender">Genre (ex : femme trans, non-binaire, ...)</label>
            <input type="text" id="gender" name="gender" maxlength="50"
                   value="<?= htmlspecialchars($gender) ?>">

            <!-- NAISSANCE -->
            <label for="birthdate">Date de naissance *</label>
            <input type="date" id="birthdate" name="birthdate" required
                   value="<?= htmlspecialchars($birthdate) ?>">

            <!-- EMAIL -->
            <label for="email">Adresse mail (facultative)</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($email) ?>">

            <!-- MOT DE PASSE -->
            <label for="password">Mot de passe *</label>
            <input type="password" id="password" name="password" required>

            <!-- CONFIRMATION -->
            <label for="password_confirm">Confirmer le mot de passe *</label>
            <input type="password" id="password_confirm" name="password_confirm" required>

            <!-- RÈGLEMENT -->
            <label class="rules-label">
                <input type="checkbox" name="accept_rules" value="1" required>
                <span>
                    J’ai pris connaissance et j’accepte le règlement / les statuts de l’association
                    Transfem Era ainsi que l’utilisation de mes données personnelles dans ce cadre.
                </span>
            </label>

            <!-- BUTTON -->
            <button type="submit" class="login-btn">
                Créer mon compte
            </button>
        </form>

        <p class="signup-text">
            Tu as déjà un compte ? <a href="login.php">Se connecter</a>
        </p>
    </div>
</div>

<!-- Preview avatar -->
<script src="SCRIPTS/register.js"></script>
<!-- Vanta -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.fog.min.js"></script>
<script src="SCRIPTS/vanta.js"></script>
</body>
</html>
