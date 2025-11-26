<?php
// register.php
require_once __DIR__ . '/config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage basique
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // --- Validation ---
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Merci d'indiquer une adresse email valide.";
    }

    if (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }

    if ($password !== $password_confirm) {
        $errors[] = "Les deux mots de passe ne correspondent pas.";
    }

    // Si pas d'erreurs pour l'instant, on vérifie si l'email existe déjà
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $existing = $stmt->fetch();

        if ($existing) {
            $errors[] = "Un compte existe déjà avec cette adresse email.";
        }
    }

    // Si toujours pas d'erreur -> on insère
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $insert = $pdo->prepare(
            "INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)"
        );

        try {
            $insert->execute([
                'email'         => $email,
                'password_hash' => $hash
            ]);
            $success = true;
        } catch (PDOException $e) {
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

    <!-- On peut réutiliser le style de login -->
    <link rel="stylesheet" href="CSS/login.css">
</head>
<body>

    <div class="login-container">
        <h2>Créer un compte</h2>

        <?php if (!empty($errors)): ?>
            <div style="margin-bottom:1rem; padding:0.8rem; border-radius:8px;
                        background:#ffe6ea; color:#b00020; font-size:0.9rem;">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div style="margin-bottom:1rem; padding:0.8rem; border-radius:8px;
                        background:#e6fff3; color:#146c43; font-size:0.9rem;">
                Ton compte a bien été créé 💖<br>
                Tu peux maintenant <a href="login.php">te connecter</a>.
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

            <label for="password_confirm">Confirmer le mot de passe</label>
            <input
                type="password"
                id="password_confirm"
                name="password_confirm"
                required
            >

            <button type="submit" class="login-btn">Créer mon compte</button>
        </form>

        <p class="signup-text">
            Tu as déjà un compte ? <a href="login.php">Se connecter</a>
        </p>
    </div>

</body>
</html>
