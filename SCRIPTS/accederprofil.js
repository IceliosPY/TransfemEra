// Menu profil (copié de index.php)
document.addEventListener('DOMContentLoaded', function () {
    const menu = document.querySelector('.profile-menu');
    if (!menu) return;

    const trigger  = menu.querySelector('.profile-trigger');
    const dropdown = menu.querySelector('.profile-dropdown');
    if (!trigger || !dropdown) return;

    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('open');
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
        if (!menu.contains(e.target)) {
            dropdown.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
});