// Accordéons par année
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.tdor-year-header').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const panel = document.getElementById(targetId);
            if (!panel) return;

            const isOpen = panel.style.display === 'block';
            panel.style.display = isOpen ? 'none' : 'block';

            const chev = btn.querySelector('.chevron');
            if (chev) chev.textContent = isOpen ? '▾' : '▴';
        });
    });

    // Camembert catégories (bloc 2, année sélectionnée)
    if (typeof TDOR_CATEGORY_LABELS !== 'undefined' && TDOR_CATEGORY_LABELS.length > 0) {
        const ctx = document.getElementById('tdor-category-chart');
        if (ctx) {
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: TDOR_CATEGORY_LABELS,
                    datasets: [{
                        data: TDOR_CATEGORY_VALUES
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        title: {
                            display: false
                        }
                    }
                }
            });
        }
    }

    // Onglets statistiques totales
    document.querySelectorAll('.tdor-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tdor-tab').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tdor-tab-content').forEach(c => c.classList.remove('active'));

            btn.classList.add('active');
            const target = document.getElementById(btn.dataset.target);
            if (target) target.classList.add('active');

            // Si on ouvre l'onglet "Carte du monde", on (ré)init la carte
            if (btn.dataset.target === 'stats-map') {
                initTdorMap();
            }
        });
    });

    // Onglets des sources TDoR (par année)
    document.querySelectorAll('.tdor-src-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            const yearTabs = btn.parentElement.querySelectorAll('.tdor-src-tab');
            const allContents = document.querySelectorAll('.tdor-sources-content');

            yearTabs.forEach(b => b.classList.remove('active'));
            allContents.forEach(c => c.classList.remove('active'));

            btn.classList.add('active');
            const target = document.getElementById(btn.dataset.target);
            if (target) target.classList.add('active');
        });
    });

    // Si par défaut l'onglet carte était actif (théoriquement non), on initialise quand même
    const defaultMapTab = document.querySelector('.tdor-tab.active[data-target="stats-map"]');
    if (defaultMapTab) {
        initTdorMap();
    }
});

// --- Carte du monde ---
// Dictionnaire pays -> coord approximatives (centre du pays)
const TDOR_COUNTRY_COORDS = {
    'Brazil': {lat: -10, lng: -52},
    'Brasil': {lat: -10, lng: -52},
    'Mexico': {lat: 23, lng: -102},
    'USA': {lat: 38, lng: -97},
    'United States': {lat: 38, lng: -97},
    'United States of America': {lat: 38, lng: -97},
    'United Kingdom': {lat: 55, lng: -3},
    'UK': {lat: 55, lng: -3},
    'France': {lat: 46.5, lng: 2.5},
    'Canada': {lat: 56, lng: -96},
    'Argentina': {lat: -34, lng: -64},
    'Colombia': {lat: 4, lng: -72},
    'Ecuador': {lat: -1.5, lng: -78},
    'Peru': {lat: -9, lng: -75},
    'Chile': {lat: -30, lng: -71},
    'Uruguay': {lat: -32.5, lng: -56},
    'Venezuela': {lat: 7, lng: -66},
    'Italy': {lat: 42.5, lng: 12.5},
    'Spain': {lat: 40, lng: -4},
    'Germany': {lat: 51, lng: 10},
    'Netherlands': {lat: 52.5, lng: 5.7},
    'Belgium': {lat: 50.8, lng: 4.3},
    'Switzerland': {lat: 46.8, lng: 8.2},
    'Portugal': {lat: 39.5, lng: -8},
    'Pakistan': {lat: 30, lng: 70},
    'India': {lat: 22.5, lng: 79},
    'Bangladesh': {lat: 24, lng: 90},
    'Philippines': {lat: 13, lng: 122},
    'Thailand': {lat: 15, lng: 101},
    'Myanmar': {lat: 21, lng: 96},
    'Turkey': {lat: 39, lng: 35},
    'Iran': {lat: 32, lng: 53},
    'Iraq': {lat: 33, lng: 44},
    'Russia': {lat: 60, lng: 90},
    'Ukraine': {lat: 49, lng: 32},
    'Costa Rica': {lat: 10, lng: -84},
    'Guatemala': {lat: 15.5, lng: -90.25},
    'Honduras': {lat: 15, lng: -86.5},
    'Cuba': {lat: 21.5, lng: -80},
    'Bolivia': {lat: -17, lng: -65},
    'Dominican Republic': {lat: 19, lng: -70.7},
    'Puerto Rico': {lat: 18.2, lng: -66.5},
    'Fiji': {lat: -17.8, lng: 178.1},
    'Ivory Coast': {lat: 7.5, lng: -5.5},
    "Côte d'Ivoire": {lat: 7.5, lng: -5.5}
};

let tdorMap = null;
let tdorMapInitialized = false;

function initTdorMap() {
    if (tdorMapInitialized) return;
    const mapContainer = document.getElementById('tdor-map');
    if (!mapContainer || typeof TDOR_MAP_POINTS === 'undefined') return;

    tdorMapInitialized = true;

    // Centre global approximatif
    tdorMap = L.map('tdor-map').setView([20, 0], 2);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(tdorMap);

    const bounds = [];

    TDOR_MAP_POINTS.forEach(p => {
        const country = (p.country || '').trim();
        if (!country) return;

        let coords = null;
        if (TDOR_COUNTRY_COORDS[country]) {
            coords = TDOR_COUNTRY_COORDS[country];
        } else {
            // si pays non trouvé, on ignore
            return;
        }

        let lat = coords.lat;
        let lng = coords.lng;

        // Léger "jitter" aléatoire pour éviter la pile de marqueurs
        const jitter = 1.0; // en degrés
        lat += (Math.random() - 0.5) * jitter;
        lng += (Math.random() - 0.5) * jitter;

        const marker = L.marker([lat, lng]).addTo(tdorMap);
        const popupHtml = `
            <div style="font-size:0.85rem; max-width:220px;">
                <strong>${p.name ? p.name : '(Nom non rapporté)'}</strong><br>
                ${p.date ? p.date + '<br>' : ''}
                ${p.location ? p.location + '<br>' : ''}
                ${p.country ? '<em>' + p.country + '</em><br>' : ''}
                ${p.category ? '<span>Catégorie : ' + p.category + '</span><br>' : ''}
                ${p.cause ? '<span>Cause : ' + p.cause + '</span>' : ''}
            </div>
        `;
        marker.bindPopup(popupHtml);
        bounds.push([lat, lng]);
    });

    if (bounds.length > 0) {
        tdorMap.fitBounds(bounds, { padding: [20, 20] });
    }
}

