<?php

/*
|--------------------------------------------------------------------------
| Messages de validation (français)
|--------------------------------------------------------------------------
|
| Sans ce fichier, Laravel renvoie les clés brutes (« validation.required »)
| dans les formulaires publics et le back-office.
|
*/

return [
    'accepted' => 'Le champ :attribute doit être accepté.',
    'accepted_if' => 'Le champ :attribute doit être accepté quand :other vaut :value.',
    'active_url' => 'Le champ :attribute n’est pas une URL valide.',
    'after' => 'Le champ :attribute doit être une date postérieure au :date.',
    'after_or_equal' => 'Le champ :attribute doit être une date postérieure ou égale au :date.',
    'alpha' => 'Le champ :attribute ne peut contenir que des lettres.',
    'alpha_dash' => 'Le champ :attribute ne peut contenir que des lettres, des chiffres, des tirets et des soulignés.',
    'alpha_num' => 'Le champ :attribute ne peut contenir que des lettres et des chiffres.',
    'array' => 'Le champ :attribute doit être un tableau.',
    'ascii' => 'Le champ :attribute ne doit contenir que des caractères alphanumériques et des symboles sur un octet.',
    'before' => 'Le champ :attribute doit être une date antérieure au :date.',
    'before_or_equal' => 'Le champ :attribute doit être une date antérieure ou égale au :date.',
    'between' => [
        'array' => 'Le tableau :attribute doit contenir entre :min et :max éléments.',
        'file' => 'La taille du fichier :attribute doit être comprise entre :min et :max kilo-octets.',
        'numeric' => 'La valeur de :attribute doit être comprise entre :min et :max.',
        'string' => 'Le texte :attribute doit contenir entre :min et :max caractères.',
    ],
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'can' => 'Le champ :attribute contient une valeur non autorisée.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'contains' => 'Le champ :attribute doit contenir l’une des valeurs suivantes : :values.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => 'Le champ :attribute doit être une date valide.',
    'date_equals' => 'Le champ :attribute doit être une date égale au :date.',
    'date_format' => 'Le champ :attribute doit correspondre au format :format.',
    'decimal' => 'Le champ :attribute doit avoir :decimal décimales.',
    'declined' => 'Le champ :attribute doit être refusé.',
    'declined_if' => 'Le champ :attribute doit être refusé quand :other vaut :value.',
    'different' => 'Les champs :attribute et :other doivent être différents.',
    'digits' => 'Le champ :attribute doit contenir :digits chiffres.',
    'digits_between' => 'Le champ :attribute doit contenir entre :min et :max chiffres.',
    'dimensions' => 'Les dimensions de l’image :attribute ne sont pas valides.',
    'distinct' => 'Le champ :attribute a une valeur en double.',
    'doesnt_end_with' => 'Le champ :attribute ne doit pas se terminer par l’une des valeurs suivantes : :values.',
    'doesnt_start_with' => 'Le champ :attribute ne doit pas commencer par l’une des valeurs suivantes : :values.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'ends_with' => 'Le champ :attribute doit se terminer par l’une des valeurs suivantes : :values.',
    'enum' => 'La valeur sélectionnée pour :attribute n’est pas valide.',
    'exists' => 'La valeur sélectionnée pour :attribute n’est pas valide.',
    'extensions' => 'Le champ :attribute doit avoir l’une des extensions suivantes : :values.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'filled' => 'Le champ :attribute doit avoir une valeur.',
    'gt' => [
        'array' => 'Le tableau :attribute doit contenir plus de :value éléments.',
        'file' => 'La taille du fichier :attribute doit être supérieure à :value kilo-octets.',
        'numeric' => 'La valeur de :attribute doit être supérieure à :value.',
        'string' => 'Le texte :attribute doit contenir plus de :value caractères.',
    ],
    'gte' => [
        'array' => 'Le tableau :attribute doit contenir au moins :value éléments.',
        'file' => 'La taille du fichier :attribute doit être supérieure ou égale à :value kilo-octets.',
        'numeric' => 'La valeur de :attribute doit être supérieure ou égale à :value.',
        'string' => 'Le texte :attribute doit contenir au moins :value caractères.',
    ],
    'image' => 'Le champ :attribute doit être une image.',
    'in' => 'La valeur sélectionnée pour :attribute n’est pas valide.',
    'in_array' => 'Le champ :attribute doit exister dans :other.',
    'integer' => 'Le champ :attribute doit être un entier.',
    'ip' => 'Le champ :attribute doit être une adresse IP valide.',
    'ipv4' => 'Le champ :attribute doit être une adresse IPv4 valide.',
    'ipv6' => 'Le champ :attribute doit être une adresse IPv6 valide.',
    'json' => 'Le champ :attribute doit être un JSON valide.',
    'lowercase' => 'Le champ :attribute doit être en minuscules.',
    'lt' => [
        'array' => 'Le tableau :attribute doit contenir moins de :value éléments.',
        'file' => 'La taille du fichier :attribute doit être inférieure à :value kilo-octets.',
        'numeric' => 'La valeur de :attribute doit être inférieure à :value.',
        'string' => 'Le texte :attribute doit contenir moins de :value caractères.',
    ],
    'lte' => [
        'array' => 'Le tableau :attribute ne doit pas contenir plus de :value éléments.',
        'file' => 'La taille du fichier :attribute doit être inférieure ou égale à :value kilo-octets.',
        'numeric' => 'La valeur de :attribute doit être inférieure ou égale à :value.',
        'string' => 'Le texte :attribute ne doit pas contenir plus de :value caractères.',
    ],
    'mac_address' => 'Le champ :attribute doit être une adresse MAC valide.',
    'max' => [
        'array' => 'Le tableau :attribute ne peut pas contenir plus de :max éléments.',
        'file' => 'La taille du fichier :attribute ne peut pas dépasser :max kilo-octets.',
        'numeric' => 'La valeur de :attribute ne peut pas être supérieure à :max.',
        'string' => 'Le texte :attribute ne peut pas contenir plus de :max caractères.',
    ],
    'max_digits' => 'Le champ :attribute ne doit pas avoir plus de :max chiffres.',
    'mimes' => 'Le champ :attribute doit être un fichier de type : :values.',
    'mimetypes' => 'Le champ :attribute doit être un fichier de type : :values.',
    'min' => [
        'array' => 'Le tableau :attribute doit contenir au moins :min éléments.',
        'file' => 'La taille du fichier :attribute doit être d’au moins :min kilo-octets.',
        'numeric' => 'La valeur de :attribute doit être supérieure ou égale à :min.',
        'string' => 'Le texte :attribute doit contenir au moins :min caractères.',
    ],
    'min_digits' => 'Le champ :attribute doit avoir au moins :min chiffres.',
    'missing' => 'Le champ :attribute doit être absent.',
    'missing_if' => 'Le champ :attribute doit être absent quand :other vaut :value.',
    'missing_unless' => 'Le champ :attribute doit être absent sauf si :other vaut :value.',
    'missing_with' => 'Le champ :attribute doit être absent quand :values est présent.',
    'missing_with_all' => 'Le champ :attribute doit être absent quand :values sont présents.',
    'multiple_of' => 'La valeur de :attribute doit être un multiple de :value.',
    'not_in' => 'La valeur sélectionnée pour :attribute n’est pas valide.',
    'not_regex' => 'Le format du champ :attribute n’est pas valide.',
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'password' => [
        'letters' => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed' => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un symbole.',
        'uncompromised' => 'La valeur du champ :attribute est apparue dans une fuite de données. Merci d’en choisir une autre.',
    ],
    'present' => 'Le champ :attribute doit être présent.',
    'prohibited' => 'Le champ :attribute est interdit.',
    'prohibited_if' => 'Le champ :attribute est interdit quand :other vaut :value.',
    'prohibited_unless' => 'Le champ :attribute est interdit sauf si :other est dans :values.',
    'prohibits' => 'Le champ :attribute interdit la présence de :other.',
    'regex' => 'Le format du champ :attribute n’est pas valide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'required_array_keys' => 'Le champ :attribute doit contenir des entrées pour : :values.',
    'required_if' => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'required_if_accepted' => 'Le champ :attribute est obligatoire quand :other est accepté.',
    'required_unless' => 'Le champ :attribute est obligatoire sauf si :other est dans :values.',
    'required_with' => 'Le champ :attribute est obligatoire quand :values est présent.',
    'required_with_all' => 'Le champ :attribute est obligatoire quand :values sont présents.',
    'required_without' => 'Le champ :attribute est obligatoire quand :values n’est pas présent.',
    'required_without_all' => 'Le champ :attribute est obligatoire quand aucun de :values n’est présent.',
    'same' => 'Les champs :attribute et :other doivent être identiques.',
    'size' => [
        'array' => 'Le tableau :attribute doit contenir :size éléments.',
        'file' => 'La taille du fichier :attribute doit être de :size kilo-octets.',
        'numeric' => 'La valeur de :attribute doit être :size.',
        'string' => 'Le texte :attribute doit contenir :size caractères.',
    ],
    'starts_with' => 'Le champ :attribute doit commencer par l’une des valeurs suivantes : :values.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'timezone' => 'Le champ :attribute doit être un fuseau horaire valide.',
    'unique' => 'La valeur du champ :attribute est déjà utilisée.',
    'uploaded' => 'Le fichier :attribute n’a pas pu être téléversé.',
    'uppercase' => 'Le champ :attribute doit être en majuscules.',
    'url' => 'Le champ :attribute doit être une URL valide.',
    'ulid' => 'Le champ :attribute doit être un ULID valide.',
    'uuid' => 'Le champ :attribute doit être un UUID valide.',

    /*
    |--------------------------------------------------------------------------
    | Messages personnalisés
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'email' => [
            'email' => 'L’adresse e-mail n’est pas valide.',
        ],
        'plans.*' => [
            'mimes' => 'Chaque plan doit être au format PDF, DXF ou DWG.',
            'max' => 'Chaque plan ne doit pas dépasser 25 Mo.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Libellés des champs
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'nom',
        'company' => 'société',
        'email' => 'adresse e-mail',
        'phone' => 'téléphone',
        'requestType' => 'type de demande',
        'message' => 'message',
        'material' => 'matière',
        'thickness' => 'épaisseur',
        'quantity' => 'quantité',
        'complexity' => 'type de pièces',
        'length' => 'longueur',
        'width' => 'largeur',
        'height' => 'hauteur',
        'delay' => 'délai',
        'delivery' => 'mode de livraison',
        'city' => 'commune',
        'plans' => 'plans',
        'plans.*' => 'plan',
        'plansLater' => 'transmission des plans',
        'type' => 'nature du bâtiment',
        'usage' => 'usage prévu',
        'envelope' => 'niveau de livraison',
        'prestations' => 'prestations',
        'budget' => 'budget',
        'level' => 'qualité du demandeur',
        'password' => 'mot de passe',
        'current_password' => 'mot de passe actuel',
    ],
];
