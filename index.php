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
                <a href="#accueil">Accueil</a>
                <a href="#valeurs">Nos valeurs</a>
                <a href="#contact">Nous contacter</a>
            </nav>

            <!-- Droite : connexion / déconnexion -->
            <div class="header-right">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="login-btn">Se déconnecter</a>
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

    <!-- Vanta FOG -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r121/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.fog.min.js"></script>

    <script>
        // --- VANTA FOG ---
        window.addEventListener("load", function () {
            try {
                if (typeof VANTA !== "undefined" && VANTA.FOG) {
                    VANTA.FOG({
                        el: "#vanta-bg",
                        mouseControls: true,
                        touchControls: true,
                        gyroControls: false,
                        minHeight: 200.00,
                        minWidth: 200.00,
                        highlightColor: 0x5bcffb,
                        midtoneColor:   0xf5a9b8,
                        lowlightColor:  0xffffff,
                        baseColor:      0xfdfaff,
                        blurFactor: 0.45,
                        speed: 0.70,
                        zoom: 1.10
                    });
                }
            } catch (e) {
                console.error(e);
            }
        });

        // --- LOGIQUE : barre en haut + bouton flottant en scroll ---
        (function () {
            const body  = document.body;
            const menu  = document.getElementById("floating-menu");
            const btn   = document.getElementById("floating-menu-btn");

            if (!body || !menu || !btn) return;

            function onScroll() {
                const scrolled = window.scrollY > 80;
                if (scrolled) {
                    body.classList.add("header-compact");
                } else {
                    body.classList.remove("header-compact");
                    menu.style.display = "none";
                    btn.setAttribute("aria-expanded", "false");
                }
            }

            onScroll();
            window.addEventListener("scroll", onScroll);

            btn.addEventListener("click", function (e) {
                e.stopPropagation();
                const isOpen = menu.style.display === "block";
                if (isOpen) {
                    menu.style.display = "none";
                    btn.setAttribute("aria-expanded", "false");
                } else {
                    menu.style.display = "block";
                    btn.setAttribute("aria-expanded", "true");
                }
            });

            document.addEventListener("click", function (e) {
                if (!menu.contains(e.target) && e.target !== btn) {
                    menu.style.display = "none";
                    btn.setAttribute("aria-expanded", "false");
                }
            });

            menu.querySelectorAll("a").forEach(link => {
                link.addEventListener("click", () => {
                    menu.style.display = "none";
                    btn.setAttribute("aria-expanded", "false");
                });
            });
        })();
    </script>

</body>
</html>
