/* Le nouveau style : ce qui bouge dans la page elle-même (les rubans de lumière sont dans rubans.js).
   - le haut de page prend un fond en verre dès qu'on défile ;
   - le bloc vert s'ouvre au défilement, sa phrase s'allume mot à mot ;
   - la bougie en cours du footprint d'exemple continue de se remplir.
   Avec « mouvement réduit », rien de tout ça ne bouge : la page est complète sans. */
(function () {
  'use strict';
  var racine = document.documentElement;
  var calme = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var borne = function (x) { return x < 0 ? 0 : x > 1 ? 1 : x; };

  /* le haut de page */
  var haut = document.querySelector('.haut');
  /* le bloc vert */
  var rythme = document.querySelector('.rythme');
  var bloc = rythme && rythme.querySelector('.rythme-bloc');
  var mots = rythme ? Array.prototype.slice.call(rythme.querySelectorAll('.rythme-phrase span')) : [];
  var dits = -1;
  if (rythme && !calme && window.CSS && CSS.supports && CSS.supports('position', 'sticky')) racine.setAttribute('data-scene', '');

  var prevu = false;
  function suivre() {
    prevu = false;
    if (haut) { if (window.scrollY > 8) haut.setAttribute('data-pose', ''); else haut.removeAttribute('data-pose'); }
    if (!rythme || !racine.hasAttribute('data-scene')) return;
    var r = rythme.getBoundingClientRect();
    var course = r.height - window.innerHeight;
    if (course <= 0) return;
    // un peu avant d'arriver en haut de l'écran, le bloc commence déjà à s'ouvrir
    var p = borne((window.innerHeight * 0.35 - r.top) / (course + window.innerHeight * 0.35));
    var ouvre = borne(p / 0.34);
    bloc.style.setProperty('--ouvre', (ouvre * ouvre * (3 - 2 * ouvre)).toFixed(4));
    var n = Math.round(borne((p - 0.2) / 0.36) * mots.length);
    if (n !== dits) {
      for (var i = 0; i < mots.length; i++) mots[i].classList.toggle('dit', i < n);
      dits = n;
    }
    if (p > 0.6) rythme.setAttribute('data-fin', ''); else rythme.removeAttribute('data-fin');
  }
  function demander() { if (!prevu) { prevu = true; requestAnimationFrame(suivre); } }
  window.addEventListener('scroll', demander, { passive: true });
  window.addEventListener('resize', demander);
  suivre();

  /* le footprint d'exemple : la bougie en cours se remplit (chiffres inventés, c'est écrit dessous) */
  var grille = document.querySelector('.fp-grille');
  var delta = document.querySelector('.fp-pied [data-delta-vif]');
  if (grille && !calme) {
    var cases = Array.prototype.slice.call(grille.querySelectorAll('.fp-c.vif i'));
    var visible = true, minuteur = 0;
    var ecrire = function (n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); };
    var lire = function (el) { return parseInt(el.textContent.replace(/\D/g, ''), 10) || 0; };
    function battre() {
      if (!visible || document.hidden || !cases.length) return;
      var el = cases[Math.floor(Math.random() * cases.length)];
      el.textContent = ecrire(lire(el) + 1 + Math.floor(Math.random() * 7));
      el.classList.remove('touche');
      void el.offsetWidth;                          // relance l'éclat
      el.classList.add('touche');
      if (delta) {
        var d = 0;
        for (var i = 0; i < cases.length; i += 2) d += lire(cases[i + 1]) - lire(cases[i]);
        delta.textContent = (d > 0 ? '+' : d < 0 ? '−' : '') + ecrire(Math.abs(d));
        delta.className = 'k6 ' + (d >= 0 ? 'plus' : 'moins');
      }
    }
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (e) { visible = e[0].isIntersecting; }, { threshold: 0.1 }).observe(grille);
    }
    minuteur = window.setInterval(battre, 900);
  }
})();
