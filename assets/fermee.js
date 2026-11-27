/* Noweiz Academy, la page d'accueil (site fermé). Sans ce script, tout marche quand même :
   le formulaire part au serveur, qui vérifie tout. Ici : vérifier avant l'envoi, le compte à rebours,
   le mur des certificats et les onglets de l'aperçu. */
(function () {
  'use strict';
  function $(s, el) { return (el || document).querySelector(s); }
  function $$(s, el) { return [].slice.call((el || document).querySelectorAll(s)); }

  /* ---------- le compte à rebours (seulement si une date d'ouverture est réglée) ---------- */
  var cpt = $('#compteur');
  if (cpt) {
    var fin = parseInt(cpt.getAttribute('data-fin'), 10) * 1000;
    var deux = function (n) { return (n < 10 ? '0' : '') + n; };
    var tic = function () {
      var r = Math.max(0, Math.floor((fin - Date.now()) / 1000));
      $('#t-j').textContent = deux(Math.floor(r / 86400));
      $('#t-h').textContent = deux(Math.floor(r % 86400 / 3600));
      $('#t-m').textContent = deux(Math.floor(r % 3600 / 60));
      $('#t-s').textContent = deux(r % 60);
    };
    tic(); setInterval(tic, 1000);
  }

  /* ---------- la liste d'attente : vérifier avant d'envoyer ---------- */
  var form = $('#wl-form');
  function marque(champ, msg, ko) {
    if (champ) {
      if (ko) { champ.setAttribute('aria-invalid', 'true'); champ.setAttribute('aria-describedby', msg.id); }
      else { champ.removeAttribute('aria-invalid'); champ.removeAttribute('aria-describedby'); }
    }
    msg.hidden = !ko;
  }
  if (form) {
    form.addEventListener('submit', function (ev) {
      var p = $('#wl-prenom'), m = $('#wl-email'), a = $('#wl-accord');
      var koP = !p.value.trim(), koM = !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(m.value.trim()), koA = !a.checked;
      if (koP) $('#wl-prenom-err').textContent = 'Écris ton prénom.';
      if (koM) $('#wl-email-err').textContent = "Cette adresse n'est pas complète. Exemple : toi@exemple.fr";
      marque(p, $('#wl-prenom-err'), koP); marque(m, $('#wl-email-err'), koM); marque(a, $('#wl-accord-err'), koA);
      $('#wl-envoi-err').hidden = true;
      if (koP || koM || koA) { ev.preventDefault(); (koP ? p : koM ? m : a).focus(); return; }
      var b = $('#wl-envoi'); b.disabled = true; b.textContent = 'Inscription…';
    });
    /* le serveur a refusé : on remet le curseur sur le premier champ à corriger */
    if ($('#liste').hasAttribute('data-erreur')) { var ko = $('[aria-invalid="true"]', form); if (ko) ko.focus({ preventScroll: true }); }
  }
  /* après l'envoi : le titre « Plus qu'une étape » est lu par les lecteurs d'écran */
  var titre = $('#liste-titre[tabindex]');
  if (titre && location.hash === '#liste') titre.focus({ preventScroll: true });

  /* un bouton « Être prévenu » choisit déjà le parcours dans le formulaire */
  $$('[data-parcours]').forEach(function (b) {
    b.addEventListener('click', function () { var r = document.getElementById(b.getAttribute('data-parcours')); if (r) r.checked = true; });
  });

  /* ---------- les certificats de payout de Nojah, en deux rangées qui défilent ---------- */
  var CERTS = ['lucid-2026-06-11', 'lucid-2026-06-22', 'lucid-2026-07-17', 'lucid-2026-07-24', 'lucid-2026-07-31', 'lucid-2026-08-07', 'lucid-2026-08-12', 'lucid-2026-08-17', 'lucid-2026-08-18', 'lucid-2026-08-20', 'lucid-2026-10-20', 'lucid-2026-10-23', 'topstep-2026-11-19', 'topstep-2026-11-26'];
  var wall = $('#wall');
  if (wall) {
    for (var r = 0; r < 2; r++) {
      var row = document.createElement('div'); row.className = 'wall-row';
      var ordre = CERTS.slice(r * 6).concat(CERTS.slice(0, r * 6));
      ordre.concat(ordre).forEach(function (c) {
        var im = new Image(); im.src = '/assets/img/certificats/' + c + '.webp'; im.alt = ''; im.decoding = 'async'; im.loading = 'lazy';
        im.width = c.indexOf('topstep') > -1 ? 300 : 425; im.height = 300;
        if (c.indexOf('2026-10') > -1) im.className = 'doux';
        row.appendChild(im);
      });
      wall.appendChild(row);
    }
  }

  /* ---------- l'aperçu de l'espace élève : trois écrans ---------- */
  var SHOTS = {
    espace: ["Aperçu du tableau de bord de l'élève : module de la semaine, prochain live, progression et classement.", "Le tableau de bord : ce que tu as à faire cette semaine, d'un coup d'œil."],
    modules: ["Aperçu du parcours : les modules validés, le module en cours et les modules verrouillés.", "Ton parcours : les modules validés, celui en cours, et la date d'ouverture des suivants."],
    module: ["Aperçu d'un module : la vidéo de la leçon, la liste des leçons et le quiz.", "Un module : la vidéo, les leçons, le quiz, et le live de la semaine."]
  };
  var onglets = $('.tabs');
  if (onglets) onglets.hidden = false;
  $$('[data-shot]').forEach(function (b) {
    b.addEventListener('click', function () {
      var k = b.getAttribute('data-shot');
      $$('[data-shot]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
      $('#shot-img').src = '/assets/img/apercu-' + k + '.webp'; $('#shot-img').alt = SHOTS[k][0]; $('#shot-cap').textContent = SHOTS[k][1];
    });
  });
})();
