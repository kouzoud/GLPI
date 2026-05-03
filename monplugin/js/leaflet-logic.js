/**
 * Logique pour le dashboard géographique Leaflet
 * Version: 3.0 (Sans cluster, design CID pur, tooltips permanents)
 */

document.addEventListener('DOMContentLoaded', function() {
    
    const mapContainer = document.getElementById('map-container');
    if (!mapContainer) return;
    
    const loader = document.getElementById('geo-loader');
    
    // Initialisation de la carte
    const map = L.map('map-container', { zoomControl: true }).setView([31.7917, -7.0926], 6);
    
    // Tuiles Esri World Street Map (Aucune dépendance à OpenStreetMap)
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, DeLorme, NAVTEQ, USGS, Intermap, iPC, NRCAN, Esri Japan, METI, Esri China (Hong Kong), Esri (Thailand), TomTom, 2012',
        maxZoom: 19
    }).addTo(map);

    // Contrôle personnalisé "Recentrer"
    const centerControl = L.control({position: 'topleft'});
    centerControl.onAdd = function() {
        const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
        div.innerHTML = '<a href="#" title="Recentrer la carte sur tous les sites" role="button" aria-label="Recentrer" style="font-size: 18px; line-height: 28px; text-align: center; text-decoration: none;">⌂</a>';
        div.onclick = function(e) {
            e.preventDefault();
            if (activeMarkersGroup) {
                map.fitBounds(activeMarkersGroup.getBounds().pad(0.8));
            }
        };
        return div;
    };
    centerControl.addTo(map);

    let activeMarkersGroup = null;
    let markersList = [];
    
    let currentFilters = {
        type: 'all',
        status: 'all'
    };

    /**
     * Crée une icône div personnalisée CID pour un site
     * @param {Object} site Données du site
     * @returns {L.divIcon} L'icône Leaflet configurée
     */
    function createSiteMarker(site) {
        const color = site.stats.incidents_open > 5
            ? '#d4521c'   // orange CID — charge élevée
            : site.stats.incidents_open > 0
            ? '#2e7fba'   // bleu CID — charge modérée
            : '#1a8c6e';  // vert — site calme

        const size = Math.max(38, Math.min(65, (site.stats.total_open * 2) + 30));

        const icon = L.divIcon({
            className: '',
            html: `
            <div class="cid-marker" style="
                width:${size}px;
                height:${size}px;
                background:${color};
                border-radius:50%;
                border:3px solid rgba(255,255,255,0.9);
                box-shadow:0 4px 20px rgba(0,0,0,0.3);
                display:flex;
                flex-direction:column;
                align-items:center;
                justify-content:center;
                cursor:pointer;
                transition:transform 0.2s ease;
                position:relative;
            ">
                ${site.stats.incidents_open > 0 ? `
                <div class="pulse-ring" style="
                    position:absolute;inset:-6px;
                    border-radius:50%;
                    border:3px solid ${color};
                    animation:pulse-cid 2s ease-out infinite;
                    opacity:0.5;
                "></div>` : ''}
                <span style="
                    color:white;
                    font-weight:800;
                    font-size:${size > 65 ? '18' : '14'}px;
                    line-height:1;
                ">${site.stats.total_open}</span>
                <span style="
                    color:rgba(255,255,255,0.85);
                    font-size:9px;
                    font-weight:600;
                    margin-top:2px;
                    text-transform:uppercase;
                    letter-spacing:0.5px;
                ">tickets</span>
            </div>`,
            iconSize: [size, size],
            iconAnchor: [size/2, size/2],
            popupAnchor: [0, -size/2],
            // On sauvegarde la taille pour offset le tooltip permanent dynamiquement
            sizeProp: size 
        });
        return icon;
    }

    /**
     * Génère le HTML brut pour le popup au clic
     * @param {Object} site Données du site
     * @returns {string} Le rendu HTML du popup
     */
    function createPopupContent(site) {
        // Calcul de la proportion par rapport au total des tickets ouverts sur ce site
        const totalOpen = site.stats.total_open > 0 ? site.stats.total_open : 1;
        const incPct = (site.stats.incidents_open / totalOpen) * 100;
        const reqPct = (site.stats.requests_open / totalOpen) * 100;

        return `
        <div style="min-width:220px; font-family:Inter,sans-serif; border-radius:12px; overflow:hidden;">
            <!-- Header -->
            <div style="background:linear-gradient(135deg,#1b3a6b,#2e7fba); padding:12px 16px; color:white;">
                <div style="font-weight:800;font-size:14px">📍 ${site.name}</div>
                <div style="font-size:11px;opacity:0.8;margin-top:2px">Mise à jour : à l'instant</div>
            </div>

            <!-- Stats -->
            <div style="padding:12px 16px;background:#fff">

                <!-- Barre incidents -->
                <div style="margin-bottom:10px">
                    <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:600; color:#1b3a6b; margin-bottom:4px;">
                        <span>🔴 Incidents ouverts</span>
                        <span style="color:#d4521c">${site.stats.incidents_open}</span>
                    </div>
                    <div style="background:#f1f5f9; border-radius:4px; height:6px; overflow:hidden;">
                        <div style="width:${incPct}%; height:100%; background:#d4521c; border-radius:4px; transition:width 0.5s ease;"></div>
                    </div>
                </div>

                <!-- Barre demandes -->
                <div style="margin-bottom:10px">
                    <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:600; color:#1b3a6b; margin-bottom:4px;">
                        <span>🔵 Demandes ouvertes</span>
                        <span style="color:#2e7fba">${site.stats.requests_open}</span>
                    </div>
                    <div style="background:#f1f5f9; border-radius:4px; height:6px; overflow:hidden;">
                        <div style="width:${reqPct}%; height:100%; background:#2e7fba; border-radius:4px; transition:width 0.5s ease;"></div>
                    </div>
                </div>

                <!-- Total fermés -->
                <div style="display:flex; justify-content:space-between; font-size:12px; color:#5a7499; padding-top:8px; border-top:1px solid #f1f5f9;">
                    <span>✅ Tickets fermés</span>
                    <span style="font-weight:700;color:#1a8c6e">${site.stats.total_closed}</span>
                </div>
            </div>

            <!-- Footer lien -->
            <div style="padding:8px 16px; background:#f8fafc; border-top:1px solid #eef3fa;">
                <a href="/front/ticket.php?is_deleted=0&criteria[0][field]=83&criteria[0][searchtype]=equals&criteria[0][value]=${site.id}&search=Search"
                   style="display:block; text-align:center; color:#2e7fba; font-size:12px; font-weight:700; text-decoration:none; padding:6px; border-radius:6px; background:#eff6ff; transition:background 0.2s;" target="_blank">
                    🎫 Voir les tickets de ce site →
                </a>
            </div>
        </div>`;
    }

    /**
     * Rafraîchit les données depuis l'AJAX
     * @param {Object} filters Tableau de filtres pour tickets
     */
    function refreshMapData(filters) {
        if (loader) loader.classList.add('active');
        
        if (activeMarkersGroup) {
            map.removeLayer(activeMarkersGroup);
            activeMarkersGroup = null;
        }
        markersList = [];

        let url = '../front/ajax_stats.php';
        let params = new URLSearchParams();
        if (filters.type !== 'all') params.append('type', filters.type);
        if (filters.status !== 'all') params.append('status', filters.status);
        
        if (params.toString() !== "") {
            url += '?' + params.toString();
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                const sites = data.sites;
                let total_all = 0, inc_all = 0, req_all = 0, active_sites = sites.length;
                
                const metaInfo = document.getElementById('geo-meta-info');
                if (metaInfo) {
                    const date = new Date();
                    metaInfo.innerText = "Mise à jour : " + date.toLocaleTimeString();
                }

                if (active_sites === 0) {
                    if (loader) loader.classList.remove('active');
                    return;
                }

                sites.forEach(site => {
                    total_all += site.stats.total_open;
                    inc_all += site.stats.incidents_open;
                    req_all += site.stats.requests_open;

                    const icon = createSiteMarker(site);
                    const marker = L.marker([site.lat, site.lng], {
                        icon: icon,
                        siteData: site // pour accès extérieur
                    });
                    
                    // Tooltip permanent ("Toujours visible") en dessous
                    marker.bindTooltip(`
                        <div style="background:#1b3a6b; color:white; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600; white-space:nowrap; box-shadow:0 2px 8px rgba(0,0,0,0.2);">
                            ${site.name}
                        </div>
                    `, {
                        permanent: true,
                        direction: 'bottom',
                        offset: [0, (icon.options.sizeProp / 2) + 5],
                        className: 'cid-tooltip-permanent',
                        opacity: 1
                    });
                    
                    // Popup enrichi au clic
                    marker.bindPopup(createPopupContent(site), {
                        maxWidth: 260,
                        className: 'cid-popup-clean'
                    });
                    
                    markersList.push(marker);
                });

                // Update UI Counters (Badges dans les boutons)
                const bAll = document.getElementById('badge-all');
                const b1 = document.getElementById('badge-1');
                const b2 = document.getElementById('badge-2');
                
                if (filters.type === 'all') {
                    if (bAll) bAll.innerText = total_all;
                    if (b1) b1.innerText = inc_all;
                    if (b2) b2.innerText = req_all;
                } else if (filters.type === '1') {
                    // Si on filtre par incident, on ne met à jour QUE le compteur d'incidents
                    if (b1) b1.innerText = inc_all;
                } else if (filters.type === '2') {
                    // Si on filtre par demande, on ne met à jour QUE le compteur de demandes
                    if (b2) b2.innerText = req_all;
                }

                // Update KPIs géants
                const k1 = document.getElementById('kpi-total');
                const k2 = document.getElementById('kpi-incidents');
                const k3 = document.getElementById('kpi-requests');
                const k4 = document.getElementById('kpi-sites');
                
                if (filters.type === 'all') {
                    if (k1) k1.innerText = total_all;
                    if (k2) k2.innerText = inc_all;
                    if (k3) k3.innerText = req_all;
                } else if (filters.type === '1') {
                    if (k2) k2.innerText = inc_all;
                } else if (filters.type === '2') {
                    if (k3) k3.innerText = req_all;
                }
                if (k4) k4.innerText = active_sites;

                // Instanciation de MarkerCluster au lieu du featureGroup classique
                // maxClusterRadius réduit à 35px (très serré) pour laisser Rabat/Techoloplis séparés le plus possible
                activeMarkersGroup = L.markerClusterGroup({
                    maxClusterRadius: 40,
                    spiderfyOnMaxZoom: true,
                    showCoverageOnHover: false,
                    zoomToBoundsOnClick: true,
                    disableClusteringAtZoom: 14,
                    iconCreateFunction: function(cluster) {
                        const childCount = cluster.getChildCount();
                        
                        // Si l'utilisateur a explicitement demandé de mettre en évidence 3 ou plus :
                        let bgColor = childCount >= 3 ? '#d4521c' : '#2e7fba';
                        let size = childCount >= 3 ? 60 : 45;

                        return L.divIcon({
                            html: `
                            <div style="
                                width:${size}px;
                                height:${size}px;
                                background:${bgColor};
                                border-radius:50%;
                                border:3px solid rgba(255,255,255,0.9);
                                box-shadow:0 4px 20px rgba(0,0,0,0.3);
                                display:flex;
                                flex-direction:column;
                                align-items:center;
                                justify-content:center;
                                color:white;
                                font-family:Inter, sans-serif;
                            ">
                                <span style="font-weight:800;font-size:${childCount >= 3 ? '18' : '15'}px;">${childCount}</span>
                                <span style="font-size:9px;font-weight:600;margin-top:2px;text-transform:uppercase;">Sites</span>
                            </div>`,
                            className: 'custom-cluster-icon',
                            iconSize: [size, size],
                            iconAnchor: [size/2, size/2]
                        });
                    }
                });
                
                // Ajout des marqueurs au cluster
                activeMarkersGroup.addLayers(markersList);
                map.addLayer(activeMarkersGroup);

                // Centrage auto sur tous les sites avec un pad de 0.15 (zoom suffisant sur Rabat/Technopolis)
                map.fitBounds(activeMarkersGroup.getBounds().pad(0.15));

                // Au premier chargement, ouvrir le plus critique
                setTimeout(() => {
                    if (markersList.length > 0) {
                        const busiest = markersList.reduce((a, b) =>
                            a.options.siteData.stats.total_open > b.options.siteData.stats.total_open ? a : b
                        );
                        busiest.openPopup();
                    }
                }, 800);

                if (loader) loader.classList.remove('active');
            })
            .catch(err => {
                console.error("Erreur AJAX Leaflet:", err);
                if (loader) loader.classList.remove('active');
            });
    }

    const buttons = document.querySelectorAll('.geo-filter-btn');
    buttons.forEach(btn => {
        if (btn.tagName === 'BUTTON') {
            btn.addEventListener('click', (e) => {
                buttons.forEach(b => { if(b.tagName === 'BUTTON') b.classList.remove('active')});
                e.currentTarget.classList.add('active');
                currentFilters.type = e.currentTarget.getAttribute('data-type');
                refreshMapData(currentFilters);
            });
        } else if (btn.tagName === 'SELECT') {
            btn.addEventListener('change', (e) => {
                currentFilters.status = e.target.value;
                refreshMapData(currentFilters);
            });
        }
    });

    refreshMapData(currentFilters);
    setInterval(() => refreshMapData(currentFilters), 300000);
});

