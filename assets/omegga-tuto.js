/* La vidéo d'installation d'Omegga : les boutons de chapitres sautent au bon moment. Sans JavaScript, ils restent cachés. */
(function () {
  'use strict';
  var video = document.querySelector('[data-tuto]');
  var liste = document.querySelector('[data-tuto-chap]');
  if (!video || !liste || !liste.querySelector('button')) return;
  liste.hidden = false;
  liste.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-t]');
    if (!b) return;
    var t = Number(b.getAttribute('data-t')) || 0;
    // vidéo pas encore chargée : on attend ses métadonnées pour pouvoir sauter au chapitre
    if (video.readyState >= 1) video.currentTime = t;
    else video.addEventListener('loadedmetadata', function () { video.currentTime = t; }, { once: true });
    var p = video.play(); if (p && p.catch) p.catch(function () {});
  });
})();
