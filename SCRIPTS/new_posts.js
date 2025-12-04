// SCRIPTS/new_posts.js

document.addEventListener('DOMContentLoaded', () => {
    const textarea = document.getElementById('content');
    const preview  = document.getElementById('preview-content');
    const toolbar  = document.querySelector('.editor-toolbar');

    if (!textarea || !preview) return;

    function escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function renderMarkdown(text) {
        if (!text.trim()) {
            return 'Commence à écrire pour voir l’aperçu ici 💖';
        }

        let html = escapeHtml(text);

        // Titres
        html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
        html = html.replace(/^## (.+)$/gm,  '<h2>$1</h2>');
        html = html.replace(/^# (.+)$/gm,   '<h1>$1</h1>');

        // Listes
        html = html.replace(/^(?:-|\*) (.+)$/gm, '<li>$1</li>');
        html = html.replace(/(?:<li>.*?<\/li>\s*)+/gs, match => `<ul>${match}</ul>`);

        // Gras / italique
        html = html.replace(/\*\*(.+?)\*\*/gs, '<strong>$1</strong>');
        html = html.replace(/\*(.+?)\*/gs, '<em>$1</em>');

        // Centrage
        html = html.replace(/\[center](.+?)\[\/center]/gs, '<span class="md-center">$1</span>');

        // Couleur [color=#xxxxxx]...[/color]
        html = html.replace(
            /\[color=([#0-9a-zA-Z]+)](.+?)\[\/color]/gs,
            '<span style="color:$1">$2</span>'
        );

        // Souligné
        html = html.replace(/\[u](.+?)\[\/u]/gs, '<span class="md-underline">$1</span>');

        // Barré
        html = html.replace(/\[s](.+?)\[\/s]/gs, '<span class="md-strike">$1</span>');

        // Liens [texte](url)
        html = html.replace(
            /\[([^\]]+)]\((https?:\/\/[^\s)]+)\)/gi,
            '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
        );

        // Liens simples
        html = html.replace(
            /(https?:\/\/[^\s<]+)/gi,
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
        );

        // Paragraphes / retours à la ligne
        html = html.replace(/\r\n|\r/g, '\n');
        html = html.replace(/\n{2,}/g, '</p><p>');
        html = '<p>' + html.replace(/\n/g, '<br>') + '</p>';

        return html;
    }

    function updatePreview() {
        preview.innerHTML = renderMarkdown(textarea.value);
    }

    textarea.addEventListener('input', updatePreview);
    updatePreview();

    // Gestion de la toolbar
    if (toolbar) {
        toolbar.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            e.preventDefault();

            const start = textarea.selectionStart;
            const end   = textarea.selectionEnd;
            const value = textarea.value;
            const selected = value.slice(start, end);

            // Préfixes de ligne (H1, H2, H3, liste...)
            if (btn.dataset.md) {
                const prefix = btn.dataset.md;
                const lineStart = value.lastIndexOf('\n', start - 1) + 1;
                textarea.value = value.slice(0, lineStart) + prefix + value.slice(lineStart);
                const newPos = start + prefix.length;
                textarea.selectionStart = textarea.selectionEnd = newPos;
                updatePreview();
                textarea.focus();
                return;
            }

            // Bouton couleur 🎨
            if (btn.dataset.color !== undefined) {
                const picker = document.getElementById('md-color-picker');
                if (!picker) return;
                const color = picker.value || '#e11d48';

                const before = value.slice(0, start);
                const after  = value.slice(end);
                const inner  = selected || 'texte';
                const wrapped = `[color=${color}]` + inner + `[/color]`;

                textarea.value = before + wrapped + after;
                const cursorPos = before.length + wrapped.length;
                textarea.selectionStart = textarea.selectionEnd = cursorPos;
                updatePreview();
                textarea.focus();
                return;
            }

            // Wraps symétriques (*, **, [center], [u], [s]…)
            const wrapStart = btn.dataset.wrapStart || btn.dataset.wrap || '';
            const wrapEnd   = btn.dataset.wrapEnd   || btn.dataset.wrap || '';

            if (wrapStart || wrapEnd) {
                const before = value.slice(0, start);
                const after  = value.slice(end);
                const newText = before + wrapStart + selected + wrapEnd + after;
                textarea.value = newText;
                const cursorPos = start + wrapStart.length + selected.length + wrapEnd.length;
                textarea.selectionStart = textarea.selectionEnd = cursorPos;
                updatePreview();
                textarea.focus();
                return;
            }

            // Insertion de lien
            if (btn.dataset.link !== undefined) {
                const url = prompt('Adresse du lien (https://...)', 'https://');
                if (!url) return;
                const before = value.slice(0, start);
                const after  = value.slice(end);
                const label  = selected || 'lien';
                textarea.value = before + '[' + label + '](' + url + ')' + after;
                const cursorPos = before.length + label.length + url.length + 4;
                textarea.selectionStart = textarea.selectionEnd = cursorPos;
                updatePreview();
                textarea.focus();
                return;
            }
        });
    }
});
