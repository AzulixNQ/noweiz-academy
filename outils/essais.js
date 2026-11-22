/* Les essais automatiques des comptes. Lance son propre serveur, sur une base vide, puis joue les parcours.
     node outils/essais.js            tous les essais
     node outils/essais.js captures   en plus, des captures d'écran dans le dossier donné par NA_CAPTURES
   Les mots de passe d'essai sont tirés au hasard à chaque lancement. */
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const fs = require('fs'), os = require('os'), path = require('path'), crypto = require('crypto');

const PORT = 8766, URL = 'http://127.0.0.1:' + PORT;
const racine = path.resolve(__dirname, '..');
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
  const serveur = spawn('php', ['-S', '127.0.0.1:' + PORT, 'outils/serveur.php'], { cwd: racine, env: Object.assign({}, process.env, { NA_URL: URL, NA_COURRIER: 'fichier', NA_DONNEES: donnees }), stdio: 'ignore' });
  for (let i = 0; i < 50; i++) { try { const r = await fetch(URL + '/sante'); if (r.ok) break; } catch (e) {} await pause(100); }

  const nav = await chromium.launch();
  const erreursPage = [];
  async function fenetre(o) {
    const c = await nav.newContext(Object.assign({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR', reducedMotion: 'reduce' }, o || {}));   // sans défilement doux : les clics des essais tombent juste
    const p = await c.newPage();
    p.on('pageerror', e => erreursPage.push('erreur de script : ' + e.message));
    p.on('console', m => { if (m.type() === 'error' && !/status of 40[34]|status of 421/.test(m.text())) erreursPage.push('console : ' + m.text()); });
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
