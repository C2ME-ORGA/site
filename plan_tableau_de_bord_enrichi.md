# Plan de développement : tableau de bord enrichi

> **État : implémenté (22/10/2026).**
> Branche cible : `feature/crm`.
> Périmètre : indicateurs commerciaux du tableau de bord Filament (`/admin`) : CA signé, taux de conversion, pipeline par carnet et outils de pilotage.
> Décisions validées : double source de CA (devis acceptés + affaires gagnées), conversion devis acceptés / devis envoyés, période par défaut « année en cours » avec filtre, pipeline pondéré, panier moyen, top 5 affaires, devis à relancer/expirés et objectif mensuel.

## 1. Objectif

Transformer le tableau de bord actuel, principalement opérationnel, en un véritable outil de pilotage commercial :

- mesurer le **CA signé** selon deux sources complémentaires ;
- suivre le **taux de conversion** des devis ;
- visualiser le **pipeline ouvert par carnet**, brut et pondéré ;
- identifier les **meilleures affaires à travailler** ;
- repérer les **devis à relancer ou expirés** ;
- comparer le CA du mois à un **objectif mensuel** ;
- conserver le scoping existant : un commercial ne voit que ses données, un administrateur voit tout.

## 2. État des lieux

| Élément | Existant | À faire |
|---|---|---|
| Page dashboard | `Filament\Pages\Dashboard` standard | Remplacer par une page applicative avec filtre de période |
| Stats opérationnelles | `CrmStatsOverview` : comptes, affaires ouvertes, devis en attente, relances, retards | Conserver et compléter avec des stats financières |
| Pipeline global | Somme de `amount_estimated` des affaires ouvertes | Décliner par carnet, en brut et pondéré |
| CA | Aucun calcul de CA signé | Ajouter CA devis et CA affaires |
| Conversion | Aucun calcul | Ajouter le taux devis acceptés / devis envoyés |
| Affaires | `CrmOpportunity` avec `stage`, `amount_estimated`, `probability`, `won_at`, `expected_close_date` | Exploiter pour pipeline et top affaires |
| Devis | `Quote` avec `status`, `amount_ht`, `sent_at`, `accepted_at`, `valid_until` | Exploiter pour CA, conversion et relances |
| Carnets | `CrmBook` sur `crm_accounts.book` | Agréger le pipeline par carnet |
| Filtres dashboard | Aucun | Ajouter une période globale réactive |
| Objectif | Aucun | Ajouter un objectif mensuel configurable |

## 3. Décisions fonctionnelles

### 3.1 Sources du CA

Deux indicateurs distincts sont affichés, sans addition automatique :

1. **CA signé — devis acceptés** : somme des `quotes.amount_ht` des devis au statut `accepted`, dont `accepted_at` tombe dans la période.
2. **CA gagné — affaires gagnées** : somme des `crm_opportunities.amount_estimated` des affaires au statut `won`, dont `won_at` tombe dans la période.

Les deux montants sont affichés côte à côte avec leur nombre d’éléments. Ils ne sont pas fusionnés, car une affaire gagnée et son devis accepté peuvent représenter le même dossier.

### 3.2 Taux de conversion

- **Dénominateur** : devis dont `sent_at` tombe dans la période.
- **Numérateur** : parmi ces devis, ceux dont le statut actuel est `accepted`.
- **Formule** : `devis acceptés de la cohorte / devis envoyés de la cohorte × 100`.
- Un devis accepté après la période reste compté dans la cohorte des devis envoyés pendant la période.
- Si aucun devis n’a été envoyé, afficher `—` et non `0 %`.

### 3.3 Période

- Période par défaut : **année civile en cours**.
- Filtre global : mois, trimestre, année ou période personnalisée.
- Les bornes sont calculées en `Europe/Paris`, du début de journée au début de journée suivant.
- Le filtre est conservé dans l’URL et en session via le mécanisme Filament du dashboard.
- Les widgets de CA et de conversion suivent la période.
- Le pipeline et le top des affaires sont des **instantanés à date** : ils ne dépendent pas de la période, sauf mention contraire dans leur sous-titre.

### 3.4 Pipeline

- Périmètre : affaires ouvertes, hors `won`, `lost` et `closed`.
- **Pipeline brut** : somme des `amount_estimated`.
- **Pipeline pondéré** : `amount_estimated × probability / 100`.
- Si `probability` est vide, utiliser la probabilité par défaut de l’étape.
- Regroupement par `crm_accounts.book`.
- Affichage : graphique en barres groupées « Brut » / « Pondéré » pour les trois carnets, avec le nombre d’affaires et la part de chaque carnet.

### 3.5 Panier moyen signé

Deux valeurs, cohérentes avec les deux sources de CA :

- **Panier moyen devis** : CA devis / nombre de devis acceptés dans la période.
- **Panier moyen affaire** : CA affaires / nombre d’affaires gagnées dans la période.

Une seule carte « Panier moyen » peut afficher la valeur devis en principal et la valeur affaires en description.

### 3.6 Objectif mensuel

- Objectif global configurable via `CRM_DASHBOARD_MONTHLY_TARGET`.
- Comparaison avec le CA devis du **mois en cours**, indépendamment du filtre de période.
- Affichage : montant réalisé, objectif, pourcentage d’atteinte et barre de progression.
- Si l’objectif est à `0` ou vide, masquer la jauge et afficher « Objectif non défini ».

### 3.7 Devis à relancer / expirés

Liste opérationnelle, à date, scopée par utilisateur :

- devis `sent` dont `valid_until` est dans les 7 prochains jours ;
- devis `sent` dont `valid_until` est dépassé ;
- devis au statut `expired`.
- Colonnes : référence, compte, montant HT, validité, statut, propriétaire, action « Ouvrir ».
- Tri : validité la plus proche d’abord, puis montant décroissant.

## 4. Architecture technique

### 4.1 Page dashboard personnalisée

Créer `App\Filament\Pages\Dashboard` étendant `Filament\Pages\Dashboard`.

- Utiliser `Filament\Pages\Dashboard\Concerns\HasFiltersForm`.
- Définir `filtersForm(Schema $schema)` avec les champs de période.
- Remplacer l’import dans `AdminPanelProvider` et la liste `pages([...])`.
- Conserver les widgets actuels et ajouter les nouveaux.

Champs du filtre :

| Champ | Type | Comportement |
|---|---|---|
| `period` | Select | `month`, `quarter`, `year`, `custom` ; défaut `year` |
| `year` | Select | Années disponibles, défaut année courante |
| `quarter` | Select | T1 à T4, visible si `quarter` |
| `month` | Select | Janvier à décembre, visible si `month` |
| `from` | DatePicker | Visible si `custom`, obligatoire |
| `to` | DatePicker | Visible si `custom`, obligatoire, `>= from` |

Le formulaire doit être `live()` pour rafraîchir immédiatement les widgets.

### 4.2 Objet période

Créer `App\Support\DashboardPeriod` :

- construit depuis les filtres Filament ;
- expose `start`, `end`, `label`, `isCustom` ;
- gère les valeurs invalides en revenant à l’année en cours ;
- fournit des méthodes utilitaires : `contains(CarbonImmutable $date)`, `previousPeriod()`, `monthStart()`, `monthEnd()`.

### 4.3 Service de métriques

Créer `App\Services\DashboardMetricsService` pour centraliser les calculs et éviter la logique dans les widgets.

Méthodes proposées :

```php
signedQuoteRevenue(User $user, DashboardPeriod $period): float
wonOpportunityRevenue(User $user, DashboardPeriod $period): float
acceptedQuotesCount(User $user, DashboardPeriod $period): int
sentQuotesCount(User $user, DashboardPeriod $period): int
quoteConversionRate(User $user, DashboardPeriod $period): ?float
averageSignedQuote(User $user, DashboardPeriod $period): ?float
averageWonOpportunity(User $user, DashboardPeriod $period): ?float
pipelineByBook(User $user): array
topOpenOpportunities(User $user, int $limit = 5): Collection
quotesToFollowUp(User $user, int $days = 7): Collection
monthlyTargetProgress(User $user, ?int $target): array
```

Règles communes :

- exclure les enregistrements soft-deleted ;
- scoper via `ownedBy()` pour les affaires, devis et comptes ;
- ne jamais diviser par zéro ;
- arrondir les montants à deux décimales dans les calculs, à l’euro dans l’affichage ;
- formater les taux à une décimale.

### 4.4 Widgets

| Widget | Type | Contenu |
|---|---|---|
| `CrmStatsOverview` | StatsOverview | Conserver les stats opérationnelles existantes |
| `CommercialStatsOverview` | StatsOverview | CA devis, CA affaires, conversion, panier moyen |
| `MonthlyTargetOverview` | StatsOverview | Objectif mensuel, réalisé, progression |
| `PipelineByBookChart` | BarChart | Pipeline brut et pondéré par carnet |
| `TopOpenOpportunities` | TableWidget | Top 5 des affaires ouvertes par montant |
| `QuotesToFollowUp` | TableWidget | Devis à relancer ou expirés |
| `UpcomingActivities` | TableWidget | Conserver, ajuster le tri |

Les widgets qui dépendent de la période utilisent `Filament\Widgets\Concerns\InteractsWithPageFilters` et lisent `$this->pageFilters`.

### 4.5 Requêtes principales

CA devis :

```php
Quote::query()
    ->where('status', QuoteDocumentStatus::Accepted->value)
    ->whereBetween('accepted_at', [$period->start, $period->end])
    ->when(! $user->isAdmin(), fn ($q) => $q->where('owner_id', $user->id))
    ->sum('amount_ht');
```

CA affaires :

```php
CrmOpportunity::query()
    ->where('stage', OpportunityStage::Won->value)
    ->whereBetween('won_at', [$period->start, $period->end])
    ->when(! $user->isAdmin(), fn ($q) => $q->where('owner_id', $user->id))
    ->sum('amount_estimated');
```

Pipeline par carnet :

```php
CrmOpportunity::query()
    ->join('crm_accounts', 'crm_accounts.id', '=', 'crm_opportunities.crm_account_id')
    ->whereIn('crm_opportunities.stage', $openStages)
    ->when(! $user->isAdmin(), fn ($q) => $q->where('crm_opportunities.owner_id', $user->id))
    ->selectRaw('crm_accounts.book as book')
    ->selectRaw('COUNT(*) as opportunities_count')
    ->selectRaw('COALESCE(SUM(crm_opportunities.amount_estimated), 0) as pipeline_raw')
    ->selectRaw('COALESCE(SUM(crm_opportunities.amount_estimated * COALESCE(crm_opportunities.probability, 0) / 100), 0) as pipeline_weighted')
    ->groupBy('crm_accounts.book')
    ->get();
```

La probabilité par défaut de l’étape peut être appliquée en PHP après agrégation si `probability` est nul, pour éviter une requête SQL trop complexe.

## 5. Interface et expérience utilisateur

### 5.1 Ordre du dashboard

1. Filtre de période.
2. Stats opérationnelles (`CrmStatsOverview`).
3. Stats financières (`CommercialStatsOverview`).
4. Objectif mensuel.
5. Pipeline par carnet.
6. Top 5 affaires ouvertes.
7. Devis à relancer / expirés.
8. Actions à venir et en retard.

### 5.2 Formats

- Montants : `1 250 €`, sans décimales si le montant est entier.
- Taux : `42,5 %`.
- Dates : `jj/mm/aaaa`.
- Valeur absente : `—`.
- Couleurs :
  - CA : `success` ;
  - conversion : `info` ;
  - pipeline : `primary` ;
  - devis à relancer : `warning` ;
  - devis expirés : `danger`.

### 5.3 États vides

- Aucun devis envoyé : conversion `—`, avec description « Aucun devis envoyé sur la période ».
- Aucun devis accepté : CA `0 €` et panier moyen `—`.
- Aucun pipeline : graphique vide avec message explicite.
- Aucun devis à relancer : message « Aucun devis à relancer ».
- Objectif non configuré : message « Objectif non défini ».

## 6. Sécurité et scoping

- **Admin** : toutes les affaires, tous les devis, tous les carnets.
- **Commercial** : uniquement les affaires et devis dont il est propriétaire (`owner_id`), via `ownedBy()`.
- Les devis sans propriétaire ne sont visibles que par les admins.
- Les widgets ne doivent pas exposer de montants agrégés d’enregistrements non autorisés.
- Les liens « Ouvrir » utilisent les ressources existantes et leurs policies.
- Aucune nouvelle policy n’est nécessaire, mais les tests doivent vérifier le scoping.

## 7. Performance

- Éviter les requêtes N+1 : `with(['account', 'owner'])` pour les tableaux.
- Utiliser des agrégations SQL pour les sommes et compteurs.
- Ajouter si nécessaire une migration d’index :
  - `quotes.accepted_at` ;
  - `quotes.sent_at` ;
  - `quotes.valid_until` ;
  - `crm_opportunities.won_at` ;
  - `crm_opportunities.stage` (déjà indexé) et `crm_accounts.book`.
- Ne pas mettre en cache dans un premier temps ; les volumes restent faibles.
- Si les temps de réponse augmentent, ajouter un cache de 60 secondes par utilisateur et période, invalidé sur modification d’un devis ou d’une affaire.

## 8. Configuration

Ajouter dans `config/crm.php` :

```php
'dashboard' => [
    'monthly_revenue_target' => (float) env('CRM_DASHBOARD_MONTHLY_TARGET', 0),
    'quotes_to_follow_up_days' => 7,
],
```

Ajouter dans `.env.example` :

```dotenv
CRM_DASHBOARD_MONTHLY_TARGET=0
```

Documenter que `0` désactive l’objectif.

## 9. Tests

### 9.1 Tests du service

Fichier : `tests/Feature/DashboardMetricsTest.php`.

Cas à couvrir :

- CA devis acceptés sur la période, hors devis brouillon/envoyé/refusé.
- CA affaires gagnées sur la période, hors affaires ouvertes/perdues.
- Bornes de période incluses/exclues correctement.
- Conversion avec numérateur, dénominateur et cas zéro.
- Panier moyen devis et affaires.
- Pipeline brut et pondéré par carnet.
- Probabilité nulle ou absente.
- Top 5 trié par montant décroissant.
- Devis à relancer dans les 7 jours, devis expirés.
- Scoping admin vs commercial.
- Exclusion des enregistrements soft-deleted.
- Objectif mensuel : atteint, partiel, non configuré.

### 9.2 Tests de la page

Fichier : `tests/Feature/DashboardPageTest.php`.

Cas à couvrir :

- `/admin` accessible aux utilisateurs actifs.
- Période par défaut = année en cours.
- Changement de période met à jour les métriques.
- Filtre personnalisé valide.
- Un commercial ne voit que ses montants.
- Les widgets et tableaux s’affichent.
- Les liens des tableaux pointent vers les bonnes fiches.
- Le dashboard ne casse pas quand les tables sont vides.

### 9.3 Vérifications

```bash
php artisan test --filter=DashboardMetricsTest
php artisan test --filter=DashboardPageTest
vendor/bin/pint --test
composer types:check
php artisan test
```

## 10. Documentation

- `README.md` : ajouter une section « Tableau de bord enrichi » avec définitions des KPI, période, objectif et variables `.env`.
- `AGENTS.md` : ajouter les filtres de tests `DashboardMetricsTest` et `DashboardPageTest`.
- `TACHES_CRM.md` : cocher « Tableau de bord enrichi » et lister les widgets ajoutés.
- Ce plan : passer l’état à « implémenté » une fois les tests et la vérification visuelle terminés.

## 11. Étapes de mise en œuvre

### Phase 1 — Socle de calcul

- [x] Créer `DashboardPeriod`.
- [x] Créer `DashboardMetricsService`.
- [x] Ajouter la configuration `crm.dashboard` et `CRM_DASHBOARD_MONTHLY_TARGET`.
- [x] Ajouter les index de performance si nécessaire.
- [x] Écrire les tests du service.

### Phase 2 — Page et filtre de période

- [x] Créer `App\Filament\Pages\Dashboard`.
- [x] Ajouter le formulaire de période.
- [x] Brancher la page dans `AdminPanelProvider`.
- [x] Vérifier la persistance URL/session et le rafraîchissement des widgets.

### Phase 3 — Widgets financiers

- [x] Créer `CommercialStatsOverview`.
- [x] Créer `MonthlyTargetOverview`.
- [x] Créer `PipelineByBookChart`.
- [x] Ajouter le scoping et les états vides.
- [x] Vérifier les formats et les couleurs.

### Phase 4 — Tableaux opérationnels

- [x] Créer `TopOpenOpportunities`.
- [x] Créer `QuotesToFollowUp`.
- [x] Ajouter les liens vers les fiches et les actions autorisées.
- [x] Ajuster l’ordre et le tri des widgets.

### Phase 5 — Tests, documentation et validation

- [x] Écrire les tests de la page.
- [x] Vérifier Pint, PHPStan et la suite complète.
- [x] Vérifier visuellement `/admin` en admin et en commercial.
- [x] Mettre à jour `README.md`, `AGENTS.md` et `TACHES_CRM.md`.
- [x] Passer ce plan en « implémenté ».

## 12. Critères d’acceptation

- [x] Le dashboard affiche le CA devis acceptés et le CA affaires gagnées séparément.
- [x] Le CA respecte la période sélectionnée.
- [x] Le taux de conversion est calculé sur les devis envoyés de la période.
- [x] Le panier moyen est affiché sans division par zéro.
- [x] Le pipeline brut et pondéré est visible pour les trois carnets.
- [x] Le top 5 des affaires ouvertes est trié par montant.
- [x] Les devis à relancer et expirés sont listés avec un lien vers leur fiche.
- [x] L’objectif mensuel est affiché ou masqué proprement s’il n’est pas configuré.
- [x] Un commercial ne voit que ses données.
- [x] Le filtre de période est accessible et persistant.
- [x] Les états vides sont explicites.
- [x] Pint, PHPStan et les tests passent.
- [x] La documentation est à jour.

## 13. Hors périmètre (évolutions futures)

- Objectifs par carnet ou par commercial.
- Prévisions de CA sur plusieurs mois.
- Graphique de tendance sur 12 mois.
- Export CSV/PDF du dashboard.
- Comparaison N-1 et analyse des écarts.
- Notifications automatiques sur objectif atteint.
- Rôles avec accès lecture seule à tous les chiffres.
