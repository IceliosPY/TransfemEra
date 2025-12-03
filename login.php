<?php
// login.php
session_start();
require_once __DIR__ . '/config.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pseudo   = trim($_POST['pseudo'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if ($pseudo === '') {
        $errors[] = "Merci d'indiquer ton pseudo.";
    }

    if ($password === '') {
        $errors[] = "Merci de saisir ton mot de passe.";
    }

    if (empty($errors)) {

        // On récupère l'utilisateur par son pseudo
        $stmt = $pdo->prepare("
            SELECT id, pseudo, password_hash, avatar_path, role
            FROM users
            WHERE pseudo = :pseudo
        ");
        $stmt->execute(['pseudo' => $pseudo]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $errors[] = "Pseudo ou mot de passe incorrect.";
        } else {
            if (!password_verify($password, $user['password_hash'])) {
                $errors[] = "Pseudo ou mot de passe incorrect.";
            } else {

                // Connexion OK
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_pseudo'] = $user['pseudo'];
                $_SESSION['role']        = $user['role'];

                // Avatar (BDD ou défaut)
                $_SESSION['avatar_path'] = !empty($user['avatar_path'])
                    ? $user['avatar_path']
                    : 'IMG/profile_default.png';

                // Redirection vers la page intermédiaire
                header("Location: index.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Se connecter — Transfem Era</title>

    <link rel="stylesheet" href="CSS/login.css">

    <style>
        body {
            background: transparent !important;
        }

        #vanta-bg {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .login-container {
            position: relative;
            z-index: 5;
        }
    </style>
</head>
<body>

<div id="vanta-bg"></div>

<div class="login-container">
    <h2>Se connecter</h2>

    <?php if (!empty($errors)): ?>
        <div style="margin-bottom:1rem; padding:0.8rem; border-radius:8px;
                    background:#ffe6ea; color:#b00020; font-size:0.9rem;">
            <?php foreach ($errors as $err): ?>
                <div><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" class="login-form">
        <label for="pseudo">Pseudo</label>
        <input
            type="text"
            id="pseudo"
            name="pseudo"
            required
            value="<?= isset($pseudo) ? htmlspecialchars($pseudo) : '' ?>"
        >

        <label for="password">Mot de passe</label>
        <input
            type="password"
            id="password"
            name="password"
            required
        >

        <button type="submit" class="login-btn">Connexion</button>
    </form>

    <p class="signup-text">
            Pas encore de compte ? <a href="register.php">Créer un compte</a>
        </p>
</div>

<!-- VANTA -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.fog.min.js"></script>
<script src="SCRIPTS/vanta.js"></script>

</body>
</html>
