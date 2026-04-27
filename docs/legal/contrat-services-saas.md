# CONTRAT DE SERVICES SaaS — O3 APP

**Référence contrat :** `[CTR-AAAA-NNN]`
**Date de signature :** `[JJ/MM/AAAA]`
**Lieu :** `[Ville]`, Royaume du Maroc
**Version CGS :** `[CGS-v2026.04]`

---

## ENTRE LES SOUSSIGNÉS

**LE PRESTATAIRE** :
`[Raison sociale ou Nom commercial]`
Forme juridique : `[SARL / Auto-entrepreneur / Personne physique]`
Siège social : `[Adresse]`
RC : `[N° RC]`  ·  ICE : `[N° ICE]`  ·  IF : `[N° IF]`  ·  CNSS : `[N° CNSS]`
Représenté par : `[Nom Prénom]`, en qualité de `[Gérant / Représentant légal]`

Ci-après dénommé « **le Prestataire** ».

**ET**

**LE CLIENT** :
Raison sociale : `[Raison sociale du Client]`
Forme juridique : `[…]`
Siège social : `[Adresse]`
RC : `[…]`  ·  ICE : `[…]`  ·  IF : `[…]`
Représenté par : `[Nom Prénom]`, en qualité de `[…]`

Ci-après dénommé « **le Client** ».

Le Prestataire et le Client sont ci-après dénommés individuellement la « **Partie** » et collectivement les « **Parties** ».

---

## PRÉAMBULE

Le Prestataire édite et exploite **O3 App**, une solution SaaS de gestion commerciale multi-tenant comprenant notamment : gestion des produits et stocks, documents commerciaux (ventes / achats / stock), point de vente (POS), reporting, et modules optionnels.

Le Client souhaite bénéficier de cette solution pour les besoins de son activité, dans les conditions et selon le périmètre définis ci-après.

---

## ARTICLE 1 — DÉFINITIONS

- **Solution** : la plateforme O3 App, accessible en mode SaaS via un navigateur web et le sous-domaine dédié `[client].o3app.ma`.
- **Tenant** : l'environnement isolé (base de données + stockage) provisionné pour le Client.
- **Données du Client** : toutes les données saisies, importées ou générées par le Client ou ses utilisateurs au sein du Tenant.
- **Utilisateur** : toute personne physique disposant d'identifiants pour se connecter à la Solution au nom du Client.
- **Mise en service** : la date à laquelle le Tenant du Client est provisionné, accessible et utilisable en production.
- **Heures Ouvrées** : du lundi au samedi, de 09h00 à 19h00 (heure du Maroc), hors jours fériés légaux marocains.
- **Module** : fonctionnalité optionnelle de la Solution activable individuellement (ex. POS, e-Commerce, Paiement BL).

---

## ARTICLE 2 — OBJET DU CONTRAT

Le présent contrat a pour objet de définir les conditions dans lesquelles le Prestataire met à la disposition du Client la Solution O3 App en mode SaaS, ainsi que les services associés (hébergement, maintenance, support, sauvegardes).

Le Client conserve l'entière propriété de ses Données. Le Prestataire conserve l'entière propriété de la Solution, de son code source, de sa marque et de tout élément technique sous-jacent.

---

## ARTICLE 3 — PÉRIMÈTRE FONCTIONNEL

À la date de signature, les fonctionnalités et modules retenus par le Client sont détaillés en **Annexe 1 — Tarifs et périmètre**.

L'activation ou la désactivation ultérieure d'un module fait l'objet d'un avenant ou d'un bon de commande accepté par les deux Parties.

---

## ARTICLE 4 — MISE EN SERVICE

4.1. Le Prestataire s'engage à provisionner le Tenant du Client dans un délai de `[5]` jours ouvrés à compter de la signature du présent contrat et du paiement des frais de mise en service éventuels.

4.2. La Mise en Service comprend :
- Création du sous-domaine `[client].o3app.ma`
- Création du Tenant (base de données dédiée et stockage isolé)
- Création des comptes administrateurs initiaux
- Activation des modules retenus
- Paramétrage initial standard (langue, devise MAD, taux de TVA marocain)

4.3. Toute prestation supplémentaire (import de données existantes, paramétrages spécifiques, formation sur site, développements sur mesure) fait l'objet d'un devis distinct.

---

## ARTICLE 5 — PÉRIODE D'ESSAI *(optionnelle — supprimer si non applicable)*

5.1. Le Client bénéficie d'une période d'essai gratuite de `[30]` jours calendaires à compter de la Mise en Service.

5.2. Pendant cette période, le Client peut résilier le contrat à tout moment, sans frais ni pénalité, par simple email adressé au Prestataire. Les Données saisies durant l'essai sont supprimées définitivement dans un délai de 30 jours après résiliation.

5.3. À l'issue de la période d'essai, et sauf résiliation expresse notifiée avant son terme, le contrat se poursuit aux conditions tarifaires de l'Annexe 1.

---

## ARTICLE 6 — PHASE DE STABILISATION

6.1. Les Parties reconnaissent que la Solution est un logiciel évolutif, faisant l'objet d'améliorations et d'ajustements continus.

6.2. Pendant les **90 jours calendaires** suivant la Mise en Service (« **Phase de Stabilisation** ») :
- Le Prestataire s'engage à corriger sans frais supplémentaires tout dysfonctionnement signalé par le Client ;
- Les engagements de niveau de service (SLA) de l'Article 9 sont réduits de moitié et aucune pénalité n'est due en cas de manquement ;
- Le Client s'engage à signaler les anomalies par écrit dans un délai raisonnable et à participer aux tests correctifs.

6.3. Cette phase ne saurait constituer une renonciation aux droits du Client en matière de conformité du service.

---

## ARTICLE 7 — DURÉE ET RENOUVELLEMENT

7.1. Le présent contrat est conclu pour une durée initiale de **`[12]` mois** à compter de la Mise en Service.

7.2. À l'issue de cette période initiale, il est renouvelé tacitement par périodes successives de **6 mois**, sauf dénonciation par l'une des Parties, notifiée par lettre recommandée avec accusé de réception ou email avec accusé de lecture, au moins **`[60]` jours** avant l'échéance en cours.

---

## ARTICLE 8 — TARIFS, FACTURATION ET PAIEMENT

8.1. **Tarifs** — Les tarifs applicables sont détaillés en Annexe 1. Ils sont exprimés en Dirhams marocains (MAD), hors taxes.

8.2. **TVA** — Une TVA au taux légal en vigueur (actuellement 20 %) s'ajoute aux montants HT.

8.3. **Périodicité** — La facturation est `[mensuelle / trimestrielle / annuelle]` et émise d'avance. Les frais de mise en service sont facturés à la signature.

8.4. **Délai de paiement** — Les factures sont payables à `[15]` jours date de facture, par virement bancaire sur le compte indiqué sur la facture.

8.5. **Retard de paiement** — Tout retard de paiement entraîne, après mise en demeure restée sans effet sous 8 jours :
- L'application de pénalités de retard au taux de **3 fois le taux légal** ;
- La suspension de l'accès à la Solution après un délai supplémentaire de 8 jours ;
- La résiliation du contrat aux torts du Client après 30 jours de retard cumulés.

8.6. **Révision des tarifs** — Le Prestataire pourra réviser ses tarifs à chaque renouvellement, sous réserve d'un préavis écrit de 60 jours. À défaut d'acceptation, le Client peut résilier sans pénalité avant la prise d'effet de la révision.

---

## ARTICLE 9 — NIVEAUX DE SERVICE (SLA)

9.1. **Disponibilité** — Le Prestataire s'engage à un taux de disponibilité mensuel de la Solution de **99 % en Heures Ouvrées**, mesuré hors :
- Maintenance planifiée annoncée 48h à l'avance ;
- Force majeure (Article 21) ;
- Indisponibilité imputable au Client ou à un tiers (FAI, navigateur, etc.) ;
- Phase de Stabilisation (Article 6).

9.2. **Maintenance planifiée** — Le Prestataire effectue ses opérations de maintenance préférentiellement les dimanches entre 02h00 et 06h00. Toute fenêtre de maintenance supérieure à 30 minutes est notifiée au Client par email.

9.3. **Indemnisation** — En cas de manquement avéré au taux de disponibilité durant un mois donné, et sur demande écrite du Client dans les 30 jours, un avoir de service est accordé selon le barème suivant :

| Disponibilité mesurée | Avoir |
|---|---|
| ≥ 99 % | 0 % |
| 97 % – 98,99 % | 5 % de l'abonnement mensuel |
| 95 % – 96,99 % | 10 % |
| < 95 % | 20 % |

L'avoir total annuel est plafonné à un (1) mois d'abonnement. Cet avoir constitue la **seule et unique indemnisation** due par le Prestataire au titre du SLA.

---

## ARTICLE 10 — SUPPORT

10.1. **Canaux** — Le support est assuré par email (`support@o3app.ma`) ou via un canal dédié `[WhatsApp / téléphone]` indiqué en Annexe 1.

10.2. **Horaires** — Le support est accessible en Heures Ouvrées.

10.3. **Délais de prise en charge** :

| Sévérité | Description | Première réponse | Résolution / contournement visé |
|---|---|---|---|
| Critique (P1) | Solution totalement indisponible ou perte de données | 2h ouvrées | 8h ouvrées |
| Majeure (P2) | Fonction essentielle inopérante (POS, facturation) | 4h ouvrées | 1 jour ouvré |
| Mineure (P3) | Anomalie non bloquante, demande d'évolution | 1 jour ouvré | Selon planning |

10.4. La sévérité est qualifiée par le Prestataire après échange contradictoire avec le Client.

---

## ARTICLE 11 — HÉBERGEMENT

11.1. La Solution est hébergée sur l'infrastructure cloud **`[DigitalOcean — région Frankfurt]`** ou tout autre hébergeur de niveau équivalent que le Prestataire pourrait retenir.

11.2. Le Prestataire pourra modifier le prestataire d'hébergement sous réserve d'un préavis de 30 jours et du maintien d'un niveau de service au moins équivalent.

11.3. Les Données sont stockées sur des serveurs situés dans l'Union européenne, conformément aux exigences de la **loi marocaine n° 09-08** relative à la protection des personnes physiques à l'égard du traitement des données à caractère personnel.

---

## ARTICLE 12 — SAUVEGARDES

12.1. Le Prestataire effectue une sauvegarde **quotidienne** automatique des Données du Client.

12.2. Les sauvegardes sont conservées pendant **14 jours** glissants, puis automatiquement supprimées.

12.3. En cas d'incident entraînant une perte de Données imputable au Prestataire, ce dernier s'engage à restaurer la dernière sauvegarde valide, dans un délai maximum de **8 heures ouvrées** après la détection de l'incident.

12.4. Le Client peut demander une restauration ponctuelle (par exemple suite à une suppression accidentelle de son fait). Une intervention en Heures Ouvrées est gratuite ; en dehors, elle est facturée selon le devis du Prestataire.

12.5. Il est expressément recommandé au Client d'effectuer ses propres exports périodiques (factures PDF, exports CSV) pour usage local.

---

## ARTICLE 13 — DONNÉES DU CLIENT

13.1. **Propriété** — Les Données du Client lui appartiennent intégralement. Le Prestataire n'acquiert aucun droit de propriété sur ces Données et s'interdit toute exploitation à des fins commerciales propres.

13.2. **Traitement** — Le Prestataire agit en qualité de **sous-traitant** au sens de la loi 09-08, le Client étant **responsable du traitement**. Le Prestataire ne traite les Données que sur instructions du Client et pour les seuls besoins de l'exécution du présent contrat.

13.3. **Sous-traitants ultérieurs** — Le Client autorise le recours à l'hébergeur visé à l'Article 11 et aux services techniques essentiels au fonctionnement de la Solution (DNS, anti-spam, livraison email transactionnel). La liste actualisée est communiquée sur simple demande.

13.4. **Localisation** — Les Données sont stockées dans l'Union européenne. Aucun transfert hors UE n'est effectué sans accord préalable écrit du Client.

13.5. **Sécurité** — Le Prestataire met en œuvre les mesures techniques et organisationnelles raisonnables pour protéger les Données : chiffrement TLS, authentification à mot de passe haché (bcrypt), isolation par tenant, journalisation des accès administrateurs, sauvegardes chiffrées.

13.6. **Notification d'incident** — En cas de violation de Données, le Prestataire notifie le Client dans un délai maximal de **72 heures** après la prise de connaissance de l'incident, et fournit toute information utile à l'évaluation de l'impact.

13.7. **Restitution** — En fin de contrat (Article 23), les Données sont restituées au Client puis supprimées des systèmes du Prestataire dans les conditions de l'Article 23.

---

## ARTICLE 14 — SÉCURITÉ

14.1. Le Prestataire s'engage à maintenir un niveau de sécurité conforme aux pratiques usuelles du marché : mises à jour de sécurité régulières du système, des dépendances applicatives, pare-feu serveur, accès SSH par clé uniquement, surveillance des journaux.

14.2. Le Client est responsable de la confidentialité de ses identifiants. Toute action effectuée avec des identifiants Client est réputée effectuée sous sa responsabilité.

14.3. Le Client s'engage à signaler sans délai toute compromission ou suspicion de compromission d'un compte utilisateur.

---

## ARTICLE 15 — CONFIDENTIALITÉ

15.1. Chaque Partie s'engage à conserver confidentielles les informations non-publiques reçues de l'autre Partie dans le cadre de l'exécution du contrat (informations commerciales, techniques, financières, données opérationnelles).

15.2. Cette obligation perdure pendant **5 ans** après la fin du contrat.

15.3. Sont exclues les informations publiquement disponibles, déjà connues de la Partie réceptrice avant communication, ou dont la divulgation est exigée par une autorité compétente.

---

## ARTICLE 16 — PROPRIÉTÉ INTELLECTUELLE

16.1. **Solution** — Le Prestataire est et demeure seul titulaire de tous les droits de propriété intellectuelle relatifs à la Solution (code source, base de données fonctionnelle, interface graphique, marque « O3 App », documentation).

16.2. **Licence d'usage** — Le présent contrat confère au Client un droit personnel, non exclusif, non transférable et non cessible d'utilisation de la Solution, pour la durée du contrat et dans la limite du périmètre fonctionnel souscrit.

16.3. **Restrictions** — Le Client s'interdit de : décompiler, copier, modifier, dériver, redistribuer, sous-louer ou vendre la Solution ou tout ou partie de son code.

16.4. **Données du Client** — La présente clause ne s'applique pas aux Données, qui restent la propriété exclusive du Client (Article 13).

---

## ARTICLE 17 — OBLIGATIONS DU CLIENT

Le Client s'engage à :
- Régler ponctuellement les sommes dues ;
- Utiliser la Solution conformément à sa destination et à la législation en vigueur (notamment fiscale et commerciale marocaine) ;
- S'assurer de l'exactitude et de la conformité des Données qu'il saisit ;
- Tenir à jour ses identifiants administratifs et garantir leur confidentialité ;
- Coopérer de bonne foi avec le Prestataire en cas d'incident ou de demande d'information ;
- Disposer d'un accès Internet et d'un navigateur web à jour adapté à la Solution.

Le Client est seul responsable du contenu et de la légalité des Données qu'il introduit dans la Solution, ainsi que des décisions commerciales, comptables ou fiscales qu'il prend sur la base des informations qu'elle lui restitue.

---

## ARTICLE 18 — OBLIGATIONS DU PRESTATAIRE

Le Prestataire s'engage à :
- Mettre la Solution à disposition dans les conditions du présent contrat ;
- Fournir le support et la maintenance définis ;
- Effectuer les sauvegardes prévues à l'Article 12 ;
- Notifier le Client de toute opération de maintenance significative ou incident majeur ;
- Faire évoluer la Solution sans dégradation significative des fonctionnalités souscrites.

---

## ARTICLE 19 — RESPONSABILITÉ

19.1. Chaque Partie est responsable, dans les conditions du droit commun, des dommages directs causés à l'autre Partie par sa faute prouvée dans l'exécution du contrat.

19.2. **Plafond** — La responsabilité totale et cumulée du Prestataire au titre du présent contrat, toutes causes confondues, est expressément limitée au **montant des sommes effectivement payées par le Client au titre des douze (12) derniers mois précédant le fait générateur**.

19.3. **Exclusions** — En aucun cas le Prestataire ne saurait être tenu responsable :
- Des dommages indirects (perte de chiffre d'affaires, de marge, d'image, de clientèle, d'opportunité commerciale) ;
- Des dommages résultant d'un usage non conforme de la Solution par le Client ;
- D'une indisponibilité ou perte de Données imputable à un tiers (hébergeur, FAI, fournisseur télécom, attaque informatique malgré les mesures raisonnables mises en place) ;
- Des conséquences fiscales, comptables ou légales des décisions prises par le Client sur la base des restitutions de la Solution.

19.4. La présente limitation ne s'applique pas en cas de faute lourde, dol, atteinte aux droits de propriété intellectuelle ou manquement aux obligations de confidentialité.

---

## ARTICLE 20 — ÉVOLUTIONS ET MISES À JOUR

20.1. Le Prestataire est libre de faire évoluer la Solution (corrections, améliorations, nouvelles fonctionnalités, refontes ergonomiques) sans accord préalable du Client, dès lors que :
- Les fonctions essentielles souscrites demeurent disponibles ;
- Aucune dégradation significative n'est introduite.

20.2. Toute évolution majeure susceptible d'impacter les usages du Client (changement d'interface significatif, suppression d'une fonction) est annoncée par email au moins 30 jours avant déploiement.

20.3. Les évolutions sont automatiquement mises à disposition du Client, sans surcoût, dans la limite du périmètre souscrit.

---

## ARTICLE 21 — FORCE MAJEURE

21.1. Aucune Partie ne pourra être tenue responsable d'un manquement à ses obligations résultant d'un cas de force majeure au sens de l'article 269 du Dahir des Obligations et des Contrats marocain.

21.2. Sont notamment considérés comme tels : catastrophes naturelles, troubles civils, grèves générales, défaillance massive des opérateurs de télécommunications nationaux, attaques cybernétiques massives malgré les mesures raisonnables, décisions des autorités publiques.

21.3. La Partie empêchée notifie l'autre dans les plus brefs délais. Si l'empêchement excède **30 jours**, chaque Partie peut résilier le contrat sans indemnité par simple notification écrite.

---

## ARTICLE 22 — RÉSILIATION

22.1. **Résiliation à terme** — Conformément à l'Article 7.

22.2. **Résiliation pour faute** — En cas de manquement grave de l'une des Parties à ses obligations, l'autre Partie peut résilier le contrat de plein droit, après mise en demeure restée sans effet pendant **30 jours**, par lettre recommandée avec accusé de réception.

22.3. **Résiliation immédiate** — Le contrat peut être résilié de plein droit, sans mise en demeure préalable, en cas de :
- Cessation d'activité, redressement ou liquidation judiciaire de l'une des Parties (sous réserve des dispositions d'ordre public) ;
- Utilisation frauduleuse, illégale ou contraire à l'ordre public de la Solution par le Client ;
- Atteinte grave aux droits de propriété intellectuelle ou à la confidentialité.

22.4. La résiliation, quelle qu'en soit la cause, n'exonère pas le Client du paiement des sommes échues à la date d'effet de la résiliation.

---

## ARTICLE 23 — RÉVERSIBILITÉ ET FIN DE CONTRAT

23.1. Dans les **30 jours** suivant la fin effective du contrat, le Prestataire met à disposition du Client un export complet de ses Données dans un format structuré et exploitable (SQL, CSV, ZIP des fichiers stockés).

23.2. À l'issue de ce délai de 30 jours, et sauf demande expresse contraire du Client, le Tenant et l'ensemble des Données associées sont **supprimés définitivement** des systèmes de production du Prestataire.

23.3. Les sauvegardes contenant les Données sont purgées au plus tard **90 jours** après la fin du contrat, à l'expiration de la rétention courante (Article 12).

23.4. Une attestation de suppression est remise au Client sur simple demande.

23.5. Toute prestation de réversibilité avancée (migration vers un autre éditeur, transformation de format spécifique) fait l'objet d'un devis distinct.

---

## ARTICLE 24 — CESSION

24.1. Le Client ne peut céder le présent contrat à un tiers sans accord préalable écrit du Prestataire.

24.2. Le Prestataire pourra céder le contrat à un successeur dans le cadre d'une cession d'activité ou d'une opération de restructuration, sous réserve d'en informer le Client et que les conditions essentielles du contrat soient maintenues.

---

## ARTICLE 25 — MODIFICATION DU CONTRAT

Toute modification du présent contrat doit faire l'objet d'un avenant écrit signé par les deux Parties.

Les évolutions purement techniques de la Solution (Article 20) ne constituent pas une modification du contrat.

---

## ARTICLE 26 — LOI APPLICABLE ET JURIDICTION

26.1. Le présent contrat est régi par le **droit marocain**.

26.2. **Règlement amiable** — En cas de différend, les Parties s'efforceront de trouver une solution amiable préalablement à tout recours juridictionnel, dans un délai de 30 jours à compter de la première notification écrite.

26.3. **Juridiction compétente** — À défaut, tout litige sera soumis à la compétence exclusive du **Tribunal de Commerce de `[Ville du siège du Prestataire — ex. Casablanca]`**.

---

## ARTICLE 27 — DISPOSITIONS DIVERSES

27.1. **Non-renonciation** — Le fait pour l'une des Parties de ne pas se prévaloir d'un manquement à une obligation contractuelle ne saurait valoir renonciation à s'en prévaloir ultérieurement.

27.2. **Nullité partielle** — Si une clause du contrat est jugée nulle ou inapplicable, les autres clauses demeurent en vigueur.

27.3. **Notifications** — Toute notification valable au titre du contrat est faite par email avec accusé de lecture aux adresses figurant en en-tête, ou par lettre recommandée aux adresses des sièges sociaux.

27.4. **Intégralité** — Le présent contrat, ensemble ses Annexes, constitue l'intégralité de l'accord entre les Parties et annule tout accord ou correspondance antérieure portant sur le même objet.

---

## SIGNATURES

Fait en deux (2) exemplaires originaux, à `[Ville]`, le `[JJ/MM/AAAA]`.

**Pour le Prestataire**
Nom :
Qualité :
Signature et cachet :

**Pour le Client**
Nom :
Qualité :
Signature et cachet :

*(Mention manuscrite « Lu et approuvé » avant signature de chaque Partie)*

---

# ANNEXE 1 — TARIFS ET PÉRIMÈTRE

## A. Modules souscrits

| Module | Inclus | Description |
|---|---|---|
| Cœur (gestion commerciale) | ☑ | Produits, catégories, marques, clients, fournisseurs, dépôts, documents ventes/achats/stock, reporting de base |
| POS — Point de vente | ☐ | Caisse, sessions, tickets, paiements multiples, fermeture session |
| e-Commerce / Boutique en ligne | ☐ | Vitrine produits + commandes en ligne |
| Paiement Bons de Livraison | ☐ | Encaissements partiels sur BL clients en compte |
| `[Module additionnel]` | ☐ | `[…]` |

## B. Volumétrie incluse

- **Utilisateurs nommés** : `[5]`
- **Terminaux POS** : `[2]`
- **Stockage fichiers (images produits, PDF)** : `[5 Go]`
- **Documents / mois** : `[illimité dans la limite d'un usage normal]`

Tout dépassement fait l'objet d'un avenant tarifaire (palier suivant).

## C. Tarifs

| Désignation | Montant HT (MAD) | Périodicité |
|---|---|---|
| Frais de mise en service (one-shot) | `[…]` | À la signature |
| Abonnement mensuel — pack souscrit | `[…]` | Mensuel d'avance |
| Module POS (option) | `[…]` | Mensuel |
| Utilisateur supplémentaire | `[…]` | Mensuel par utilisateur |
| Terminal POS supplémentaire | `[…]` | Mensuel par terminal |
| Stockage supplémentaire (par tranche de 5 Go) | `[…]` | Mensuel |
| Heure de support / formation hors forfait | `[…]` | À la prestation |

TVA 20 % en sus.

## D. Coordonnées de support

- Email : `support@o3app.ma`
- WhatsApp / Téléphone : `[…]`
- Plage horaire : `Lun–Sam, 09h00–19h00 (heure du Maroc)`

---

# ANNEXE 2 — DONNÉES PERSONNELLES (Loi 09-08)

## A. Finalités

Les Données personnelles éventuellement traitées dans le cadre du contrat le sont aux seules finalités suivantes :
- Authentification et gestion des comptes utilisateurs du Client ;
- Stockage et restitution des données opérationnelles du Client (ses clients finaux, fournisseurs, etc.) ;
- Support technique sur demande du Client ;
- Sécurité et journalisation.

## B. Catégories de données

- **Utilisateurs du Client** : nom, email, téléphone, mot de passe haché, journal d'accès.
- **Données métier saisies par le Client** : selon usage (clients finaux, partenaires, ventes, etc.).

## C. Durée de conservation

Pendant la durée du contrat. Suppression dans les conditions de l'Article 23.

## D. Droits des personnes concernées

Les utilisateurs et personnes concernées peuvent exercer leurs droits d'accès, rectification, opposition et suppression auprès du Client, en sa qualité de responsable du traitement. Le Prestataire fournit son assistance technique au Client pour répondre à ces demandes.

## E. Déclaration CNDP

Le Client est seul responsable de la déclaration de ses traitements auprès de la **Commission Nationale de contrôle de la protection des Données à caractère Personnel (CNDP)** lorsque cela est requis.

---

<!--
======================================================================
NOTES POUR L'ÉDITION (à supprimer avant signature)
======================================================================

POINTS À VALIDER AVANT IMPRESSION :

1. PRIX (Annexe 1.C) — Il faut fixer :
   • Frais setup (suggéré : 1 500–3 000 MAD selon import données)
   • Abonnement mensuel pack de base (suggéré : 350–600 MAD/mois pour SMB)
   • POS option : 200–400 MAD/mois supplémentaire
   • Utilisateur additionnel : 50–100 MAD/mois

2. ENGAGEMENT (Article 7) — 12 mois est standard. Si tu veux assouplir
   pour un premier client (teliphoni), passer à 6 mois est défendable.

3. ESSAI (Article 5) — 30 jours par défaut. Supprimer l'article entier
   si tu n'offres pas d'essai gratuit.

4. SLA (Article 9) — 99 % en heures ouvrées est réaliste solo. NE PAS
   promettre 99,9 % ou 24/7 que tu ne peux pas tenir.

5. PHASE STABILISATION (Article 6) — 90 jours est ta vraie protection.
   Garde-la même si elle paraît "défavorable" au client : elle te
   protège de pénalités SLA pendant que tu stabilises.

6. PLAFOND RESPONSABILITÉ (Article 19.2) — 12 mois de facturation est
   le standard SaaS. NE PAS accepter de plafond ouvert.

7. JURIDICTION (Article 26.3) — Mets le Tribunal de Commerce de TA
   ville (avantage logistique en cas de litige).

8. ANNEXE 1.B (volumétrie) — Important pour limiter les abus
   ("utilisateurs illimités" = piège). Définis clairement.

PROCHAINES ÉTAPES :
- Faire relire par un avocat / juriste local (1–2h, ~1 500–3 000 MAD)
  AU MOINS pour la première version. Tu réutilises ensuite.
- Convertir en .docx ou .pdf signable (Pandoc, Word, ou Google Docs).
- Pour teliphoni : possible de signer en version simplifiée 2-3 pages
  + référer à ce contrat-cadre comme "Conditions Générales de Service".
-->
