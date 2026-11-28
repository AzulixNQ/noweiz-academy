/* La vitrine animée d'Omegga (partie partenaire de l'accueil) : un EXEMPLE dessiné, avec des chiffres inventés (c'est écrit sur la page).
   Trois écrans d'Omegga passent l'un après l'autre : le DOM, le scanner (ses signaux sur le graphique), puis les gros trades en bulles.
   Les onglets permettent d'en choisir un ; la barre sous l'onglet montre le temps qui reste avant le suivant.
   Les écrans sont dessinés comme dans Omegga, avec ses couleurs (menthe = achat / hausse, violet = vente / baisse).

   Sans JavaScript, la capture d'Omegga reste à sa place. En « mouvement réduit », les écrans restent fixes (les onglets marchent). */
(function () {
  'use strict';
  var boite = document.querySelector('[data-omegga]');
  if (!boite) return;
  var toile = boite.querySelector('canvas');
  if (!toile || !toile.getContext) return;
  var ctx = toile.getContext('2d');
  var onglets = [].slice.call(boite.querySelectorAll('[data-scene]'));
  var lecture = boite.querySelector('[data-om-lecture]');
  var lectureTitre = lecture && lecture.querySelector('b');
  var lectureTexte = lecture && lecture.querySelector('span');
  var calme = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var DUREE = 7600, FONDU = 650;
  // les couleurs d'Omegga : menthe = hausse / achat, violet = baisse / vente
  var MENTHE = '21, 245, 186', VIOLET = '131, 111, 255', LIGNE = '157, 124, 255', JAUNE = '255, 225, 77';
  var W = 0, H = 0, dpr = 1, petit = false;
  var POLICE = '"Nojah Sans", system-ui, sans-serif';
  function police(poids, taille) { return poids + ' ' + taille + 'px ' + POLICE; }
  function rgba(c, a) { return 'rgba(' + c + ', ' + a + ')'; }
  function espace(n) { return String(Math.round(Math.abs(n))).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
  function prixTexte(p) {
    var t = p.toFixed(2).split('.');
    return t[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ',' + t[1];
  }
  function heure(s) {
    var h = Math.floor(s / 3600), m = Math.floor(s / 60) % 60, x = s % 60;
    return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (x < 10 ? '0' : '') + x;
  }
  function tirage(graine) { var g = graine; return function () { g = (g * 16807) % 2147483647; return g / 2147483647; }; }
  function borne(x, a, b) { return x < a ? a : x > b ? b : x; }
  function lisse(x) { x = borne(x, 0, 1); return x * x * (3 - 2 * x); }
  function rebond(x) { x = borne(x, 0, 1); var c = 1.70158; return 1 + (c + 1) * Math.pow(x - 1, 3) + c * Math.pow(x - 1, 2); }
  function arrondi(x, y, l, h, r) {
    r = Math.min(r, l / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y); ctx.arcTo(x + l, y, x + l, y + h, r); ctx.arcTo(x + l, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + l, y, r); ctx.closePath();
  }

  /* ================= 1. Le DOM ================= */
  var dom = (function () {
    var PAS = 0.25, BASE = 24310;                    // le prix du niveau 0
    var MUR = 14, MUR_TAILLE = 380;                  // un gros ordre de vente posé au-dessus du prix
    var hasard, livre, volume, eclats, offre, dernier, dernierCote, centre, prochain, temps, murReste;
    function taille(niv, cote) {
      var loin = cote > 0 ? niv - offre : offre - 1 - niv;
      return Math.round(6 + hasard() * 26 + Math.min(loin, 8) * (2 + hasard() * 5));
    }
    function remplir() {
      for (var n = offre - 30; n < offre + 30; n++) if (!livre[n]) livre[n] = { t: taille(n, n >= offre ? 1 : -1), c: n >= offre ? 1 : -1 };
      livre[MUR] = livre[MUR] && livre[MUR].c > 0 && livre[MUR].mur ? livre[MUR] : { t: murReste, c: 1, mur: true };
    }
    function init() {
      hasard = tirage(4242); livre = {}; volume = {}; eclats = [];
      offre = 9; dernier = 9; dernierCote = 1; centre = 9; prochain = 0; temps = 0; murReste = MUR_TAILLE;
      remplir();
      for (var i = 0; i < 26; i++) { var n = 6 + Math.floor(hasard() * 7); volume[n] = volume[n] || [0, 0]; volume[n][hasard() < 0.5 ? 0 : 1] += Math.round(4 + hasard() * 20); }
    }
    function phase() { return temps < 3000 ? 0 : temps < 5300 ? 1 : 2; }
    function echanger(cote, combien) {
      // cote 1 = un achat au marché (il prend les ventes), −1 = une vente au marché
      var niv = cote > 0 ? offre : offre - 1, o = livre[niv];
      if (!o || o.c !== cote) { o = livre[niv] = { t: taille(niv, cote), c: cote }; }
      var pris = Math.min(combien, o.t);
      o.t -= pris; if (o.mur) murReste = o.t;
      volume[niv] = volume[niv] || [0, 0];
      volume[niv][cote > 0 ? 1 : 0] += pris;
      dernier = niv; dernierCote = cote;
      eclats.push({ n: niv, c: cote, t: temps, q: pris });
      if (o.t <= 0) {
        if (o.mur) { o.t = 0; return; }
        delete livre[niv];
        if (cote > 0) { offre += 1; livre[offre - 1] = { t: Math.round(3 + hasard() * 9), c: -1 }; }
        else { offre -= 1; livre[offre] = { t: Math.round(3 + hasard() * 9), c: 1 }; }
        remplir();
      }
    }
    function avancer(dt) {
      temps += dt;
      while (temps >= prochain) {
        var p = phase(), cote, q = Math.round(1 + hasard() * 9 + (hasard() < 0.12 ? 18 + hasard() * 30 : 0));
        if (p === 0) cote = hasard() < 0.8 ? 1 : -1;
        else if (p === 1) { cote = hasard() < 0.85 ? 1 : -1; if (cote > 0 && offre === MUR) q += 6; }
        else cote = hasard() < 0.82 ? -1 : 1;
        if (p === 0 && offre >= MUR) cote = hasard() < 0.6 ? 1 : -1;
        echanger(cote, q);
        // le mur tient : on ne le laisse pas disparaître
        if (livre[MUR] && livre[MUR].mur && livre[MUR].t < 90) { livre[MUR].t += 40; murReste = livre[MUR].t; }
        prochain += p === 1 ? 70 + hasard() * 60 : 95 + hasard() * 90;
        // les ordres posés bougent un peu
        var k = offre - 8 + Math.floor(hasard() * 16), o = livre[k];
        if (o && !o.mur) o.t = Math.max(2, o.t + Math.round((hasard() - 0.45) * 6));
      }
      centre += (offre - 0.5 - centre) * Math.min(1, dt / 260);
      eclats = eclats.filter(function (e) { return temps - e.t < 3000; });
    }
    function lectureDom() {
      var p = phase();
      if (p === 0) return ['Les acheteurs montent', 'Ils prennent les ventes, niveau après niveau.'];
      if (p === 1) return ['Un mur de ' + espace(MUR_TAILLE) + ' à la vente', 'Les achats s\'y écrasent : le prix ne passe pas.'];
      return ['Les vendeurs reprennent', 'Le prix redescend sous le mur.'];
    }
    // dessiné comme le DOM d'Omegga : acheteurs en bleu | prix | vendeurs en saumon | volume de la séance | trades des 30 dernières secondes
    var BID = '41, 197, 246', ASK = '255, 122, 107', DERNIER = '255, 225, 77';
    function dessiner() {
      var haut = 26, hl = petit ? 21 : 20, lignes = Math.floor((H - haut) / hl);
      var lA = W * (petit ? 0.28 : 0.24), lP = W * (petit ? 0.24 : 0.17), lR = W * (petit ? 0.18 : 0.12);
      var xA = 0, xP = lA, xV = xP + lP, lV = lA, xR = W - lR, xVol = xV + lV + 8, lVol = xR - 8 - xVol;
      var fp = 11.5;
      ctx.font = police(500, 11.5); ctx.fillStyle = '#9A95B8'; ctx.textBaseline = 'middle';
      ctx.textAlign = 'right'; ctx.fillText('Acheteurs', xA + lA - 8, 10);
      ctx.textAlign = 'center'; ctx.fillText('Prix', xP + lP / 2, 10); ctx.fillText('30 s', xR + lR / 2, 10);
      ctx.textAlign = 'left'; ctx.fillText('Vendeurs', xV + 8, 10);
      if (lVol > 40) ctx.fillText('Volume', xVol, 10);
      var hautNiv = Math.round(centre + lignes / 2), maxT = 1, maxV = 1, n, i, net = {};
      for (i = 0; i < lignes; i++) { n = hautNiv - i; if (livre[n] && !livre[n].mur) maxT = Math.max(maxT, livre[n].t); if (volume[n]) maxV = Math.max(maxV, volume[n][0] + volume[n][1]); }
      maxT = Math.max(maxT, 60);
      eclats.forEach(function (e) { if (temps - e.t < 3000) net[e.n] = (net[e.n] || 0) + e.c * e.q; });
      var decal = (centre + lignes / 2 - hautNiv) * hl;   // le défilement doux du DOM
      ctx.fillStyle = 'rgba(255, 255, 255, .08)'; ctx.fillRect(0, haut - 3, W, 1);
      ctx.save(); ctx.beginPath(); ctx.rect(0, haut - 2, W, H - haut + 2); ctx.clip();
      for (i = -1; i <= lignes; i++) {
        n = hautNiv - i;
        var y = haut + i * hl + decal, o = livre[n], milieu = y + hl / 2;
        ctx.fillStyle = 'rgba(255, 255, 255, .04)'; ctx.fillRect(0, Math.round(y + hl) - 0.5, W, 1);
        if (o && o.t > 0) {
          var achat = o.c < 0, meilleur = achat ? n === offre - 1 : n === offre, large = (o.mur ? 1 : Math.min(1, o.t / maxT)) * (lA - 4);
          ctx.fillStyle = rgba(achat ? BID : ASK, o.mur ? 0.7 : meilleur ? 0.5 : 0.28);
          ctx.fillRect(achat ? xA + lA - 2 - large : xV + 2, y + 1, large, hl - 2);
          ctx.font = police(o.mur ? 700 : 500, fp); ctx.fillStyle = '#ECE9FB'; ctx.textAlign = achat ? 'right' : 'left';
          ctx.fillText(espace(o.t), achat ? xA + lA - 8 : xV + 8, milieu);
          if (o.mur) {
            var lt = ctx.measureText(espace(o.t)).width;
            ctx.font = police(650, 10.5); ctx.fillStyle = '#ECE9FB'; ctx.fillText('gros ordre', xV + 8 + lt + 8, milieu);
          }
        }
        // le prix : le dernier échangé en jaune
        var estDernier = n === dernier;
        if (estDernier) { ctx.fillStyle = rgba(DERNIER, 0.18); ctx.fillRect(xP, y, lP, hl); }
        ctx.font = police(estDernier ? 700 : 500, fp); ctx.textAlign = 'center';
        ctx.fillStyle = estDernier ? rgba(DERNIER, 1) : (((n % 4) + 4) % 4 === 0 ? '#ECE9FB' : '#9A95B8');
        ctx.fillText(prixTexte(BASE + n * PAS), xP + lP / 2, milieu);
        // le volume de la séance à ce prix : la part vendue en violet, la part achetée en menthe
        var v = volume[n];
        if (v && lVol > 40) {
          var tot = v[0] + v[1], lv = (lVol - 34) * tot / maxV, lb = lv * v[0] / tot;
          ctx.fillStyle = rgba(VIOLET, 0.45); ctx.fillRect(xVol, y + 2, lb, hl - 4);
          ctx.fillStyle = rgba(MENTHE, 0.45); ctx.fillRect(xVol + lb, y + 2, lv - lb, hl - 4);
          ctx.font = police(500, 10.5); ctx.textAlign = 'left'; ctx.fillStyle = '#9A95B8'; ctx.fillText(espace(tot), xVol + lv + 5, milieu);
        }
        // les trades agressifs récents à ce prix (achats − ventes)
        var d = net[n];
        if (d) { ctx.font = police(650, fp); ctx.textAlign = 'center'; ctx.fillStyle = rgba(d > 0 ? MENTHE : VIOLET, 1); ctx.fillText((d > 0 ? '+' : '−') + Math.abs(d), xR + lR / 2, milieu); }
      }
      // l'ordre qui vient d'être pris : la case s'éclaire un instant
      eclats.forEach(function (e) {
        var a = 1 - (temps - e.t) / 380, i2 = hautNiv - e.n, y = haut + i2 * hl + decal;
        if (a <= 0 || i2 < -1 || i2 > lignes) return;
        ctx.fillStyle = rgba(e.c > 0 ? ASK : BID, 0.35 * a);
        ctx.fillRect(e.c > 0 ? xV : xA, y, lA, hl);
      });
      ctx.restore();
      ctx.fillStyle = 'rgba(255, 255, 255, .07)';
      ctx.fillRect(xP, haut - 2, 1, H - haut); ctx.fillRect(xV, haut - 2, 1, H - haut); ctx.fillRect(xVol - 4, haut - 2, 1, H - haut); ctx.fillRect(xR, haut - 2, 1, H - haut);
    }
    return { init: init, avancer: avancer, dessiner: dessiner, lecture: lectureDom };
  })();

  /* ================= le graphique d'Omegga : il sert au scanner et aux gros trades ================= */
  // Bougies d'une minute aux couleurs d'Omegga (menthe à la hausse, violet à la baisse), dessinées comme dans le terminal.
  // La bougie de droite se forme tick après tick ; une nouvelle commence toutes les 235 ms, et le graphique glisse.
  // Le chemin du prix est écrit d'avance (une petite histoire par écran) ; les chiffres sont inventés.
  function graphiqueOmegga(o) {
    var PERIODE = 235, DEBUT = o.debut, N = o.n, BASE = 15 * 3600;
    var bougies, gros, temps, bas, haut, cbas, chaut;
    function suivre(K, i) {
      for (var j = 1; j < K.length; j++) if (i <= K[j][0]) { var a = K[j - 1], b = K[j]; return a[1] + (b[1] - a[1]) * lisse((i - a[0]) / (b[0] - a[0])); }
      return K[K.length - 1][1];
    }
    function q(p) { return Math.round(p * 4) / 4; }
    // le prix à un moment de la bougie (f de 0 à 1)
    function prixA(b, f) { return q(b.o + (b.c - b.o) * lisse(f * 1.15) + Math.sin(f * Math.PI * 3) * 0.9 * (1 - f)); }
    function extremes(b, f) {
      var hh = b.o, ll = b.o;
      for (var s = 0; s <= f + 1e-9; s += 0.05) { var p = prixA(b, s); if (p > hh) hh = p; if (p < ll) ll = p; }
      return [hh, ll];
    }
    function construire() {
      var h = tirage(o.graine), avant = suivre(o.cles, 0), i;
      bougies = [];
      for (i = 0; i < N; i++) {
        var c = q(suivre(o.cles, i) + (h() - 0.5) * 2.4), ou = avant;
        bougies.push({ o: ou, c: c, h: q(Math.max(ou, c) + 0.25 + h() * 2), l: q(Math.min(ou, c) - 0.25 - h() * 2), cvd: suivre(o.cvdc, i) + (h() - 0.5) * 34 });
        avant = c;
      }
      if (o.corriger) o.corriger(bougies);
      var pv = 0, vv = 0;
      bougies.forEach(function (b, k) {
        var e = extremes(b, 1);
        b.h = Math.max(b.h, e[0]); b.l = Math.min(b.l, e[1]);
        b.v = Math.round(70 + Math.abs(b.cvd - (k ? bougies[k - 1].cvd : b.cvd)) * 1.5 + h() * 60);
        pv += (b.h + b.l + b.c) / 3 * b.v; vv += b.v; b.vwap = pv / vv;
      });
      gros = o.gros ? o.gros(h, bougies) : [];
      gros.forEach(function (g) { var b = bougies[g.i]; g.p = borne(prixA(b, g.f), b.l, b.h); g.t = g.i + g.f; });
    }
    function init() { construire(); temps = 0; bas = null; }
    function avancer(dt) { temps = Math.min(temps + dt, (N - 1 - DEBUT) * PERIODE - 1); }
    function vue() {
      var p = temps / PERIODE, k = DEBUT + Math.floor(p), f = p - Math.floor(p), b = bougies[k], e = extremes(b, f), ouvre = borne(f * 1.6, 0, 1);
      var cour = prixA(b, f), avant = bougies[k - 1];
      return { p: DEBUT + p, k: k, cour: { o: b.o, c: cour, h: Math.max(e[0], q(b.o + (b.h - b.o) * ouvre)), l: Math.min(e[1], q(b.o + (b.l - b.o) * ouvre)),
        cvd: avant.cvd + (b.cvd - avant.cvd) * f, v: Math.round(b.v * f), vwap: avant.vwap } };
    }
    function lecture() { return o.lecture(vue(), gros); }

    function dessiner() {
      var v = vue(), axe = petit ? 62 : 72, profil = o.profil ? (petit ? 46 : 104) : 0, cvdH = o.cvd ? (petit ? 50 : 62) : 0;
      var xD = W - axe - profil - (o.profil ? 8 : 26), bw = petit ? 5 : 5.6, hautP = 30, basP = H - (cvdH ? cvdH + 12 : 8), hautC = H - cvdH + 2, basC = H - 4;
      var bg = function (i) { return i < v.k ? bougies[i] : i === v.k ? v.cour : null; };
      var xOf = function (i) { return xD - (v.p - i) * bw; };
      var i0 = Math.max(0, Math.floor(v.p - xD / bw) - 1), i, b, xA = W - axe - 3;
      var lo = Infinity, hi = -Infinity, clo = Infinity, chi = -Infinity;
      for (i = i0; i <= v.k; i++) { b = bg(i); lo = Math.min(lo, b.l); hi = Math.max(hi, b.h); clo = Math.min(clo, b.cvd); chi = Math.max(chi, b.cvd); }
      (o.niveaux || []).forEach(function (n) { if (n[4]) { hi = Math.max(hi, n[0]); lo = Math.min(lo, n[0]); } });
      lo -= 3; hi += 4;
      if (bas === null) { bas = lo; haut = hi; cbas = clo; chaut = chi; }
      // l'échelle rattrape vite le prix quand il sort de l'écran, et revient doucement sinon
      var kp = lo < bas || hi > haut ? 0.22 : 0.06, kc = clo < cbas || chi > chaut ? 0.22 : 0.06;
      bas += (lo - bas) * kp; haut += (hi - haut) * kp; cbas += (clo - cbas) * kc; chaut += (chi - chaut) * kc;
      var Y = function (p) { return basP - (p - bas) / (haut - bas) * (basP - hautP); };
      var YC = function (c) { return basC - 6 - (c - cbas) / Math.max(1, chaut - cbas) * (basC - hautC - 16); };

      ctx.textBaseline = 'middle';
      var etiquettes = [];                              // les noms des niveaux, écrits par-dessus les bougies
      // la grille et l'axe des prix
      ctx.font = police(500, 11); ctx.textAlign = 'left';
      for (var g = Math.ceil(bas / 5) * 5; g < haut; g += 5) {
        var yg = Math.round(Y(g)) + 0.5;
        if (yg < hautP - 4 || yg > basP + 2) continue;
        ctx.fillStyle = 'rgba(255, 255, 255, .045)'; ctx.fillRect(0, yg, W - axe, 1);
        ctx.fillStyle = '#615C80'; ctx.fillText(espace(g), W - axe + 9, yg);
      }
      ctx.fillStyle = 'rgba(255, 255, 255, .07)'; ctx.fillRect(W - axe, 0, 1, H);
      if (cvdH) ctx.fillRect(0, H - cvdH - 4, W - axe, 1);

      // le profil de volume, accroché à l'axe : la part vendue en violet, la part achetée en menthe
      if (o.profil) {
        var cases = {}, total = 0, poc = 0, pocV = 0;
        for (i = 0; i <= v.k; i++) {
          b = bg(i); var n0 = Math.floor(b.l), n1 = Math.floor(b.h), part = b.v / (n1 - n0 + 1), achat = b.c >= b.o ? 0.68 : 0.32;
          for (var n = n0; n <= n1; n++) { var cs = cases[n] || (cases[n] = [0, 0]); cs[0] += part * (1 - achat); cs[1] += part * achat; }
        }
        Object.keys(cases).forEach(function (n) { var s = cases[n][0] + cases[n][1]; total += s; if (s > pocV) { pocV = s; poc = +n; } });
        var vah = poc, val = poc, dans = pocV;
        while (dans < total * 0.7) {
          var dessus = cases[vah + 1] ? cases[vah + 1][0] + cases[vah + 1][1] : 0, dessous = cases[val - 1] ? cases[val - 1][0] + cases[val - 1][1] : 0;
          if (!dessus && !dessous) break;
          if (dessus >= dessous) { vah++; dans += dessus; } else { val--; dans += dessous; }
        }
        Object.keys(cases).forEach(function (n) {
          n = +n; var c = cases[n], y1 = Y(n + 1), y0 = Y(n), hb = Math.max(1, y0 - y1 - 1);
          if (y0 < hautP - 2 || y1 > basP + 2) return;
          var l = (c[0] + c[1]) / pocV * profil, lv = l * c[0] / (c[0] + c[1]), va = n >= val && n <= vah;
          ctx.fillStyle = rgba(VIOLET, va ? 0.62 : 0.32); ctx.fillRect(xA - l, y1, lv, hb);
          ctx.fillStyle = rgba(MENTHE, va ? 0.62 : 0.3); ctx.fillRect(xA - l + lv, y1, l - lv, hb);
        });
        ctx.save(); ctx.setLineDash([2, 3]);
        [[poc + 0.5, 'POC', 0.55], [vah + 1, 'VAH', 0.32], [val, 'VAL', 0.32]].forEach(function (z) {
          var yz = Math.round(Y(z[0])) + 0.5; if (yz < hautP || yz > basP) return;
          ctx.strokeStyle = 'rgba(236, 233, 251, ' + z[2] + ')'; ctx.beginPath(); ctx.moveTo(0, yz); ctx.lineTo(xA, yz); ctx.stroke();
          etiquettes.push([z[1], xA - profil - 6, yz - 7, 'right', 'rgba(236, 233, 251, ' + (z[2] + 0.25) + ')', 600, 10]);
        });
        ctx.restore();
      }
      // les niveaux clés : [prix, nom, nom court, couleur]
      ctx.save(); ctx.setLineDash([5, 4]);
      (o.niveaux || []).forEach(function (z) {
        var yz = Math.round(Y(z[0])) + 0.5; if (yz < hautP || yz > basP) return;
        ctx.strokeStyle = 'rgba(' + z[3] + ', .6)'; ctx.beginPath(); ctx.moveTo(0, yz); ctx.lineTo(W - axe, yz); ctx.stroke();
        // sur téléphone, le nom va à droite, contre l'axe : à gauche, il gênerait les signaux
        etiquettes.push(petit ? [z[2], W - axe - 6, yz - 7, 'right', 'rgba(' + z[3] + ', .9)', 500, 10.5] : [z[1], 6, yz - 7, 'left', 'rgba(' + z[3] + ', .9)', 500, 10.5]);
      });
      ctx.restore();

      ctx.save(); ctx.beginPath(); ctx.rect(0, hautP - 6, xD + bw, basP - hautP + 12); ctx.clip();
      // la VWAP
      if (o.vwap) {
        ctx.beginPath();
        for (i = i0; i <= v.k; i++) { var yw = Y(i < v.k ? bougies[i].vwap : v.cour.vwap); if (i === i0) ctx.moveTo(xOf(i), yw); else ctx.lineTo(xOf(i), yw); }
        ctx.strokeStyle = rgba(JAUNE, 0.85); ctx.lineWidth = 1.4; ctx.stroke(); ctx.lineWidth = 1;
      }
      // les bougies : une mèche fine, un corps étroit, comme dans Omegga
      for (i = i0; i <= v.k; i++) {
        b = bg(i); var x = Math.round(xOf(i)) + 0.5, col = rgba(b.c >= b.o ? MENTHE : VIOLET, 1);
        ctx.fillStyle = col; ctx.fillRect(x - 0.5, Y(b.h), 1, Math.max(1, Y(b.l) - Y(b.h)));
        var yo = Y(Math.max(b.o, b.c)), yc = Y(Math.min(b.o, b.c));
        ctx.fillRect(x - Math.max(1, bw * 0.3), yo, Math.max(2, bw * 0.6), Math.max(1.2, yc - yo));
      }
      // les gros trades : une bulle par ordre au marché, à son heure et à son prix ; la surface suit la taille
      gros.forEach(function (gt) {
        var age = (v.p - gt.t) * PERIODE; if (age < 0) return;
        var a = Math.abs(gt.q), r = borne(5 * Math.sqrt(a / 20), 5, 42) * (petit ? 0.82 : 1) * rebond(age / 380);
        var bx = xOf(gt.i) + (gt.f - 0.5) * bw * 0.7, by = Y(gt.p), col = gt.q > 0 ? MENTHE : VIOLET;
        if (bx + r < 0) return;
        if (age < 900 && a >= 80) { ctx.beginPath(); ctx.arc(bx, by, r + 4 + 14 * (age / 900), 0, 6.2832); ctx.strokeStyle = rgba(col, 0.55 * (1 - age / 900)); ctx.lineWidth = 2; ctx.stroke(); }
        ctx.beginPath(); ctx.arc(bx, by, Math.max(0.1, r), 0, 6.2832);
        ctx.strokeStyle = 'rgba(4, 3, 10, .9)'; ctx.lineWidth = 4; ctx.stroke();
        ctx.fillStyle = rgba(col, 0.34); ctx.fill();
        ctx.strokeStyle = rgba(col, 1); ctx.lineWidth = 1.5; ctx.stroke(); ctx.lineWidth = 1;
      });
      ctx.restore();
      etiquettes.forEach(function (e) {
        ctx.font = police(e[5], e[6]); ctx.textAlign = e[3];
        var lt = ctx.measureText(e[0]).width, x0 = e[3] === 'right' ? e[1] - lt : e[1];
        ctx.fillStyle = 'rgba(4, 3, 10, .78)'; arrondi(x0 - 4, e[2] - 7, lt + 8, 14, 4); ctx.fill();
        ctx.fillStyle = e[4]; ctx.fillText(e[0], e[1], e[2]);
      });
      if (o.vwap) { ctx.font = police(600, 10); ctx.textAlign = 'left'; ctx.fillStyle = rgba(JAUNE, 0.95); ctx.fillText('VWAP', xOf(v.k) + 6, Y(v.cour.vwap) + 9); }

      // les signaux du scanner : les divergences prix / CVD et les absorptions
      var aff = function (i) { return borne((v.p - i - 1) / 1.2, 0, 1); };
      ctx.save(); ctx.beginPath(); ctx.rect(0, 0, W - axe - 2, H); ctx.clip();
      (o.signaux || []).forEach(function (s) {
        if (s.k === 'div') {
          var a = aff(s.i2); if (!a) return;
          var col = s.dir < 0 ? VIOLET : MENTHE, off = s.dir < 0 ? -9 : 9, b1 = bougies[s.i1], b2 = bougies[s.i2];
          var xa = xOf(s.i1), xz = xOf(s.i2), ya = Y(s.dir < 0 ? b1.h : b1.l) + off, yz = Y(s.dir < 0 ? b2.h : b2.l) + off;
          ctx.globalAlpha = a;
          ctx.strokeStyle = 'rgba(4, 3, 10, .85)'; ctx.lineWidth = 4.5; ctx.beginPath(); ctx.moveTo(xa, ya); ctx.lineTo(xz, yz); ctx.stroke();
          ctx.strokeStyle = rgba(col, 1); ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(xa, ya); ctx.lineTo(xz, yz); ctx.stroke();
          ctx.fillStyle = rgba(col, 1); ctx.beginPath(); ctx.arc(xa, ya, 2.5, 0, 6.2832); ctx.arc(xz, yz, 2.5, 0, 6.2832); ctx.fill();
          ctx.font = police(700, 10.5); ctx.textAlign = 'center'; ctx.fillText(s.dir < 0 ? 'DIV ▼' : 'DIV ▲', xz, yz + (s.dir < 0 ? -11 : 12));
          if (cvdH) {                                   // la même comparaison sur le CVD : lui ne confirme pas
            var ca = YC(b1.cvd), cz = YC(b2.cvd);
            ctx.lineWidth = 1.6; ctx.beginPath(); ctx.moveTo(xa, ca); ctx.lineTo(xz, cz); ctx.stroke();
            ctx.beginPath(); ctx.arc(xa, ca, 2.2, 0, 6.2832); ctx.arc(xz, cz, 2.2, 0, 6.2832); ctx.fill();
          }
          ctx.globalAlpha = 1; ctx.lineWidth = 1;
        } else {
          var ap = aff(s.i); if (!ap) return;
          var cA = s.dir > 0 ? MENTHE : VIOLET, x = xOf(s.i), y = Y(s.niveau) + (s.dir > 0 ? 9 : -9), r = (petit ? 5 : 6) * rebond(ap), plein = v.p >= s.conf + 1;
          ctx.globalAlpha = ap;
          ctx.beginPath(); ctx.moveTo(x, y - r); ctx.lineTo(x + r, y); ctx.lineTo(x, y + r); ctx.lineTo(x - r, y); ctx.closePath();
          ctx.strokeStyle = 'rgba(4, 3, 10, .9)'; ctx.lineWidth = 4; ctx.stroke();
          ctx.fillStyle = plein ? rgba(cA, 1) : 'rgba(4, 3, 10, .85)'; ctx.fill();
          ctx.strokeStyle = rgba(cA, 1); ctx.lineWidth = 1.6; ctx.stroke(); ctx.lineWidth = 1;
          if (!petit) { ctx.font = police(700, 10); ctx.textAlign = 'left'; ctx.fillStyle = rgba(cA, 1); ctx.fillText('ABS ' + s.vol, x + r + 4, y + 0.5); }
          ctx.globalAlpha = 1;
        }
      });
      ctx.restore();

      // le CVD
      if (cvdH) {
        ctx.font = police(600, 10.5); ctx.textAlign = 'left'; ctx.fillStyle = '#9A95B8'; ctx.fillText('CVD', 6, hautC + 6);
        ctx.save(); ctx.beginPath(); ctx.rect(0, hautC, xD + bw, basC - hautC); ctx.clip();
        ctx.beginPath();
        for (i = i0; i <= v.k; i++) { var yv = YC(bg(i).cvd); if (i === i0) ctx.moveTo(xOf(i), yv); else ctx.lineTo(xOf(i), yv); }
        ctx.strokeStyle = rgba(LIGNE, 0.95); ctx.lineWidth = 1.5; ctx.lineJoin = 'round'; ctx.stroke(); ctx.lineWidth = 1;
        ctx.restore();
      }

      // la liste des gros trades, en haut à gauche, comme le panneau d'Omegga
      if (o.ruban && !petit) {
        var der4 = gros.filter(function (gt) { return gt.t <= v.p; }).slice(-4).reverse();
        var lx = 0, ly = 24, lw = 214, lh = 26 + der4.length * 21;
        ctx.fillStyle = 'rgba(7, 6, 15, .86)'; arrondi(lx, ly, lw, lh, 9); ctx.fill();
        ctx.strokeStyle = 'rgba(255, 255, 255, .09)'; ctx.stroke();
        ctx.font = police(600, 11); ctx.textAlign = 'left'; ctx.fillStyle = '#ECE9FB'; ctx.fillText('Gros trades', lx + 10, ly + 13);
        ctx.font = police(500, 10.5); ctx.textAlign = 'right'; ctx.fillStyle = '#615C80'; ctx.fillText('≥ 20 contrats', lx + lw - 10, ly + 13); ctx.textAlign = 'left';
        der4.forEach(function (gt, k) {
          var yy = ly + 34 + k * 21, neuf = Math.max(0, 1 - (v.p - gt.t) * PERIODE / 1200), col = gt.q > 0 ? MENTHE : VIOLET;
          if (neuf > 0) { ctx.fillStyle = rgba(col, 0.18 * neuf); ctx.fillRect(lx + 4, yy - 10, lw - 8, 20); }
          ctx.font = police(500, 11); ctx.textAlign = 'left'; ctx.fillStyle = '#9A95B8';
          ctx.fillText(heure(BASE + Math.floor(gt.t * 60)), lx + 10, yy);
          ctx.fillStyle = rgba(col, 1); ctx.font = police(650, 11); ctx.fillText(gt.q > 0 ? 'Achat' : 'Vente', lx + 70, yy);
          ctx.textAlign = 'right'; ctx.fillText(espace(gt.q), lx + 142, yy);
          ctx.font = police(500, 11); ctx.fillStyle = '#ECE9FB'; ctx.fillText(prixTexte(gt.p), lx + lw - 10, yy);
        });
      }

      // le dernier prix sur l'axe, et l'en-tête de la bougie en cours
      var der = v.cour, yd = Y(der.c), mont = der.c >= der.o;
      ctx.fillStyle = rgba(mont ? MENTHE : VIOLET, 0.5); ctx.fillRect(xOf(v.k), Math.round(yd), xA - xOf(v.k), 1);
      ctx.fillStyle = rgba(mont ? MENTHE : VIOLET, 1); arrondi(W - axe + 3, yd - 10, axe - 5, 20, 5); ctx.fill();
      ctx.font = police(650, 11); ctx.textAlign = 'left'; ctx.fillStyle = '#04030A'; ctx.fillText(prixTexte(der.c), W - axe + 8, yd + 0.5);
      ctx.font = police(650, 12); ctx.fillStyle = '#ECE9FB'; ctx.fillText('NQ · 1 min', 0, 10);
      if (!petit) {
        var delta = Math.round(der.cvd - bougies[v.k - 1].cvd), xt = 78;
        ctx.font = police(500, 11.5);
        [['O', der.o], ['H', der.h], ['B', der.l], ['C', der.c]].forEach(function (z) {
          ctx.fillStyle = '#615C80'; ctx.fillText(z[0], xt, 10); xt += ctx.measureText(z[0]).width + 4;
          ctx.fillStyle = '#9A95B8'; var t = prixTexte(z[1]); ctx.fillText(t, xt, 10); xt += ctx.measureText(t).width + 10;
        });
        ctx.fillStyle = rgba(delta >= 0 ? MENTHE : VIOLET, 1); ctx.fillText('Δ ' + (delta >= 0 ? '+' : '−') + Math.abs(delta), xt, 10);
      }
    }
    return { init: init, avancer: avancer, dessiner: dessiner, lecture: lecture };
  }

  /* ================= 2. Le scanner : il pose ses signaux sur le graphique ================= */
  // Les divergences prix / CVD (DIV ▲▼) et les absorptions (losange creux = en cours, plein = confirmée),
  // avec le profil de volume (POC, VAH, VAL), deux niveaux clés et le CVD en dessous.
  var scanner = graphiqueOmegga({
    graine: 1312, debut: 70, n: 104, profil: true, cvd: true,
    cles: [[0, 24249], [9, 24257], [16, 24253], [26, 24263], [34, 24272], [40, 24267], [47, 24283], [52, 24291], [59, 24300.5], [64, 24291],
      [70, 24298], [76, 24305.5], [79, 24300], [84, 24282], [86, 24288], [90, 24277], [93, 24283], [97, 24290], [103, 24294]],
    cvdc: [[0, -60], [9, 40], [16, 10], [26, 120], [34, 260], [40, 180], [47, 380], [52, 470], [59, 640], [64, 480],
      [70, 530], [76, 555], [79, 470], [84, 190], [86, 255], [90, 280], [93, 350], [97, 430], [103, 470]],
    corriger: function (B) {                          // des sommets et des creux nets pour les deux divergences
      var i;
      for (i = 52; i < 82; i++) B[i].h = Math.min(B[i].h, 24304.75);
      B[59].h = 24301.25; B[76].h = 24306.25;
      for (i = 80; i < 100; i++) B[i].l = Math.max(B[i].l, 24278.25);
      B[84].l = 24281.5; B[90].l = 24276.25;
    },
    niveaux: [[24306.5, 'Haut Asie', 'Haut Asie', '255, 138, 101', true], [24277, 'QQQ 650 · mur des puts', 'Mur des puts', '236, 233, 251', false]],
    signaux: [
      { k: 'abs', dir: 1, i: 40, niveau: 24265.75, conf: 42, vol: 214 },
      { k: 'div', dir: -1, i1: 59, i2: 76 },
      { k: 'abs', dir: -1, i: 76, niveau: 24305.75, conf: 79, vol: 412 },
      { k: 'div', dir: 1, i1: 84, i2: 90 },
      { k: 'abs', dir: 1, i: 90, niveau: 24276.75, conf: 93, vol: 386 }
    ],
    lecture: function (v) {
      var k = v.k;
      if (k > 93) return ['Absorption au creux', 'Les vendeurs sont absorbés sur le mur des puts : le losange est plein, le prix repart.'];
      if (k > 90) return ['Divergence ▲ au creux', 'Le prix fait un plus bas, le CVD non : les vendeurs s\'essoufflent.'];
      if (k > 79) return ['Absorption au sommet', 'Des achats absorbés sous le haut d\'Asie : le losange est plein, c\'est confirmé.'];
      if (k > 76) return ['Divergence ▼ au sommet', 'Le prix fait un plus haut, le CVD non : les acheteurs s\'essoufflent.'];
      return ['Le prix monte vers le haut d\'Asie', 'Le scanner surveille les divergences et les absorptions.'];
    }
  });

  /* ================= 3. Les gros trades en bulles, sur le graphique ================= */
  // Une bulle par ordre au marché d'au moins 20 contrats (réglage d'Omegga), à son heure et à son prix : menthe = achat, violet = vente.
  // Comme sur le vrai NQ, il y en a peu en temps normal, et beaucoup plus aux moments qui comptent.
  // L'histoire : une cassure portée par de gros acheteurs, un gros vendeur au sommet, puis les acheteurs qui défendent la VWAP.
  var GROS = [[78, 0.35, 184, 'La cassure : 184 contrats achetés d\'un coup.'], [79, 0.7, 236, '236 contrats achetés au marché : plus il y en a, plus la bulle est grosse.'],
    [81, 0.3, 142, 'Les acheteurs continuent : le prix sort de la zone.'], [87, 0.55, -212, 'Un gros vendeur au sommet : 212 contrats, le prix cale.'],
    [88, 0.25, -96, 'Encore 96 contrats vendus : le prix revient vers la VWAP.'], [96, 0.6, 168, 'Les acheteurs défendent la VWAP : 168 contrats d\'un coup.']];
  var bulles = graphiqueOmegga({
    graine: 2024, debut: 70, n: 104, vwap: true, ruban: true,
    cles: [[0, 24231], [12, 24240], [22, 24236], [34, 24247], [44, 24243], [54, 24252], [62, 24249], [70, 24253], [76, 24251], [78, 24257],
      [82, 24270], [87, 24276], [92, 24266], [95, 24263], [99, 24271], [103, 24275]],
    cvdc: [[0, 0], [22, 60], [44, 140], [70, 230], [76, 210], [82, 520], [87, 610], [95, 430], [103, 560]],
    gros: function (h, B) {
      var liste = [], i;
      for (i = 0; i < B.length; i++) {                // les petits gros trades de tous les jours
        // rares en temps normal : un toutes les 10 à 15 bougies, 20 à 50 contrats
        if (h() < 0.08) { var monte = B[i].c >= B[i].o, cote = h() < (monte ? 0.7 : 0.3) ? 1 : -1; liste.push({ i: i, f: 0.15 + h() * 0.7, q: cote * Math.round(21 + h() * h() * 30) }); }
      }
      // plus nombreux aux moments qui comptent : la cassure, le sommet, la VWAP
      [[77, 0.8, 34], [80, 0.55, 58], [82, 0.45, 29], [86, 0.85, -41], [87, 0.9, -63], [95, 0.8, 47], [97, 0.35, 31]].forEach(function (g) { liste.push({ i: g[0], f: g[1], q: g[2] }); });
      GROS.forEach(function (g) { liste.push({ i: g[0], f: g[1], q: g[2], texte: g[3] }); });
      return liste.sort(function (a, b) { return a.i + a.f - b.i - b.f; });
    },
    lecture: function (v, gros) {
      var der = null;
      gros.forEach(function (g) { if (g.texte && g.t <= v.p) der = g; });
      if (!der) return ['Les gros trades en bulles', 'Une bulle par gros ordre au marché : menthe = achat, violet = vente.'];
      return [espace(der.q) + ' contrats ' + (der.q > 0 ? 'à l\'achat' : 'à la vente'), der.texte];
    }
  });

  /* ================= la vitrine ================= */
  var SCENES = [dom, scanner, bulles];
  var actuelle = 0, precedente = -1, debut = 0, maintenant = 0, avant = 0, visible = true, dernierTexte = '';

  function mesurer() {
    var r = toile.getBoundingClientRect();
    if (!r.width) return false;
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    W = r.width; H = r.height; petit = W < 400;
    toile.width = Math.round(W * dpr); toile.height = Math.round(H * dpr);
    return true;
  }
  function choisir(i, sansFondu) {
    if (i === actuelle && !sansFondu) return;
    precedente = sansFondu ? -1 : actuelle;
    actuelle = i; debut = maintenant;
    SCENES[i].init();
    onglets.forEach(function (o, k) {
      o.setAttribute('aria-pressed', k === i ? 'true' : 'false');
      var barre = o.querySelector('i'); if (barre) barre.style.transform = 'scaleX(' + (k < i ? 1 : 0) + ')';
    });
  }
  function ecrireLecture() {
    if (!lecture) return;
    var l = SCENES[actuelle].lecture(), t = l[0] + '|' + l[1];
    if (t === dernierTexte) return;
    dernierTexte = t;
    lectureTitre.textContent = l[0]; lectureTexte.textContent = l[1];
    lecture.classList.remove('change'); void lecture.offsetWidth; lecture.classList.add('change');
  }
  function dessinerScene(s, alpha, glisse) {
    if (alpha <= 0) return;
    ctx.save(); ctx.globalAlpha = alpha; ctx.translate(0, glisse);
    SCENES[s].dessiner();
    ctx.restore();
  }
  var sale = true;                                   // en « mouvement réduit », on ne redessine que si quelque chose a changé
  function image(t) {
    window.requestAnimationFrame(image);
    var dt = avant ? Math.min(64, t - avant) : 16; avant = t;
    if (!visible || document.hidden || !W) return;
    if (calme && !sale) return;
    sale = false;
    if (!calme) maintenant += dt;
    var ecoule = maintenant - debut;
    if (!calme && ecoule >= DUREE) { choisir((actuelle + 1) % SCENES.length); ecoule = 0; }
    if (!calme) {
      SCENES[actuelle].avancer(dt);
      if (precedente >= 0 && ecoule < FONDU) SCENES[precedente].avancer(dt);
    }
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, W, H);
    var sortie = calme ? 1 : lisse(ecoule / (FONDU * 0.55)), a = calme ? 1 : lisse((ecoule - FONDU * 0.3) / (FONDU * 0.7));
    if (precedente >= 0 && sortie < 1) dessinerScene(precedente, 1 - sortie, -10 * sortie);
    dessinerScene(actuelle, a, 14 * (1 - a));
    var barre = onglets[actuelle] && onglets[actuelle].querySelector('i');
    if (barre && !calme) barre.style.transform = 'scaleX(' + borne(ecoule / DUREE, 0, 1).toFixed(4) + ')';
    ecrireLecture();
  }
  // en « mouvement réduit » : chaque écran est montré à un moment parlant, sans bouger
  function figer(k) { precedente = -1; SCENES[k].avancer(k === 0 ? 4200 : k === 1 ? 6900 : 6200); sale = true; }

  function demarrer() {
    boite.setAttribute('data-vif', '');
    if (!mesurer()) return;
    choisir(0, true);
    if (calme) figer(0);
    onglets.forEach(function (o, k) {
      o.addEventListener('click', function () {
        if (k === actuelle) return;
        choisir(k);
        if (calme) figer(k);
      });
    });
    window.requestAnimationFrame(image);
  }
  window.addEventListener('resize', function () { if (boite.hasAttribute('data-vif') && mesurer()) sale = true; });
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (e) { visible = e[0].isIntersecting; if (visible) sale = true; }, { threshold: 0.05 }).observe(boite);
  }
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(demarrer); else demarrer();
})();
