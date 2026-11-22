/* Le nouveau style : ce qui bouge dans la page elle-même (les rubans de lumière sont dans rubans.js, le footprint
   vivant dans footprint.js).
   - le haut de page prend un fond dès qu'on défile ;
   - le parcours : la partie reste à l'écran pendant qu'on défile, et les huit semaines s'allument une à une
     (la page écrit l'avancée dans data-avance, rubans.js allume le ruban jusque-là) ;
   - le relief : les plans de verre du footprint suivent légèrement la souris.
   Avec « mouvement réduit », rien de tout ça ne bouge : la page est complète sans. */
(function () {
  'use strict';
  var racine = document.documentElement;
  var calme = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var borne = function (x) { return x < 0 ? 0 : x > 1 ? 1 : x; };

  var haut = document.querySelector('.haut');
  var parcours = document.querySelector('[data-chemin]');
  var etapes = parcours ? Array.prototype.slice.call(parcours.querySelectorAll('.etape')) : [];
  var allumees = -2;
  var scene = parcours && !calme && window.CSS && CSS.supports && CSS.supports('position', 'sticky');
  if (scene) racine.setAttribute('data-scene', '');

  function allumer(n) {                          // n : le numéro de la dernière semaine allumée (0 à 7), -1 pour aucune
    if (n === allumees) return;
    for (var i = 0; i < etapes.length; i++) etapes[i].classList.toggle('allumee', i <= n);
    allumees = n;
  }

  var prevu = false;
  function suivre() {
    prevu = false;
    if (haut) { if (window.scrollY > 8) haut.setAttribute('data-pose', ''); else haut.removeAttribute('data-pose'); }
    if (!scene) return;
    var r = parcours.getBoundingClientRect();
    var course = r.height - window.innerHeight;
    if (course <= 0) return;
    // la lumière part un peu avant que la partie se fixe à l'écran, et elle est arrivée au bout avant qu'elle reparte
    var avant = window.innerHeight * 0.3;
    var p = borne((avant - r.top) / (course + avant));
    var avance = borne((p - 0.1) / 0.72) * 7.5 - 0.5;
    parcours.setAttribute('data-avance', avance.toFixed(3));
    allumer(Math.floor(avance + 0.06));
    if (avance >= 6.98) parcours.setAttribute('data-fin', ''); else parcours.removeAttribute('data-fin');
  }
  function demander() { if (!prevu) { prevu = true; requestAnimationFrame(suivre); } }
  window.addEventListener('scroll', demander, { passive: true });
  window.addEventListener('resize', demander);
  if (parcours && !scene) { allumer(7); parcours.setAttribute('data-fin', ''); }
  suivre();

  /* le relief : seulement avec une souris, et sur un écran assez large pour que le footprint soit à côté du texte */
  var relief = document.querySelector('[data-relief]');
  var hero = document.querySelector('.hero');
  var souris = window.matchMedia('(hover: hover) and (pointer: fine)');
  if (relief && hero && !calme) {
    var cx = 0, cy = 0, vx = 0, vy = 0, tourne = false;
    var pas = function () {
      vx += (cx - vx) * 0.08; vy += (cy - vy) * 0.08;
      relief.style.setProperty('--ry', (vx * 5).toFixed(3) + 'deg');
      relief.style.setProperty('--rx', (-vy * 4).toFixed(3) + 'deg');
      if (Math.abs(cx - vx) + Math.abs(cy - vy) > 0.002) requestAnimationFrame(pas); else tourne = false;
    };
    var viser = function (x, y) { cx = x; cy = y; if (!tourne) { tourne = true; requestAnimationFrame(pas); } };
    hero.addEventListener('pointermove', function (ev) {
      if (!souris.matches || window.innerWidth <= 1080) return;
      var r = hero.getBoundingClientRect();
      viser((ev.clientX - r.left) / r.width * 2 - 1, (ev.clientY - r.top) / r.height * 2 - 1);
    });
    hero.addEventListener('pointerleave', function () { viser(0, 0); });
  }
})();
