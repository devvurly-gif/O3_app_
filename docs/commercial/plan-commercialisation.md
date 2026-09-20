# Plan de commercialisation — O3App

> Établi le 2026-09-20, à partir d'un audit du code (`PublicRegistrationController`,
> `Tenant`, `CheckTenantFeature`, `PackageService`) et des documents `docs/legal/`.
> Les prix proposés en §3 sont une recommandation : c'est la seule partie du
> document qui demande une décision de ta part avant de coder quoi que ce soit.

---

## 1. Le constat : il ne manque pas un produit, il manque une clôture

L'idée reçue serait « l'app n'est pas prête à être vendue ». C'est faux.
Le parcours d'acquisition est déjà construit, et il est bon :

| Étape | État | Où |
|---|---|---|
| Choix du sous-domaine + vérif. de disponibilité en direct | ✅ fait | `PublicRegistrationController::checkSubdomain()` |
| Inscription publique sans intervention humaine | ✅ fait | `POST /api/central/register` |
| Création automatique du tenant + base de données dédiée | ✅ fait | Stancl, `Tenant::create()` |
| Création de l'admin, rôles, réglages, compteurs de documents | ✅ fait | seeders dans `register()` |
| Email de vérification + notification interne | ✅ fait | `TenantVerificationMail` |
| Essai de 14 jours positionné | ✅ fait | `trial_ends_at = now()->addDays(14)` |
| Contrat de service + fiche de souscription | ✅ rédigés | `docs/legal/` |
| Supports de formation (livret, mémos métier) | ✅ faits | `docs/formation/` |

**Ce qui manque n'est pas en amont, c'est en aval.** Un prospect peut créer son
espace tout seul en 2 minutes — et ensuite l'utiliser gratuitement, sans limite
de temps, sans jamais rencontrer une demande de paiement.

### Les fuites, précisément

1. **L'essai ne se termine jamais.**
   `Tenant::isOnTrial()` et `Tenant::isExpired()` existent mais ne sont appelées
   nulle part dans le code. Passé le 14ᵉ jour, `trial_ends_at` devient une date
   dans le passé que personne ne lit.

2. **`is_active` n'est jamais vérifié.**
   Le flag est écrit à l'inscription (`false`) puis à la vérification (`true`),
   mais aucun middleware ne le contrôle à la connexion ni sur les requêtes.
   Concrètement : un compte non vérifié, désactivé à la main depuis le
   back-office, ou impayé, continue de fonctionner normalement. Le seul endroit
   qui lit ce flag est le cron de trésorerie.

3. **La colonne `plan` est décorative.**
   `Tenant::hasModule()` mappe `starter/business/enterprise` vers des modules —
   et n'est appelée nulle part côté serveur. Le vrai contrôle d'accès passe par
   `CheckTenantFeature`, qui lit des booléens indépendants (`pos_enabled`,
   `ecom_enabled`, `variants_enabled`, `imei_enabled`). Choisir « enterprise »
   dans le back-office ne change strictement rien pour le client.

4. **Trois systèmes de packaging coexistent et se contredisent.**
   - `Tenant::plan` → trial / starter / business / enterprise *(mort)*
   - les booléens `*_enabled` sur le tenant → **le seul réellement appliqué**
   - `PackageService` → basic / professional / business, lu depuis
     `Setting::get('billing','package_type')` **dans la base du tenant**.
     Cette clé n'est écrite nulle part : tout le monde est donc « basic » à vie,
     et l'import OCR est désactivé pour tous les clients sans exception.

   Tant que ces trois systèmes cohabitent, aucun prix ne peut être rattaché à
   quoi que ce soit : il n'existe pas de définition unique de « ce que le client
   a acheté ».

5. **Aucun moyen d'encaisser.** Rien dans le code (aucune passerelle, aucune
   facture d'abonnement, aucune échéance). L'email de vérification le dit
   d'ailleurs à voix haute : *« À la fin de l'essai, nous vous contacterons pour
   finaliser l'abonnement »*. Tout repose sur toi, manuellement, client par client.

6. **Les prix ne sont pas fixés.** Dans `contrat-services-saas.md`, l'Annexe 1.C
   est entièrement en `[…]`. Le contrat n'est donc pas signable en l'état.

---

## 2. La décision structurante : un seul modèle de plan

Avant tout code de facturation, il faut **fusionner les trois systèmes en un**.
Recommandation : garder `Tenant::plan` comme source de vérité unique (table
centrale, hors de portée du client), et faire dériver les booléens de features
du plan plutôt que de les saisir à la main.

- `PackageService` → à supprimer. Son état vit dans la base du tenant, ce qui est
  un défaut de conception pour une donnée commerciale : elle doit rester centrale.
- Les `*_enabled` → conservés comme *cache* du plan (le middleware
  `CheckTenantFeature` continue de fonctionner sans réécriture), mais recalculés
  par un service `PlanService::applyTo(Tenant)` à chaque changement de plan.
- Les dérogations commerciales (un client Essentiel à qui tu offres le POS)
  restent possibles via un override explicite, tracé.

Sans cette étape, chaque ligne de tarif que tu écriras sera invérifiable.

---

## 3. L'offre — proposition chiffrée

Marché visé : TPE/PME marocaines de 3 à 20 salariés — négoce, distribution,
magasins avec caisse, téléphonie (tu as déjà `teliphoni` et `jadema` comme
références réelles). Concurrence effective : Excel, cahiers papier, et des ERP
généralistes trop chers ou trop lourds. Ton avantage : marocain, en français,
TVA et devise correctes d'origine, caisse intégrée, mise en route en minutes.

### Grille proposée (HT, en MAD)

| | **Essentiel** | **Pro** | **Business** |
|---|---|---|---|
| **Prix mensuel** | **390** | **690** | **1 290** |
| Utilisateurs inclus | 3 | 7 | 15 |
| Ventes, achats, stock, tiers | ✅ | ✅ | ✅ |
| Documents PDF personnalisés | ✅ | ✅ | ✅ |
| Trésorerie / caisse comptable | ✅ | ✅ | ✅ |
| Multi-dépôts & transferts | — | ✅ | ✅ |
| Listes de prix / promotions | — | ✅ | ✅ |
| **POS (caisse)** — 2 terminaux | — | ✅ | ✅ |
| Rapports avancés | — | ✅ | ✅ |
| Déclinaisons produit / IMEI | — | — | ✅ |
| Boutique en ligne (e-com) | — | — | ✅ |
| Import OCR de factures | — | — | ✅ |
| Paiement sur bon de livraison | — | option | ✅ |
| Support | Email, 48 h | Email + tél., 24 h | Prioritaire, 4 h ouvrées |

**Options mensuelles** — utilisateur supplémentaire 60 · terminal POS
supplémentaire 150 · tranche de 5 Go 50 · module e-com sur Pro 350.

**Frais de mise en service : 1 900 MAD HT** (paramétrage, import du fichier
articles et tiers, 2 h de formation à distance). **Offerts pour tout engagement
annuel** — c'est le levier qui pousse à l'annuel sans casser le prix affiché.

**Annuel : 10 mois payés pour 12** (≈ −17 %), payable d'avance. C'est la ligne
la plus importante du tableau : elle règle ton problème de trésorerie et divise
par douze le nombre de relances à faire.

### Pourquoi ces niveaux

- 390 MAD/mois reste sous le seuil de réflexion d'un gérant de TPE : décidable
  seul, sans réunion, sans dossier.
- Le saut Essentiel → Pro est porté par **le POS**, ton vrai différenciateur et
  ce qu'un magasin ne peut pas remplacer par un tableur.
- Business se justifie par l'e-commerce et l'IMEI : deux fonctions qui ciblent
  précisément le profil `teliphoni`.
- Les écarts 390 / 690 / 1 290 (×1,8 puis ×1,9) laissent la place à une
  négociation descendante sans jamais passer sous ton coût de service.

**Remplir l'Annexe 1.C de `contrat-services-saas.md` avec ces chiffres est le
tout premier geste à faire** : sans elle, aucun contrat n'est signable.

---

## 4. Le parcours cible, de bout en bout

```
Inscription (déjà fait)
   ↓
Email de vérification (déjà fait)
   ↓
Essai 14 jours — bandeau permanent « il vous reste N jours »      ← à construire
   ↓
J-7, J-3, J-1 : emails automatiques de relance                    ← à construire
   ↓
Fin d'essai → lecture seule + écran « choisir une formule »       ← à construire
   ↓
Choix d'une formule → fiche de souscription pré-remplie + contrat ← semi-auto
   ↓
Virement / chèque → « payé jusqu'au JJ/MM » au back-office        ← manuel, assumé
   ↓
Facture d'abonnement PDF envoyée automatiquement                  ← à construire
   ↓
Relance automatique 15 jours avant l'échéance suivante            ← à construire
```

**Recommandation contre-intuitive : ne construis pas de paiement en ligne
maintenant.** Le e-paiement au Maroc (CMI, Payzone, NAPS) demande un dossier,
une entité, des frais fixes et des délais — et ta clientèle TPE/PME B2B paie de
toute façon par virement ou chèque, sur facture. Ce qui te bloque n'est pas
*encaisser*, c'est **que l'essai ne s'arrête jamais et que personne ne relance à
ta place**. Le paiement en ligne est une optimisation de phase 3, pas un
prérequis : le construire d'abord te coûterait des semaines sans débloquer une
seule vente.

---

## 5. Le plan, par ordre de priorité

### Phase 0 — Décisions (aucune ligne de code, ~2 h)

1. Valider ou corriger la grille du §3.
2. Reporter les montants dans l'Annexe 1.C du contrat et dans la fiche de souscription.
3. Faire relire le contrat par un juriste local (le document lui-même le
   recommande, ~1 500–3 000 MAD) — à lancer maintenant, ça prend des jours.

### Phase 1 — Rendre l'abonnement réel — ✅ **implémentée le 2026-09-20**

> Code écrit et testé (626 tests verts), **pas encore déployé**. La mise en
> production suit sa propre note : [deploiement-phase-1.md](deploiement-phase-1.md).
> Les tarifs du §3 sont désormais dans `config/plans.php`, source unique.

C'est ici que se joue la commercialisation. Dans l'ordre :

1. ✅ **Packaging unifié** — `config/plans.php` (catalogue), `PlanService`,
   `PackageService` supprimé, `Tenant::hasModule()` rebranché, `*_enabled`
   dérivés de la formule. Les gestes commerciaux passent par des dérogations
   tracées (`feature_overrides`) qui survivent à un changement d'offre.
2. ✅ **Échéance** — `status` (enum `TenantStatus`) et `subscription_ends_at` sur
   la table centrale, plus une migration de reprise des tenants existants.
3. ✅ **Middleware `EnsureTenantActive`** — expiré → lecture seule (écritures en
   402), suspendu ou désactivé → 403. Les routes d'authentification et
   d'abonnement restent toujours joignables, sans quoi un client échu ne
   pourrait pas régulariser.
4. ✅ **Bandeau d'essai** (`SubscriptionBanner`) + écran `/abonnement` où le
   client choisit sa formule ; la demande déclenche un email à O3App.
5. ✅ **`subscriptions:check`** — planifiée à 7 h : bascule les statuts, relance
   à J-7 / J-3 / J-1 puis à l'échéance, sans jamais réexpédier deux fois la même
   relance. Option `--dry-run`.
6. ✅ **Back-office central** — carte « Abonnement » sur la fiche tenant :
   statut, échéance, bouton « Encaisser » qui enregistre le règlement
   (`tenant_payments`) et repousse l'échéance, plus l'historique.

Deux corrections sont tombées au passage : `/api/package-info` était accessible
sans jeton (n'importe qui pouvait lire la formule d'un client), et
`TenantCreate.vue` portait une quatrième grille tarifaire codée en dur
(499 / 999 / 1999) qui ne correspondait ni au contrat ni à ce qui était facturé.
Le formulaire lit maintenant le catalogue servi par `/api/central/plans`.

> ⚠️ **Attention en production.** `jadema` et `teliphoni` sont des tenants réels.
> Le jour où le middleware de la phase 1 est déployé, ils doivent être
> préalablement passés en `status = active` avec une échéance lointaine, sinon
> tu coupes l'accès à tes clients actuels. À faire avant le déploiement, pas après.
> Rappel `CLAUDE.md` : toute modification de `routes/api.php` exige
> `php artisan route:cache` sur le VPS, sinon elle reste inerte.

### Phase 2 — Facturation automatique — ✅ **implémentée le 2026-09-20**

> Code écrit et testé (658 tests verts). **Inactive tant que l'identité de
> facturation n'est pas renseignée** : le code refuse d'émettre plutôt que de
> produire une facture non conforme. Mise en service et variables à renseigner :
> [facturation-abonnements.md](facturation-abonnements.md).

Facture d'abonnement en PDF, numérotée `FA-2026-0001`, émise 15 jours avant
l'échéance pour la période suivante (facturation d'avance, article 8.3 du
contrat) et envoyée par email. Le règlement saisi au back-office la solde et
repousse l'échéance.

Ce qui est verrouillé : séquence continue sans doublon (verrou de ligne, pas de
`MAX()+1`), facture figée à l'émission, annulation plutôt que suppression, pas
de double facturation d'une période, et un email qui échoue ne détruit pas un
document déjà numéroté. TVA marocaine à 20 %, ou mention d'exonération si
l'entité n'y est pas assujettie.

Le client télécharge ses factures depuis `/abonnement` ; son ICE lui est demandé
au moment où il choisit sa formule.

### Phase 3 — Confort *(plus tard, sur signal du marché)*

Paiement en ligne (CMI/Payzone), changement de formule en self-service,
parrainage. À ne lancer que quand le volume de relances manuelles devient
pénible — c'est-à-dire quand tu as déjà des clients payants.

### En parallèle, dès la phase 0 — Acquisition

- **Page tarifs publique** sur la vitrine (O3_ecom) reprenant le §3, avec le
  bouton d'essai qui pointe sur le formulaire d'inscription **déjà fonctionnel**.
  Aujourd'hui le parcours existe mais rien n'y mène : c'est un gâchis pur.
- **Deux études de cas** : `jadema` et `teliphoni`. Chiffrées, une page chacune.
  Sur ce segment, la référence locale vend mieux que n'importe quel argumentaire.
- **Démo permanente** : le tenant `demo` existe déjà, avec ses données seedées.
  Un lien « essayer sans inscription » supprime la principale friction.
- **Canal principal recommandé** : le direct. Associations professionnelles,
  grossistes, zones commerciales de ta ville. Pour un premier ticket à
  390–690 MAD/mois, la visite physique convertit largement mieux que la publicité.

---

## 6. Ce qui t'appartient, et que je ne peux pas décider

1. **Les montants finaux** du §3 — tu connais le pouvoir d'achat de tes prospects
   mieux que le code.
2. **Le sort des clients actuels** : `jadema` et `teliphoni` passent-ils au tarif
   public, ou gardent-ils un tarif historique en échange de leur témoignage ?
   (Recommandation : tarif historique gelé 12 mois contre étude de cas signée.)
3. **L'entité de facturation** : les factures d'abonnement doivent sortir au nom
   d'une structure existante. Le code de la phase 2 est prêt et refuse d'émettre
   tant que raison sociale, adresse et **ICE** ne sont pas renseignés — c'est
   désormais le seul obstacle entre toi et la première facture envoyée.

---

## 7. La prochaine action

Valider la grille du §3 (ou la corriger), puis enchaîner sur la phase 1 — en
commençant par l'unification du packaging, qui conditionne tout le reste.
