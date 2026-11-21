/* Noweiz Academy, les petits plus des pages de compte. Sans ce script, tout marche quand même. */
(function () {
  'use strict';
  function tous(s) { return [].slice.call(document.querySelectorAll(s)); }

  /* afficher ou cacher un mot de passe */
  tous('[data-voir]').forEach(function (b) {
    var champ = document.getElementById(b.getAttribute('data-voir'));
    if (!champ) return;
    b.hidden = false;
    b.addEventListener('click', function () {
      var voir = champ.type === 'password';
      champ.type = voir ? 'text' : 'password';
      b.textContent = voir ? 'Cacher' : 'Afficher';
      b.setAttribute('aria-pressed', String(voir));
      champ.focus();
    });
    /* le mot de passe ne part jamais affiché en clair dans l'historique du navigateur */
    if (champ.form) champ.form.addEventListener('submit', function () { champ.type = 'password'; });
  });

  /* copier un lien d'invitation */
  tous('[data-copier]').forEach(function (b) {
    var champ = document.getElementById(b.getAttribute('data-copier'));
    var etat = document.getElementById('copie-etat');
    if (!champ) return;
    b.hidden = false;
    champ.addEventListener('focus', function () { champ.select(); });
    b.addEventListener('click', function () {
      function dit(t) { if (etat) etat.textContent = t; }
      function secours() { champ.focus(); champ.select(); try { document.execCommand('copy'); dit('Lien copié.'); } catch (e) { dit('Sélectionne le lien et copie-le à la main.'); } }
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(champ.value).then(function () { dit('Lien copié.'); }, secours);
      else secours();
    });
  });
})();
