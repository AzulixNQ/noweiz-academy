/* Le footprint vivant du haut de page : un EXEMPLE dessiné, avec des chiffres inventés (c'est écrit sur la page).
   Huit bougies jouées en boucle. La bougie en cours se construit ordre par ordre, se ferme, et le graphique avance.
   Le scénario raconte une lecture : des achats empilés, une absorption en haut, les vendeurs qui reprennent, le bas défendu.
   La carte « Lecture » et la carte « Delta cumulé » suivent ce qui se passe.

   Sans JavaScript, ou en « mouvement réduit », la page garde la grille fixe écrite dans le HTML. */
(function () {
  'use strict';
  var boite = document.querySelector('[data-footprint]');
  if (!boite || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var toile = boite.querySelector('canvas');
  if (!toile || !toile.getContext) return;
  var ctx = toile.getContext('2d');
  var lecture = document.querySelector('[data-lecture]');
  var lectureTitre = lecture && lecture.querySelector('b');
  var lectureTexte = lecture && lecture.querySelector('span');
  var cvdNombre = document.querySelector('[data-cvd]');
  var cvdToile = document.querySelector('[data-cvd-courbe]');
  var cvdCtx = cvdToile && cvdToile.getContext ? cvdToile.getContext('2d') : null;

  /* Les huit bougies. n : niveau de prix (1 = le plus haut) → [ventes, achats, marque]. o, c : niveaux d'ouverture et de clôture.
     Marques : a = les acheteurs dominent, v = les vendeurs dominent, f = des achats massifs que le prix n'arrive pas à suivre. */
  var BOUGIES = [
    { o: 11, c: 8, n: { 8: [12, 48], 9: [31, 140, 'a'], 10: [44, 168, 'a'], 11: [58, 96], 12: [40, 22] } },
    { o: 9, c: 6, n: { 6: [9, 61], 7: [22, 154, 'a'], 8: [36, 188, 'a'], 9: [51, 120], 10: [47, 35] } },
    { o: 7, c: 3, n: { 3: [18, 97, 'a'], 4: [27, 176, 'a'], 5: [39, 203, 'a'], 6: [62, 110], 7: [55, 41], 8: [30, 26] } },
    { o: 3, c: 2, n: { 1: [212, 1240, 'f'], 2: [187, 980, 'f'], 3: [96, 310, 'a'], 4: [74, 122], 5: [60, 58] } },
    { o: 2, c: 7, n: { 2: [44, 12], 3: [201, 63, 'v'], 4: [244, 71, 'v'], 5: [190, 88, 'v'], 6: [133, 90], 7: [97, 104] } },
    { o: 7, c: 8, n: { 6: [28, 9], 7: [76, 31, 'v'], 8: [64, 52], 9: [22, 18] } },
    { o: 8, c: 10, n: { 8: [41, 22], 9: [88, 34, 'v'], 10: [61, 57], 11: [25, 39] } },
    { o: 10, c: 11, n: { 9: [14, 8], 10: [47, 39], 11: [72, 88], 12: [33, 61, 'a'] } }
  ];
  var LECTURES = [
    ['Achats empilés', 'Les acheteurs dominent sur plusieurs niveaux de suite.'],
    ['Achats empilés', 'Les acheteurs dominent sur plusieurs niveaux de suite.'],
    ['Achats empilés', 'Trois niveaux de suite : la hausse est portée.'],
    ['Absorption', ''],
    ['Les vendeurs reprennent', 'Après l\'absorption, les ventes s\'empilent.'],
    ['La baisse ralentit', 'Moins de volume à chaque niveau.'],
    ['La baisse ralentit', 'Moins de volume à chaque niveau.'],
    ['Le bas est défendu', 'Des achats reviennent sur le niveau le plus bas.']
  ];
  var NIVEAUX = 12, PRIX_HAUT = 24315, PAS_PRIX = 0.5;
  var DUREE_ORDRE = 80, DUREE_GLISSE = 520;

  var espace = function (n) { return String(Math.abs(Math.round(n))).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); };
  var signe = function (n) { return (n > 0 ? '+' : n < 0 ? '−' : '') + espace(n); };

  /* Un tirage qui donne toujours la même suite : la boucle se rejoue à l'identique. */
  function tirage(graine) { var g = graine; return function () { g = (g * 16807) % 2147483647; return g / 2147483647; }; }

  /* La liste des ordres d'une bougie : le prix part de l'ouverture, va chercher un extrême, puis l'autre, et finit à la clôture. */
  function ordres(b, numero) {
    var hasard = tirage(1000 + numero * 77);
    var niveaux = Object.keys(b.n).map(Number), hautB = Math.min.apply(null, niveaux), basB = Math.max.apply(null, niveaux);
    var monte = b.c < b.o;
    var etapes = monte ? [b.o, basB, hautB, b.c] : [b.o, hautB, basB, b.c];
    var trajet = [], i, l;
    for (i = 0; i < etapes.length - 1; i++) {
      var pas = etapes[i + 1] > etapes[i] ? 1 : -1;
      for (l = etapes[i]; l !== etapes[i + 1]; l += pas) trajet.push(l);
    }
    trajet.push(b.c);
    var visites = {};
    trajet.forEach(function (x) { visites[x] = (visites[x] || 0) + 1; });
    var liste = [];
    trajet.forEach(function (x) {
      [0, 1].forEach(function (cote) {
        var part = b.n[x][cote] / visites[x];
        var morceaux = Math.max(1, Math.min(9, Math.round(part / 55)));
        for (var m = 0; m < morceaux; m++) liste.push({ l: x, cote: cote, q: part / morceaux, ordre: hasard() });
      });
    });
    // à l'intérieur d'un même passage sur un niveau, ventes et achats arrivent mêlés
    var sortie = [], debut = 0;
    while (debut < liste.length) {
      var fin = debut;
      while (fin < liste.length && liste[fin].l === liste[debut].l) fin++;
      sortie = sortie.concat(liste.slice(debut, fin).sort(function (x, y) { return x.ordre - y.ordre; }));
      debut = fin;
    }
    return sortie;
  }
  function pleine(numero) {
    var b = BOUGIES[numero], v = {};
    Object.keys(b.n).forEach(function (l) { v[l] = [b.n[l][0], b.n[l][1]]; });
    return { numero: numero, v: v, niveau: b.c, finie: true };
  }
  function delta(bg) { var d = 0; Object.keys(bg.v).forEach(function (l) { d += bg.v[l][1] - bg.v[l][0]; }); return d; }

  /* ---------- l'état ---------- */
  var vue = [pleine(6), pleine(7), pleine(0), pleine(1), pleine(2)];         // les bougies fermées à l'écran
  var cours = null, file = [], glisse = 0, glisseDepuis = 0, cumul = 0, courbe = [];
  var W = 0, H = 0, dpr = 1, colonnes = 6, police = '';
  var visible = true, minuteur = 0;

  function ouvrir(numero) {
    if (numero === 0) { cumul = 0; courbe = []; }                // une nouvelle séance : le delta cumulé repart de zéro
    cours = { numero: numero, v: {}, niveau: BOUGIES[numero].o, finie: false, touche: {} };
    file = ordres(BOUGIES[numero], numero);
    annoncer();
  }
  function annoncer() {
    if (!lecture) return;
    var t = LECTURES[cours.numero];
    if (lectureTitre.textContent !== t[0]) {
      lecture.classList.remove('change'); void lecture.offsetWidth; lecture.classList.add('change');
      lectureTitre.textContent = t[0];
    }
    if (cours.numero === 3) {
      var a = (cours.v[1] ? cours.v[1][1] : 0) + (cours.v[2] ? cours.v[2][1] : 0);
      lectureTexte.textContent = a < 400 ? 'Le prix revient tester le haut.' : espace(a) + ' achats sur deux niveaux, et le prix ne passe pas.';
    } else {
      lectureTexte.textContent = t[1];
    }
  }

  function battre() {
    if (!visible || document.hidden) return;
    if (glisse) return;
    if (!file.length) {
      // la bougie se ferme : le graphique avance d'une colonne
      cours.finie = true;
      vue.push(cours);
      var suivante = (cours.numero + 1) % BOUGIES.length;
      ouvrir(suivante);
      glisse = 1; glisseDepuis = performance.now();
      requestAnimationFrame(glisser);
      return;
    }
    var o = file.shift();
    if (!cours.v[o.l]) cours.v[o.l] = [0, 0];
    cours.v[o.l][o.cote] += o.q;
    cours.niveau = o.l;
    cours.touche[o.l + '-' + o.cote] = performance.now();
    cumul += o.cote === 1 ? o.q : -o.q;
    courbe.push(cumul);
    if (courbe.length > 90) courbe.shift();
    if (cours.numero === 3) annoncer();
    dessiner();
    dessinerCumul();
  }
  function glisser(t) {
    var p = Math.min(1, (t - glisseDepuis) / DUREE_GLISSE);
    glisse = p < 1 ? Math.max(0.0001, p) : 0;
    if (!glisse && vue.length > colonnes - 1) vue = vue.slice(vue.length - (colonnes - 1));
    dessiner();
    if (glisse) requestAnimationFrame(glisser);
  }

  /* ---------- le dessin ---------- */
  function mesurer() {
    var r = toile.getBoundingClientRect();
    if (!r.width) return;
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    W = r.width; H = r.height;
    toile.width = Math.round(W * dpr); toile.height = Math.round(H * dpr);
    colonnes = W < 430 ? 4 : W < 560 ? 5 : 6;
    police = (W < 430 ? 11.5 : 12.5) + 'px "Nojah Sans", system-ui, sans-serif';
    if (vue.length > colonnes - 1) vue = vue.slice(vue.length - (colonnes - 1));
  }
  function arrondi(x, y, l, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y); ctx.lineTo(x + l - r, y); ctx.quadraticCurveTo(x + l, y, x + l, y + r);
    ctx.lineTo(x + l, y + h - r); ctx.quadraticCurveTo(x + l, y + h, x + l - r, y + h);
    ctx.lineTo(x + r, y + h); ctx.quadraticCurveTo(x, y + h, x, y + h - r);
    ctx.lineTo(x, y + r); ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
  }
  function dessiner() {
    if (!W) return;
    var maintenant = performance.now();
    var axe = W < 430 ? 58 : 66, pied = 34;
    var hGrille = H - pied, ligne = hGrille / NIVEAUX, col = (W - axe) / colonnes;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, W, H);
    ctx.textBaseline = 'middle';

    // les prix, à droite
    ctx.font = '500 ' + police;
    ctx.textAlign = 'right';
    for (var l = 1; l <= NIVEAUX; l++) {
      var prix = (PRIX_HAUT - (l - 1) * PAS_PRIX).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
      var ici = cours && cours.niveau === l && !glisse;
      if (ici) {
        ctx.fillStyle = '#C6F432';
        arrondi(W - axe + 6, (l - 1) * ligne + 3, axe - 6, ligne - 6, 6); ctx.fill();
        ctx.fillStyle = '#0B0B0C';
      } else {
        ctx.fillStyle = '#7C807E';
      }
      ctx.fillText(prix, W - 6, (l - 0.5) * ligne + 0.5);
    }
    ctx.fillStyle = '#7C807E';
    ctx.fillText('Delta', W - 6, hGrille + pied / 2 + 4);

    // le filet au-dessus des deltas
    ctx.fillStyle = 'rgba(255,255,255,.1)';
    ctx.fillRect(0, hGrille + 5, W, 1);

    // les bougies : pendant la glissade, celle qui sort à gauche est encore là
    ctx.save();
    ctx.beginPath(); ctx.rect(0, 0, W - axe, H); ctx.clip();
    var toutes = vue.concat(cours ? [cours] : []);
    var doux = glisse ? glisse * glisse * (3 - 2 * glisse) : 0;
    var depart = toutes.length - colonnes - (glisse ? 1 : 0);
    for (var j = Math.max(0, depart); j < toutes.length; j++) {
      var bg = toutes[j], b = BOUGIES[bg.numero];
      var x0 = (j - depart - (glisse ? doux : 0)) * col;
      var niveauxB = Object.keys(bg.v).map(Number);
      niveauxB.forEach(function (n) {
        var y = (n - 1) * ligne + 1, lCase = col - 9, x = x0 + 7, moitie = lCase / 2;
        var marque = b.n[n][2] || '';
        var montre = marque && (bg.finie || bg.v[n][marque === 'v' ? 0 : 1] >= b.n[n][marque === 'v' ? 0 : 1] * 0.55);
        ctx.fillStyle = bg.finie ? 'rgba(255,255,255,.045)' : 'rgba(255,255,255,.075)';
        arrondi(x, y, lCase, ligne - 2, 5); ctx.fill();
        if (montre) {
          ctx.fillStyle = marque === 'f' ? '#C6F432' : marque === 'a' ? 'rgba(198,244,50,.22)' : 'rgba(230,236,245,.18)';
          if (marque === 'v') { arrondi(x, y, moitie, ligne - 2, 5); } else { arrondi(x + moitie, y, moitie, ligne - 2, 5); }
          ctx.fill();
        }
        ctx.fillStyle = 'rgba(255,255,255,.07)';
        ctx.fillRect(x + moitie - 0.5, y + 5, 1, ligne - 12);
        [0, 1].forEach(function (cote) {
          var vif = !bg.finie && bg.touche[n + '-' + cote] ? Math.max(0, 1 - (maintenant - bg.touche[n + '-' + cote]) / 420) : 0;
          var surMarque = montre && ((marque === 'v' && cote === 0) || (marque !== 'v' && cote === 1));
          ctx.font = (surMarque && marque === 'f' ? '650 ' : '500 ') + police;
          ctx.fillStyle = surMarque ? (marque === 'f' ? '#0B0B0C' : marque === 'a' ? '#E9FBAE' : '#FFFFFF') : (vif > 0.05 ? '#FFFFFF' : '#B9BDBB');
          ctx.textAlign = 'center';
          ctx.fillText(espace(bg.v[n][cote]), x + moitie * (cote + 0.5), y + (ligne - 2) / 2 + 0.5);
        });
      });
      // le corps de la bougie : de l'ouverture au prix du moment (ou à la clôture)
      var fin = bg.finie ? b.c : bg.niveau, hautC = Math.min(b.o, fin), basC = Math.max(b.o, fin);
      ctx.fillStyle = fin <= b.o ? '#C6F432' : '#E6ECF5';
      arrondi(x0 + 1, (hautC - 1) * ligne + 3, 3, (basC - hautC + 1) * ligne - 6, 1.5); ctx.fill();
      // son delta
      var d = delta(bg);
      ctx.font = '600 ' + police;
      ctx.textAlign = 'center';
      ctx.fillStyle = d >= 0 ? '#C6F432' : '#E6ECF5';
      ctx.fillText(signe(d), x0 + 7 + (col - 9) / 2, hGrille + pied / 2 + 4);
    }
    ctx.restore();
  }

  function dessinerCumul() {
    if (cvdNombre) cvdNombre.textContent = signe(cumul);
    if (!cvdCtx || courbe.length < 2) return;
    var r = cvdToile.getBoundingClientRect();
    if (!r.width) return;
    var d = Math.min(window.devicePixelRatio || 1, 2);
    if (cvdToile.width !== Math.round(r.width * d)) { cvdToile.width = Math.round(r.width * d); cvdToile.height = Math.round(r.height * d); }
    cvdCtx.setTransform(d, 0, 0, d, 0, 0);
    cvdCtx.clearRect(0, 0, r.width, r.height);
    var min = Math.min.apply(null, courbe), max = Math.max.apply(null, courbe), plage = Math.max(1, max - min);
    var X = function (i) { return 2 + (r.width - 6) * i / 89; };
    var Y = function (v) { return r.height - 4 - (r.height - 8) * (v - min) / plage; };
    cvdCtx.beginPath();
    courbe.forEach(function (v, i) { if (i) cvdCtx.lineTo(X(i), Y(v)); else cvdCtx.moveTo(X(i), Y(v)); });
    cvdCtx.lineWidth = 1.6; cvdCtx.lineJoin = 'round'; cvdCtx.strokeStyle = '#C6F432';
    cvdCtx.stroke();
    cvdCtx.beginPath();
    cvdCtx.arc(X(courbe.length - 1), Y(courbe[courbe.length - 1]), 3, 0, Math.PI * 2);
    cvdCtx.fillStyle = '#FFFFFF';
    cvdCtx.fill();
  }

  function demarrer() {
    boite.setAttribute('data-vif', '');
    mesurer();
    ouvrir(3);                                       // on arrive au moment de l'absorption
    dessiner();
    minuteur = window.setInterval(battre, DUREE_ORDRE);
  }
  window.addEventListener('resize', function () { mesurer(); dessiner(); });
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (e) { visible = e[0].isIntersecting; }, { threshold: 0.05 }).observe(boite);
  }
  // les chiffres attendent la police du site, sinon leur largeur change en route
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(demarrer); else demarrer();
})();
