/**
 * geodash-vue.js v3.0 — GeoDashboard CID
 * Vue 3 Composition API + h() (zero template compiler)
 * Features: split view, dark map, SVG pins, KPI band, ticker,
 *           search, side panel, day/night toggle, sparklines, export PDF
 */
(function () {
    'use strict';
    if (typeof Vue === 'undefined') { console.error('[GD] Vue manquant'); return; }
    const { createApp, ref, computed, onMounted, onUnmounted, watch, h } = Vue;

    /* ── Config ── */
    const REFRESH = 60000;
    const CENTER  = [31.7917, -7.0926];
    const TILES   = {
        dark : 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        light: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
    };
    const CRIT = {
        high  : { bg: '#d4521c', lbl: 'ÉLEVÉE'  },
        medium: { bg: '#2e7fba', lbl: 'MOYENNE' },
        low   : { bg: '#1a8c6e', lbl: 'FAIBLE'  },
    };
    const STATUTS = [
        { v: 'all', t: 'Tous les statuts' }, { v: '1', t: 'Nouveau' },
        { v: '2',   t: 'En cours (Attribué)' }, { v: '3', t: 'En cours (Planifié)' },
        { v: '4',   t: 'En attente' }, { v: '5', t: 'Résolu' }, { v: '6', t: 'Clos' },
    ];

    /* ── Helpers Leaflet (HTML strings) ── */
    function spark(data, color) {
        const max = Math.max(...data, 1), W = 100, H = 20;
        const pts = data.map((v, i) => ((i / (data.length - 1)) * W).toFixed(1) + ',' + (H - 2 - (v / max) * (H - 4)).toFixed(1)).join(' ');
        const lx = W, ly = (H - 2 - (data[data.length - 1] / max) * (H - 4)).toFixed(1);
        return '<svg width="' + W + '" height="' + H + '" viewBox="0 0 ' + W + ' ' + H + '" style="overflow:visible"><polyline points="' + pts + '" fill="none" stroke="' + color + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="' + lx + '" cy="' + ly + '" r="3" fill="' + color + '"/></svg>';
    }
    function mockSpark(base) {
        const d = Array.from({ length: 7 }, () => Math.max(0, Math.round(base * (0.5 + Math.random() * 0.8))));
        d[6] = base; return d;
    }
    function popupHtml(site) {
        const c = CRIT[site.stats.criticality] || CRIT.low;
        const tot = Math.max(site.stats.total_open, 1);
        const ip = Math.round(site.stats.incidents_open / tot * 100);
        const rp = Math.round(site.stats.requests_open / tot * 100);
        return '<div style="min-width:240px;font-family:Inter,sans-serif;border-radius:12px;overflow:hidden">'
            + '<div style="background:linear-gradient(135deg,#1b3a6b,#2e7fba);padding:12px 16px;color:#fff"><div style="font-weight:800;font-size:14px">📍 ' + site.name + '</div><div style="font-size:11px;opacity:.8;margin-top:2px">Criticité : <span style="background:' + c.bg + ';padding:2px 7px;border-radius:4px;font-weight:700">' + c.lbl + '</span></div></div>'
            + '<div style="padding:12px 16px;background:#fff">'
            + '<div style="margin-bottom:8px"><div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;color:#1b3a6b;margin-bottom:3px"><span>🔴 Incidents</span><span style="color:#d4521c">' + site.stats.incidents_open + '</span></div><div style="background:#f1f5f9;border-radius:4px;height:5px;overflow:hidden"><div style="width:' + ip + '%;height:100%;background:#d4521c;border-radius:4px"></div></div><div style="margin-top:4px">' + spark(mockSpark(site.stats.incidents_open), '#d4521c') + '</div></div>'
            + '<div style="margin-bottom:8px"><div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;color:#1b3a6b;margin-bottom:3px"><span>🔵 Demandes</span><span style="color:#2e7fba">' + site.stats.requests_open + '</span></div><div style="background:#f1f5f9;border-radius:4px;height:5px;overflow:hidden"><div style="width:' + rp + '%;height:100%;background:#2e7fba;border-radius:4px"></div></div><div style="margin-top:4px">' + spark(mockSpark(site.stats.requests_open), '#2e7fba') + '</div></div>'
            + '<div style="display:flex;justify-content:space-between;font-size:12px;color:#5a7499;padding-top:6px;border-top:1px solid #f1f5f9"><span>✅ Fermés</span><span style="font-weight:700;color:#1a8c6e">' + site.stats.total_closed + '</span></div>'
            + '</div><div style="padding:8px 16px;background:#f8fafc;border-top:1px solid #eef3fa"><a href="/front/ticket.php?is_deleted=0&criteria[0][field]=83&criteria[0][searchtype]=equals&criteria[0][value]=' + site.id + '&search=Search" style="display:block;text-align:center;color:#2e7fba;font-size:12px;font-weight:700;text-decoration:none;padding:5px;border-radius:6px;background:#eff6ff" target="_blank">🎫 Voir les tickets →</a></div></div>';
    }
    function pinIcon(site) {
        const c = CRIT[site.stats.criticality] || CRIT.low;
        return L.divIcon({
            className: '',
            html: '<div class="cid-pin-wrapper"><svg width="40" height="50" viewBox="0 0 40 50"><path d="M20 0C9 0 0 9 0 20C0 35 20 50 20 50C20 50 40 35 40 20C40 9 31 0 20 0Z" fill="' + c.bg + '" stroke="white" stroke-width="2.5"/><text x="20" y="25" text-anchor="middle" fill="white" font-size="13" font-weight="800" font-family="Inter">' + site.stats.total_open + '</text></svg><div class="pin-shadow"></div></div>',
            iconSize: [40, 50], iconAnchor: [20, 50], popupAnchor: [0, -52], _size: 50,
        });
    }

    /* ── Composant ── */
    const GeoDashboard = {
        setup() {
            const mountEl   = document.getElementById('geo-dashboard-app');
            const ajaxUrl    = mountEl ? mountEl.dataset.ajaxUrl    : '';
            const ticketsUrl = mountEl ? mountEl.dataset.ticketsUrl : '';
            const sites = ref([]), loading = ref(true), hasError = ref(false);
            const recentTickets = ref([]);
            const activeType = ref('all'), activeStatus = ref('all');
            const metaInfo = ref('Chargement…'), tileMode = ref('dark');
            const searchQuery = ref(''), isExporting = ref(false);

            let map = null, tileLayers = {}, markersGroup = null, markersList = [], timer = null;

            const kpiT = computed(() => sites.value.reduce((a, s) => a + (s.stats.total_open || 0), 0));
            const kpiI = computed(() => sites.value.reduce((a, s) => a + (s.stats.incidents_open || 0), 0));
            const kpiR = computed(() => sites.value.reduce((a, s) => a + (s.stats.requests_open || 0), 0));
            const kpiS = computed(() => sites.value.length);
            const sorted = computed(() => [...sites.value].sort((a, b) => b.stats.total_open - a.stats.total_open));
            const filterBtns = computed(() => [
                { type: 'all', label: 'Tous',      icon: 'ti ti-layout-list',    count: kpiT.value },
                { type: '1',   label: 'Incidents', icon: 'ti ti-alert-triangle', count: kpiI.value },
                { type: '2',   label: 'Demandes',  icon: 'ti ti-headset',        count: kpiR.value },
            ]);
            /**
             * criticalSites — Sites dont le taux de tickets non résolus
             * dépasse la moyenne globale de tous les sites.
             * Formule : taux(site) = total_open / (total_open + total_closed) × 100
             * Condition : taux(site) > moyenne(tous les taux)
             */
            const criticalSites = computed(() => {
                if (!sites.value.length) return [];
                // 1. Calcul du taux par site
                const withRate = sites.value.map(s => {
                    const total = (s.stats.total_open || 0) + (s.stats.total_closed || 0);
                    const rate  = total > 0 ? (s.stats.total_open / total * 100) : 0;
                    return { ...s, openRate: rate, totalDeclared: total };
                }).filter(s => s.totalDeclared > 0);
                if (!withRate.length) return [];
                // 2. Moyenne globale
                const avg = withRate.reduce((sum, s) => sum + s.openRate, 0) / withRate.length;
                // 3. Sites au-dessus de la moyenne
                return withRate
                    .filter(s => s.openRate > avg)
                    .sort((a, b) => b.openRate - a.openRate)
                    .map(s => ({
                        ...s,
                        label   : s.openRate >= avg + 20 ? '🔴 CRITIQUE' : '🟡 ÉLEVÉ',
                        color   : s.openRate >= avg + 20 ? '#c0392b'    : '#e67e22',
                        rateStr : s.openRate.toFixed(0) + '%',
                        avgStr  : avg.toFixed(0) + '%',
                    }));
            });
            /* panelTickets — tous les tickets ouverts pour le panel latéral */
            const panelTickets = computed(() => recentTickets.value.slice(0, 10));

            async function loadData() {
                loading.value = true; hasError.value = false;
                try {
                    const p = new URLSearchParams();
                    if (activeType.value !== 'all') p.set('type', activeType.value);
                    if (activeStatus.value !== 'all') p.set('status', activeStatus.value);
                    const res = await fetch(ajaxUrl + (p.toString() ? '?' + p : ''), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const json = await res.json();
                    sites.value    = json.sites || [];
                    metaInfo.value = json.meta.total_sites + ' site(s) · ' + new Date().toLocaleTimeString('fr-FR');
                    updateMap();
                    fetchRecentTickets();
                } catch (e) { hasError.value = true; metaInfo.value = 'Erreur: ' + e.message; console.error('[GD]', e); }
                finally { loading.value = false; }
            }
            async function fetchRecentTickets() {
                if (!ticketsUrl) return;
                try {
                    const res = await fetch(ticketsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!res.ok) return;
                    const json = await res.json();
                    recentTickets.value = json.tickets || [];
                } catch (e) { console.warn('[GD] recent tickets:', e); }
            }


            function setType(t) { activeType.value = t; loadData(); }
            watch(activeStatus, loadData);
            watch(searchQuery, q => {
                const lq = q.toLowerCase();
                markersList.forEach(m => { const ok = !q || m.options.siteData.name.toLowerCase().includes(lq); m.setOpacity(ok ? 1 : 0.1); m.setZIndexOffset(ok ? 1000 : -1000); });
            });
            function toggleTile() {
                if (!map) return;
                map.removeLayer(tileLayers[tileMode.value]);
                tileMode.value = tileMode.value === 'dark' ? 'light' : 'dark';
                tileLayers[tileMode.value].addTo(map);
            }
            function exportPDF() {
                if (typeof html2canvas === 'undefined' || typeof window.jspdf === 'undefined') { alert('Bibliothèques PDF non disponibles'); return; }
                isExporting.value = true;
                const { jsPDF } = window.jspdf;
                html2canvas(document.getElementById('geo-dashboard-container'), { scale: 1.5, useCORS: true })
                    .then(canvas => { const pdf = new jsPDF('landscape', 'mm', 'a4'); const pw = pdf.internal.pageSize.getWidth(), ph = pdf.internal.pageSize.getHeight(); pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 5, 5, pw - 10, ph - 10); pdf.save('rapport-cid-' + new Date().toISOString().slice(0, 10) + '.pdf'); })
                    .finally(() => { isExporting.value = false; });
            }
            function focusSite(site) {
                if (!map || !site.lat || !site.lng) return;
                map.setView([site.lat, site.lng], 12, { animate: true });
                const m = markersList.find(m => m.options.siteData.id === site.id);
                if (m) setTimeout(() => m.openPopup(), 600);
            }
            function initMap() {
                tileLayers.dark  = L.tileLayer(TILES.dark,  { attribution: '© CARTO', maxZoom: 19 });
                tileLayers.light = L.tileLayer(TILES.light, { attribution: '© CARTO', maxZoom: 19 });
                map = L.map('map-container', { zoomControl: true }).setView(CENTER, 6);
                tileLayers.dark.addTo(map);
                const cc = L.control({ position: 'topleft' });
                cc.onAdd = function () { const d = L.DomUtil.create('div', 'leaflet-bar leaflet-control'); d.innerHTML = '<a href="#" style="font-size:18px;line-height:28px;text-align:center;text-decoration:none;display:block;">⌂</a>'; d.onclick = function (e) { e.preventDefault(); if (markersGroup) map.fitBounds(markersGroup.getBounds().pad(0.15)); }; return d; };
                cc.addTo(map);
            }
            function updateMap() {
                if (!map) return;
                if (markersGroup) { map.removeLayer(markersGroup); markersGroup = null; }
                markersList = [];
                if (!sites.value.length) return;
                markersGroup = L.markerClusterGroup({
                    maxClusterRadius: 40, spiderfyOnMaxZoom: true, showCoverageOnHover: false, zoomToBoundsOnClick: true, disableClusteringAtZoom: 14,
                    iconCreateFunction: function (cl) { const n = cl.getChildCount(), bg = n >= 3 ? '#d4521c' : '#2e7fba', sz = n >= 3 ? 60 : 45; return L.divIcon({ html: '<div style="width:' + sz + 'px;height:' + sz + 'px;background:' + bg + ';border-radius:50%;border:3px solid rgba(255,255,255,.9);box-shadow:0 4px 20px rgba(0,0,0,.3);display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;font-family:Inter,sans-serif"><span style="font-weight:800;font-size:' + (n >= 3 ? 18 : 15) + 'px">' + n + '</span><span style="font-size:9px;font-weight:600;text-transform:uppercase;margin-top:2px">Sites</span></div>', className: '', iconSize: [sz, sz], iconAnchor: [sz / 2, sz / 2] }); }
                });
                sites.value.forEach(function (site) {
                    if (!site.lat || !site.lng) return;
                    const icon = pinIcon(site);
                    const marker = L.marker([site.lat, site.lng], { icon, siteData: site });
                    marker.bindTooltip('<div style="background:#1b3a6b;color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,.2)">' + site.name + '</div>', { permanent: true, direction: 'bottom', offset: [0, 8], className: 'cid-tooltip-permanent', opacity: 1 });
                    marker.bindPopup(popupHtml(site), { maxWidth: 280, className: 'cid-popup-clean' });
                    markersList.push(marker);
                });
                markersGroup.addLayers(markersList);
                map.addLayer(markersGroup);
                map.fitBounds(markersGroup.getBounds().pad(0.15));
            }

            onMounted(function () { initMap(); loadData(); timer = setInterval(loadData, REFRESH); });
            onUnmounted(function () { clearInterval(timer); if (map) map.remove(); });

            /* ── Render ── */
            function ic(cls) { return h('i', { class: cls }); }

            return function () {
                return h('div', { id: 'geo-dashboard-container' }, [
                    /* 1. Header */
                    h('div', { class: 'geo-header-pro' }, [
                        h('div', { class: 'geo-header-left' }, [
                            h('div', { class: 'geo-header-badge' }, ['🗺️']),
                            h('div', { class: 'geo-header-titles' }, [h('h1', null, 'Supervision Géographique'), h('p', null, 'Centre de Supervision IT — CID')]),
                        ]),
                        h('div', { class: 'geo-header-right' }, [
                            h('div', { class: 'geo-mode-toggle' }, [
                                h('button', { class: 'geo-mode-btn' + (tileMode.value === 'light' ? ' active' : ''), onClick: () => { if (tileMode.value !== 'light') toggleTile(); } }, '☀️ Jour'),
                                h('button', { class: 'geo-mode-btn' + (tileMode.value === 'dark' ? ' active' : ''), onClick: () => { if (tileMode.value !== 'dark') toggleTile(); } }, '🌙 Nuit'),
                            ]),
                            h('button', { class: 'geo-header-btn btn-export', onClick: exportPDF, disabled: isExporting.value }, [ic('ti ti-file-export'), ' ', isExporting.value ? 'Export…' : 'Rapport PDF']),
                            h('button', { class: 'geo-header-btn btn-refresh', onClick: loadData }, [ic('ti ti-refresh'), ' Actualiser']),
                        ]),
                    ]),
                    /* 2. KPI Band */
                    h('div', { class: 'geo-kpi-band' }, [
                        { cls: 'kpi-total',    icon: 'ti ti-ticket',             label: 'Total tickets', val: kpiT.value, acc: '#1b3a6b', acl: '#eef3fa' },
                        { cls: 'kpi-incident', icon: 'ti ti-alert-triangle',     label: 'Incidents',     val: kpiI.value, acc: '#d4521c', acl: '#fff3ee' },
                        { cls: 'kpi-demande',  icon: 'ti ti-headset',            label: 'Demandes',      val: kpiR.value, acc: '#2e7fba', acl: '#eff6ff' },
                        { cls: 'kpi-sites',    icon: 'ti ti-building-community', label: 'Sites actifs',  val: kpiS.value, acc: '#1a8c6e', acl: '#eafaf4' },
                    ].map(it => h('div', { class: 'geo-kpi-item ' + it.cls, style: '--accent:' + it.acc + ';--accent-light:' + it.acl }, [
                        h('div', { class: 'geo-kpi-item-icon' }, [ic(it.icon)]),
                        h('div', { class: 'geo-kpi-item-body' }, [h('div', { class: 'geo-kpi-number' }, String(it.val)), h('div', { class: 'geo-kpi-item-label' }, it.label)]),
                    ]))),
                    /* 3. Alert Ticker */
                    criticalSites.value.length ? h('div', { class: 'geo-alert-ticker' }, [
                        h('span', { class: 'ticker-label' }, [ic('ti ti-alert-triangle'), ' SITES EN ALERTE']),
                        h('div', { class: 'ticker-scroll' }, [
                            h('div', {
                                class: 'ticker-content',
                                style: {
                                    /* 10s par site, minimum 40s */
                                    animationDuration: Math.max(40, criticalSites.value.length * 10) + 's'
                                }
                            },
                                /* Double la liste pour boucle infinie fluide */
                                [...criticalSites.value, ...criticalSites.value].map((site, i) =>
                                    h('span', { key: i, class: 'ticker-item', style: { cursor: 'pointer' }, onClick: () => focusSite(site) }, [
                                        h('span', { class: 'ticker-dot', style: { background: site.color } }),
                                        h('strong', { style: { color: '#fff' } }, site.label + '\u00a0'),
                                        h('span', null, '📍 ' + site.name),
                                        h('span', { class: 'ticker-time' }, '\u00a0—\u00a0' + site.rateStr + ' non résolus'),
                                    ])
                                )
                            ),
                        ]),
                    ]) : null,
                    /* 4. Filter Bar */
                    h('div', { class: 'geo-filter-bar' }, [
                        h('div', { class: 'geo-filter-group' },
                            filterBtns.value.map(btn => h('button', { key: btn.type, class: { 'geo-filter-btn': true, active: activeType.value === btn.type }, 'data-type': btn.type, onClick: () => setType(btn.type) }, [ic(btn.icon), h('span', null, btn.label), h('span', { class: 'badge-count' }, String(btn.count))]))
                        ),
                        h('div', { class: 'geo-filter-separator' }),
                        h('div', { class: 'geo-filter-right', style: 'display:flex;align-items:center;gap:.75rem' }, [
                            h('div', { class: 'geo-search-wrapper' }, [
                                ic('ti ti-search geo-search-icon'),
                                h('input', { class: 'geo-search-input', placeholder: 'Rechercher un site…', value: searchQuery.value, onInput: e => { searchQuery.value = e.target.value; } }),
                            ]),
                            h('div', { class: 'geo-select-wrapper' }, [
                                h('select', { class: 'geo-status-select', id: 'geo-status-filter', value: activeStatus.value, onChange: e => { activeStatus.value = e.target.value; } }, STATUTS.map(o => h('option', { value: o.v }, o.t))),
                            ]),
                        ]),
                    ]),
                    /* 5. Status Bar */
                    h('div', { class: 'geo-status-bar', style: 'margin-bottom:.75rem' }, [
                        h('div', null, [h('span', { class: 'geo-status-dot', style: { background: hasError.value ? '#c0392b' : '#1a8c6e' } }), ' ' + (hasError.value ? 'Erreur de connexion…' : 'Mise à jour automatique active')]),
                        h('div', { id: 'geo-meta-info' }, metaInfo.value),
                    ]),
                    /* 6. Split Layout */
                    h('div', { class: 'geo-dashboard-split' }, [
                        /* Map */
                        h('div', { id: 'map-container', style: 'position:relative;' }, [
                            loading.value ? h('div', { class: 'geo-loader active' }, [h('div', { class: 'geo-loader-spinner' })]) : null,
                        ]),
                        /* Side Panel */
                        h('div', { class: 'geo-side-panel' }, [
                            /* Site Summary */
                            h('div', { class: 'geo-panel-card' }, [
                                h('div', { class: 'geo-panel-header' }, [ic('ti ti-chart-bar'), ' RÉSUMÉ PAR SITE']),
                                ...sorted.value.slice(0, 8).map(site => {
                                    const c = CRIT[site.stats.criticality] || CRIT.low;
                                    const maxOpen = sorted.value[0] ? sorted.value[0].stats.total_open : 1;
                                    const pct = Math.round(site.stats.total_open / Math.max(maxOpen, 1) * 100);
                                    return h('div', { class: 'geo-site-row', key: site.id, onClick: () => focusSite(site) }, [
                                        h('div', { class: 'geo-site-name' }, [h('span', { style: { width: '8px', height: '8px', borderRadius: '50%', background: c.bg, display: 'inline-block', flexShrink: 0 } }), ' 📍 ' + site.name]),
                                        h('div', { class: 'geo-site-bar-bg' }, [h('div', { class: 'geo-site-bar-fill', style: { width: pct + '%', background: c.bg } })]),
                                        h('div', { class: 'geo-site-badges' }, [
                                            h('span', { class: 'geo-site-badge badge-inc' }, '🔴 ' + site.stats.incidents_open + ' inc.'),
                                            h('span', { class: 'geo-site-badge badge-req' }, '🔵 ' + site.stats.requests_open + ' dem.'),
                                            h('span', { class: 'geo-site-badge badge-cls' }, '✅ ' + site.stats.total_closed + ' clôt.'),
                                        ]),
                                    ]);
                                }),
                            ]),
                        ]),
                    ]),
                ]);
            };
        },
    };

    function mountApp() {
        const el = document.getElementById('geo-dashboard-app');
        if (!el) return;
        createApp(GeoDashboard).mount(el);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mountApp);
    else mountApp();
})();
