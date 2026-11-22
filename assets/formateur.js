/* L'espace formateur : glisser une vidéo, et elle part par morceaux, avec une barre de progression.
   Une coupure réseau ne fait pas tout recommencer : chaque morceau est renvoyé jusqu'à 4 fois, et le serveur dit
   toujours combien d'octets il a déjà. */
(function () {
  'use strict';
  var tous = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var enCours = 0;

  window.addEventListener('beforeunload', function (ev) {
    if (enCours > 0) { ev.preventDefault(); ev.returnValue = ''; }
  });

  function taille(o) {
    if (o >= 1073741824) return (o / 1073741824).toFixed(1).replace('.', ',') + ' Go';
    return Math.max(1, Math.round(o / 1048576)) + ' Mo';
  }
  function minutes(s) {
    if (!isFinite(s) || s <= 0) return '';
    if (s < 90) return 'moins de 2 minutes';
    return 'environ ' + Math.round(s / 60) + ' minutes';
  }
  function pause(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

  /* la durée de la vidéo, lue par le navigateur avant l'envoi (null s'il ne sait pas la lire) */
  function lireDuree(fichier) {
    return new Promise(function (ok) {
      var v = document.createElement('video');
      var url;
      var fini = false;
      function fin(d) { if (fini) return; fini = true; try { URL.revokeObjectURL(url); } catch (e) {} ok(d); }
      try { url = URL.createObjectURL(fichier); } catch (e) { ok(null); return; }
      v.preload = 'metadata';
      v.muted = true;
      v.addEventListener('loadedmetadata', function () { fin(isFinite(v.duration) && v.duration > 0 ? Math.round(v.duration) : null); });
      v.addEventListener('error', function () { fin(null); });
      setTimeout(function () { fin(null); }, 8000);
      v.src = url;
    });
  }

  async function poster(url, jeton, corps, signal, type) {
    var r = await fetch(url, {
      method: 'POST', credentials: 'same-origin', signal: signal,
      headers: { 'X-Jeton': jeton, 'Accept': 'application/json', 'Content-Type': type || 'application/x-www-form-urlencoded' },
      body: corps
    });
    var d = null;
    try { d = await r.json(); } catch (e) { d = { erreur: r.status === 403 ? 'La page a expiré : recharge-la, puis recommence.' : 'Le serveur a répondu de travers (' + r.status + ').' }; }
    d.statut = r.status;
    return d;
  }

  /* envoie le fichier vers une leçon ou un replay ; ui : { barre, texte } */
  async function envoyer(fichier, genre, id, jeton, ui, signal) {
    var duree = await lireDuree(fichier);
    ui.texte.textContent = 'Préparation…';
    var d = await poster('/formateur/envoi?action=debut', jeton, new URLSearchParams({ genre: genre, id: String(id), taille: String(fichier.size), nom: fichier.name }), signal);
    if (d.erreur) throw new Error(d.erreur);
    var envoi = d.envoi, morceau = d.morceau || 4194304, pos = 0, t0 = Date.now();
    while (pos < fichier.size) {
      var bout = fichier.slice(pos, Math.min(fichier.size, pos + morceau));
      var r = null, essai = 0;
      while (true) {
        try {
          r = await poster('/formateur/envoi?action=morceau&e=' + envoi + '&debut=' + pos, jeton, bout, signal, 'application/octet-stream');
          if (typeof r.recu === 'number') break;            // reçu, ou déjà reçu (le serveur dit où il en est)
          throw new Error(r.erreur || 'Erreur');
        } catch (e) {
          if (signal.aborted) throw new Error('Envoi annulé.');
          if (++essai > 4) throw new Error((e && e.message ? e.message + ' ' : '') + 'La connexion a coupé plusieurs fois : réessaie.');
          ui.texte.textContent = 'Connexion coupée, nouvel essai…';
          await pause(1500 * essai);
        }
      }
      pos = r.recu;
      var pct = Math.floor(100 * pos / fichier.size);
      ui.barre.value = pct;
      var vitesse = pos / Math.max(1, (Date.now() - t0) / 1000);
      ui.texte.textContent = pct + ' % · ' + taille(pos) + ' sur ' + taille(fichier.size) + (pos < fichier.size ? ' · encore ' + minutes((fichier.size - pos) / vitesse) : '');
    }
    ui.texte.textContent = 'Vérification de la vidéo…';
    var f = await poster('/formateur/envoi?action=fin&e=' + envoi, jeton, new URLSearchParams({ duree: duree ? String(duree) : '' }), signal);
    if (f.erreur) throw new Error(f.erreur);
    ui.barre.value = 100;
    ui.texte.textContent = 'Vidéo en ligne.';
  }

  function preparer(depot) {
    var champ = depot.querySelector('.depot-fichier');
    var zone = depot.querySelector('.depot-zone');
    var etat = depot.querySelector('.depot-etat');
    var ui = { barre: depot.querySelector('.depot-barre'), texte: depot.querySelector('.depot-texte') };
    var annuler = depot.querySelector('.depot-annuler');
    var jeton = (depot.querySelector('input[name="jeton-envoi"]') || {}).value || '';
    var form = depot.closest('form[data-nouvelle-lecon]');
    var controle = null;
    depot.fichier = null;
    if (champ) champ.removeAttribute('name');      // la vidéo ne part jamais avec le formulaire : elle part par morceaux

    function erreur(msg) {
      etat.hidden = false;
      ui.barre.hidden = true;
      annuler.hidden = true;
      ui.texte.textContent = msg;
      ui.texte.classList.add('depot-erreur');
      zone.hidden = false;
    }

    async function lancer(id) {
      zone.hidden = true;
      etat.hidden = false;
      ui.barre.hidden = false;
      annuler.hidden = false;
      ui.texte.classList.remove('depot-erreur');
      controle = new AbortController();
      enCours++;
      try {
        await envoyer(depot.fichier, depot.getAttribute('data-envoi'), id, jeton, ui, controle.signal);
        return true;
      } catch (e) {
        erreur(e && e.message ? e.message : 'L\'envoi a échoué.');
        return false;
      } finally {
        enCours--;
        annuler.hidden = true;
      }
    }

    function choisir(f) {
      if (!f) return;
      if (!/\.(mp4|m4v|mov)$/i.test(f.name)) { erreur('Envoie une vidéo MP4 (export H.264).'); return; }
      depot.fichier = f;
      var id = depot.getAttribute('data-id');
      if (id) {
        lancer(id).then(function (ok) { if (ok) location.reload(); });
      } else {
        // nouvelle leçon : la vidéo partira quand on valide le formulaire ; le titre se remplit avec le nom du fichier
        var titre = form && form.querySelector('[name="titre"]');
        if (titre && !titre.value.trim()) titre.value = f.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
        depot.classList.add('depot-pret');
        zone.querySelector('.depot-titre').textContent = f.name + ' (' + taille(f.size) + ')';
        zone.querySelector('.depot-aide').textContent = 'Prête : elle partira quand tu mets la leçon en ligne. Clique pour en choisir une autre.';
      }
    }

    champ.addEventListener('change', function () { choisir(champ.files && champ.files[0]); });
    ['dragenter', 'dragover'].forEach(function (t) {
      zone.addEventListener(t, function (ev) { ev.preventDefault(); zone.classList.add('depot-survol'); });
    });
    ['dragleave', 'drop'].forEach(function (t) {
      zone.addEventListener(t, function (ev) { ev.preventDefault(); zone.classList.remove('depot-survol'); });
    });
    zone.addEventListener('drop', function (ev) { choisir(ev.dataTransfer && ev.dataTransfer.files && ev.dataTransfer.files[0]); });
    annuler.addEventListener('click', function () { if (controle) controle.abort(); });

    if (form) {
      form.addEventListener('submit', async function (ev) {
        if (!depot.fichier) return;                  // sans vidéo : le formulaire part normalement
        ev.preventDefault();
        if (!form.reportValidity()) return;
        var bouton = form.querySelector('button[type="submit"]');
        if (bouton) bouton.disabled = true;
        var donnees = new URLSearchParams(new FormData(form));
        var d;
        try {
          d = await poster(form.getAttribute('action'), jeton, donnees);
        } catch (e) { d = { erreur: 'Pas de connexion : réessaie.' }; }
        if (d.erreur || !d.id) { erreur(d.erreur || 'La leçon n\'a pas été créée.'); if (bouton) bouton.disabled = false; return; }
        var ok = await lancer(d.id);
        if (ok) { location.href = d.retour; return; }
        // la leçon existe déjà : on renvoie la vidéo depuis sa page
        var lien = document.createElement('a');
        lien.href = d.retour;
        lien.className = 'btn btn-line btn-sm';
        lien.textContent = 'Ouvrir la leçon pour renvoyer la vidéo';
        etat.appendChild(lien);
      });
    }
  }

  tous('.depot').forEach(preparer);
})();
