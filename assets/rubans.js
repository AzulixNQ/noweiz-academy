/* Les rubans de lumière : le fond animé du site.

   Deux lumières, comme partout sur le site : le vert citron (Nojah, order flow) et le blanc (Weizix, ICT).
   En haut de page, deux grands rubans se pincent au même endroit (le « foyer », un élément de la page marqué
   data-foyer) ; plus bas, trois rubans calmes traversent l'écran.

   Un ruban = des dizaines de brins fins. Tout le calcul est fait par la carte graphique (WebGL) : la page ne
   calcule rien image par image. Trois niveaux, choisis tout seuls :
     - « plein »   ordinateur : tous les brins, halo, 60 images par seconde ;
     - « léger »   téléphone, ou ordinateur qui peine : moins de brins, pas de halo, 30 images par seconde ;
     - « fixe »    mouvement réduit demandé, ou appareil trop lent : une seule image, redessinée au défilement.
   Sans WebGL, rien ne se passe : la page garde ses halos en CSS. */
(function () {
  'use strict';
  var toile = document.getElementById('rubans');
  if (!toile || !window.WebGLRenderingContext) return;
  var racine = document.documentElement;
  var veutCalme = window.matchMedia('(prefers-reduced-motion: reduce)');
  var estPetit = window.matchMedia('(max-width: 860px), (pointer: coarse)');

  var NIVEAUX = {
    plein: { brinsHaut: 96, brinsBas: 40, pas: 132, densite: 1.5, demi: 7, halo: 0.11, alpha: 0.27, cadence: 1 },
    leger: { brinsHaut: 34, brinsBas: 16, pas: 84, densite: 1.25, demi: 2.6, halo: 0, alpha: 0.46, cadence: 2 }
  };
  var niveau = estPetit.matches ? 'leger' : 'plein';
  var fixe = veutCalme.matches;

  var SOMMETS = [
    'attribute vec4 a;',                       // t le long du brin, côté (-1 ou 1), numéro du brin (0 à 1), numéro du ruban
    'uniform vec2 u_taille;',                  // la toile, en pixels
    'uniform float u_px;',                     // pixels de toile pour un pixel de page
    'uniform float u_temps;',
    'uniform float u_defil;',                  // le défilement de la page, en pixels de toile
    'uniform vec2 u_foyer;',                   // là où les rubans du haut se pincent, en pixels de toile (page non défilée)
    'uniform mediump float u_demi;',           // demi-épaisseur d'un brin, en pixels de page (même précision que côté pixels)
    'uniform float u_alpha;',
    'uniform float u_portrait;',               // 1 sur un écran en hauteur (téléphone) : les rubans du haut tombent vers le bas
    'varying float v_d;',
    'varying vec3 v_c;',
    'const float PI = 3.14159265;',
    'float hasard(float n) { return fract(sin(n * 91.3458) * 47453.5453); }',

    // Le centre du ruban r au point t ; renvoie aussi sa torsion (en radians) et sa demi-largeur
    'vec2 centre(float r, float t, out float torsion, out float large) {',
    '  float W = u_taille.x, H = u_taille.y, tt = u_temps;',
    '  vec2 p;',
    '  if (r < 1.5) {',                        // les deux épingles du haut : verte (0) et blanche (1)
    '    float b = step(0.5, r);',
    '    float u = 2.0 * t - 1.0;',
    '    float po = u_portrait;',
    // écran large : une épingle couchée, ouverte vers la droite ; écran en hauteur : un chevron, la pointe en haut
    '    float A = mix(mix(1.08, 1.5, b) * max(W, H * 1.15), mix(1.3, 1.65, b) * H, po);',
    '    float B = mix(mix(0.64, 1.0, b) * H, mix(0.6, 0.98, b) * W, po);',
    '    float rot = mix(mix(0.09, -0.25, b), 1.5708 + mix(0.07, -0.13, b), po) + sin(tt * 0.11 + b * 2.0) * 0.04;',
    '    float M = mix(H, W * 1.15, po);',
    '    vec2 q = vec2(A * u * u + sin(u * 2.6 + tt * 0.21 + b) * 0.02 * M * abs(u),',
    '                  B * u + sin(u * 4.0 - tt * 0.17 + b * 3.0) * 0.024 * M * abs(u));',
    '    float c = cos(rot), s = sin(rot);',
    '    p = u_foyer + vec2(q.x * c - q.y * s, q.x * s + q.y * c);',
    '    p.y -= u_defil;',
    '    torsion = PI * 0.5 + (u + b * 0.06 + sin(tt * 0.09 + b * 1.7) * 0.035) * PI * mix(0.86, 0.6, b);',
    '    large = M * mix(0.17, 0.27, b) * (0.22 + 0.78 * u * u);',
    '  } else {',                              // les rubans calmes : ils traversent l'écran et glissent doucement quand on défile
    '    float k = r - 2.0;',
    '    float y0 = fract(0.24 + k * 0.31 - u_defil / (H * (7.0 + k * 2.0))) * 1.8 - 0.4;',
    '    p = vec2(mix(-0.14, 1.14, t) * W,',
    '             (y0 + sin(t * (2.1 + k * 0.7) + tt * 0.12 + k * 1.9) * 0.12 + sin(t * 5.3 - tt * 0.085 + k) * 0.045) * H);',
    '    torsion = t * PI * (1.5 + k * 0.45) + tt * 0.07 + k * 1.3;',
    '    large = H * 0.085 * (0.6 + 0.4 * sin(t * 3.1 + k * 2.0));',
    '  }',
    '  return p;',
    '}',

    'void main() {',
    '  float t = a.x, r = a.w;',
    '  float h1 = hasard(a.z * 37.0 + r * 3.1), h2 = hasard(a.z * 91.0 + r * 7.7), h3 = hasard(a.z * 13.0 + r * 1.3);',
    '  float tor, lar, tor2, lar2;',
    '  vec2 p = centre(r, t, tor, lar);',
    '  vec2 p2 = centre(r, t + 0.004, tor2, lar2);',
    '  vec2 tg = normalize(p2 - p + vec2(0.0001, 0.0));',
    '  vec2 n = vec2(-tg.y, tg.x);',
    '  float s = h1 * 2.0 - 1.0;',                                // la place du brin dans la largeur du ruban
    '  float ang = tor + (h2 - 0.5) * 0.42;',                     // chaque brin se pince un peu avant ou un peu après
    '  float ecart = s * lar * cos(ang) + sin(t * (6.0 + 5.0 * h3) + u_temps * (0.22 + 0.3 * h2) + h1 * 40.0) * lar * 0.04;',
    '  float fond = s * sin(ang);',                               // devant (1) ou derrière (-1)
    '  p += n * (ecart + a.y * u_demi * u_px);',
    '  v_d = a.y * u_demi;',
    // la lumière qui court le long du brin
    '  float vague = 0.5 + 0.5 * sin((t * (3.0 + 5.0 * h3) - u_temps * (0.05 + 0.07 * h2) + h1) * 2.0 * PI);',
    '  float eclat = pow(vague, 3.0);',
    '  float bouts = smoothstep(0.0, 0.1, t) * smoothstep(1.0, 0.9, t);',
    '  float blanc = step(0.5, r) * step(r, 1.5) + step(2.5, r) * step(r, 3.5);',   // les rubans 1 et 3 sont blancs
    '  vec3 sombre = mix(vec3(0.16, 0.34, 0.04), vec3(0.22, 0.27, 0.36), blanc);',
    '  vec3 clair = mix(vec3(0.776, 0.957, 0.196), vec3(0.90, 0.94, 1.0), blanc);',
    '  vec3 c = mix(sombre, clair, 0.25 + 0.75 * eclat);',
    '  c = mix(c, vec3(1.0, 1.0, 0.9), pow(vague, 10.0) * 0.45);',
    // force : les rubans du haut sont vifs ; les calmes restent en retrait, encore plus tant qu'on est en haut de page
    '  float haut = 1.0 - step(1.5, r);',
    '  float enBas = smoothstep(0.15, 0.9, u_defil / u_taille.y);',
    '  float force = mix(mix(0.2, 0.5, enBas), mix(1.0, 0.74, blanc), haut);',
    '  v_c = c * u_alpha * force * bouts * (0.5 + 0.5 * fond) * (0.3 + 0.7 * eclat);',
    '  gl_Position = vec4(p.x / u_taille.x * 2.0 - 1.0, 1.0 - p.y / u_taille.y * 2.0, 0.0, 1.0);',
    '}'
  ].join('\n');

  var PIXELS = [
    'precision mediump float;',
    'uniform float u_halo;',
    'uniform float u_demi;',
    'varying float v_d;',
    'varying vec3 v_c;',
    'void main() {',
    '  float d = v_d * v_d;',
    '  float bord = 1.0 - d / (u_demi * u_demi);',
    '  float f = exp(-d * 1.5) + u_halo * exp(-d * 0.05) * bord;',
    '  gl_FragColor = vec4(v_c * f, 1.0);',
    '}'
  ].join('\n');

  /* Les lueurs : deux grandes taches douces derrière le foyer, pour que les rubans éclairent le noir autour d'eux. */
  var SOMMETS_LUEUR = [
    'attribute vec2 a;',
    'uniform vec2 u_taille;',
    'uniform vec2 u_centre;',
    'uniform vec2 u_rayon;',
    'varying vec2 v_p;',
    'void main() {',
    '  v_p = a;',
    '  vec2 p = u_centre + a * u_rayon;',
    '  gl_Position = vec4(p.x / u_taille.x * 2.0 - 1.0, 1.0 - p.y / u_taille.y * 2.0, 0.0, 1.0);',
    '}'
  ].join('\n');
  var PIXELS_LUEUR = [
    'precision mediump float;',
    'uniform vec3 u_couleur;',
    'varying vec2 v_p;',
    'void main() {',
    '  float d = dot(v_p, v_p);',
    '  float f = exp(-d * 3.2) * (1.0 - smoothstep(0.7, 1.0, d));',
    '  gl_FragColor = vec4(u_couleur * f, 1.0);',
    '}'
  ].join('\n');

  var gl = null, prog = null, progLueur = null, tampon = null, tamponLueur = null, nbSommets = 0;
  var lieux = {}, lieuxLueur = {};
  var px = 1, large = 0, haut = 0;
  var foyer = { x: 0, y: 0 };
  var temps = 0, dernier = 0, image = 0, demande = 0, enRoute = false;
  var mesures = [], mesureDepuis = 0;

  function compiler(type, source) {
    var s = gl.createShader(type);
    gl.shaderSource(s, source);
    gl.compileShader(s);
    if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(s) || 'shader');
    return s;
  }
  function programme(sommets, pixels) {
    var p = gl.createProgram();
    gl.attachShader(p, compiler(gl.VERTEX_SHADER, sommets));
    gl.attachShader(p, compiler(gl.FRAGMENT_SHADER, pixels));
    gl.bindAttribLocation(p, 0, 'a');
    gl.linkProgram(p);
    if (!gl.getProgramParameter(p, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(p) || 'programme');
    return p;
  }

  /* Tous les brins de tous les rubans, en une seule liste de triangles. */
  function construire() {
    var n = NIVEAUX[niveau];
    var rubans = [n.brinsHaut, n.brinsHaut, n.brinsBas, n.brinsBas, n.brinsBas];
    var total = 0, r, i, j;
    for (r = 0; r < rubans.length; r++) total += rubans[r] * n.pas * 6;
    var d = new Float32Array(total * 4), k = 0;
    function sommet(t, cote, brin, ruban) { d[k++] = t; d[k++] = cote; d[k++] = brin; d[k++] = ruban; }
    for (r = 0; r < rubans.length; r++) {
      for (i = 0; i < rubans[r]; i++) {
        var brin = (i + 0.5) / rubans[r];
        for (j = 0; j < n.pas; j++) {
          var t0 = j / n.pas, t1 = (j + 1) / n.pas;
          sommet(t0, -1, brin, r); sommet(t0, 1, brin, r); sommet(t1, -1, brin, r);
          sommet(t1, -1, brin, r); sommet(t0, 1, brin, r); sommet(t1, 1, brin, r);
        }
      }
    }
    nbSommets = total;
    gl.bindBuffer(gl.ARRAY_BUFFER, tampon);
    gl.bufferData(gl.ARRAY_BUFFER, d, gl.STATIC_DRAW);
  }

  function preparer() {
    gl = toile.getContext('webgl', { alpha: false, antialias: false, depth: false, stencil: false, powerPreference: 'low-power', preserveDrawingBuffer: false });
    if (!gl) return false;
    try {
      prog = programme(SOMMETS, PIXELS);
      progLueur = programme(SOMMETS_LUEUR, PIXELS_LUEUR);
    } catch (e) {
      if (window.console && console.warn) console.warn('Rubans de lumière : ' + e.message);
      gl = null;
      return false;
    }
    ['u_taille', 'u_px', 'u_temps', 'u_defil', 'u_foyer', 'u_demi', 'u_alpha', 'u_halo', 'u_portrait'].forEach(function (u) { lieux[u] = gl.getUniformLocation(prog, u); });
    ['u_taille', 'u_centre', 'u_rayon', 'u_couleur'].forEach(function (u) { lieuxLueur[u] = gl.getUniformLocation(progLueur, u); });
    tampon = gl.createBuffer();
    tamponLueur = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, tamponLueur);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]), gl.STATIC_DRAW);
    construire();
    gl.disable(gl.DEPTH_TEST);
    gl.enable(gl.BLEND);
    gl.blendFunc(gl.ONE, gl.ONE);                 // les lumières s'additionnent : là où les brins se croisent, ça brille
    gl.enableVertexAttribArray(0);
    return true;
  }

  function mesurer() {
    var n = NIVEAUX[niveau];
    px = Math.min(window.devicePixelRatio || 1, n.densite);
    var l = Math.max(1, Math.round(toile.clientWidth * px)), h = Math.max(1, Math.round(toile.clientHeight * px));
    if (l !== large || h !== haut) { large = l; haut = h; toile.width = l; toile.height = h; }
    var ancre = document.querySelector('[data-foyer]');
    if (ancre) {
      var b = ancre.getBoundingClientRect();
      foyer.x = (b.left + b.width / 2) * px;
      foyer.y = (b.top + b.height / 2 + window.scrollY) * px;
    } else {
      foyer.x = large * 0.42; foyer.y = haut * 0.6;
    }
  }

  function dessiner() {
    var n = NIVEAUX[niveau];
    var defil = window.scrollY * px;
    gl.viewport(0, 0, large, haut);
    gl.clearColor(0.02, 0.024, 0.022, 1);
    gl.clear(gl.COLOR_BUFFER_BIT);

    // les lueurs, tant que le foyer n'est pas trop loin de l'écran
    var fy = foyer.y - defil;
    if (fy > -haut && fy < haut * 2) {
      gl.useProgram(progLueur);
      gl.bindBuffer(gl.ARRAY_BUFFER, tamponLueur);
      gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
      gl.uniform2f(lieuxLueur.u_taille, large, haut);
      var base = Math.max(large, haut), petit = Math.min(large, haut);
      var souffle = 1 + Math.sin(temps * 0.4) * 0.06;
      gl.uniform2f(lieuxLueur.u_centre, foyer.x + base * 0.05, fy);
      gl.uniform2f(lieuxLueur.u_rayon, base * 0.5 * souffle, base * 0.42 * souffle);
      gl.uniform3f(lieuxLueur.u_couleur, 0.11, 0.15, 0.02);
      gl.drawArrays(gl.TRIANGLES, 0, 6);
      gl.uniform2f(lieuxLueur.u_centre, foyer.x, fy);
      gl.uniform2f(lieuxLueur.u_rayon, petit * 0.2 * souffle, petit * 0.17 * souffle);
      gl.uniform3f(lieuxLueur.u_couleur, 0.30, 0.37, 0.10);
      gl.drawArrays(gl.TRIANGLES, 0, 6);
    }

    gl.useProgram(prog);
    gl.bindBuffer(gl.ARRAY_BUFFER, tampon);
    gl.vertexAttribPointer(0, 4, gl.FLOAT, false, 0, 0);
    gl.uniform2f(lieux.u_taille, large, haut);
    gl.uniform1f(lieux.u_px, px);
    gl.uniform1f(lieux.u_temps, temps);
    gl.uniform1f(lieux.u_defil, defil);
    gl.uniform2f(lieux.u_foyer, foyer.x, foyer.y);
    gl.uniform1f(lieux.u_demi, n.demi);
    gl.uniform1f(lieux.u_alpha, n.alpha);
    gl.uniform1f(lieux.u_halo, n.halo);
    gl.uniform1f(lieux.u_portrait, large / haut < 0.85 ? 1 : 0);
    gl.drawArrays(gl.TRIANGLES, 0, nbSommets);
  }

  /* Si l'appareil peine, on descend d'un niveau : plein → léger → fixe. */
  function surveiller(ecart) {
    if (performance.now() - mesureDepuis < 1500) return;         // on laisse la page finir de se charger
    mesures.push(ecart);
    if (mesures.length < 80) return;
    var tri = mesures.slice().sort(function (x, y) { return x - y; });
    var mediane = tri[40];
    mesures = [];
    var attendu = 1000 / 60 * NIVEAUX[niveau].cadence;
    if (mediane < attendu * 1.7) return;
    if (niveau === 'plein') {
      niveau = 'leger';
      construire();
      mesurer();
      mesureDepuis = performance.now();
    } else {
      fixe = true;
      arreter();
      racine.setAttribute('data-rubans', 'fixe');
      dessiner();
    }
  }

  function tour(maintenant) {
    demande = 0;
    if (!enRoute) return;
    demande = requestAnimationFrame(tour);
    image++;
    if (image % NIVEAUX[niveau].cadence !== 0) return;
    var ecart = dernier ? maintenant - dernier : 16;
    dernier = maintenant;
    if (ecart < 250) { temps += ecart / 1000; surveiller(ecart); }   // au retour d'un autre onglet, le temps ne saute pas
    dessiner();
  }
  function lancer() {
    if (enRoute || fixe || !gl || document.hidden) return;
    enRoute = true; dernier = 0; mesures = []; mesureDepuis = performance.now();
    demande = requestAnimationFrame(tour);
  }
  function arreter() {
    enRoute = false;
    if (demande) cancelAnimationFrame(demande);
    demande = 0;
  }
  var enAttente = false;
  function redessiner() {
    if (!gl || enRoute || enAttente) return;
    enAttente = true;
    requestAnimationFrame(function () { enAttente = false; if (gl && !enRoute) dessiner(); });
  }

  function demarrer() {
    if (!preparer()) return;
    temps = 40;                                    // un moment où les rubans sont déjà bien ouverts, pour l'image fixe
    mesurer();
    racine.setAttribute('data-rubans', fixe ? 'fixe' : 'animes');
    dessiner();
    lancer();
  }

  window.addEventListener('resize', function () { if (gl) { mesurer(); redessiner(); } });
  window.addEventListener('scroll', redessiner, { passive: true });
  window.addEventListener('load', function () { if (gl) { mesurer(); redessiner(); } });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { if (gl) { mesurer(); redessiner(); } });
  document.addEventListener('visibilitychange', function () { if (document.hidden) arreter(); else lancer(); });
  var surCalme = function () {
    fixe = veutCalme.matches;
    if (!gl) return;
    if (fixe) { arreter(); racine.setAttribute('data-rubans', 'fixe'); dessiner(); } else { racine.setAttribute('data-rubans', 'animes'); lancer(); }
  };
  if (veutCalme.addEventListener) veutCalme.addEventListener('change', surCalme);
  toile.addEventListener('webglcontextlost', function (ev) { ev.preventDefault(); arreter(); gl = null; racine.removeAttribute('data-rubans'); });
  toile.addEventListener('webglcontextrestored', demarrer);

  demarrer();
})();
