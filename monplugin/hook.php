<?php
/**
 * hook.php — Injection CSS + JS par profil GLPI
 * Plugin : monplugin — Charte CID
 *
 * Profils gérés :
 *   ID 4 → Super-Admin   → superadmin.css + effects.js
 *   ID 6 → Technicien    → technicien.css + effects.js
 *   ID 1 → Employé       → employe.css + effects.js
 *
 * Login : hook display_login → login.css (injection directe via echo)
 */

function plugin_monplugin_inject_css()
{
    global $PLUGIN_HOOKS;

    // Sécurité : session active avec profil défini
    if (!isset($_SESSION['glpiactiveprofile']['id'])) {
        return;
    }

    $profile_id = (int) $_SESSION['glpiactiveprofile']['id'];
    $version = defined('PLUGIN_MONPLUGIN_VERSION') ? PLUGIN_MONPLUGIN_VERSION : '1.7.1';

    // Mapping profil → fichier CSS
    $profile_css_map = [
        4 => 'css/superadmin.css',   // Super-Admin
        6 => 'css/technicien.css',   // Technicien
        1 => 'css/employe.css',      // Employé / Self-Service
    ];

    if (isset($profile_css_map[$profile_id])) {
        $css_file = $profile_css_map[$profile_id];
        $PLUGIN_HOOKS['add_css']['monplugin'] = [$css_file];

        // Injection du JS d'effets pour tous les profils reconnus
        $js_file = 'js/effects.js';
        $PLUGIN_HOOKS['add_javascript']['monplugin'] = [$js_file];

        // Fix modaux mot de passe : les déplace vers <body> pour que
        // position:fixed fonctionne correctement (hors du DOM du formulaire)
        $PLUGIN_HOOKS['add_javascript']['monplugin'][] = 'js/modal-fix.js';

        // Super Admin uniquement — page centrale : affiche seulement Metabase
        if ($profile_id === 4) {
            $PLUGIN_HOOKS['add_javascript']['monplugin'][] = 'js/superadmin-central.js';
        }
    }

    // === DASHBOARD GÉOGRAPHIQUE — Styles globaux ===
    // map-style.css reste global (styles de la carte disponibles sur la page dashboard)
    // geodash-vue.js est chargé uniquement depuis front/dashboard.php (pas globalement)
    $PLUGIN_HOOKS['add_css']['monplugin'][] = 'css/map-style.css';

    // leaflet-logic.js retiré — remplacé par js/geodash-vue.js (chargé par front/dashboard.php)
}

/**
 * Hook display_login — v2.0.0
 * Injection CSS login + animation Constellation Réseau Neuronal (style enterprise)
 * Appelé automatiquement par GLPI avant l'affichage du formulaire de connexion.
 *
 * Animation : nœuds lumineux flottants reliés par des lignes dynamiques
 *   - Fond dégradé bleu marine professionnel
 *   - Connexions entre particules proches (< 160px)
 *   - Réactivité souris : attraction légère des nœuds vers le curseur
 *   - Pulsations subtiles sur chaque nœud
 */
function plugin_monplugin_display_login()
{
    $version  = defined('PLUGIN_MONPLUGIN_VERSION') ? PLUGIN_MONPLUGIN_VERSION : '2.0.0';
    $base_url = Plugin::getWebDir('monplugin', true, true);

    /* ── 1. CSS LOGIN ─────────────────────────────────────────────────── */
    echo '<link rel="stylesheet" type="text/css" href="'
        . htmlspecialchars($base_url . '/css/login.css')
        . '?v=' . $version . '">' . "\n";

    /* ── 2. ANIMATION CONSTELLATION RÉSEAU — Inline script ────────────── */
    echo <<<'HTML'
<script>
(function () {
    "use strict";

    /* ════════════════════════════════════════════════════════════
       CONFIGURATION
    ════════════════════════════════════════════════════════════ */
    var CFG = {
        count:        72,          /* nombre de nœuds                     */
        linkDist:     160,         /* distance max pour tracer une ligne   */
        nodeRadius:   { min: 1.5, max: 3.5 },
        speed:        { min: 0.15, max: 0.55 },
        mouseForce:   80,          /* rayon d'influence du curseur (px)    */
        bgGrad: [
            { stop: 0,    color: "#0d1b2a" },  /* bleu nuit profond      */
            { stop: 0.55, color: "#112240" },  /* bleu marine            */
            { stop: 1,    color: "#0a192f" }   /* quasi-noir bleu        */
        ],
        nodeColor:  "rgba(100, 210, 255, {a})",   /* cyan lumineux        */
        lineColor:  "rgba(100, 210, 255, {a})",   /* même teinte, alpha variable */
        glowColor:  "rgba(64, 190, 255, 0.18)"    /* halo doux            */
    };

    var W, H, canvas, ctx;
    var nodes = [];
    var mouse = { x: -9999, y: -9999, active: false };
    var ripples = [];  /* ondes de choc au clic */

    /* ════════════════════════════════════════════════════════════
       INIT DOM
    ════════════════════════════════════════════════════════════ */
    function init() {
        if (document.getElementById("cid-particles-canvas")) return;

        /* ── Canvas ── */
        canvas = document.createElement("canvas");
        canvas.id = "cid-particles-canvas";
        canvas.style.cssText = [
            "position:fixed", "top:0", "left:0",
            "width:100vw", "height:100vh",
            "z-index:0", "pointer-events:none", "display:block"
        ].join(";");
        document.body.insertBefore(canvas, document.body.firstChild);

        /* ── Fond dégradé sur body ── */
        document.body.style.background = "linear-gradient(160deg, #0d1b2a 0%, #112240 55%, #0a192f 100%)";
        document.body.style.overflowX  = "hidden";

        ctx = canvas.getContext("2d");

        /* ── Resize ── */
        function resize() {
            W = canvas.width  = window.innerWidth;
            H = canvas.height = window.innerHeight;
            buildBackground();
        }
        window.addEventListener("resize", resize);
        resize();

        /* ── Suivi souris ── */
        window.addEventListener("mousemove", function (e) {
            mouse.x      = e.clientX;
            mouse.y      = e.clientY;
            mouse.active = true;
        });
        window.addEventListener("mouseleave", function () {
            mouse.x      = -9999;
            mouse.y      = -9999;
            mouse.active = false;
        });

        /* ── Clic = onde de choc ── */
        window.addEventListener("click", function (e) {
            ripples.push({ x: e.clientX, y: e.clientY, r: 0, maxR: 220, alpha: 0.7 });
            /* Répulsion explosive des nœuds proches */
            nodes.forEach(function (n) {
                var dx   = n.x - e.clientX;
                var dy   = n.y - e.clientY;
                var dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < 220 && dist > 0) {
                    var force = (1 - dist / 220) * 3.5;
                    n.vx += dx / dist * force;
                    n.vy += dy / dist * force;
                }
            });
        });

        /* ── Création des nœuds ── */
        for (var i = 0; i < CFG.count; i++) {
            nodes.push(createNode());
        }

        requestAnimationFrame(loop);
    }

    /* ════════════════════════════════════════════════════════════
       NŒUD
    ════════════════════════════════════════════════════════════ */
    function createNode() {
        var angle = Math.random() * Math.PI * 2;
        var spd   = CFG.speed.min + Math.random() * (CFG.speed.max - CFG.speed.min);
        return {
            x:      Math.random() * W,
            y:      Math.random() * H,
            vx:     Math.cos(angle) * spd,
            vy:     Math.sin(angle) * spd,
            r:      CFG.nodeRadius.min + Math.random() * (CFG.nodeRadius.max - CFG.nodeRadius.min),
            phase:  Math.random() * Math.PI * 2,  /* phase de pulsation  */
            speed:  0.012 + Math.random() * 0.018 /* vitesse pulsation   */
        };
    }

    /* ════════════════════════════════════════════════════════════
       FOND DÉGRADÉ (recalculé au resize)
    ════════════════════════════════════════════════════════════ */
    var bgCache = null;
    function buildBackground() {
        var g = ctx.createLinearGradient(0, 0, W * 0.4, H);
        CFG.bgGrad.forEach(function (s) { g.addColorStop(s.stop, s.color); });
        bgCache = g;
    }

    /* ════════════════════════════════════════════════════════════
       BOUCLE PRINCIPALE
    ════════════════════════════════════════════════════════════ */
    function loop() {
        /* Fond */
        ctx.fillStyle = bgCache || "#0d1b2a";
        ctx.fillRect(0, 0, W, H);

        /* Mise à jour positions */
        nodes.forEach(function (n) {
            n.phase += n.speed;

            var dx   = mouse.x - n.x;
            var dy   = mouse.y - n.y;
            var dist = Math.sqrt(dx * dx + dy * dy);

            /* Attraction douce vers le curseur */
            if (dist < CFG.mouseForce && dist > 0) {
                var force = (CFG.mouseForce - dist) / CFG.mouseForce * 0.012;
                n.vx += dx / dist * force;
                n.vy += dy / dist * force;
            }

            /* Mémoriser la proximité au curseur pour l'affichage */
            n.mouseDist = dist;

            /* Amortissement pour limiter la vitesse max */
            var spd = Math.sqrt(n.vx * n.vx + n.vy * n.vy);
            var maxSpd = CFG.speed.max * 1.8;
            if (spd > maxSpd) {
                n.vx = n.vx / spd * maxSpd;
                n.vy = n.vy / spd * maxSpd;
            }

            n.x += n.vx;
            n.y += n.vy;

            /* Rebond sur les bords */
            if (n.x < 0)  { n.x = 0;  n.vx *= -1; }
            if (n.x > W)  { n.x = W;  n.vx *= -1; }
            if (n.y < 0)  { n.y = 0;  n.vy *= -1; }
            if (n.y > H)  { n.y = H;  n.vy *= -1; }
        });

        /* Lignes entre nœuds proches */
        for (var i = 0; i < nodes.length; i++) {
            for (var j = i + 1; j < nodes.length; j++) {
                var a = nodes[i], b = nodes[j];
                var ddx = a.x - b.x, ddy = a.y - b.y;
                var d2  = ddx * ddx + ddy * ddy;
                if (d2 < CFG.linkDist * CFG.linkDist) {
                    var ratio = 1 - Math.sqrt(d2) / CFG.linkDist;
                    var alpha = ratio * 0.55;
                    ctx.beginPath();
                    ctx.moveTo(a.x, a.y);
                    ctx.lineTo(b.x, b.y);
                    ctx.strokeStyle = "rgba(100,210,255," + alpha.toFixed(3) + ")";
                    ctx.lineWidth   = ratio * 1.2;
                    ctx.stroke();
                }
            }
        }

        /* Lignes spéciales : curseur → nœuds proches (rayon élargi) */
        if (mouse.active) {
            var HOVER_DIST = 200;
            nodes.forEach(function (n) {
                if (n.mouseDist < HOVER_DIST) {
                    var ratio = 1 - n.mouseDist / HOVER_DIST;
                    ctx.beginPath();
                    ctx.moveTo(mouse.x, mouse.y);
                    ctx.lineTo(n.x, n.y);
                    ctx.strokeStyle = "rgba(150,230,255," + (ratio * 0.80).toFixed(3) + ")";
                    ctx.lineWidth   = ratio * 2.0;
                    ctx.stroke();
                }
            });
        }

        /* Nœuds (avec halo + pulsation + boost proximité) */
        nodes.forEach(function (n) {
            var pulse = 0.75 + 0.25 * Math.sin(n.phase);

            /* Boost visuel si le curseur est proche (< 200px) */
            var proximity = (n.mouseDist < 200) ? (1 - n.mouseDist / 200) : 0;
            var r         = n.r * pulse * (1 + proximity * 1.6);  /* radius amplifié  */
            var glowAlpha = 0.22 + proximity * 0.55;              /* halo plus vif     */
            var coreAlpha = 0.6  + 0.4 * pulse + proximity * 0.4; /* cœur plus blanc  */
            if (coreAlpha > 1) coreAlpha = 1;

            /* Halo diffus (amplifié au hover) */
            var haloR = r * (4 + proximity * 4);
            var glow  = ctx.createRadialGradient(n.x, n.y, 0, n.x, n.y, haloR);
            glow.addColorStop(0, "rgba(100,210,255," + glowAlpha.toFixed(2) + ")");
            glow.addColorStop(1, "rgba(100,210,255,0)");
            ctx.beginPath();
            ctx.arc(n.x, n.y, haloR, 0, Math.PI * 2);
            ctx.fillStyle = glow;
            ctx.fill();

            /* Nœud central */
            ctx.beginPath();
            ctx.arc(n.x, n.y, r, 0, Math.PI * 2);
            ctx.fillStyle = "rgba(180,235,255," + coreAlpha.toFixed(2) + ")";
            ctx.fill();
        });

        /* Nœud curseur (point lumineux au centre du curseur) */
        if (mouse.active) {
            var cr = 5;
            var cg = ctx.createRadialGradient(mouse.x, mouse.y, 0, mouse.x, mouse.y, cr * 6);
            cg.addColorStop(0,   "rgba(255,255,255,0.90)");
            cg.addColorStop(0.3, "rgba(100,210,255,0.50)");
            cg.addColorStop(1,   "rgba(100,210,255,0)");
            ctx.beginPath();
            ctx.arc(mouse.x, mouse.y, cr * 6, 0, Math.PI * 2);
            ctx.fillStyle = cg;
            ctx.fill();
            ctx.beginPath();
            ctx.arc(mouse.x, mouse.y, cr, 0, Math.PI * 2);
            ctx.fillStyle = "rgba(255,255,255,0.95)";
            ctx.fill();
        }

        /* Ondes de choc (clic) */
        ripples = ripples.filter(function (rp) { return rp.alpha > 0.01; });
        ripples.forEach(function (rp) {
            rp.r     += 6;
            rp.alpha *= 0.90;
            ctx.beginPath();
            ctx.arc(rp.x, rp.y, rp.r, 0, Math.PI * 2);
            ctx.strokeStyle = "rgba(100,210,255," + rp.alpha.toFixed(3) + ")";
            ctx.lineWidth   = 2;
            ctx.stroke();
        });

        requestAnimationFrame(loop);
    }

    /* Lance init() dès que le DOM est prêt */
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

})();
</script>
HTML;
}

