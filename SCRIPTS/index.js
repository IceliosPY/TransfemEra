
// --- MENU PROFIL (avatar) ---
document.addEventListener('DOMContentLoaded', function () {
    const menu     = document.querySelector('.profile-menu');
    if (!menu) return;

    const trigger  = menu.querySelector('.profile-trigger');
    const dropdown = menu.querySelector('.profile-dropdown');

    // sécurité
    if (!trigger || !dropdown) return;

    // Clic sur l'avatar -> toggle du menu
    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('open');
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Clique n'importe où ailleurs -> ferme le menu
    document.addEventListener('click', function (e) {
        if (!menu.contains(e.target)) {
            dropdown.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
});

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