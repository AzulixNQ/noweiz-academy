/* Les essais automatiques des comptes. Lance son propre serveur, sur une base vide, puis joue les parcours.
     node outils/essais.js            tous les essais
     node outils/essais.js captures   en plus, des captures d'écran dans le dossier donné par NA_CAPTURES
   Les mots de passe d'essai sont tirés au hasard à chaque lancement. */
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const fs = require('fs'), os = require('os'), path = require('path'), crypto = require('crypto');

const PORT = 8766, URL = 'http://127.0.0.1:' + PORT;
const racine = path.resolve(__dirname, '..');
// La vidéo des essais (envoi de leçons, cohorte d'essai) : gardée hors du dépôt, plus sur le site depuis le 26/11.
process.env.NA_VIDEO_ESSAI = process.env.NA_VIDEO_ESSAI || path.join(racine, '..', 'essais-academy', 'absorption-v2.mp4');
const donnees = fs.mkdtempSync(path.join(os.tmpdir(), 'na-essais-'));
const captures = process.argv[2] === 'captures' ? (process.env.NA_CAPTURES || path.join(donnees, 'captures')) : null;
const mdp = () => 'essai ' + crypto.randomBytes(9).toString('base64url');
const M = { alice: mdp(), bob: mdp(), bob2: mdp(), bob3: mdp(), carol: mdp() };

let ok = 0; const rates = [];
function verif(cond, quoi) { if (cond) { ok++; } else { rates.push(quoi); console.log('  RATÉ : ' + quoi); } }
const pause = ms => new Promise(r => setTimeout(r, ms));

function courriels(a) {
  const d = path.join(donnees, 'courriels');
  if (!fs.existsSync(d)) return [];
  return fs.readdirSync(d).sort().map(f => JSON.parse(fs.readFileSync(path.join(d, f), 'utf8'))).filter(c => !a || c.a === a);
}
function lienDe(c) { const m = c.texte.match(/https?:\/\/\S+/); return m ? m[0] : null; }

(async () => {
  // une petite limite d'envoi, comme un hébergement serré : les vidéos de l'espace formateur partent en plusieurs morceaux
  const serveur = spawn('php', ['-d', 'post_max_size=3M', '-d', 'upload_max_filesize=3M', '-S', '127.0.0.1:' + PORT, 'outils/serveur.php'], { cwd: racine, env: Object.assign({}, process.env, { NA_URL: URL, NA_COURRIER: 'fichier', NA_DONNEES: donnees }), stdio: 'ignore' });
  for (let i = 0; i < 50; i++) { try { const r = await fetch(URL + '/sante'); if (r.ok) break; } catch (e) {} await pause(100); }

  const nav = await chromium.launch();
  const erreursPage = [];
  async function fenetre(o) {
    const c = await nav.newContext(Object.assign({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR', reducedMotion: 'reduce' }, o || {}));   // sans défilement doux : les clics des essais tombent juste
    const p = await c.newPage();
    p.on('pageerror', e => erreursPage.push('erreur de script : ' + e.message));
    p.on('console', m => { if (m.type() === 'error' && !/status of 40[34]|status of 42[12]/.test(m.text())) erreursPage.push('console : ' + m.text()); });
    return p;
  }
  const texte = p => p.evaluate(() => document.body.innerText);
  async function photo(p, nom) {
    if (!captures) return;
    fs.mkdirSync(captures, { recursive: true });
    await p.screenshot({ path: path.join(captures, nom + '.png'), fullPage: true });
  }
  async function inscrire(p, prenom, email, motdepasse, discord) {
    await p.fill('#c-prenom', prenom); await p.fill('#c-email', email); await p.fill('#c-mdp', motdepasse);
    if (discord) await p.fill('#c-discord', discord);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit].btn-main')]);
  }
  async function connecter(p, email, motdepasse) {
    await p.goto(URL + '/connexion');
    await p.fill('#c-email', email); await p.fill('#c-mdp', motdepasse);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit].btn-main')]);
  }
  async function envoyer(p, selecteur) { await Promise.all([p.waitForNavigation(), p.click(selecteur)]); }

  try {
    /* ---------- A. le premier compte ---------- */
    console.log('A. Le premier compte');
    const alice = await fenetre();
    await alice.goto(URL + '/');
    verif(alice.url() === URL + '/' && (await texte(alice)).includes('Inscris-toi sur la liste d\'attente'), 'l\'accueil montre la page de présentation et la liste d\'attente');
    await alice.goto(URL + '/connexion');
    verif((await texte(alice)).includes('Créer le premier compte'), 'la connexion propose de créer le premier compte');
    await photo(alice, 'pc-connexion');
    await alice.goto(URL + '/inscription');
    verif((await texte(alice)).includes('droits d\'administrateur'), 'l\'inscription annonce le compte administrateur');
    await envoyer(alice, 'button[type=submit].btn-main');
    let t = await texte(alice);
    verif(t.includes('Écris ton prénom.') && t.includes('Écris ton e-mail.') && t.includes('Choisis un mot de passe.'), 'formulaire vide : trois messages');
    await alice.fill('#c-prenom', 'Alice'); await alice.fill('#c-email', 'alice@exemple'); await alice.fill('#c-mdp', 'court');
    await envoyer(alice, 'button[type=submit].btn-main');
    t = await texte(alice);
    verif(t.includes('pas complète') && t.includes('trop court'), 'adresse incomplète et mot de passe trop court refusés');
    verif(await alice.inputValue('#c-prenom') === 'Alice', 'le prénom reste dans le champ après une erreur');
    await photo(alice, 'pc-inscription-erreurs');
    await alice.fill('#c-email', 'alice@exemple.fr'); await alice.fill('#c-mdp', 'alice@exemple.fr');
    await envoyer(alice, 'button[type=submit].btn-main');
    verif((await texte(alice)).includes('ne doit pas être ton e-mail'), 'mot de passe égal à l\'e-mail refusé');
    await alice.fill('#c-mdp', 'motdepasse');
    await envoyer(alice, 'button[type=submit].btn-main');
    verif((await texte(alice)).includes('trop courant'), 'mot de passe courant refusé');
    await alice.click('.pw-voir');
    verif(await alice.getAttribute('#c-mdp', 'type') === 'text', 'le bouton Afficher montre le mot de passe');
    await inscrire(alice, 'Alice', 'Alice@Exemple.fr', M.alice, '@alice.of');
    verif(alice.url() === URL + '/espace', 'après l\'inscription : l\'espace');
    t = await texte(alice);
    verif(t.includes('Bonjour Alice') && t.includes('Administrateur'), 'l\'espace salue Alice, administratrice');
    verif(t.includes('Ton compte est créé') && t.includes('pas encore confirmée'), 'annonce de création et rappel de confirmation');
    verif(t.includes('alice.of') && !t.includes('@alice.of'), 'le @ du pseudo Discord est retiré');
    await photo(alice, 'pc-espace-admin');
    let c = courriels('alice@exemple.fr');
    verif(c.length === 1 && /Bienvenue/.test(c[0].sujet) && /confirmer\?lien=[a-f0-9]{48}/.test(c[0].texte), 'e-mail de bienvenue avec le lien de confirmation');
    verif(c[0] && c[0].html.includes('Confirmer mon adresse') && !c[0].html.includes(M.alice), 'l\'e-mail a sa version HTML, sans le mot de passe');
    const lienAlice = lienDe(c[0]);
    await alice.goto(lienAlice);
    t = await texte(alice);
    verif(alice.url() === URL + '/espace' && t.includes('Ton adresse e-mail est confirmée') && !t.includes('pas encore confirmée'), 'le lien confirme l\'adresse');
    await alice.goto(lienAlice);
    verif(alice.url() === URL + '/espace', 'le lien déjà utilisé ramène à l\'espace sans erreur');

    /* ---------- B. inscriptions fermées ---------- */
    console.log('B. Inscriptions fermées');
    const visiteur = await fenetre();
    await visiteur.goto(URL + '/inscription');
    verif((await texte(visiteur)).includes('Les inscriptions ne sont pas ouvertes'), 'sans invitation : inscriptions fermées');
    await photo(visiteur, 'pc-inscriptions-fermees');
    await visiteur.goto(URL + '/inscription?invitation=' + 'a'.repeat(48));
    verif((await texte(visiteur)).includes('n\'est plus valable'), 'fausse invitation refusée');
    await visiteur.goto(URL + '/espace');
    verif(visiteur.url() === URL + '/connexion', 'l\'espace demande d\'être connecté');
    await visiteur.goto(URL + '/admin');
    verif(visiteur.url() === URL + '/connexion', 'l\'administration demande d\'être connecté');

    /* ---------- C. déconnexion, connexion ---------- */
    console.log('C. Déconnexion et connexion');
    await envoyer(alice, '.top button[type=submit]');
    verif(alice.url() === URL + '/connexion' && (await texte(alice)).includes('Tu es déconnecté'), 'déconnexion');
    await alice.goto(URL + '/espace');
    verif(alice.url() === URL + '/connexion', 'après la déconnexion, l\'espace est fermé');
    await connecter(alice, 'alice@exemple.fr', 'pas le bon mot de passe');
    verif((await texte(alice)).includes('E-mail ou mot de passe incorrect'), 'mauvais mot de passe : message neutre');
    verif(await alice.inputValue('#c-email') === 'alice@exemple.fr' && await alice.inputValue('#c-mdp') === '', 'l\'e-mail reste, pas le mot de passe');
    await connecter(alice, 'personne@exemple.fr', M.alice);
    verif((await texte(alice)).includes('E-mail ou mot de passe incorrect'), 'compte inconnu : même message');
    await connecter(alice, 'ALICE@exemple.fr', M.alice);
    verif(alice.url() === URL + '/espace', 'connexion (majuscules de l\'e-mail ignorées)');
    const temoins = await alice.context().cookies();
    const ts = temoins.find(x => x.name === 'na_session');
    verif(ts && ts.httpOnly && ts.sameSite === 'Lax' && /^[a-f0-9]{64}$/.test(ts.value), 'le témoin de connexion est protégé (HttpOnly, SameSite)');

    /* ---------- D. administration et invitations ---------- */
    console.log('D. Invitations');
    await alice.goto(URL + '/admin');
    await envoyer(alice, 'form:has(input[value=inviter]) button[type=submit]');
    verif((await texte(alice)).includes('Écris pour qui'), 'invitation sans nom refusée');
    await alice.fill('#c-note', 'Bob');
    await envoyer(alice, 'form:has(input[value=inviter]) button[type=submit]');
    const lienBob = await alice.inputValue('#lien-invitation');
    verif(/^http:\/\/127\.0\.0\.1:8766\/inscription\?invitation=[a-f0-9]{48}$/.test(lienBob), 'le lien d\'invitation est affiché');
    await photo(alice, 'pc-admin-lien');
    await alice.goto(URL + '/admin');
    t = await texte(alice);
    verif(await alice.locator('#lien-invitation').count() === 0 && t.includes('Bob'), 'le lien n\'est montré qu\'une fois ; l\'invitation est dans la liste');

    const bob = await fenetre();
    await bob.goto(lienBob);
    verif((await texte(bob)).includes('Tu es invité comme élève'), 'la page d\'inscription reconnaît l\'invitation');
    await photo(bob, 'pc-inscription-invite');
    await inscrire(bob, '<script>alert(1)</script>', 'bob@exemple.fr', M.bob, '');
    verif(bob.url() === URL + '/espace', 'Bob a son compte');
    verif(await bob.locator('.h1-app').innerText() === 'Bonjour <script>alert(1)</script>', 'un prénom piégé s\'affiche comme du texte');
    t = await texte(bob);
    verif(!t.includes('Comptes et invitations') && t.includes('Élève'), 'Bob est élève, sans menu d\'administration');
    const r403 = await bob.goto(URL + '/admin');
    verif(r403.status() === 403 && (await texte(bob)).includes('réservée aux administrateurs'), 'l\'administration est interdite à un élève');
    await visiteur.goto(lienBob);
    verif((await texte(visiteur)).includes('n\'est plus valable'), 'une invitation ne sert qu\'une fois');

    await alice.goto(URL + '/admin');
    await alice.fill('#c-note', 'Doublon');
    await envoyer(alice, 'form:has(input[value=inviter]) button[type=submit]');
    const lienDoublon = await alice.inputValue('#lien-invitation');
    await visiteur.goto(lienDoublon);
    await inscrire(visiteur, 'Faux', 'alice@exemple.fr', mdp(), '');
    verif((await texte(visiteur)).includes('Un compte existe déjà avec cette adresse'), 'pas de deuxième compte avec le même e-mail');
    await alice.goto(URL + '/admin');
    await envoyer(alice, 'form:has(input[value=annuler]) button[type=submit]');
    verif((await texte(alice)).includes('Invitation annulée'), 'invitation annulée');
    await visiteur.goto(lienDoublon);
    verif((await texte(visiteur)).includes('n\'est plus valable'), 'le lien annulé ne marche plus');

    const ligneBob = alice.locator('tr', { hasText: 'bob@exemple.fr' });
    await ligneBob.locator('select').selectOption('formateur');
    await envoyer(alice, 'tr:has-text("bob@exemple.fr") button[type=submit]');
    verif((await texte(alice)).includes('Rôle changé'), 'rôle de Bob changé');
    await bob.goto(URL + '/espace');
    verif((await texte(bob)).includes('Formateur'), 'Bob voit son nouveau rôle');
    await alice.locator('tr', { hasText: 'alice@exemple.fr' }).locator('select').selectOption('eleve');
    await envoyer(alice, 'tr:has-text("alice@exemple.fr") button[type=submit]');
    verif((await texte(alice)).includes('au moins un administrateur'), 'le dernier administrateur ne peut pas perdre son rôle');
    verif(!(await alice.content()).includes('$argon2') && !(await alice.content()).includes('$2y$'), 'aucune empreinte de mot de passe dans la page');
    await photo(alice, 'pc-admin');

    /* ---------- E. mon compte ---------- */
    console.log('E. Mon compte');
    await bob.goto(URL + '/compte');
    await bob.fill('#c-prenom', 'Bob'); await bob.fill('#c-discord', 'bob_ict');
    await envoyer(bob, 'form:has(input[value=profil]) button[type=submit]');
    t = await texte(bob);
    verif(t.includes('C\'est enregistré') && await bob.inputValue('#c-prenom') === 'Bob' && await bob.inputValue('#c-discord') === 'bob_ict', 'prénom et pseudo Discord enregistrés');
    await photo(bob, 'pc-compte');

    const bobTel = await fenetre({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
    await connecter(bobTel, 'bob@exemple.fr', M.bob);
    await bob.goto(URL + '/compte');
    verif((await texte(bob)).includes('aussi connecté sur un autre appareil'), 'le compte voit l\'autre appareil');

    await bob.fill('#c-mdp_actuel', 'pas le bon'); await bob.fill('#c-mdp_nouveau', M.bob2);
    await envoyer(bob, 'form:has(input[value=mdp]) button[type=submit]');
    verif((await texte(bob)).includes('pas ton mot de passe actuel'), 'changement de mot de passe : l\'actuel est vérifié');
    await bob.fill('#c-mdp_actuel', M.bob); await bob.fill('#c-mdp_nouveau', M.bob);
    await envoyer(bob, 'form:has(input[value=mdp]) button[type=submit]');
    verif((await texte(bob)).includes('différent de l\'actuel'), 'le nouveau mot de passe doit être différent');
    await bob.fill('#c-mdp_actuel', M.bob); await bob.fill('#c-mdp_nouveau', M.bob2);
    await envoyer(bob, 'form:has(input[value=mdp]) button[type=submit]');
    verif((await texte(bob)).includes('Ton mot de passe est changé'), 'mot de passe changé');
    await bobTel.goto(URL + '/espace');
    verif(bobTel.url() === URL + '/connexion', 'l\'autre appareil est déconnecté');
    verif(courriels('bob@exemple.fr').some(x => /mot de passe a été changé/.test(x.sujet)), 'e-mail d\'alerte « mot de passe changé »');

    await bob.fill('#c-email', 'bob.nouveau@exemple.fr'); await bob.fill('#c-mdp_email', 'pas le bon');
    await envoyer(bob, 'form:has(input[value=email]) button[type=submit]');
    verif((await texte(bob)).includes('pas ton mot de passe actuel'), 'changement d\'adresse : mot de passe vérifié');
    await bob.fill('#c-email', 'alice@exemple.fr'); await bob.fill('#c-mdp_email', M.bob2);
    await envoyer(bob, 'form:has(input[value=email]) button[type=submit]');
    verif((await texte(bob)).includes('Un autre compte utilise déjà cette adresse'), 'pas d\'adresse déjà prise');
    await bob.fill('#c-email', 'bob.nouveau@exemple.fr'); await bob.fill('#c-mdp_email', M.bob2);
    await envoyer(bob, 'form:has(input[value=email]) button[type=submit]');
    t = await texte(bob);
    verif(t.includes('Ton adresse est changée') && t.includes('bob.nouveau@exemple.fr') && t.includes('pas encore confirmée'), 'adresse changée, à confirmer');
    verif(courriels('bob@exemple.fr').some(x => /adresse e-mail de ton compte a changé/.test(x.sujet) && x.texte.includes('bo…@exemple.fr')), 'l\'ancienne adresse est prévenue, la nouvelle y est masquée');
    verif(courriels('bob.nouveau@exemple.fr').some(x => /Confirme ton adresse/.test(x.sujet)), 'la nouvelle adresse reçoit le lien de confirmation');
    for (let i = 0; i < 3; i++) await envoyer(bob, 'form:has(input[value=renvoyer]) button[type=submit]');
    verif((await texte(bob)).includes('E-mail envoyé'), 'renvoi de l\'e-mail de confirmation');
    await envoyer(bob, 'form:has(input[value=renvoyer]) button[type=submit]');
    verif((await texte(bob)).includes('Trop de demandes'), 'pas plus de trois renvois par heure');
    const dernier = courriels('bob.nouveau@exemple.fr').filter(x => /Confirme/.test(x.sujet));
    await visiteur.goto(lienDe(dernier[0]));
    verif((await texte(visiteur)).includes('n\'est plus valable'), 'un ancien lien de confirmation ne marche plus');
    await visiteur.goto(lienDe(dernier[dernier.length - 1]));
    verif(visiteur.url() === URL + '/connexion' && (await texte(visiteur)).includes('confirmée'), 'le dernier lien confirme l\'adresse, même sans être connecté');

    /* ---------- F. mot de passe oublié ---------- */
    console.log('F. Mot de passe oublié');
    await visiteur.goto(URL + '/connexion');
    await visiteur.click('text=Mot de passe oublié');
    await visiteur.waitForURL(URL + '/mot-de-passe-oublie');
    await photo(visiteur, 'pc-mdp-oublie');
    const avant = courriels().length;
    await visiteur.fill('#c-email', 'inconnu@exemple.fr');
    await envoyer(visiteur, 'button[type=submit].btn-main');
    verif((await texte(visiteur)).includes('Regarde tes e-mails') && courriels().length === avant, 'compte inconnu : même réponse, aucun e-mail');
    await visiteur.goto(URL + '/mot-de-passe-oublie');
    await visiteur.fill('#c-email', 'bob.nouveau@exemple.fr');
    await envoyer(visiteur, 'button[type=submit].btn-main');
    verif((await texte(visiteur)).includes('Regarde tes e-mails'), 'compte connu : même réponse');
    await photo(visiteur, 'pc-mdp-oublie-envoye');
    const oubli = courriels('bob.nouveau@exemple.fr').filter(x => /nouveau mot de passe/.test(x.sujet));
    verif(oubli.length === 1 && /nouveau-mot-de-passe\?lien=[a-f0-9]{48}/.test(oubli[0].texte), 'e-mail avec le lien de nouveau mot de passe');
    const lienOubli = lienDe(oubli[0]);
    await visiteur.goto(lienOubli);
    verif((await texte(visiteur)).includes('bo…@exemple.fr'), 'la page du lien montre l\'adresse masquée');
    await visiteur.fill('#c-mdp', 'court');
    await envoyer(visiteur, 'button[type=submit].btn-main');
    verif((await texte(visiteur)).includes('trop court'), 'nouveau mot de passe trop court refusé');
    await visiteur.fill('#c-mdp', M.bob3);
    await envoyer(visiteur, 'button[type=submit].btn-main');
    verif(visiteur.url() === URL + '/connexion' && (await texte(visiteur)).includes('Tu peux te connecter'), 'nouveau mot de passe enregistré');
    await bob.goto(URL + '/espace');
    verif(bob.url() === URL + '/connexion', 'les appareils de Bob sont déconnectés');
    await visiteur.goto(lienOubli);
    verif((await texte(visiteur)).includes('n\'est plus valable'), 'le lien ne sert qu\'une fois');
    await connecter(bob, 'bob.nouveau@exemple.fr', M.bob2);
    verif((await texte(bob)).includes('incorrect'), 'l\'ancien mot de passe ne marche plus');
    await connecter(bob, 'bob.nouveau@exemple.fr', M.bob3);
    verif(bob.url() === URL + '/espace', 'connexion avec le nouveau mot de passe');

    /* ---------- G. sécurité ---------- */
    console.log('G. Sécurité');
    let r = await fetch(URL + '/connexion', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'email=alice%40exemple.fr&mdp=x', redirect: 'manual' });
    verif(r.status === 403, 'formulaire sans jeton refusé');
    const jeton = await alice.inputValue('input[name=jeton]');
    const temoinsAlice = (await alice.context().cookies()).map(x => x.name + '=' + x.value).join('; ');
    r = await fetch(URL + '/deconnexion', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: temoinsAlice, Origin: 'https://mechant.example' }, body: 'jeton=' + jeton, redirect: 'manual' });
    verif(r.status === 403, 'formulaire venu d\'un autre site refusé, même avec le bon jeton');
    await alice.goto(URL + '/espace');
    verif(alice.url() === URL + '/espace', 'Alice est toujours connectée');
    r = await fetch(URL + '/deconnexion', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', Cookie: temoinsAlice }, body: 'jeton=' + 'f'.repeat(64), redirect: 'manual' });
    verif(r.status === 403, 'mauvais jeton refusé');
    for (const ch of ['/app/config.php', '/app/pages/admin.php', '/donnees/', '/outils/essais.js', '/.git/config', '/.gitignore']) {
      r = await fetch(URL + ch);
      verif(r.status === 403, ch + ' est fermé');
    }
    r = await fetch(URL + '/sante');
    const sante = await r.json();
    verif(sante.ok && sante.base === 'sqlite' && sante.donnees === 'hors du site' && !('email' in sante), 'contrôle de santé sans donnée privée');
    r = await fetch(URL + '/connexion');
    const csp = r.headers.get('content-security-policy') || '';
    verif(csp.includes("default-src 'none'") && csp.includes("frame-ancestors 'none'") && r.headers.get('x-frame-options') === 'DENY' && r.headers.get('cache-control') === 'no-store' && !r.headers.get('x-powered-by'), 'en-têtes de sécurité');
    r = await fetch(URL + '/connexion', { headers: { Host: 'autre.example' } }).catch(() => null);
    const essai = await fenetre();
    for (let i = 0; i < 8; i++) await connecter(essai, 'carol@exemple.fr', 'faux ' + i);
    await connecter(essai, 'carol@exemple.fr', 'faux 9');
    verif((await texte(essai)).includes('Trop d\'essais avec cette adresse'), 'au 9e essai raté, l\'adresse est bloquée un quart d\'heure');
    await connecter(essai, 'alice@exemple.fr', 'faux');
    verif((await texte(essai)).includes('E-mail ou mot de passe incorrect'), 'le blocage ne touche pas les autres adresses');

    /* ---------- H. suppression ---------- */
    console.log('H. Suppression');
    await bob.goto(URL + '/compte');
    await bob.fill('#c-mdp_suppr', 'pas le bon');
    await envoyer(bob, 'form:has(input[value=supprimer]) button[type=submit]');
    verif((await texte(bob)).includes('pas ton mot de passe actuel'), 'suppression : mot de passe vérifié');
    await alice.goto(URL + '/compte');
    await alice.fill('#c-mdp_suppr', M.alice);
    await envoyer(alice, 'form:has(input[value=supprimer]) button[type=submit]');
    verif((await texte(alice)).includes('seul administrateur'), 'le seul administrateur ne peut pas se supprimer tant qu\'il reste des comptes');
    await bob.fill('#c-mdp_suppr', M.bob3);
    await envoyer(bob, 'form:has(input[value=supprimer]) button[type=submit]');
    verif(bob.url() === URL + '/connexion' && (await texte(bob)).includes('Ton compte est supprimé'), 'Bob supprime son compte');
    await connecter(bob, 'bob.nouveau@exemple.fr', M.bob3);
    verif((await texte(bob)).includes('incorrect'), 'le compte supprimé ne se connecte plus');
    await alice.goto(URL + '/admin');
    verif(!(await texte(alice)).includes('bob.nouveau@exemple.fr'), 'Bob n\'est plus dans la liste');

    /* ---------- I. téléphone et captures ---------- */
    console.log('I. Téléphone');
    await alice.goto(URL + '/admin');
    await alice.fill('#c-note', 'Carol');
    await envoyer(alice, 'form:has(input[value=inviter]) button[type=submit]');
    const lienCarol = await alice.inputValue('#lien-invitation');
    const tel = await fenetre({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
    await tel.goto(lienCarol);
    await photo(tel, 'tel-inscription');
    await inscrire(tel, 'Carol', 'carol@exemple.fr', M.carol, 'carol');
    verif(tel.url() === URL + '/espace', 'inscription sur téléphone');
    for (const [ch, nom] of [['/espace', 'tel-espace'], ['/compte', 'tel-compte'], ['/confidentialite', 'tel-confidentialite']]) {
      await tel.goto(URL + ch);
      const large = await tel.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      verif(large <= 0, ch + ' ne déborde pas sur téléphone (' + large + ' px)');
      await photo(tel, nom);
    }
    await envoyer(tel, '.top button[type=submit]');
    await photo(tel, 'tel-connexion');
    const telAdmin = await fenetre({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
    await connecter(telAdmin, 'alice@exemple.fr', M.alice);
    await telAdmin.goto(URL + '/admin');
    const largeAdmin = await telAdmin.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    verif(largeAdmin <= 0, '/admin ne déborde pas sur téléphone (' + largeAdmin + ' px)');
    await photo(telAdmin, 'tel-admin');
    await alice.goto(URL + '/confidentialite'); await photo(alice, 'pc-confidentialite');
    await alice.goto(URL + '/espace'); await photo(alice, 'pc-espace');
    await alice.goto(URL + '/compte'); await photo(alice, 'pc-compte-admin');
    await alice.goto(URL + '/inconnu'); await photo(alice, 'pc-introuvable');

    /* ---------- J. la liste d'attente ---------- */
    console.log("J. La liste d'attente");
    const passant = await fenetre();
    await passant.goto(URL + '/');
    r = await fetch(URL + '/');
    verif((r.headers.get('content-security-policy') || '').includes("media-src 'self'"), "l'accueil autorise sa vidéo (media-src)");
    await passant.click('#wl-envoi');
    t = await texte(passant);
    verif(passant.url() === URL + '/' && t.includes('Écris ton prénom.') && t.includes('Coche la case'), 'formulaire vide : messages affichés sans quitter la page');
    // sans JavaScript, le serveur vérifie tout
    const sansJs = await fenetre({ javaScriptEnabled: false });
    await sansJs.goto(URL + '/');
    await sansJs.fill('#wl-prenom', 'Jean'); await sansJs.fill('#wl-email', 'jean@exemple.fr');
    await envoyer(sansJs, '#wl-envoi');
    t = await texte(sansJs);
    verif(t.includes('Coche la case') && (await sansJs.inputValue('#wl-email')) === 'jean@exemple.fr', 'sans JavaScript : la case non cochée est refusée par le serveur, les champs restent remplis');
    verif(courriels('jean@exemple.fr').length === 0, 'rien n\'est envoyé tant que le formulaire est refusé');
    await sansJs.check('#wl-accord');
    await envoyer(sansJs, '#wl-envoi');
    verif((await texte(sansJs)).includes('Plus qu\'une étape'), 'sans JavaScript : l\'inscription marche aussi');
    let mails = courriels('jean@exemple.fr');
    verif(mails.length === 1 && mails[0].sujet.includes('Confirme ton inscription'), 'e-mail « Confirme ton inscription » envoyé');
    verif(mails[0].html.includes(URL + '/assets/img/courriel-icone.png') && mails[0].html.includes('Réponds simplement à cet e-mail') && !mails[0].html.includes('ne réponds pas'),
      'l\'e-mail a le bandeau Noweiz et invite à répondre');
    verif((await fetch(URL + '/assets/img/courriel-icone.png')).headers.get('content-type') === 'image/png', 'l\'icône des e-mails est servie');
    const lienJean = lienDe(mails[0]);
    verif(/\/liste-attente\/confirmer\?lien=[a-f0-9]{64}$/.test(lienJean || ''), 'le lien de confirmation a la bonne forme');
    await alice.goto(URL + '/admin/liste-attente');
    t = await texte(alice);
    verif(t.includes('Liste d\'attente') && /Inscrits\s*0/.test(t) && t.includes('Une inscription attend sa confirmation'), 'avant confirmation : 0 inscrit, 1 en attente');
    await passant.goto(lienJean);
    verif((await texte(passant)).includes('C\'est confirmé, Jean'), 'le lien confirme l\'inscription');
    await passant.goto(lienJean);
    verif((await texte(passant)).includes('n\'est plus valable'), 'le lien ne sert qu\'une fois');
    // la même adresse une 2e fois : même réponse sur la page, e-mail « déjà sur la liste » avec désinscription en un clic
    await passant.goto(URL + '/');
    await passant.fill('#wl-prenom', 'Jean'); await passant.fill('#wl-email', 'JEAN@exemple.fr'); await passant.check('#wl-p-ict'); await passant.check('#wl-accord');
    await envoyer(passant, '#wl-envoi');
    verif(passant.url() === URL + '/#liste' && (await texte(passant)).includes('Plus qu\'une étape'), 'une adresse déjà inscrite obtient la même réponse (la page ne dit pas qui est inscrit)');
    mails = courriels('jean@exemple.fr');
    const deja = mails[mails.length - 1];
    verif(mails.length === 2 && deja.sujet.includes('déjà sur la liste'), 'e-mail « Tu es déjà sur la liste » envoyé');
    verif((deja.entetes || []).some(h => h.startsWith('List-Unsubscribe: <')) && (deja.entetes || []).includes('List-Unsubscribe-Post: List-Unsubscribe=One-Click'), 'en-têtes de désinscription en un clic');
    const desJean = (deja.texte.match(/https?:\/\/\S+\/liste-attente\/desinscription\?\S+/) || [])[0];
    await alice.goto(URL + '/admin/liste-attente');
    t = await texte(alice);
    verif(/Inscrits\s*1/.test(t) && /ICT\s*1/.test(t) && t.includes('jean@exemple.fr'), 'l\'admin voit Jean, avec le parcours mis à jour (ICT)');
    r = await fetch(URL + '/admin/liste-attente.csv', { headers: { Cookie: (await alice.context().cookies()).map(x => x.name + '=' + x.value).join('; ') } });
    const brut = Buffer.from(await r.arrayBuffer()), csv = brut.toString('utf8');
    verif(r.status === 200 && (r.headers.get('content-type') || '').startsWith('text/csv') && brut[0] === 0xEF && brut[1] === 0xBB && brut[2] === 0xBF && csv.includes('"jean@exemple.fr";"ICT"'), 'export CSV pour Excel (accents gardés)');
    // désinscription : ouvrir la page ne suffit pas (les antivirus ouvrent les liens), il faut cliquer
    await passant.goto(desJean);
    verif((await texte(passant)).includes('Te désinscrire de la liste d\'attente ?'), 'la page de désinscription demande de confirmer');
    await alice.goto(URL + '/admin/liste-attente');
    verif(/Inscrits\s*1/.test(await texte(alice)), 'ouvrir le lien ne désinscrit pas');
    await envoyer(passant, 'main form button[type=submit]');
    verif((await texte(passant)).includes('tu n\'es plus sur la liste'), 'désinscription faite');
    await alice.goto(URL + '/admin/liste-attente');
    verif(/Inscrits\s*0/.test(await texte(alice)) && !(await texte(alice)).includes('jean@exemple.fr'), 'Jean est effacé de la liste');
    r = await fetch(desJean.replace(/s=[a-f0-9]+/, 's=' + '0'.repeat(32)));
    verif((await r.text()).includes('pas (ou plus) sur la liste'), 'une signature fausse ne marche pas');
    // Paul : désinscription en un clic par la messagerie (RFC 8058)
    await passant.goto(URL + '/');
    await passant.fill('#wl-prenom', 'Paul'); await passant.fill('#wl-email', 'paul@exemple.fr'); await passant.check('#wl-accord');
    await envoyer(passant, '#wl-envoi');
    await passant.goto(lienDe(courriels('paul@exemple.fr')[0]));
    await passant.goto(URL + '/');
    await passant.fill('#wl-prenom', 'Paul'); await passant.fill('#wl-email', 'paul@exemple.fr'); await passant.check('#wl-accord');
    await envoyer(passant, '#wl-envoi');
    const entPaul = (courriels('paul@exemple.fr')[1].entetes || []).find(h => h.startsWith('List-Unsubscribe: <')) || '';
    r = await fetch(entPaul.slice(19, -1), { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'List-Unsubscribe=One-Click' });
    verif(r.status === 200 && (await r.text()) === 'OK', 'désinscription en un clic depuis la messagerie');
    await alice.goto(URL + '/admin/liste-attente');
    verif(!(await texte(alice)).includes('paul@exemple.fr'), 'Paul est effacé de la liste');
    // le champ piège : un robot croit être inscrit, rien n'est gardé ni envoyé
    await passant.goto(URL + '/');
    await passant.fill('#wl-prenom', 'Robot'); await passant.fill('#wl-email', 'robot@exemple.fr'); await passant.check('#wl-accord');
    await passant.evaluate(() => { document.getElementById('wl-site').value = 'http://spam.example'; });
    await envoyer(passant, '#wl-envoi');
    verif((await texte(passant)).includes('Plus qu\'une étape') && courriels('robot@exemple.fr').length === 0, 'champ piège : rien n\'est gardé ni envoyé');
    // une même adresse ne reçoit jamais plus de 3 e-mails par jour
    for (let i = 0; i < 4; i++) {
      await passant.goto(URL + '/');
      await passant.fill('#wl-prenom', 'Léa'); await passant.fill('#wl-email', 'lea@exemple.fr'); await passant.check('#wl-accord');
      await envoyer(passant, '#wl-envoi');
    }
    verif(courriels('lea@exemple.fr').length === 3, '3 e-mails au plus par jour à une même adresse (' + courriels('lea@exemple.fr').length + ')');
    // trop d'inscriptions depuis une même connexion
    for (let i = 0; i < 3; i++) {
      await passant.goto(URL + '/');
      await passant.fill('#wl-prenom', 'Spam'); await passant.fill('#wl-email', 'spam' + i + '@exemple.fr'); await passant.check('#wl-accord');
      await envoyer(passant, '#wl-envoi');
    }
    verif((await texte(passant)).includes('Trop d\'inscriptions depuis ta connexion'), 'au-delà de 10 inscriptions par heure depuis une connexion, refus');
    // réservé à l'administrateur
    await alice.goto(URL + '/admin');
    await alice.fill('#c-note', 'Dan');
    await envoyer(alice, 'form:has(input[value=inviter]) button[type=submit]');
    const eleve = await fenetre();
    await eleve.goto(await alice.inputValue('#lien-invitation'));
    await inscrire(eleve, 'Dan', 'dan@exemple.fr', mdp());
    for (const ch of ['/admin/liste-attente', '/admin/liste-attente.csv']) {
      const rr = await eleve.goto(URL + ch);
      verif(rr.status() === 403, ch + ' refusé à une élève');
    }
    await eleve.goto(URL + '/espace');
    verif(!(await eleve.locator('.side-nav').innerText()).includes('Liste d\'attente'), 'le menu de l\'élève n\'a pas « Liste d\'attente »');
    r = await fetch(URL + '/admin/liste-attente.csv', { redirect: 'manual' });
    verif(r.status === 303, 'export refusé sans connexion');
    // téléphone et pages légales
    const telV = await fenetre({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
    for (const [ch, nom] of [['/', 'tel-accueil'], ['/mentions-legales', 'tel-mentions'], ['/confidentialite', 'tel-confidentialite-2']]) {
      await telV.goto(URL + ch);
      const large = await telV.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      verif(large <= 0, ch + ' ne déborde pas sur téléphone (' + large + ' px)');
      await photo(telV, nom);
    }
    await passant.goto(URL + '/'); await photo(passant, 'pc-accueil');
    await alice.goto(URL + '/admin/liste-attente'); await photo(alice, 'pc-admin-liste');
    await alice.goto(URL + '/mentions-legales');
    verif((await texte(alice)).includes('2 rue Kellermann'), 'mentions légales : l\'hébergeur est indiqué');

    /* ---------- K. l'espace élève (cohorte d'essai) ---------- */
    console.log('K. L\'espace élève');
    const window_URL = require('url').URL;
    const base = (sql) => JSON.parse(require('child_process').execFileSync('php', ['-r', 'require "app/demarrage.php"; echo json_encode(lignes($argv[1]));', sql],
      { cwd: racine, env: Object.assign({}, process.env, { NA_DONNEES: donnees, NA_COURRIER: 'fichier', NA_URL: URL }) }).toString());
    await eleve.goto(URL + '/espace');
    verif((await texte(eleve)).includes('Aucune cohorte'), 'sans cohorte : « Aucune cohorte pour l\'instant »');
    verif(!(await eleve.locator('.side-nav').innerText()).includes('Mon parcours'), 'sans cohorte, pas de « Mon parcours » dans le menu');
    await eleve.goto(URL + '/parcours');
    verif(new window_URL(eleve.url()).pathname === '/espace', '/parcours sans cohorte renvoie au tableau de bord');
    verif((await eleve.goto(URL + '/admin/essai')).status() === 403, 'la cohorte d\'essai est réservée à l\'administrateur');

    await alice.goto(URL + '/admin/essai');
    await envoyer(alice, 'form:has(input[value=creer]) button[type=submit]');
    verif((await texte(alice)).includes('La cohorte d\'essai est prête'), 'cohorte d\'essai créée');
    const mods = base('SELECT id, numero FROM modules ORDER BY numero').map(m => m.id);
    verif(mods.length === 8 && base('SELECT COUNT(*) AS n FROM lecons')[0].n === 40 && base('SELECT COUNT(*) AS n FROM questions')[0].n === 24, '8 modules, 40 leçons, 24 questions');
    verif(fs.readdirSync(path.join(donnees, 'videos')).length === 1, 'la vidéo d\'essai est copiée hors du site');
    await alice.goto(URL + '/espace');
    t = await texte(alice);
    verif(t.includes('Semaine 3 : Ordres limite et ordres au marché'), 'tableau de bord : semaine 3, module 1 à travailler');
    verif(t.includes('Live d\'essai : les exercices du module 3, sur Discord'), 'tableau de bord : le prochain live');
    verif((await alice.locator('.side-nav').innerText()).includes('Mon parcours') && (await alice.locator('.side-box').innerText()).includes('Semaine 3 sur 8'), 'menu de l\'élève et semaine dans la barre de côté');
    await alice.goto(URL + '/parcours');
    t = await texte(alice);
    verif(t.includes('Commencer') && t.includes('quand le quiz du module 1 est validé') && /S'ouvre le \d/.test(t), 'parcours : module 1 ouvert, module 2 attend le quiz, module 4 attend sa date');
    await photo(alice, 'pc-parcours');
    let rr = await alice.goto(URL + '/module?id=' + mods[1]);
    verif(rr.status() === 404 && (await texte(alice)).includes('pas ouvert pour toi'), 'module 2 fermé tant que le quiz 1 n\'est pas validé');
    rr = await alice.goto(URL + '/quiz?m=' + mods[1]);
    verif(rr.status() === 404, 'quiz du module 2 fermé aussi');

    // les 5 leçons du module 1, puis le quiz
    await alice.goto(URL + '/module?id=' + mods[0]);
    await photo(alice, 'pc-module');
    for (let i = 0; i < 5; i++) await envoyer(alice, '.lecon-act button[type=submit]');
    verif(new window_URL(alice.url()).pathname === '/quiz', 'après la 5e leçon, on arrive au quiz');
    verif(base('SELECT COUNT(*) AS n FROM vues')[0].n === 5, 'les 5 leçons sont notées comme vues');
    const bonnes = {};
    for (const q of base('SELECT id, module_id, bonne, choix FROM questions')) bonnes[q.id] = q;
    async function repondre(p, m, justes) {
      await p.goto(URL + '/quiz?m=' + m);
      const ids = Object.values(bonnes).filter(q => q.module_id === m).map(q => q.id);
      for (const [n, id] of ids.entries()) {
        const choix = justes > n ? bonnes[id].bonne : (bonnes[id].bonne + 1) % JSON.parse(bonnes[id].choix).length;
        await p.check('input[name=q' + id + '][value="' + choix + '"]');
      }
      await envoyer(p, '.quiz-form button[type=submit]');
      return texte(p);
    }
    await alice.goto(URL + '/quiz?m=' + mods[0]);
    await photo(alice, 'pc-quiz');
    t = await repondre(alice, mods[0], 1);
    verif(t.includes('1/3') && t.includes('Pas encore') && !t.includes('Bonne réponse'), 'quiz raté : 1/3, sans donner les bonnes réponses');
    t = await repondre(alice, mods[0], 3);
    verif(t.includes('3/3') && t.includes('Quiz validé') && t.includes('Bonne réponse') === false && t.includes('Ouvrir le module 2'), 'quiz réussi : 3/3, le module 2 s\'ouvre');
    await photo(alice, 'pc-quiz-reussi');
    verif((await alice.goto(URL + '/module?id=' + mods[1])).status() === 200, 'module 2 ouvert après le quiz 1');
    t = await repondre(alice, mods[1], 3);
    verif(t.includes('Ouvrir le module 3'), 'quiz 2 réussi : le module 3 s\'ouvre (sa date est passée)');

    // la vraie vidéo du module 3
    await alice.goto(URL + '/module?id=' + mods[2]);
    const src = await alice.getAttribute('video.player-vrai', 'src');
    verif(/^\/video\?g=l&id=\d+&e=\d+&s=[a-f0-9]{40}$/.test(src || ''), 'la leçon a une vidéo avec un lien signé');
    const lue = await alice.waitForFunction(() => { const v = document.querySelector('video.player-vrai'); return v && v.readyState >= 1 && v.duration > 60; }, null, { timeout: 15000 }).then(() => true, () => false);
    verif(lue, 'le lecteur du navigateur charge la vidéo (plus d\'une minute)');
    verif(await alice.getAttribute('video.player-vrai', 'controlslist') === 'nodownload noremoteplayback', 'pas de bouton Télécharger dans le lecteur');
    await photo(alice, 'pc-video');
    let v = await alice.request.get(URL + src, { headers: { Range: 'bytes=0-' } });
    verif(v.status() === 206 && v.headers()['content-type'] === 'video/mp4' && /^bytes 0-4194303\/\d+$/.test(v.headers()['content-range'] || ''), 'vidéo envoyée par morceaux de 4 Mo (206)');
    v = await alice.request.get(URL + src, { headers: { Range: 'bytes=4194304-' } });
    verif(v.status() === 206 && (await v.body()).length > 0, 'le morceau suivant arrive aussi');
    v = await alice.request.get(URL + src, { headers: { Range: 'bytes=0-', 'Sec-Fetch-Dest': 'document' } });
    verif(v.status() === 403, 'ouverte dans un onglet, la vidéo est refusée');
    v = await alice.request.get(URL + src.replace(/s=[a-f0-9]{40}/, 's=' + '0'.repeat(40)), { headers: { Range: 'bytes=0-' } });
    verif(v.status() === 403, 'signature fausse : refus');
    v = await eleve.request.get(URL + src, { headers: { Range: 'bytes=0-' } });
    verif(v.status() === 403, 'le lien d\'Alice ne marche pas pour Dan');
    r = await fetch(URL + src, { headers: { Range: 'bytes=0-' } });
    verif(r.status === 403, 'sans connexion : refus');

    // classement, inscription de Dan, lives, certificat
    await alice.goto(URL + '/classement');
    verif(/Toi \(Alice\)\s*\d+ pts/.test(await texte(alice)), 'classement : Alice avec ses points');
    await alice.goto(URL + '/admin/essai');
    await alice.selectOption('#es-qui', { label: 'Dan (Élève)' });
    await envoyer(alice, 'form:has(input[value=inscrire]) button[type=submit]');
    verif((await texte(alice)).includes('Compte inscrit'), 'Dan inscrit dans la cohorte d\'essai');
    await eleve.goto(URL + '/espace');
    t = await texte(eleve);
    verif(t.includes('Semaine 3') && t.includes('Tu es 2e sur 2'), 'Dan voit la cohorte et sa place au classement');
    verif((await eleve.goto(URL + '/module?id=' + mods[1])).status() === 404, 'pour Dan, le module 2 reste fermé (son quiz 1 n\'est pas fait)');
    await eleve.goto(URL + '/classement');
    t = await texte(eleve);
    verif(t.includes('Alice') && t.includes('Toi (Dan)') && !t.includes('alice@exemple.fr'), 'classement : prénoms seulement');
    await eleve.goto(URL + '/lives');
    t = await texte(eleve);
    verif(t.includes('Live d\'essai : les exercices du module 3') && t.includes('Voir le replay'), 'lives : le prochain live et un replay');
    await envoyer(eleve, 'a[href^="/lives?replay="]');
    verif(await eleve.locator('video.player-vrai').count() === 1, 'le replay se regarde dans la page');
    await eleve.goto(URL + '/certificat');
    verif((await texte(eleve)).includes('Il se débloque quand les 8 modules sont validés'), 'certificat : pas avant les 8 modules');

    // téléphone
    const telA = await fenetre({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
    await connecter(telA, 'alice@exemple.fr', M.alice);
    for (const [ch, nom] of [['/espace', 'tel-espace'], ['/parcours', 'tel-parcours'], ['/module?id=' + mods[2], 'tel-module'], ['/quiz?m=' + mods[2], 'tel-quiz'], ['/lives', 'tel-lives'], ['/classement', 'tel-classement']]) {
      await telA.goto(URL + ch);
      const large = await telA.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      verif(large <= 0, ch + ' ne déborde pas sur téléphone (' + large + ' px)');
      await photo(telA, nom);
    }
    await alice.goto(URL + '/espace'); await photo(alice, 'pc-espace');

    // tout effacer
    await alice.goto(URL + '/admin/essai');
    await envoyer(alice, 'form:has(input[value=supprimer]) button[type=submit]');
    verif((await texte(alice)).includes('La cohorte d\'essai est effacée'), 'cohorte d\'essai effacée');
    verif(['modules', 'lecons', 'questions', 'cohortes', 'inscriptions', 'vues', 'quiz', 'lives'].every(tb => base('SELECT COUNT(*) AS n FROM ' + tb)[0].n === 0), 'plus rien de l\'essai dans la base');
    verif(fs.readdirSync(path.join(donnees, 'videos')).length === 0, 'la vidéo copiée est effacée');
    await eleve.goto(URL + '/espace');
    verif((await texte(eleve)).includes('Aucune cohorte'), 'Dan revient à « Aucune cohorte »');

    /* ---------- L. l'espace formateur ---------- */
    console.log('L. L\'espace formateur');
    const videoEssai = process.env.NA_VIDEO_ESSAI;
    const nbVideos = () => fs.readdirSync(path.join(donnees, 'videos')).length;
    verif((await eleve.goto(URL + '/formateur')).status() === 403, 'espace formateur refusé à une élève');
    verif((await eleve.request.post(URL + '/formateur/envoi?action=debut', { form: { genre: 'lecon', id: '1', taille: '9999', nom: 'a.mp4' } })).status() === 403, 'envoi de vidéo refusé à une élève');
    await alice.goto(URL + '/espace');
    verif((await alice.locator('.side-nav').innerText()).includes('Espace formateur'), 'l\'administrateur a « Espace formateur » dans son menu');
    await alice.goto(URL + '/formateur');
    t = await texte(alice);
    verif(t.includes('Order flow avec Nojah') && t.includes('Crée d\'abord un module') && t.includes('ICT · Weizix'), 'espace formateur : parcours order flow, onglet ICT, pas encore de module');
    await alice.fill('#md-titre', 'Les ordres au marché');
    await envoyer(alice, 'form:has(input[value=module]) button[type=submit]');
    verif((await texte(alice)).includes('Module ajouté'), 'module ajouté');
    await alice.fill('#md-titre', 'Le footprint');
    await envoyer(alice, 'form:has(input[value=module]) button[type=submit]');
    // une leçon avec sa vidéo, en glissant le fichier : création, puis envoi en plusieurs morceaux
    await alice.selectOption('#nl-module', { index: 0 });
    await alice.setInputFiles('.form-lecon .depot-fichier', videoEssai);
    verif(await alice.inputValue('#nl-titre') === 'absorption v2', 'le titre se remplit avec le nom du fichier');
    await alice.fill('#nl-titre', 'Qui frappe au marché');
    await Promise.all([alice.waitForURL(/\/formateur\/lecon\?id=\d+$/, { timeout: 30000 }), alice.click('.form-lecon button[type=submit]')]);
    t = await texte(alice);
    verif(t.includes('Vidéo en ligne') && await alice.locator('video.player-vrai').count() === 1, 'leçon créée, vidéo envoyée par morceaux et en ligne');
    const lecon1 = base('SELECT * FROM lecons ORDER BY id DESC LIMIT 1')[0];
    verif(lecon1.video && /^[a-f0-9]{32}\.mp4$/.test(lecon1.video) && Math.abs(lecon1.duree - 64) <= 1 && fs.statSync(path.join(donnees, 'videos', lecon1.video)).size === fs.statSync(videoEssai).size, 'la vidéo est rangée hors du site, entière, avec sa durée (' + lecon1.duree + ' s)');
    verif(fs.readdirSync(path.join(donnees, 'envois')).length === 0, 'plus rien dans le dossier des envois');
    const lueF = await alice.waitForFunction(() => { const v = document.querySelector('video.player-vrai'); return v && v.readyState >= 1; }, null, { timeout: 15000 }).then(() => true, () => false);
    verif(lueF, 'le formateur voit sa vidéo, même sans cohorte');
    await photo(alice, 'pc-formateur-lecon');
    // un faux fichier vidéo est refusé
    await alice.setInputFiles('.depot .depot-fichier', { name: 'faux.mp4', mimeType: 'video/mp4', buffer: Buffer.alloc(6000, 120) });
    await alice.waitForSelector('.depot-erreur', { timeout: 15000 });
    verif((await alice.locator('.depot-erreur').innerText()).includes('pas une vidéo MP4') && base('SELECT video FROM lecons WHERE id = ' + lecon1.id)[0].video === lecon1.video, 'un faux MP4 est refusé, l\'ancienne vidéo reste');
    await alice.goto(URL + '/formateur/lecon?id=' + lecon1.id);
    // une leçon sans vidéo, puis le quiz
    await alice.goto(URL + '/formateur/lecon?m=' + lecon1.module_id);
    await alice.fill('#nl-titre', 'Le carnet et le prix');
    await envoyer(alice, '.form-lecon button[type=submit]');
    verif((await texte(alice)).includes('Leçon en ligne') && (await texte(alice)).includes('Pas encore de vidéo'), 'leçon sans vidéo créée');
    await alice.goto(URL + '/formateur/question?m=' + lecon1.module_id);
    await alice.fill('#q-enonce', 'Un ordre au marché à l\'achat passe…');
    await alice.fill('#q-choix-0', 'Tout de suite, au meilleur prix vendeur');
    await alice.fill('#q-choix-1', 'Seulement à mon prix');
    await alice.check('#q-bonne-0');
    await envoyer(alice, 'button[name=encore]');
    verif(new window_URL(alice.url()).search === '?m=' + lecon1.module_id && (await texte(alice)).includes('Question enregistrée'), '« enregistrer et en ajouter une autre »');
    await alice.fill('#q-enonce', 'Une question sans bonne réponse');
    await alice.fill('#q-choix-0', 'Oui'); await alice.fill('#q-choix-1', 'Non');
    await alice.evaluate(() => { document.querySelectorAll('input[name=bonne]').forEach(r => { r.checked = false; r.required = false; }); });
    await envoyer(alice, '.lecon-act button.btn-main');
    verif((await texte(alice)).includes('Coche la bonne réponse'), 'question refusée sans bonne réponse');
    await alice.goto(URL + '/formateur/module?id=' + lecon1.module_id);
    t = await texte(alice);
    verif(t.includes('1 question') && t.includes('Qui frappe au marché') && t.includes('Le carnet et le prix'), 'page du module : 2 leçons et le quiz');
    await envoyer(alice, 'form:has(input[value=lecon-deplacer]):has(input[name=sens][value="1"]) button');
    verif(base('SELECT titre FROM lecons WHERE module_id = ' + lecon1.module_id + ' ORDER BY numero')[0].titre === 'Le carnet et le prix', 'les leçons se réordonnent');
    await photo(alice, 'pc-formateur-module');
    // une cohorte, un élève, un live passé et son replay
    await alice.goto(URL + '/formateur');
    await alice.fill('#ch-nom', 'Cohorte 1');
    await alice.fill('#ch-jour', new Date(Date.now() - 2 * 86400000).toISOString().slice(0, 10));
    await envoyer(alice, 'form:has(input[value=cohorte]) button[type=submit]');
    verif((await texte(alice)).includes('Cohorte créée'), 'cohorte créée par l\'administrateur');
    const cohorte1 = new window_URL(alice.url()).searchParams.get('id');
    await alice.selectOption('#in-qui', { label: 'Dan (Élève, da…@exemple.fr)' });
    await envoyer(alice, 'form:has(input[value=inscrire]) button[type=submit]');
    verif((await texte(alice)).includes('Élève inscrit'), 'Dan inscrit dans la cohorte 1');
    await alice.goto(URL + '/formateur');
    await alice.fill('#lv-jour', new Date(Date.now() - 86400000).toISOString().slice(0, 10));
    await alice.fill('#lv-titre', 'Les exercices du module 1');
    await envoyer(alice, 'form:has(input[value=live]) button[type=submit]');
    verif((await texte(alice)).includes('Live programmé'), 'live programmé');
    await alice.fill('#lv-titre', 'Live à venir');
    await alice.fill('#lv-lien', 'https://exemple.fr/pas-discord');
    await envoyer(alice, 'form:has(input[value=live]) button[type=submit]');
    verif((await texte(alice)).includes('Le lien doit être un lien Discord'), 'un lien qui n\'est pas Discord est refusé');
    await alice.goto(URL + '/formateur/cohorte?id=' + cohorte1);
    const avantReplay = nbVideos();
    await alice.setInputFiles('.mod-live .depot-fichier', videoEssai);
    await alice.waitForFunction(() => document.body.innerText.includes('replay en ligne'), null, { timeout: 30000 });
    verif(nbVideos() === avantReplay + 1, 'replay envoyé et en ligne');
    t = await texte(alice);
    verif(t.includes('Dan') && t.includes('Pas commencé'), 'suivi : Dan apparaît, pas commencé');
    await photo(alice, 'pc-formateur-cohorte');
    // côté élève : le module, la vidéo, le quiz (réponses mélangées), le replay
    await eleve.goto(URL + '/espace');
    t = await texte(eleve);
    verif(t.includes('Le footprint') === false || t.includes('Les ordres au marché'), 'Dan voit le parcours créé par le formateur');
    await eleve.goto(URL + '/parcours');
    await envoyer(eleve, 'a.btn-main[href^="/module?id="]');
    verif(await eleve.locator('video.player-vrai').count() === 0 && (await texte(eleve)).includes('Le carnet et le prix'), 'Dan commence par la 1re leçon (sans vidéo)');
    await envoyer(eleve, '.lecon-act button[type=submit]');
    verif(await eleve.locator('video.player-vrai').count() === 1, 'la 2e leçon a la vidéo envoyée par le formateur');
    await envoyer(eleve, '.lecon-act button[type=submit]');
    verif(new window_URL(eleve.url()).pathname === '/quiz', 'puis le quiz');
    const q1 = base('SELECT id, bonne FROM questions WHERE module_id = ' + lecon1.module_id)[0];
    await eleve.check('input[name=q' + q1.id + '][value="' + q1.bonne + '"]');
    await envoyer(eleve, '.quiz-form button[type=submit]');
    verif((await texte(eleve)).includes('1/1') && (await texte(eleve)).includes('Quiz validé'), 'Dan réussit le quiz du formateur');
    await eleve.goto(URL + '/lives');
    verif((await texte(eleve)).includes('Voir le replay'), 'Dan voit le replay du live');
    await alice.goto(URL + '/formateur/cohorte?id=' + cohorte1);
    t = await texte(alice);
    verif(t.includes('1 / 2') && /À jour|Terminé/.test(t) && t.includes('Moyenne des meilleurs essais : 1,0 sur 1'), 'suivi : Dan à jour, 1 module validé sur 2, chiffres du quiz');

    // un formateur : seulement les parcours de son équipe
    await alice.goto(URL + '/admin');
    await alice.fill('#c-note', 'Eve');
    await alice.selectOption('#c-role', 'formateur');
    await envoyer(alice, 'form:has(input[value=inviter]) button[type=submit]');
    const eve = await fenetre();
    await eve.goto(await alice.inputValue('#lien-invitation'));
    await inscrire(eve, 'Eve', 'eve@exemple.fr', mdp());
    await eve.goto(URL + '/formateur');
    verif((await texte(eve)).includes('ne t\'a pas encore confié de parcours'), 'formateur sans parcours : message clair');
    await alice.goto(URL + '/formateur?p=2');
    await alice.selectOption('#eq-qui', { label: 'Eve' });
    await envoyer(alice, 'form:has(input[value=equipe]) button[type=submit]');
    verif((await texte(alice)).includes('Ajouté à l\'équipe'), 'Eve ajoutée à l\'équipe ICT');
    await eve.goto(URL + '/formateur');
    t = await texte(eve);
    verif(t.includes('ICT avec Weizix') && !t.includes('Order flow avec Nojah') && !t.includes('Créer une cohorte'), 'Eve ne voit que l\'ICT, sans création de cohorte');
    verif((await eve.goto(URL + '/formateur/module?id=' + lecon1.module_id)).status() === 403, 'Eve ne peut pas ouvrir un module d\'order flow');
    verif((await eve.goto(URL + '/formateur/cohorte?id=' + cohorte1)).status() === 403, 'ni la cohorte d\'order flow');
    r = await eve.request.post(URL + '/formateur/envoi?action=debut', { form: { genre: 'lecon', id: String(lecon1.id), taille: '99999', nom: 'a.mp4' }, headers: { 'X-Jeton': await eve.inputValue('input[name=jeton]').catch(() => '') } });
    verif(r.status() === 422 || r.status() === 403, 'Eve ne peut pas envoyer de vidéo dans une leçon d\'order flow');

    // téléphone
    for (const [ch, nom] of [['/formateur', 'tel-formateur'], ['/formateur/lecon?id=' + lecon1.id, 'tel-formateur-lecon'], ['/formateur/cohorte?id=' + cohorte1, 'tel-formateur-cohorte'], ['/formateur/question?m=' + lecon1.module_id, 'tel-formateur-question']]) {
      await telA.goto(URL + ch);
      const large = await telA.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      verif(large <= 0, ch + ' ne déborde pas sur téléphone (' + large + ' px)');
      await photo(telA, nom);
    }
    await alice.goto(URL + '/formateur?p=1'); await photo(alice, 'pc-formateur');

    // effacer : la leçon et sa vidéo, puis le module
    const avantSup = nbVideos();
    const l2 = base('SELECT id FROM lecons WHERE video IS NOT NULL AND module_id = ' + lecon1.module_id)[0];
    await alice.goto(URL + '/formateur/lecon?id=' + l2.id + '&supprimer=1');
    await envoyer(alice, 'form:has(input[value=supprimer]) button');
    verif((await texte(alice)).includes('Leçon effacée') && nbVideos() === avantSup - 1, 'leçon effacée avec sa vidéo');
    await alice.goto(URL + '/formateur/module?id=' + lecon1.module_id + '&supprimer=1');
    await envoyer(alice, 'form:has(input[value=supprimer]) button');
    verif((await texte(alice)).includes('Module effacé') && base('SELECT COUNT(*) AS n FROM modules')[0].n === 1 && base('SELECT numero FROM modules')[0].numero === 1, 'module effacé, le suivant devient le module 1');

    /* ---------- M. l'envoi des e-mails par la boîte (SMTP) ---------- */
    console.log('M. L\'envoi des e-mails par la boîte');
    await alice.goto(URL + '/admin/courriel');
    t = await texte(alice);
    verif(t.includes('Les e-mails partent par l\'hébergement') && (await alice.locator('.side-nav').innerText()).includes('E-mails'), 'page E-mails : par défaut, l\'envoi par l\'hébergement');
    const curieux = await fenetre();
    await curieux.goto(URL + '/admin/courriel');
    verif(new window_URL(curieux.url()).pathname === '/connexion', 'page E-mails fermée à qui n\'est pas connecté');
    await curieux.context().close();

    // un deuxième site, qui envoie pour de vrai, et une fausse boîte e-mail en face
    const net = require('net');
    const donnees2 = fs.mkdtempSync(path.join(os.tmpdir(), 'na-essais-smtp-'));
    const PORT2 = 8767, URL2 = 'http://127.0.0.1:' + PORT2, PORT_SMTP = 2526;
    const BOITE = 'contact@boite.exemple', MDP_BOITE = 'Boîte ' + crypto.randomBytes(9).toString('base64url') + ' .é@';
    const recus = []; let refuse = false, connexions = 0;
    const fausseBoite = net.createServer(sock => {
      connexions++;
      let tampon = '', donnee = null, msg = { de: null, a: null, auth: false, brut: '' };
      const dire = l => sock.write(l + '\r\n');
      dire('220 fausse boite');
      sock.on('error', () => {});
      sock.on('data', d => {
        tampon += d.toString('utf8');
        let i;
        while ((i = tampon.indexOf('\r\n')) !== -1) {
          const ligne = tampon.slice(0, i); tampon = tampon.slice(i + 2);
          if (donnee !== null) {
            if (ligne === '.') { msg.brut = donnee.join('\r\n'); recus.push(msg); donnee = null; msg = { de: null, a: null, auth: msg.auth, brut: '' }; dire('250 2.0.0 Ok: queued'); }
            else donnee.push(ligne.startsWith('..') ? ligne.slice(1) : ligne);
            continue;
          }
          const cmd = ligne.toUpperCase();
          if (cmd.startsWith('EHLO')) { sock.write('250-fausse.boite\r\n250-SIZE 10240000\r\n250-AUTH PLAIN LOGIN\r\n250 8BITMIME\r\n'); }
          else if (cmd.startsWith('AUTH PLAIN ')) {
            const [, u, m] = Buffer.from(ligne.slice(11), 'base64').toString('utf8').split('\0');
            if (!refuse && u === BOITE && m === MDP_BOITE) { msg.auth = true; dire('235 2.7.0 Authentication successful'); } else dire('535 5.7.8 Error: authentication failed');
          }
          else if (cmd.startsWith('MAIL FROM:')) { if (!msg.auth) dire('530 5.7.0 Authentication required'); else { msg.de = ligne.slice(10).replace(/[<>]/g, ''); dire('250 2.1.0 Ok'); } }
          else if (cmd.startsWith('RCPT TO:')) { msg.a = ligne.slice(8).replace(/[<>]/g, ''); dire('250 2.1.5 Ok'); }
          else if (cmd === 'DATA') { donnee = []; dire('354 End data with <CR><LF>.<CR><LF>'); }
          else if (cmd === 'QUIT') { dire('221 2.0.0 Bye'); sock.end(); }
          else dire('502 5.5.2 Error: command not recognized');
        }
      });
    });
    await new Promise(r => fausseBoite.listen(PORT_SMTP, '127.0.0.1', r));
    // sendmail_path=/bin/true : l'envoi « par l'hébergement » réussit sans rien envoyer
    const serveur2 = spawn('php', ['-d', 'sendmail_path=/bin/true', '-S', '127.0.0.1:' + PORT2, 'outils/serveur.php'], { cwd: racine, env: Object.assign({}, process.env, { NA_URL: URL2, NA_COURRIER: 'mail', NA_PAR_BOITE: '1', NA_DONNEES: donnees2, NA_SMTP_HOTE: '127.0.0.1', NA_SMTP_PORT: String(PORT_SMTP) }), stdio: 'ignore' });
    try {
      for (let i = 0; i < 50; i++) { try { const r = await fetch(URL2 + '/sante'); if (r.ok) break; } catch (e) {} await pause(100); }
      const base2 = (sql) => JSON.parse(require('child_process').execFileSync('php', ['-r', 'require "app/demarrage.php"; echo json_encode(lignes($argv[1]));', sql],
        { cwd: racine, env: Object.assign({}, process.env, { NA_DONNEES: donnees2, NA_COURRIER: 'fichier', NA_URL: URL2 }) }).toString());
      const sante2 = async () => (await (await fetch(URL2 + '/sante')).json()).courriel_par;
      const corpsDe = m => Buffer.from((m.brut.split('Content-Transfer-Encoding: base64\r\n\r\n')[1] || '').split('\r\n--')[0].replace(/\r\n/g, ''), 'base64').toString('utf8');
      const sujetDe = m => ((m.brut.match(/^Subject: ((?:.*)(?:\r\n[ \t].*)*)/m) || [])[1] || '').replace(/\r\n[ \t]/g, '').replace(/=\?UTF-8\?B\?([^?]*)\?=/g, (x, b) => Buffer.from(b, 'base64').toString('utf8'));

      const zoe = await fenetre();
      await zoe.goto(URL2 + '/inscription');
      await zoe.fill('#c-prenom', 'Zoé'); await zoe.fill('#c-email', 'zoe@exemple.fr'); await zoe.fill('#c-mdp', mdp());
      await Promise.all([zoe.waitForNavigation(), zoe.click('button[type=submit].btn-main')]);
      verif(recus.length === 0 && await sante2() === 'hébergement', 'boîte pas branchée : rien ne passe par elle');

      await zoe.goto(URL2 + '/admin/courriel');
      verif(await zoe.inputValue('#c-boite') === 'contact@noweiz.fr' && await zoe.inputValue('#c-essai') === 'zoe@exemple.fr', 'formulaire prérempli : la boîte contact, et l\'essai vers l\'adresse de l\'admin');
      await zoe.fill('#c-boite', BOITE); await zoe.fill('#c-mdp_boite', 'pas le bon');
      await Promise.all([zoe.waitForNavigation(), zoe.click('form:has(input[value=brancher]) button[type=submit]')]);
      t = await texte(zoe);
      verif(t.includes('La boîte a refusé l\'identifiant ou le mot de passe') && recus.length === 0, 'mauvais mot de passe : refus expliqué, rien n\'est envoyé');
      verif(base2("SELECT COUNT(*) AS n FROM reglages WHERE cle = 'smtp'")[0].n === 0 && await zoe.inputValue('#c-mdp_boite') === '' && await zoe.inputValue('#c-boite') === BOITE, 'mauvais mot de passe : rien n\'est gardé, le champ est vide, l\'adresse reste');

      await zoe.fill('#c-mdp_boite', MDP_BOITE);
      await Promise.all([zoe.waitForNavigation(), zoe.click('form:has(input[value=brancher]) button[type=submit]')]);
      t = await texte(zoe);
      verif(t.includes('La boîte est branchée') && t.includes('Les e-mails partent par la boîte ' + BOITE), 'bon mot de passe : la boîte est branchée');
      verif(recus.length === 1 && recus[0].de === BOITE && recus[0].a === 'zoe@exemple.fr', 'l\'e-mail d\'essai est parti par la boîte, vers l\'adresse choisie');
      const e1 = recus[0] || { brut: '' };
      verif(/^From: Noweiz Academy <contact@boite\.exemple>$/m.test(e1.brut) && /^To: <zoe@exemple\.fr>$/m.test(e1.brut) && /^Date: /m.test(e1.brut) && /^Message-ID: <[a-f0-9]{32}@boite\.exemple>$/m.test(e1.brut),
        'e-mail complet : expéditeur = la boîte, destinataire, date, identifiant');
      verif(sujetDe(e1).includes('Essai d\'envoi') && corpsDe(e1).includes('L\'envoi des e-mails fonctionne'), 'objet et texte de l\'essai lisibles');
      const garde = base2("SELECT valeur FROM reglages WHERE cle = 'smtp'")[0].valeur;
      const tout = fs.readdirSync(donnees2).filter(f => fs.statSync(path.join(donnees2, f)).isFile()).map(f => fs.readFileSync(path.join(donnees2, f))).reduce((a, b) => Buffer.concat([a, b]), Buffer.alloc(0));
      verif(!garde.includes(MDP_BOITE) && /"mdp":"s1:/.test(garde) && !tout.includes(Buffer.from(MDP_BOITE, 'utf8')) && !tout.includes(Buffer.from(MDP_BOITE, 'utf8').toString('base64')),
        'le mot de passe de la boîte n\'est écrit en clair nulle part dans les données (base, journal)');
      verif((fs.statSync(path.join(donnees2, 'cle-courriel')).mode & 0o077) === 0, 'la clé de chiffrement n\'est lisible que par le site');
      verif(!(await zoe.content()).includes(MDP_BOITE) && await sante2() === 'boîte', 'le mot de passe n\'est jamais réaffiché ; /sante dit « boîte »');

      // un vrai e-mail du site passe maintenant par la boîte
      const passant2 = await fenetre();
      await passant2.goto(URL2 + '/');
      await passant2.fill('#wl-prenom', 'Nina'); await passant2.fill('#wl-email', 'nina@exemple.fr'); await passant2.check('#wl-accord');
      await Promise.all([passant2.waitForNavigation(), passant2.click('#wl-envoi')]);
      verif((await texte(passant2)).includes('Plus qu\'une étape') && recus.length === 2 && recus[1].a === 'nina@exemple.fr' && recus[1].de === BOITE, 'liste d\'attente : l\'e-mail de confirmation part par la boîte');
      const e2 = recus[1] || { brut: '' };
      const lien2 = (corpsDe(e2).match(/https?:\/\/\S+/) || [])[0] || '';
      verif(sujetDe(e2).includes('Confirme ton inscription') && lien2.startsWith(URL2 + '/liste-attente/confirmer?lien='), 'son lien de confirmation est intact');
      await passant2.goto(lien2);
      verif((await texte(passant2)).includes('C\'est confirmé, Nina'), 'et il marche');
      verif(e2.brut.split('\r\n').every(l => l.length <= 998), 'aucune ligne trop longue pour un serveur d\'e-mails');

      // la boîte refuse (mot de passe changé chez OVH) : l'e-mail part quand même, par l'hébergement, et l'admin le voit
      refuse = true;
      await passant2.goto(URL2 + '/');
      await passant2.fill('#wl-prenom', 'Omar'); await passant2.fill('#wl-email', 'omar@exemple.fr'); await passant2.check('#wl-accord');
      await Promise.all([passant2.waitForNavigation(), passant2.click('#wl-envoi')]);
      verif((await texte(passant2)).includes('Plus qu\'une étape') && recus.length === 2, 'boîte en panne : le visiteur ne voit rien, l\'e-mail part par l\'hébergement');
      await zoe.goto(URL2 + '/admin/courriel');
      t = await texte(zoe);
      verif(t.includes('Dernière panne') && t.includes('refusé l\'identifiant ou le mot de passe') && await sante2() === 'boîte (en panne : hébergement)', 'la panne est affichée à l\'admin et dans /sante');
      await Promise.all([zoe.waitForNavigation(), zoe.click('form:has(input[value=essai]) button[type=submit]')]);
      verif((await texte(zoe)).includes('parti par l\'hébergement à la place'), 'essai pendant la panne : dit clairement que l\'e-mail n\'est pas passé par la boîte');
      refuse = false;
      await zoe.fill('#c-essai', 'ailleurs@exemple.fr');
      await Promise.all([zoe.waitForNavigation(), zoe.click('form:has(input[value=essai]) button[type=submit]')]);
      t = await texte(zoe);
      verif(t.includes('OVH a accepté l\'e-mail d\'essai') && !t.includes('Dernière panne') && recus.length === 3 && recus[2].a === 'ailleurs@exemple.fr' && await sante2() === 'boîte', 'boîte revenue : l\'essai passe, la panne s\'efface');

      // le serveur ne répond plus du tout : on n'attend pas à chaque e-mail
      await new Promise(r => fausseBoite.close(r));
      const avantCoupure = connexions;
      await passant2.goto(URL2 + '/');
      await passant2.fill('#wl-prenom', 'Paco'); await passant2.fill('#wl-email', 'paco@exemple.fr'); await passant2.check('#wl-accord');
      await Promise.all([passant2.waitForNavigation(), passant2.click('#wl-envoi')]);
      verif((await texte(passant2)).includes('Plus qu\'une étape'), 'serveur d\'envoi injoignable : l\'inscription marche quand même');
      const r2 = JSON.parse(base2("SELECT valeur FROM reglages WHERE cle = 'smtp'")[0].valeur);
      verif(/pas pu joindre 127\.0\.0\.1/.test(r2.erreur) && r2.pause > Date.now() / 1000 + 300 && connexions === avantCoupure, 'injoignable : panne notée, la boîte est mise de côté 10 minutes');

      await zoe.goto(URL2 + '/admin/courriel');
      await Promise.all([zoe.waitForNavigation(), zoe.click('form:has(input[value=debrancher]) button[type=submit]')]);
      t = await texte(zoe);
      verif(t.includes('Boîte débranchée') && t.includes('Les e-mails partent par l\'hébergement') && base2("SELECT COUNT(*) AS n FROM reglages WHERE cle = 'smtp'")[0].n === 0 && await sante2() === 'hébergement', 'débrancher : le mot de passe est effacé, retour à l\'hébergement');
      const journal2 = path.join(donnees2, 'erreurs.log');
      const j2 = fs.existsSync(journal2) ? fs.readFileSync(journal2, 'utf8') : '';
      verif(j2.split('\n').filter(l => l.trim() !== '' && !/Envoi par la boîte refusé/.test(l)).length === 0, 'journal du deuxième site : seulement les pannes de boîte attendues : ' + j2.slice(0, 300));
      await zoe.context().close(); await passant2.context().close();
    } finally {
      serveur2.kill();
      try { fausseBoite.close(); } catch (e) {}
      if (!process.env.NA_GARDER) fs.rmSync(donnees2, { recursive: true, force: true });
    }

    /* ---------- N. le nouveau style de l'accueil ---------- */
    console.log('N. Le nouveau style de l\'accueil');
    const apercu = await fenetre();
    await apercu.goto(URL + '/');
    t = await texte(apercu);
    verif(t.includes('L\'order flow') && t.includes('avec Weizix.') && t.includes('Exemple illustratif') && t.includes('Les chiffres de cet exemple sont inventés'), 'aperçu : le titre, et le footprint annoncé comme un exemple aux chiffres inventés');
    verif(await apercu.locator('.fp-c').count() === 31 && await apercu.locator('.etape').count() === 8 && (await apercu.locator('.faits dt').allInnerTexts()).join('|').includes('13 certificats'), 'aperçu : 31 cases de footprint, 8 étapes du parcours, les faits (13 certificats)');
    verif(!/8 semaines|huit semaines|par semaine|chaque semaine/i.test(t), 'accueil : aucune durée de formation annoncée (pas encore fixée)');
    verif((await apercu.locator('meta[name=robots]').count()) === 0, 'accueil ouvert au public : référençable (plus de noindex)');
    const html = await apercu.content();
    verif(!/\d,\d\d(&nbsp;|\s|\u00a0)?€/.test(html) && html.includes('Prix annoncé bientôt') && html.includes('annoncés d\'abord aux inscrits'), 'accueil : aucun prix affiché (prix_visibles = false)');
    verif(await apercu.locator('.reseaux a').count() === 8 && html.includes('instagram.com/fx_nojah') && html.includes('tiktok.com/@nojahonorderflow') && html.includes('t.me/+zSf9D4ee_4w5NzM0') && html.includes('discord.gg/zVJuSdCjSG'), 'accueil : les 4 réseaux, en haut et en bas');
    verif(await apercu.locator('#compteur').count() === 1 && t.includes('Ouverture en 2027') && /avant 2027/.test(t) && +(await apercu.locator('#t-j').innerText()) > 0, 'accueil : compte à rebours jusqu\'en 2027');
    verif(await apercu.locator('video').count() === 0 && !html.includes('absorption-v2') && !html.includes('#extrait'), 'accueil : plus d\'extrait vidéo (retiré à la demande de Nojah)');
    verif(await apercu.evaluate(() => !document.documentElement.hasAttribute('data-scene') && document.documentElement.getAttribute('data-rubans') !== 'animes' && !document.querySelector('.fp[data-vif]') && document.querySelectorAll('.etape.allumee').length === 8
      && getComputedStyle(document.querySelector('.fp-grille')).display === 'grid' && getComputedStyle(document.querySelector('.carte-lecture')).display === 'none'),
      'aperçu en mouvement réduit : pas de scène, rubans fixes ou absents, footprint en grille fixe, les 8 étapes allumées');
    verif(await apercu.evaluate(() => !/C6F432|198, 244, 50/i.test(getComputedStyle(document.querySelector('.parcours')).backgroundColor + getComputedStyle(document.body).backgroundColor)), 'aperçu : le vert n\'est jamais un fond de page');
    const anime = await fenetre({ reducedMotion: 'no-preference' });
    await anime.goto(URL + '/');
    await pause(900);
    verif(await anime.evaluate(() => document.documentElement.hasAttribute('data-scene') && !!document.querySelector('.fp[data-vif]') && getComputedStyle(document.querySelector('.fp-toile')).display === 'block' && getComputedStyle(document.querySelector('.fp-grille')).display === 'none'),
      'aperçu animé : la scène du parcours est en place, le footprint vivant remplace la grille');
    const lu1 = await anime.locator('[data-lecture] b').innerText();
    const cvd1 = await anime.locator('[data-cvd]').innerText();
    await pause(1500);
    verif(lu1 === 'Absorption' && (await anime.locator('[data-cvd]').innerText()) !== cvd1 && /achats sur deux niveaux|revient tester/.test(await anime.locator('[data-lecture] span').innerText()), 'aperçu animé : la bougie se construit (le delta cumulé bouge), la carte lit une absorption');
    const sec = await anime.evaluate(() => { const r = document.querySelector('.parcours').getBoundingClientRect(); return { haut: r.top + window.scrollY, h: r.height }; });
    await anime.evaluate(y => window.scrollTo(0, y), sec.haut + 10);
    await pause(500);
    const debut = await anime.evaluate(() => document.querySelectorAll('.etape.allumee').length);
    await anime.evaluate(y => window.scrollTo(0, y), sec.haut + (sec.h - 900) * 0.5);
    await pause(500);
    const milieu = await anime.evaluate(() => document.querySelectorAll('.etape.allumee').length);
    await anime.evaluate(y => window.scrollTo(0, y), sec.haut + sec.h - 900 - 30);
    await pause(700);
    const bout = await anime.evaluate(() => ({ n: document.querySelectorAll('.etape.allumee').length, fin: document.querySelector('.parcours').hasAttribute('data-fin'), avance: parseFloat(document.querySelector('.parcours').getAttribute('data-avance')) }));
    verif(debut <= 1 && milieu >= 3 && milieu <= 6 && bout.n === 8 && bout.fin && bout.avance >= 7, 'aperçu animé : les étapes s\'allument au défilement (' + debut + ', puis ' + milieu + ', puis ' + bout.n + ')');
    await apercu.context().close(); await anime.context().close();

    verif(erreursPage.length === 0, 'aucune erreur de script ni de sécurité dans le navigateur : ' + erreursPage.slice(0, 3).join(' / '));
    const journal = path.join(donnees, 'erreurs.log');
    verif(!fs.existsSync(journal) || fs.readFileSync(journal, 'utf8').trim() === '', 'journal d\'erreurs du serveur vide : ' + (fs.existsSync(journal) ? fs.readFileSync(journal, 'utf8').slice(0, 300) : ''));
  } catch (e) {
    rates.push('arrêt : ' + e.message.split('\n')[0]);
    console.log('  ARRÊT : ' + e.stack.split('\n').slice(0, 4).join('\n'));
  } finally {
    await nav.close();
    serveur.kill();
  }
  console.log('\n' + ok + ' vérifications réussies, ' + rates.length + ' ratée' + (rates.length > 1 ? 's' : '') + '.');
  if (captures) console.log('Captures : ' + captures);
  if (process.env.NA_GARDER) console.log('Données gardées : ' + donnees); else fs.rmSync(donnees, { recursive: true, force: true });
  process.exit(rates.length ? 1 : 0);
})();
