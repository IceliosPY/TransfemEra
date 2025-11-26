<?php
// login.php
session_start();
require_once __DIR__ . '/config.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation rapide
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Merci d'indiquer une adresse email valide.";
    }

    if ($password === '') {
        $errors[] = "Merci de saisir ton mot de passe.";
    }

    if (empty($errors)) {
        // Recherche de l'utilisateur en base
        $stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            // aucun compte avec cet email
            $errors[] = "Adresse email ou mot de passe incorrect.";
        } else {
            // vérification du mot de passe
            if (!password_verify($password, $user['password_hash'])) {
                $errors[] = "Adresse email ou mot de passe incorrect.";
            } else {
                // ✅ Connexion OK -> on crée la session
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_email'] = $user['email'];

                // Redirection vers la page d'accueil (ou autre)
                header('Location: index.php');
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
</head>
<body>

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
            <label for="email">Adresse email</label>
            <input
                type="email"
                id="email"
                name="email"
                required
                value="<?= isset($email) ? htmlspecialchars($email) : '' ?>"
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

</body>
</html>
