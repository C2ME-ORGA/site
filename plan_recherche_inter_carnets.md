# Plan de développement : Recherche globale Filament inter-carnets

> **État : implémenté (21/10/2026).** Les phases 1 à 5 sont terminées.
> Fichiers principaux : `app/Filament/Pages/GlobalSearch.php`, `app/Filament/Services/GlobalSearchService.php`, `resources/views/filament/pages/global-search.blade.php`.
> Tests : `tests/Feature/CrmAdminPagesTest.php` (33 tests dédiés au panel admin, dont la recherche globale).

## Objectif
Mettre en place une recherche globale dans l'interface Filament permettant de rechercher des comptes, contacts, affaires (opportunités) et devis à travers tous les carnets (books), avec un système de filtres et de résultats unifiés.

## État des lieux actuel
- Le projet utilise Filament 5.10 avec Livewire 4
- Chaque ressource (comptes, contacts, affaires, devis) dispose de sa propre page de liste avec filtres et recherche locale
- Aucune recherche inter-ressources n'est actuellement implémentée
- Les modèles concernés : `CrmAccount`, `CrmContact`, `CrmOpportunity`, `Quote`

## Analyse des composants existants

### 1. Filament Search Component
Filament fournit un composant `Searchable` qui peut être ajouté aux ressources. La recherche utilise généralement le champ `queryString` en URL et le composant `LivewireSearch` dans les tableaux.

### 2. Modèles concernés
- `CrmAccount` : champs `name`, `book`, `owner_id`, `crm_book`
- `CrmContact` : champs `first_name`, `last_name`, `email`, `crm_account_id`
- `CrmOpportunity` : champs `title`, `stage`, `amount_estimated`, `crm_account_id`
- `Quote` : champs `reference`, `status`, `crm_account_id`, `stage`

### 3. Patterns existants
- Chaque ressource a sa propre page de liste (`ListAccounts`, `ListContacts`, etc.)
- La recherche locale utilise `SelectFilter` et `TernaryFilter` dans Filament

## Plan de mise en œuvre

### Phase 1 : Configuration de la recherche globale (1 semaine)

#### 1.1 Création d'une classe de recherche unifiée
- Créer un nouveau service `App\Filament\Services\GlobalSearchService`
- Cette classe sera responsable de la construction des requêtes pour chaque modèle
- Méthodes clés :
  - `searchAccounts($query)` → retourne des `CrmAccount`
  - `searchContacts($query)` → retourne des `CrmContact`
  - `searchOpportunities($query)` → retourne des `CrmOpportunity`
  - `searchQuotes($query)` → retourne des `Quote`

#### 1.2 Ajout des routes de recherche
- Ajouter une route `/admin/global-search` dans `routes/web.php`
- Créer une page Livewire `GlobalSearch` qui utilisera le service

#### 1.2 Mise à jour du layout global
- Ajouter un champ de recherche dans la barre de navigation ou le tableau de bord
- Le composant de recherche Filament peut être inclus dans `resources/views/filament/layouts/default-layout.blade.php` ou dans une page dédiée

### Phase 2 : Implémentation par ressource (1 semaine)

#### 2.1 Comptes (Accounts)
- Modifier `AccountResource` pour ajouter une propriété `canSearch()` returns true
- Ajouter des champs de recherche dans la table `AccountsTable`
- Les champs recherchables : `name`, `book->name`, `owner->name`
- Tester la recherche avec des termes partiels

#### 2.2 Contacts
- Modifier `ContactsRelationManager` ou `ContactResource` pour ajouter la recherche
- Champs recherchables : `first_name`, `last_name`, `email`, `crm_account->name`
- Implémenter le filtrage par compte associé

#### 2.3 Affaires (Opportunités)
- Modifier `OpportunitiesRelationManager` ou `OpportunityResource`
- Champs recherchables : `title`, `stage`, `amount_estimated`, `crm_account->name`
- Implémenter le tri par stage et montant

#### 2.4 Devis
- Modifier `QuotesResource` ou `QuoteRequestsResource`
- Champs recherchables : `reference`, `status`, `crm_account->name`, `stage`
- Implémenter le filtrage par statut et compte

### Phase 3 : Interface utilisateur (1 semaine)

#### 3.1 Composant de recherche globale
Créer un composant Filament `GlobalSearchComponent` qui :
- Affiche un champ de recherche typeahead
- Montre les résultats groupés par catégorie (Comptes, Contacts, Affaires, Devis)
- Permet de cliquer sur un résultat pour accéder à la fiche détaillée
- Inclut des icônes distinctes pour chaque type de résultat

#### 3.2 Icônes et couleurs
- Utiliser les icônes existantes de Filament pour chaque type :
- Comptes : `Heroicon::OutlinedBuildingOffice`
- Contacts : `Heroicon::OutlinedUser`
- Affaires : `Heroicon::OutlinedBriefcase`
- Devis : `Heroicon::OutlinedClipboardList`

#### 3.2 Filtres avancés
- Ajouter des options de filtrage dans le formulaire de recherche :
- Par book/carnet
- Par date de création
- Par statut (pour les affaires/devis)
- Par contact associé

### Phase 4 : Tests et validation (1 semaine)

#### 4.1 Tests unitaires
- Tester chaque type de recherche avec des termes variés
- Vérifier le filtrage par book/carnet
- Tester les résultats vides

#### 4.2 Tests d'interface
- Vérifier l'affichage du composant de recherche
- Tester la navigation vers les fiches détaillées
- Vérifier la réactivité sur mobile

#### 4.3 Tests de performance
- Mesurer le temps de réponse pour différents termes de recherche
- S'assurer que les requêtes SQL sont optimisées
- Vérifier le chargement paresseux (lazy loading) des résultats

### Phase 5 : Documentation et déploiement (3 jours)

#### 5.1 Documentation
- Documenter la fonctionnalité dans le README
- Ajouter des captures d'écran
- Expliquer comment personnaliser les champs recherchables

#### 5.2 Déploiement
- Tester en environnement de préproduction
- Vérifier la compatibilité avec les différents navigateurs
- Documenter les limitations connues

## Exemple d'implémentation

### Exemple de recherche de comptes

```php
// Dans App\Filament\Services\GlobalSearchService.php

class GlobalSearchService
{
    public function searchAccounts(string $query, ?int $bookId = null): LengthAwarePaginator
    {
        $query = CrmAccount::query()
            ->when($bookId, fn ($q, $id) => $q->where('book', $bookId))
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', '%'.$query.'%')
                  ->orWhere('reference', 'like', '%'.$query.'%');
            })
            ->latest('created_at');
        
        return $query->paginate(15);
    }
    
    // Méthodes similaires pour contacts, affaires, devis...
}
```

### Exemple de composant de recherche

```blade
<!-- resources/views/components/global-search-component.blade.php -->

<div class="fi-relative">
    <x-filament::text-input
        id="global-search"
        wire:model.debounce-300ms="searchQuery"
        class="fi-input"
        placeholder="Rechercher dans le CRM..."
        aria-label="Recherche globale"
    />
    
    @if(!empty($searchResults))
        <div class="fi-absolute fi-inset-y-0 fi-right-0 fi-width-64 fi-max-w-full fi-mt-2 z-10">
            <div class="fi-shadow-lg fi-rounded-md fi-overflow-hidden">
                @foreach($searchResults as $group => $results)
                    <div class="fi-p-4">
                        <h3 class="fi-text-sm fi-font-medium fi-text-gray-600 mb-2">
                            {{ ucfirst(str_replace('_', ' ', $group)) }}
                        </h3>
                        <ul class="fi-space-y-1">
                            @foreach($results as $result)
                                <li class="fi-line-height-relaxed">
                                    <a href="{{ $result->route() }}" class="fi-text-sm fi-hover-primary">
                                        {{ $result->getDisplayName() }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
```

## Fichiers à modifier/créer

1. `app/Filament/Services/GlobalSearchService.php` - Nouveau service de recherche
2. `routes/web.php` - Route `/admin/global-search`
3. `app/Filament/Resources/...` - Modifications mineures sur chaque ressource
4. `resources/views/components/global-search-component.blade.php` - Composant UI
5. `resources/views/livewire/global-search.blade.php` - Page Livewire
6. Mise à jour potentielle des templates de table existants

## Risques et considérations

1. **Performance** : La recherche sur plusieurs tables pourrait être lente sans optimisation appropriée. Utiliser des index sur les champs de recherche (`name`, `email`, `title`, `reference`).

2. **Sécurité** : S'assurer que les utilisateurs ne peuvent rechercher que les données qu'ils sont autorisés à voir (via les policies).

3. **UX** : L'interface ne doit pas être surchargée. Limiter le nombre de résultats affichés (maximum 10 par catégorie).

4. **Maintenabilité** : Garder la logique de recherche dans un service dédié pour faciliter les modifications futures.

## Étapes suivantes

1. Débuter par la création du `GlobalSearchService`
2. Implémenter la recherche pour un type (commencer par les Comptes)
3. Tester et affiner l'interface
4. Étendre aux autres types (Contacts, Affaires, Devis)
5. Finaliser l'interface utilisateur et les tests
6. Documenter la fonctionnalité

## Calendrier estimé

- Semaine 1 : Configuration et service de recherche
- Semaine 2 : Implémentation pour Comptes et Contacts
- Semaine 3 : Implémentation pour Affaires et Devis
- Semaine 4 : Interface utilisateur et tests
- Semaine 5 : Documentation et déploiement

---
*Ce plan a été créé dans le cadre du développement du CRM C2ME (branche `feature/crm`).*