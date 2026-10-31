# Accessibilité — audit du site public

Audit réalisé avant la mise en production sur le site public et les pages
légales (le back-office Filament et le mode terrain ont leurs propres
vérifications). Référentiel : WCAG 2.1 niveau AA / RGAA 4.1, sur les critères
vérifiables sans outil spécialisé.

## Méthode

1. Relecture des gabarits : structure des titres, points de repère, ordre de
   tabulation, libellés, images, formulaires.
2. Navigation réelle au clavier et au lecteur d’écran sur les pages clés
   (`/`, `/contact`, `/devis`, `/devis/laser`, `/devis/batiment`, `/realisations`,
   `/mentions-legales`, `/confidentialite`).
3. Calcul des contrastes sur les couples texte/fond des jetons de couleur.
4. Contrôles en 375 px et 1280 px, avec et sans JavaScript pour les contenus
   critiques.

Aucun outil automatisé n’a été ajouté au projet (choix validé) : les
non-régressions structurelles sont couvertes par `tests/Feature/AccessibilityTest.php`.

## Corrections apportées

### Structure et navigation

- Lien d’évitement « Aller au contenu principal » en tête de page, visible au
  focus, ciblant `<main id="main" tabindex="-1">`.
- Indicateur de focus commun (`:focus-visible`, contour 2 px) sur tous les
  éléments interactifs, y compris ceux qui n’avaient pas de style dédié.
- Menu services : `aria-expanded`, `aria-haspopup`, `aria-controls`, fermeture
  par `Échap` et au clic extérieur.
- Menu mobile : nom accessible dynamique (« Ouvrir »/« Fermer le menu »),
  `aria-controls`, fermeture par `Échap`.
- Liens de navigation : `aria-current="page"` sur la page active.
- Logo de l’en-tête : `alt=""` (le lien porte déjà le nom accessible), évitant
  une double annonce.

### Formulaires

- Champs obligatoires : `aria-required="true"` et mention « (obligatoire) »
  réservée aux lecteurs d’écran ; l’astérisque est décoratif (`aria-hidden`).
- Erreurs : résumé `role="alert"` en tête de formulaire, avec liens vers les
  champs concernés ; chaque champ en erreur reçoit `aria-invalid="true"` et
  `aria-describedby` vers son message.
- Messages d’erreur dotés d’un `id` unique.
- Cartes de choix (matière, type de pièce, enveloppe) : `role="radiogroup"`,
  libellé associé et anneau de focus sur la carte (`focus-within`).
- Dépôt de plans : zone cliquable focusable, erreurs associées.
- Mention RGPD sous chaque formulaire, avec lien vers la politique de
  confidentialité (`x-ui.privacy-notice`).
- Messages de validation traduits en français (`lang/fr/validation.php`) :
  sans ce fichier, les formulaires affichaient « validation.required ».

### Contenus et médias

- Galerie de réalisations : filtres convertis de `role="tab"` (sans panneau
  associé) en boutons `aria-pressed` dans un groupe libellé.
- Visionneuse : focus déplacé sur le bouton de fermeture à l’ouverture,
  restitué au déclencheur à la fermeture, fermeture par `Échap`.
- Icônes et flèches décoratives : `aria-hidden="true"`.
- Liens externes : mention « (nouvel onglet) » réservée aux lecteurs d’écran.
- Tableaux des pages légales : `scope="col"` sur les en-têtes.
- Titres de cartes de la page contact passés de `<p>` à `<h2>`.

### Contrastes

| Élément | Avant | Après |
| --- | --- | --- |
| Texte atténué (`text-ink-700/60`, `/50`, placeholders `/40`) | 3,0 à 3,6:1 | `/70` → 5,2:1 |
| Liens et libellés `brand-600` (#0d8888) | 4,3:1 | #0b7c7c → 5,0:1 |
| Boutons pleins `brand-500` avec texte blanc | 3,1:1 | `brand-600` → 5,0:1 |
| Liens de la mention RGPD | — | `brand-700` → 6,1:1 |

Le logo et les aplats décoratifs conservent la teinte d’origine (#0FA1A1).

## Points connus, non bloquants

- Les boutons `brand-500` purement décoratifs (pastilles d’icônes) conservent
  leur teinte : ils ne portent pas de texte.
- Le filtre de la galerie et la visionneuse ne piègent pas le focus à
  l’intérieur de la modale : `Échap` et la restitution du focus couvrent
  l’essentiel, un piège complet reste à ajouter si la modale devient un
  parcours critique.
- Les pages du back-office Filament dépendent du framework ; les composants
  personnalisés (tableau de bord, calendrier) n’ont pas été audités ici.
- Le mode terrain a déjà fait l’objet d’une passe dédiée (cibles 44 px,
  focus, ARIA, navigation clavier) documentée dans `plan_mode_terrain.md`.

## Vérifications automatiques

`tests/Feature/AccessibilityTest.php` contrôle : lien d’évitement, `main#main`,
`lang`, nom accessible du logo, attributs du menu, `aria-required`,
`aria-describedby`/`aria-invalid` après erreur, résumé d’erreurs, mention RGPD
et lien de confidentialité, `scope` des tableaux légaux.
