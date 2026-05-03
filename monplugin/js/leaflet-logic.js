/**
 * @file js/leaflet-logic.js
 * @description Logique de la carte interactive Leaflet avec Dashboard CID
 */

const CIDMap = (function() {
    'use strict';

    let map;
    let markersLayer;
    let currentData = { sites: [], meta: {} };

    // --- Selectors ---
    const mapContainerId = 'map-container';
    const loaderId = 'geo-loader';
    const btnFilters = document.querySelectorAll('.geo-filter-btn[data-type]');
    const statusFilter = document.getElementById('geo-status-filter');

    /**
     * Initialisation principale
     */
    function init() {
        if (!document.getElementById(mapContainerId)) return;

        // Init map
        map = L.map(mapContainerId, {
            center: [31.7917, -7.0926], // Centre sur le Maroc
            zoom: 6,
            tap: false, // Prevents zoom on scroll issue for mobile,
            scrollWheelZoom: false, // Désactiver le zoom scroll par défaut sur mobile
            zoomControl: false // Repositionned later
        });
        
        // Re-enable scroll wheel zoom on click/focus if needed or just leave disabled
        map.once('focus', function() { map.scrollWheelZoom.enable(); });

        // Layer Esri World Street Map
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri &mdash; Source: Esri, DeLorme, NAVTEQ, USGS, Intermap, iPC, NRCAN, Esri Japan, METI, Esri China (Hong Kong), Esri (Thailand), TomTom, 2012',
            maxZoom: 18
        }).addTo(map);

        // Controls
        L.control.zoom({ position: 'topright' }).addTo(map);
        L.control.scale({ position: 'bottomleft', metric: true, imperial: false }).addTo(map);

        // MarkerCluster Layer
        markersLayer = L.markerClusterGroup({
            showCoverageOnHover: false,
            maxClusterRadius: 50
        });
        map.addLayer(markersLayer);

        // Events
        initEvents();
        
        // Add Legend
        addLegend();

        // Initial Data Fetch
        fetchData();
    }

    /**
     * Listeners sur les filtres
     */
    function initEvents() {
        btnFilters.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                btnFilters.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                fetchData();
            });
        });

        if (statusFilter) {
            statusFilter.addEventListener('change', fetchData);
        }
    }

    /**
     * Requête AJAX vers ajax_geodata.php
     */
    async function fetchData() {
        showLoader();

        const activeTypeBtn = document.querySelector('.geo-filter-btn.active[data-type]');
        const type = activeTypeBtn ? activeTypeBtn.getAttribute('data-type') : 'all';
        const status = statusFilter ? statusFilter.value : 'all';

        const url = new URL('ajax_geodata.php', window.location.href);
        url.searchParams.append('type', type);
        url.searchParams.append('status', status);

        try {
            const response = await fetch(url.toString(), {
                method: 'GET'
            });

            if (!response.ok) {
                throw new Error('Erreur HTTP ' + response.status);
            }

            const data = await response.json();
            currentData = data;
            renderMarkers(data.sites);
            updateKPIs(data.sites);

        } catch (error) {
            console.error('Erreur de chargement des données géographiques:', error);
            alert('Erreur lors du chargement des données. Veuillez réessayer.');
        } finally {
            hideLoader();
        }
    }

    /**
     * Style de marqueur selon criticité et volume
     * @param {Object} site
     * @returns {Object} options de path
     */
    function getMarkerStyle(site) {
        // Rayon proportionnel au volume de tickets
        const radius = Math.max(12, Math.min(40, site.stats.total_open * 1.5));

        const colors = {
            high   : '#d4521c',   // orange CID — urgent
            medium : '#2e7fba',   // bleu CID — modéré
            low    : '#1a8c6e'    // vert — faible charge
        };

        return {
            radius      : radius,
            fillColor   : colors[site.stats.criticality] || colors['low'],
            color       : '#1b3a6b',
            weight      : 2,
            opacity     : 1,
            fillOpacity : 0.82
        };
    }

    /**
     * Rendu des marqueurs sur la carte
     * @param {Array} sites
     */
    function renderMarkers(sites) {
        markersLayer.clearLayers();

        sites.forEach(site => {
            if (!site.lat || !site.lng) return;

            const style = getMarkerStyle(site);
            const marker = L.circleMarker([site.lat, site.lng], style);

            const colors = {
                high   : '#d4521c',
                medium : '#2e7fba',
                low    : '#1a8c6e'
            };
            const borderColor = colors[site.stats.criticality] || colors['low'];
            
            const critLabel = site.stats.criticality === 'high' ? 'ÉLEVÉE' : 
                             (site.stats.criticality === 'medium' ? 'MOYENNE' : 'FAIBLE');
                             
            const popupHtml = `
                <div class="cid-popup-header">
                    📍 ${site.name}
                </div>
                <div class="cid-popup-body" style="--popup-border-color: ${borderColor};">
                    <div class="cid-popup-stat">
                        <span>🔴 Incidents ouverts :</span>
                        <strong>${site.stats.incidents_open}</strong>
                    </div>
                    <div class="cid-popup-stat">
                        <span>🔵 Demandes ouvertes :</span>
                        <strong>${site.stats.requests_open}</strong>
                    </div>
                    <div class="cid-popup-stat">
                        <span>✅ Total fermés :</span>
                        <strong>${site.stats.total_closed}</strong>
                    </div>
                    <div class="cid-popup-stat" style="margin-top: 8px;">
                        <span>Criticité :</span>
                        <strong style="color: ${borderColor};">● ${critLabel}</strong>
                    </div>
                    <a href="../../../front/ticket.php?is_deleted=0&criteria[0][field]=3&criteria[0][searchtype]=equals&criteria[0][value]=${site.id}" class="cid-popup-link" target="_blank">
                        Voir les tickets &rarr;
                    </a>
                </div>
            `;

            marker.bindPopup(popupHtml, { minWidth: 260 });
            markersLayer.addLayer(marker);
        });

        // Ajuster le zoom s'il y a des sites
        if (sites.length > 0) {
            map.fitBounds(markersLayer.getBounds(), { padding: [50, 50], maxZoom: 12 });
        }
    }

    /**
     * Ajout de la légende
     */
    function addLegend() {
        const legend = L.control({ position: 'bottomright' });

        legend.onAdd = function (map) {
            const div = L.DomUtil.create('div', 'info geo-legend');
            div.innerHTML = `
                <div class="geo-legend-title">Légende</div>
                <div class="geo-legend-item">
                    <span class="geo-legend-dot" style="background:#d4521c; width:12px; height:12px;"></span>
                    <span>Charge élevée (> 15 tickets)</span>
                </div>
                <div class="geo-legend-item">
                    <span class="geo-legend-dot" style="background:#2e7fba; width:12px; height:12px;"></span>
                    <span>Charge moyenne (6 à 15 tickets)</span>
                </div>
                <div class="geo-legend-item">
                    <span class="geo-legend-dot" style="background:#1a8c6e; width:12px; height:12px;"></span>
                    <span>Charge faible (≤ 5 tickets)</span>
                </div>
                <div class="geo-legend-item" style="margin-top: 8px; font-style: italic; color: #5a7499;">
                    ○ Taille du cercle = volume relatif
                </div>
            `;
            return div;
        };

        legend.addTo(map);
    }

    /**
     * Mise à jour des compteurs KPI avec animation count-up
     * @param {Array} sites
     */
    function updateKPIs(sites) {
        let tOpen = 0, tIncidents = 0, tRequests = 0;

        sites.forEach(s => {
            tOpen += s.stats.total_open;
            tIncidents += s.stats.incidents_open;
            tRequests += s.stats.requests_open;
        });

        animateValue("kpi-total", tOpen, 800);
        animateValue("kpi-incidents", tIncidents, 800);
        animateValue("kpi-requests", tRequests, 800);
        animateValue("kpi-sites", sites.length, 800);
    }

    /**
     * Animation de compteur
     */
    function animateValue(id, end, duration) {
        const obj = document.getElementById(id);
        if (!obj) return;
        
        let start = parseInt(obj.innerHTML, 10) || 0;
        if (start === end) {
            obj.innerHTML = end;
            return;
        }

        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            obj.innerHTML = Math.floor(progress * (end - start) + start);
            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                obj.innerHTML = end;
            }
        };
        window.requestAnimationFrame(step);
    }

    function showLoader() {
        const loader = document.getElementById(loaderId);
        if (loader) loader.style.display = 'flex';
    }

    function hideLoader() {
        const loader = document.getElementById(loaderId);
        if (loader) loader.style.display = 'none';
    }

    // Export public
    return {
        init: init
    };

})();

// Initialize map when DOM is loaded
document.addEventListener('DOMContentLoaded', CIDMap.init);
