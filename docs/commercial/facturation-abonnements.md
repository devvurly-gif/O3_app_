# Facturation des abonnements — phase 2

> Implémentée le 2026-09-20. **Pas encore active en production** : tant que
> l'identité de facturation n'est pas renseignée, le code refuse d'émettre —
> volontairement, voir §2.

---

## 1. Comment le cycle tourne

```
J-15 avant l'échéance   subscriptions:invoice émet la facture de la période
                        SUIVANTE et l'envoie au client, PDF en pièce jointe
        ↓
Le client règle         virement ou chèque
        ↓
Back-office             « Encaisser » sur la fiche tenant
        ↓
Automatique             la facture passe à « Réglée », l'échéance se repousse,
                        la formule s'applique
```

Deux commandes, enchaînées chaque matin :

| Heure (UTC) | Commande | Rôle |
|---|---|---|
| 07:00 | `subscriptions:check` | bascule les statuts, relance les échéances |
| 07:15 | `subscriptions:invoice` | émet et envoie les factures |

`config/app.php` étant en UTC, cela tombe à **8 h et 8 h 15 au Maroc**.

La facturation est **d'avance**, conformément à l'article 8.3 du contrat : un
client ne peut pas régler une échéance dont il n'a pas encore la facture.

---

## 2. Ce qu'il faut renseigner avant que quoi que ce soit s'émette

Une facture marocaine sans ICE n'est pas une facture. Plutôt que d'en produire
une non conforme, `SubscriptionInvoiceService` **refuse d'émettre** tant que les
mentions obligatoires manquent, et la commande s'arrête en nommant les champs
absents. Le back-office affiche le même message sur la fiche du tenant.

À ajouter au `.env` du serveur :

```
BILLING_ISSUER_NAME="..."
BILLING_ISSUER_LEGAL_FORM="SARL au capital de ... MAD"
BILLING_ISSUER_ADDRESS="..."
BILLING_ISSUER_CITY="..."
BILLING_ISSUER_PHONE="+212 ..."
BILLING_ISSUER_EMAIL="facturation@o3app.ma"
BILLING_ISSUER_ICE="..."
BILLING_ISSUER_RC="..."
BILLING_ISSUER_IF="..."
BILLING_ISSUER_PATENTE="..."
BILLING_ISSUER_IBAN="..."
BILLING_ISSUER_BANK="..."
BILLING_VAT_RATE=20
```

Puis, **obligatoirement** :

```bash
cd /var/www/O3_app && sudo -u www-data php artisan config:cache
```

`config/billing.php` est un fichier neuf : sans cette commande il reste
invisible, exactement comme `config/plans.php` en phase 1.

Le minimum bloquant est fixé par `billing.required_issuer_fields` :
`name`, `address`, `city`, `ice`.

**Si l'entité n'est pas assujettie à la TVA**, mettre `BILLING_VAT_RATE=0` : la
facture porte alors la mention d'exonération au lieu d'une ligne de TVA à zéro,
qui serait trompeuse.

---

## 3. Ce que le code garantit

- **Numérotation continue et sans doublon.** `FA-2026-0001`, séquence par année,
  attribuée sous verrou de ligne (`invoice_sequences`) et non par un
  `MAX()+1` — deux émissions simultanées produiraient sinon le même numéro.
- **Une facture ne se supprime pas.** Une erreur s'annule ; le numéro reste
  consommé. Un trou dans la séquence s'explique, deux factures du même numéro
  non.
- **Une facture est figée.** Le PDF est écrit une fois sur le disque et relu tel
  quel ; l'identité des deux parties et le tarif sont recopiés dans `snapshot`.
  Changer une adresse ou un prix ne modifie aucune facture déjà émise.
- **Pas de double facturation d'une période.** Le cron peut tourner deux fois,
  la période reste couverte par une seule facture vivante.
- **Un email qui ne part pas ne détruit rien.** La facture existe, elle est
  numérotée, et elle reste renvoyable depuis le back-office.

---

## 4. Les frais de mise en service

Ajoutés automatiquement à la **première** facture d'un client, et **offerts sur
l'engagement annuel** — c'est le levier de la grille tarifaire (§3 du plan de
commercialisation). Pour forcer ou retirer ce comportement sur une facture
précise : bouton d'émission manuelle du back-office, ou
`subscriptions:invoice --tenant=<id>`.

---

## 5. Commandes utiles

Simulation, sans rien écrire ni envoyer :

```bash
cd /var/www/O3_app && sudo -u www-data php artisan subscriptions:invoice --dry-run
```

Émettre pour un seul client, même si son échéance est lointaine :

```bash
cd /var/www/O3_app && sudo -u www-data php artisan subscriptions:invoice --tenant=jadema
```

Émettre sans envoyer, pour relire le PDF avant :

```bash
cd /var/www/O3_app && sudo -u www-data php artisan subscriptions:invoice --no-send
```

---

## 6. Côté client

L'espace `/abonnement` liste les factures du tenant et permet de les
télécharger. C'est ce qui évite la moitié des demandes de renvoi.

L'ICE et l'adresse de facturation du client sont demandés au moment où il
choisit sa formule — le seul moment où il est devant l'écran et motivé. Ils
restent modifiables depuis la fiche tenant du back-office (`billing_ice`,
`billing_address`).

---

## 7. Ce qui n'est délibérément pas fait

- **Pas de paiement en ligne.** Voir §4 du plan de commercialisation : au Maroc,
  sur ce segment, le client paie par virement ou par chèque, et le e-paiement
  demande un dossier, une entité et des délais. C'est une optimisation de
  phase 3, à lancer quand le volume de relances manuelles devient pénible.
- **Pas d'avoirs.** Une facture réglée ne s'annule pas depuis l'interface : le
  code le refuse explicitement et renvoie vers l'avoir, qui reste à construire
  si le besoin se présente.
- **Pas de facturation des options** (utilisateur supplémentaire, terminal POS,
  stockage). La grille les prévoit, la facture ne porte pour l'instant que
  l'abonnement et les frais de mise en service. À ajouter le jour où une option
  est réellement vendue.
