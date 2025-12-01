// JS/vanta-bg.js

document.addEventListener("DOMContentLoaded", () => {
    function initVanta() {
        if (typeof VANTA === "undefined" || !VANTA.FOG) {
            console.warn("Vanta not ready yet, retrying in 300ms...");
            setTimeout(initVanta, 300);
            return;
        }

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

    initVanta();
});
