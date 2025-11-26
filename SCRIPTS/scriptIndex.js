window.addEventListener("load", () => {
    console.log("Page chargée, tentative d'init Vanta…");
  
    if (typeof VANTA === "undefined" || !VANTA.CLOUDS) {
      console.error("VANTA.CLOUDS est introuvable. Vérifie les <script> CDN dans index.php.");
      return;
    }
  
    VANTA.CLOUDS({
      el: "#vanta-bg",
      mouseControls: true,
      touchControls: true,
      gyroControls: false,
      minHeight: 200.00,
      minWidth: 200.00,
  
      // Palette trans pastel
      skyColor: 0xfdfaff,          // ciel clair
      cloudColor: 0xffffff,        // nuages
      cloudShadowColor: 0xf5a9b8,  // ombres rosées
      sunColor: 0x5bcffb,          // bleu trans
      sunGlareColor: 0xfceff5,
      sunlightColor: 0xfdfdff,
  
      speed: 0.40,
      texturePath: ""
    });
  
    console.log("Vanta CLOUDS initialisé.");
  });
  