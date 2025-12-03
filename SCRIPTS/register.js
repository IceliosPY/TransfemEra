document.getElementById("avatar").addEventListener("change", function() {
    const file = this.files[0];
    if (!file) return;

    const preview = document.getElementById("avatar-preview");
    preview.src = URL.createObjectURL(file);
});
(function() {
    const input = document.getElementById('avatar');
    const preview = document.getElementById('avatar-preview');
    if (input && preview) {
        input.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;
            preview.src = URL.createObjectURL(file);
        });
    }
})();
document.getElementById('avatar').addEventListener('change', function () {
    if (this.files && this.files[0]) {
        const preview = document.getElementById('avatar-preview');
        preview.src = URL.createObjectURL(this.files[0]);
    }
});

(function() {
    const input = document.getElementById('avatar');
    const preview = document.getElementById('avatar-preview');
    if (input && preview) {
        input.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;
            preview.src = URL.createObjectURL(file);
        });
    }
})();
