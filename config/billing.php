<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Facturation des abonnements O3App
|--------------------------------------------------------------------------
|
| Ce fichier decrit QUI facture, pas ce qui est vendu (voir config/plans.php).
| Il s'agit des factures qu'O3App emet a ses propres clients — a ne pas
| confondre avec les factures que chaque tenant emet aux siens, qui vivent
| dans sa base et suivent ses propres compteurs.
|
| ⚠ L'entite de facturation n'est pas encore arretee (voir
| docs/commercial/plan-commercialisation.md §6). Tant que les mentions legales
| ci-dessous sont incompletes, SubscriptionInvoiceService REFUSE d'emettre :
| une facture marocaine sans ICE n'est pas une facture, et il vaut mieux une
| erreur explicite qu'une liasse de documents a refaire.
|
| Renseigner ces valeurs dans le .env du serveur, jamais en dur ici.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Emetteur
    |----------------------------------------------------------------------
    |
    | `ice` est obligatoire sur toute facture marocaine. `rc`, `if` et
    | `patente` le sont des que l'entite y est assujettie ; ils figurent sur
    | la facture des qu'ils sont renseignes.
    |
    */
    'issuer' => [
        'name'    => env('BILLING_ISSUER_NAME'),
        'legal_form' => env('BILLING_ISSUER_LEGAL_FORM'),
        'address' => env('BILLING_ISSUER_ADDRESS'),
        'city'    => env('BILLING_ISSUER_CITY'),
        'phone'   => env('BILLING_ISSUER_PHONE'),
        'email'   => env('BILLING_ISSUER_EMAIL'),
        'website' => env('BILLING_ISSUER_WEBSITE', 'https://o3app.ma'),
        'ice'     => env('BILLING_ISSUER_ICE'),
        'rc'      => env('BILLING_ISSUER_RC'),
        'if'      => env('BILLING_ISSUER_IF'),
        'patente' => env('BILLING_ISSUER_PATENTE'),
        'cnss'    => env('BILLING_ISSUER_CNSS'),
        'iban'    => env('BILLING_ISSUER_IBAN'),
        'bank'    => env('BILLING_ISSUER_BANK'),
    ],

    /*
    |----------------------------------------------------------------------
    | Mentions sans lesquelles une facture ne peut pas etre emise
    |----------------------------------------------------------------------
    */
    'required_issuer_fields' => ['name', 'address', 'city', 'ice'],

    /*
    |----------------------------------------------------------------------
    | TVA
    |----------------------------------------------------------------------
    |
    | 20 % est le taux normal marocain sur les prestations de services.
    | Mettre 0 tant que l'entite n'est pas assujettie : la facture porte alors
    | la mention d'exoneration au lieu d'une ligne de TVA a zero, qui serait
    | trompeuse.
    |
    */
    'vat_rate'            => (float) env('BILLING_VAT_RATE', 20),
    'vat_exemption_note'  => env('BILLING_VAT_EXEMPTION_NOTE', 'TVA non applicable'),

    /*
    |----------------------------------------------------------------------
    | Numerotation
    |----------------------------------------------------------------------
    |
    | Sequence continue par annee, sans trou : c'est une exigence legale, et
    | c'est pourquoi le numero est attribue par InvoiceNumberService dans une
    | transaction verrouillee, jamais par un simple MAX()+1.
    |
    */
    'number_prefix' => env('BILLING_NUMBER_PREFIX', 'FA'),
    'number_format' => '{PREFIX}-{YEAR}-{SEQ}',
    'number_padding' => 4,

    /*
    |----------------------------------------------------------------------
    | Rythme d'emission
    |----------------------------------------------------------------------
    |
    | La facture part `issue_lead_days` avant l'echeance, pour la periode
    | suivante : le contrat prevoit une facturation d'avance (article 8.3), et
    | un client ne peut pas regler une echeance dont il n'a pas encore la
    | facture. `payment_terms_days` fixe la date limite de reglement.
    |
    */
    'issue_lead_days'    => (int) env('BILLING_ISSUE_LEAD_DAYS', 15),
    'payment_terms_days' => (int) env('BILLING_PAYMENT_TERMS_DAYS', 15),

    /*
    |----------------------------------------------------------------------
    | Stockage des PDF
    |----------------------------------------------------------------------
    |
    | Le PDF est fige au moment de l'emission et relu tel quel ensuite : une
    | facture regeneree a la volee changerait si un tarif ou une adresse
    | bougeait, ce qui est exactement ce qu'une facture ne doit pas faire.
    |
    */
    'pdf_disk' => env('BILLING_PDF_DISK', 'local'),
    'pdf_path' => 'invoices',
];
