<?php
// generate_hash.php
// Petit outil local pour générer des hash de mots de passe

// ⚠️ À utiliser seulement en local / admin, et à supprimer du serveur public ensuite

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if ($password === '') {
        $error = "Merci de saisir un mot de passe.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Générer un hash de mot de passe</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 2rem; }
        input[type="password"] { padding: 0.4rem; width: 300px; }
        button { padding: 0.4rem 1rem; margin-left: 0.5rem; }
        .hash-box { margin-top: 1rem; padding: 0.8rem; background: #f7f7f7; border-radius: 8px; }
        code { word-break: break-all; }
    </style>
</head>
<body>

<h1>Générateur de hash (password_hash)</h1>

<form method="post">
    <label>Mot de passe à hasher :</label><br>
    <input type="password" name="password" required>
    <button type="submit">Générer</button>
</form>

<?php if (!empty($error)): ?>
    <p style="color:#b00020;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<?php if (!empty($hash)): ?>
    <div class="hash-box">
        <strong>Hash généré :</strong><br>
        <code><?= htmlspecialchars($hash) ?></code>
        <p>Copie ce hash dans la colonne <code>password_hash</code> de ta table <code>users</code>.</p>
    </div>
<?php endif; ?>

</body>
</html>
