/* La page de vente d'Omegga : « au mois / à l'année ». Sans JavaScript, les deux prix et les deux boutons restent affichés. */
(function () {
  'use strict';
  var choix = document.querySelector('[data-periode-choix]');
  var formules = document.querySelector('[data-formules]');
  if (!choix || !formules) return;
  var boutons = [].slice.call(choix.querySelectorAll('[data-periode]'));
  function mettre(p) {
    formules.setAttribute('data-periode', p);
    boutons.forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-periode') === p ? 'true' : 'false'); });
  }
  boutons.forEach(function (b) { b.addEventListener('click', function () { mettre(b.getAttribute('data-periode')); }); });
  choix.hidden = false;
  mettre('mois');
})();
