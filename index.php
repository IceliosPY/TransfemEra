<?php
// index.php
session_start(); // important : doit être tout en haut
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfem Era</title>

    <link rel="stylesheet" href="CSS/index.css">
</head>
<body>

    <!-- Fond animé Vanta -->
    <div id="vanta-bg"></div>

    <!-- BARRE DU HAUT -->
    <header>
        <div id="top-bar">
            <!-- Gauche : titre -->
            <div class="header-left">
                <h1>Transfem Era</h1>
            </div>

            <!-- Centre : navigation principale -->
            <nav class="main-nav">
    <a href="index.php#accueil">Accueil</a>
    <a href="index.php#valeurs">Nos valeurs</a>
    <a href="index.php#contact">Nous contacter</a>
    <?php if (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['membre', 'admin'], true)): ?>
    <a href="posts.php">Posts</a>
    <?php endif; ?>

    <?php if (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['membre', 'admin'], true)): ?>
        <a href="tdor.php">TDoR</a>
    <?php endif; ?>
</nav>

            <!-- Droite : profil / connexion -->
            <div class="header-right">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="profile-menu">
                        <!-- Bouton avatar -->
                        <button class="profile-trigger" type="button"
                                aria-haspopup="true" aria-expanded="false">
                            <img
                                src="<?= htmlspecialchars($_SESSION['avatar_path'] ?? 'IMG/profile_default.png') ?>"
                                class="profile-pic <?= htmlspecialchars($_SESSION['avatar_shape'] ?? 'circle') ?>"
                                alt="Profil"
                            >
                        </button>

                        <!-- Menu déroulant caché par défaut -->
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

    <!-- BOUTON FLOTTANT + MENU (s’affiche quand on scroll) -->
    <button id="floating-menu-btn" aria-expanded="false" aria-haspopup="true">
        ☰ Menu
    </button>

    <div id="floating-menu" role="menu">
        <a href="#accueil" role="menuitem">Accueil</a>
        <a href="#valeurs" role="menuitem">Nos valeurs</a>
        <a href="#contact" role="menuitem">Nous contacter</a>
    </div>

    <!-- CONTENU PRINCIPAL -->
    <main id="accueil">

        <!-- Hero avec logo -->
        <div class="hero-logo">
            <img src="IMG/logo.png" alt="Logo Transfem Era" class="logo-img">
        </div>

        <!-- Section bienvenue -->
        <section class="intro-section">
            <h2>Bienvenue 💖</h2>
            <p>
                Transfem Era est un espace d’entraide, d’écoute et de sororité destiné aux femmes trans
                et aux personnes transféminines. Ici, nos vies ne sont pas une parenthèse&nbsp;: elles sont au centre.
            </p>
            <p>
                On y trouve des moments conviviaux, des groupes de parole, de l’accompagnement concret et des échanges
                sans jugement. L’objectif est simple&nbsp;: ne plus être seules face au monde.
            </p>
        </section>

        <!-- Section valeurs -->
        <section id="valeurs">
            <div class="card">
                <h2>Nos valeurs</h2>
                <p>🌸 Bienveillance &amp; confidentialité</p>
                <p>🌸 Non-mixité transféminine</p>
                <p>🌸 Anti-transphobie, anti-racisme, anti-validisme</p>
                <p>🌸 Entraide, écoute et sororité</p>
            </div>
        </section>

        <!-- Section contact -->
        <section id="contact">
            <div class="card">
                <h2>Nous contacter</h2>
                <p>Pour plus d'informations, rejoindre un groupe ou participer à une rencontre&nbsp;:</p>
                <p><strong>Email :</strong> contact@transfem-era.org</p>
                <p>(Tu pourras modifier l’adresse plus tard.)</p>
            </div>
        </section>

    </main>

    <!-- FOOTER -->
    <footer>
        © <span class="footer-year"><?= date("Y"); ?></span> — Transfem Era
    </footer>

    <!-- Scripts Vanta + logique JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.fog.min.js"></script>
    <script src="SCRIPTS/index.js"></script>


</body>
</html>
