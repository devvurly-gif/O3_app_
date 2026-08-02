<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Libellés fourre-tout
    |--------------------------------------------------------------------------
    |
    | À la création d'un tenant, une catégorie « Non catégorisé » et une marque
    | « Marque inconnue » sont créées pour accueillir les produits importés sans
    | rattachement (cf. TenantController::seedDefaults). Ce sont des béquilles
    | internes à l'ERP : sur la boutique, elles s'affichaient en gros badge au-
    | dessus du nom du produit (« NON CATÉGORISÉ ») et polluaient les filtres.
    |
    | L'API les traite donc comme une absence de rattachement : ni badge, ni
    | entrée dans les listes de filtres. Les produits concernés restent bien
    | visibles et vendables.
    |
    | Ces libellés servent aussi de clé à firstOrCreate() côté TenantController :
    | c'est bien le titre qui identifie ces entrées, pas un drapeau en base.
    | Un tenant qui renomme la catégorie en fait une vraie catégorie, et elle
    | réapparaît normalement — le comportement de repli est sans danger.
    |
    */

    'placeholder_labels' => [
        'category' => 'Non catégorisé',
        'brand'    => 'Marque inconnue',
    ],

];
