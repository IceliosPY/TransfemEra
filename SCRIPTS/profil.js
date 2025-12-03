// SCRIPTS/profil.js
document.addEventListener('DOMContentLoaded', () => {
    /* -----------------------------------
       Preview de l’avatar choisi
    ----------------------------------- */
    const avatarInput   = document.getElementById('avatar-input');
    const avatarPreview = document.getElementById('avatar-preview');

    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = e => {
                avatarPreview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    /* -----------------------------------
       Popup "Gestion des comptes"
    ----------------------------------- */
    const openModalBtn = document.getElementById('open-users-modal');
    const backdrop     = document.getElementById('users-modal-backdrop');
    const closeModalBtn= document.getElementById('close-users-modal');

    function openModal() {
        if (backdrop) backdrop.classList.add('open');
    }

    function closeModal() {
        if (backdrop) backdrop.classList.remove('open');
    }

    if (openModalBtn && backdrop) {
        openModalBtn.addEventListener('click', openModal);
    }

    if (closeModalBtn && backdrop) {
        closeModalBtn.addEventListener('click', closeModal);
    }

    // clic sur le fond extérieur → fermeture
    if (backdrop) {
        backdrop.addEventListener('click', e => {
            if (e.target === backdrop) {
                closeModal();
            }
        });
    }

    /* -----------------------------------
       Onglets dans la popup
       (Comptes / Validation des inscriptions)
    ----------------------------------- */
    const tabs    = document.querySelectorAll('.admin-tab');
    const panels  = document.querySelectorAll('.admin-tab-panel');

    function activateTab(name) {
        tabs.forEach(tab => {
            const isActive = tab.dataset.tab === name;
            tab.classList.toggle('active', isActive);
        });

        panels.forEach(panel => {
            const isActive = panel.dataset.tabPanel === name;
            panel.classList.toggle('active', isActive);
        });
    }

    if (tabs.length && panels.length) {
        // onglet par défaut : "Comptes"
        const defaultTab = tabs[0].dataset.tab;
        activateTab(defaultTab);

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const name = tab.dataset.tab;
                if (!name) return;
                activateTab(name);
            });
        });
    }

    /* -----------------------------------
       Boutons "Valider" (passer en membre)
       => appellent admin_validate.php
    ----------------------------------- */
    const validateButtons = document.querySelectorAll('.validate-user-btn');

    validateButtons.forEach(btn => {
        btn.addEventListener('click', async () => {
            const userId = btn.dataset.userId;
            if (!userId) return;

            const ok = confirm(
                "Valider ce compte et le passer au rôle 'membre' ?"
            );
            if (!ok) return;

            try {
                const response = await fetch('admin_validate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'user_id=' + encodeURIComponent(userId)
                });

                const data = await response.json();

                if (data && data.success) {
                    // On retire la ligne de la table
                    const row = btn.closest('tr');
                    if (row) row.remove();

                    // Optionnel : mettre à jour le compteur dans l’onglet
                    const pendingTab = document.querySelector(
                        '.admin-tab[data-tab="pending"]'
                    );
                    if (pendingTab && data.pendingCount !== undefined) {
                        pendingTab.textContent =
                            'Validation des inscriptions (' +
                            data.pendingCount +
                            ')';
                    }
                } else {
                    alert(
                        (data && data.message) ||
                        "Impossible de valider ce compte."
                    );
                }
            } catch (e) {
                console.error(e);
                alert("Erreur technique lors de la validation.");
            }
        });
    });
});
