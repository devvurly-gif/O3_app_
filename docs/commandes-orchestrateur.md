# Commandes possibles pour l'orchestrateur chef

Dérivées du [dictionnaire des données](dictionnaire-donnees.md) (57 tables, 652 colonnes). Pour chaque commande : la phrase à dire, les tables et colonnes lues, et ce qu'elle produit.

**Légende**
- ✅ existe déjà dans le chat de l'orchestrateur
- 📖 lecture seule : une réponse chiffrée, rien n'est modifié (à construire, risque faible)
- ✍️ brouillon ou proposition : l'orchestrateur prépare, vous validez par un clic (à construire)
- 🛠 demande de développement : une donnée, un événement ou une action qui n'existe pas encore

**Règles communes (déjà celles du socle)** : lecture seule par défaut ; toute écriture est une proposition validée par un clic ; résultats plafonnés à 40 lignes ; montants arrondis ; chaque action au journal des agents ; les données lues par l'IA partent chez Anthropic, sauf les colonnes interdites (section 10).

---

## 0. Ce qui existe déjà

| Commande | Agent | Données |
| --- | --- | --- |
| ✅ « état des agents », « événements à trier », « relances à valider » | Orchestrateur | `agent_events`, `agent_cases`, `agent_approvals`, `payment_reminders` |
| ✅ « prépare un inventaire » (entrepôt, « articles à vérifier ») | Stocks | `warehouse_has_stock`, `warehouses`, `products` |
| ✅ « contrôle les encaissements » | Recouvrement | `document_headers.due_at`, `document_footers.amount_due`, `payment_reminders` |
| ✅ « mettre à jour les fiches produits », « complète les descriptions, catégories et marques », « révise les prix avec une marge de 25 % », « attribue des codes-barres », « active les fiches produits », « prépare les fiches pour l'utilisation » | Achats et catalogue | `products` (`p_description`, `p_long_description`, `category_id`, `brand_id`, `p_salePrice`, `p_purchasePrice`, `p_ean13`, `p_status`) |
| ✅ « quels produits sont sans photo », « cherche les photos Jadever » | Achats et catalogue | `products.p_sku`, `product_images` |
| ✅ « publie les produits sur le website » | Marketing | `products.is_ecom`, `p_slug`, `p_status`, `product_images` |
| ✅ dépôt d'une photo ou d'un PDF (facture, bon de commande, paiement) | Achats, Ventes | `purchase_imports`, `document_headers` (brouillons) |
| ✅ « recrute un agent qui… », « chaque lundi à 8 h… », « retiens : … », « que sait faire chaque agent », « crée les comptes des agents », « demande de développement : … », « discutons de : … » | Atelier | `agents`, `agent_routines`, `agent_directives`, `users` |

---

## 1. Produits et catalogue

Tables : `products`, `categories`, `brands`, `product_suppliers`, `product_variants`, `price_lists`, `price_list_items`, `promotions`, `promotion_product`.

| Commande | Colonnes lues | Type |
| --- | --- | --- |
| « produits vendus sous leur prix d'achat » | `p_salePrice`, `p_purchasePrice`, `p_cost` | 📖 (déjà dans le contrôle des fiches) |
| « marge par catégorie / par marque » | `p_salePrice`, `p_purchasePrice`, `category_id`, `brand_id` | 📖 |
| « produits sans fournisseur » | `product_suppliers.product_id` | 📖 |
| « fournisseur le moins cher pour le produit X » | `product_suppliers` (prix, fournisseur) | 📖 |
| « doublons de produits » (même `p_sku`, `p_ean13` ou titre proche) | `p_sku`, `p_ean13`, `p_title` | 📖 puis ✍️ fusion proposée |
| « codes-barres en double ou invalides » | `p_ean13` | 📖 |
| « produits avec une TVA inhabituelle » | `p_taxRate` (20 % par défaut) | 📖 |
| « produits jamais vendus depuis 90 jours » | `document_lignes.product_id` + `document_headers.issued_at` | 📖 |
| « produits sans catégorie réelle / sans marque » | `category_id`, `brand_id` | ✅ partiel, 📖 pour le détail par liste |
| « fiches de la boutique en ligne sans description longue ou sans photo » | `is_ecom`, `p_long_description`, `product_images` | ✅ partiel |
| « quelles promotions sont actives / se terminent cette semaine » | `promotions.is_active`, `starts_at`, `ends_at`, `value`, `type` | 📖 |
| « produits absents de la liste de prix X » | `price_list_items`, `price_lists.channel` | 📖 |
| « propose une promotion sur les produits sans rotation » | stock + ventes + `promotions` | ✍️ (brouillon de promotion, à développer) |
| « retire du site les produits sans stock » | `is_ecom`, `warehouse_has_stock` | ✍️ (inverse de la publication) |

## 2. Stock et entrepôts

Tables : `warehouses`, `warehouse_has_stock` (`stockLevel`, `wh_average`), `stock_mouvements` (`direction`, `reason`, `quantity`, `stock_before`, `stock_after`, `status`), `warehouse_transfers`.

| Commande | Colonnes lues | Type |
| --- | --- | --- |
| « valeur du stock par entrepôt » | `stockLevel` × `wh_average` | 📖 |
| « produits en rupture ou sous le seuil » | `stockLevel`, seuil d'alerte (réglage) | ✅ (agent « Alerte stock faible ») |
| « produits à stock négatif » | `stockLevel` < 0 | 📖 |
| « mouvements du jour / de la semaine, par motif » | `stock_mouvements.reason`, `created_at`, `quantity` | 📖 |
| « pertes et casse du mois » | `reason` = `loss` | 📖 |
| « ajustements d'inventaire récents et qui les a faits » | `reason` = `inventory_adjustment`, `user_id` | 📖 |
| « transferts en attente » | `warehouse_transfers.status` = `pending` | 📖 |
| « produits dormants (aucun mouvement depuis 90 jours) » | `stock_mouvements.created_at` | 📖 |
| « mouvements en attente non appliqués » | `stock_mouvements.status` = `pending` | 📖 |
| « propose un transfert entre entrepôts » (équilibrer) | `warehouse_has_stock` par entrepôt | ✍️ + 🛠 |
| « propose un réapprovisionnement à partir des ventes » | ventes des 30 jours, `stockLevel`, `product_suppliers` | ✍️ + 🛠 (brouillon de bon de commande) |

## 3. Clients et fournisseurs (`third_partners`)

| Commande | Colonnes lues | Type |
| --- | --- | --- |
| « clients sans téléphone, e-mail ou ICE » | `tp_phone`, `tp_email`, `tp_Ice_Number` | 📖 |
| « doublons de clients ou de fournisseurs » | `tp_Ice_Number`, `tp_phone`, `tp_title` | 📖 puis ✍️ |
| « clients qui dépassent leur seuil de crédit » | `encours_actuel` > `seuil_credit` | 📖 |
| « clients qui n'ont pas commandé depuis 60 jours » | dernière facture de vente par `thirdPartner_id` | 📖 |
| « meilleurs clients du trimestre » | `document_footers.total_ttc` par `thirdPartner_id` | 📖 |
| « clients en compte à facturer ce mois » | `type_compte` = `en_compte`, `frequence_facturation` | 📖 puis ✍️ (brouillons de factures périodiques, 🛠) |
| « fournisseurs inactifs depuis un an » | `tp_status`, dernier achat | 📖 |

Colonnes jamais lues ni envoyées : `order_pin_hash`, `order_pin_failures`, `order_pin_locked_at`.

## 4. Ventes (`document_headers`, `document_lignes`, `document_footers`)

Les types de documents sont dans `document_type` ; les états dans `status` (brouillon, confirmé, livré, payé, partiel, annulé, converti…).

| Commande | Colonnes lues | Type |
| --- | --- | --- |
| « chiffre d'affaires du jour / de la semaine / du mois » (HT, TTC) | `total_ht`, `total_ttc`, `issued_at` | 📖 |
| « ventes par produit / par client / par vendeur / par session de caisse » | `document_lignes`, `user_id`, `pos_session_id` | 📖 |
| « top 10 des produits vendus » | `document_lignes.quantity`, `total_ligne_ht` | 📖 |
| « factures échues depuis plus de 30 jours » | `due_at`, `amount_due` | ✅ (via encaissements), 📖 pour la liste |
| « devis sans suite depuis 10 jours » | `document_type` devis, `status`, `issued_at` | 📖 puis ✍️ (relance) |
| « bons de livraison non facturés » | `status` livré, `parent_id` | 📖 |
| « remises accordées ce mois » | `discount_percent`, `total_discount` | 📖 |
| « lignes vendues sous le prix de référence » | `unit_price` < `reference_price` | 📖 |
| « factures annulées ou converties récemment, et par qui » | `status`, `user_id`, `activity_log` | 📖 |
| « prépare un devis pour le client X : produits… » | `document_headers`, `document_lignes` | ✍️ + 🛠 (brouillon de devis) |
| « relance les devis sans réponse » | idem | ✍️ + 🛠 (message à valider, aucun envoi) |

## 5. Achats

| Commande | Colonnes lues | Type |
| --- | --- | --- |
| « achats du mois par fournisseur » | `document_footers.total_ttc`, `thirdPartner_id` | 📖 |
| « bons de commande fournisseurs en attente de réception » | `document_type`, `status` | 📖 |
| « factures fournisseurs à payer cette semaine » | `due_at`, `amount_due`, `company_role` | 📖 |
| « produits dont le prix d'achat a augmenté » | `document_lignes.unit_price` dans le temps, `p_purchasePrice` | 📖 |
| « compare les prix de plusieurs fournisseurs » | `product_suppliers` | 📖 + 🛠 |
| « brouillons de bons de commande chaque matin » | stock bas + `product_suppliers` | ✅ (planificateur 07:30) |
| « relance le fournisseur en retard de livraison » | `due_at`, `status` | ✍️ + 🛠 |

## 6. Paiements, caisse et trésorerie

Tables : `payments` (`method`, `amount`, `paid_at`), `pos_terminals`, `pos_sessions`, `cash_accounts`, `cash_categories`, `cash_transactions`, `cash_recurrences`.

| Commande | Colonnes lues | Type |
| --- | --- | --- |
| « encaissements du jour par mode de paiement » | `payments.method`, `amount`, `paid_at` | 📖 |
| « solde de chaque compte de trésorerie » | `ca_initial_balance` + `ct_amount` selon `ct_direction` | 📖 |
| « dépenses du mois par catégorie » | `cash_transactions` sortantes, `cash_category_id` | 📖 |
| « échéances récurrentes des 30 prochains jours » | `cash_recurrences.cr_next_run_at`, `cr_amount` | 📖 |
| « dépenses sans justificatif » | `ct_attachment_path` vide | 📖 |
| « sessions de caisse ouvertes depuis plus de 24 h » | `pos_sessions.opened_at`, `closed_at` | 📖 |
| « sessions de caisse à valider » | `closed_at` rempli, `validated_at` vide | 📖 |
| « écarts de caisse du mois » | `cash_difference`, `variance_reason` | 📖 |
| « rapproche ce virement ou ce chèque d'une facture » | `payments.reference`, `amount`, factures ouvertes | ✍️ + 🛠 |
| « propose un échéancier de paiement pour le client X » | `amount_due`, `encours_actuel` | ✍️ + 🛠 |

## 7. Boutique en ligne et clients

| Commande | Colonnes lues | Type |
| --- | --- | --- |
| « publie les produits sur le website » | voir section 0 | ✅ |
| « produits en ligne sans stock ou sans photo » | `is_ecom`, `stockLevel`, `product_images` | 📖 |
| « commandes reçues par WhatsApp ou chat aujourd'hui » | `order_messages`, `whatsapp_order_imports`, `customer_chat_sessions` | 📖 (et ✅ pour la transformation en brouillon de livraison) |
| « bannières et promotions du site » | `slides`, `promotions.banner_text` | 🛠 (agent Marketing) |
| « relance les clients par e-mail ou WhatsApp » | `tp_phone`, `tp_email` | ✍️ + 🛠 (aucun envoi sans validation) |

## 8. Utilisateurs, rôles et traçabilité

| Commande | Colonnes lues | Type |
| --- | --- | --- |
| « qui a modifié le produit / la facture X » | `activity_log` | 📖 |
| « activité récente d'un utilisateur » | `activity_log`, `users` | 📖 |
| « utilisateurs inactifs, rôles et nombre de permissions » | `users.is_active`, `role_id`, `role_permission` | 📖 |
| « agents IA : actions du jour, propositions en attente, appels IA consommés » | `agent_actions`, `agent_events`, plafonds | ✅ partiel, 📖 |

## 9. Commandes transversales (utiles à tous les agents)

| Commande | Principe |
| --- | --- |
| « résume la journée » | Un seul message : ventes, encaissements, stock bas, validations en attente, anomalies (assemblage de 📖) |
| « que dois-je valider ? » | Toutes les propositions en attente (`agent_events` routés), triées par ancienneté |
| « chaque matin à 8 h, envoie-moi le point » | Routine existante + étapes 📖 ci-dessus (nouvelles étapes de routine, lecture seule) |
| « dès qu'un client dépasse son seuil de crédit, préviens-moi » | Déclencheur interne à ajouter (🛠) à la liste : produit créé, stock bas, facture confirmée, paiement reçu, document déposé |

## 10. Colonnes à ne jamais lire ni envoyer à l'IA

| Table | Colonnes |
| --- | --- |
| `users` | `password`, `remember_token` |
| `third_partners` | `order_pin_hash`, `order_pin_failures`, `order_pin_locked_at` |
| `personal_access_tokens` | `token` |
| `push_subscriptions` | clés d'abonnement |
| `settings` | clés API (Anthropic, messagerie), secrets |
| `document_footers` | `bank_details` (données bancaires de la société) : lisibles par l'administrateur, pas envoyées à l'IA |

## 11. Comment les construire sans risque

1. **Un catalogue de lectures** : chaque commande 📖 devient un outil borné de plus dans `AgentDataTools` (domaine de données, paramètres limités, 40 lignes au plus). Le modèle choisit l'outil et ses paramètres ; il n'écrit jamais de requête.
2. **L'orchestrateur reconnaît la phrase** : règles pour les formulations courantes, compréhension avancée (IA) pour le reste, comme aujourd'hui.
3. **Chaque commande ✍️ est une proposition** (lot ou proposition numérotée) appliquée par un clic, avec revérification au moment d'appliquer.
4. **Chaque commande 🛠 passe par l'entretien de conception** (« discutons de : … ») puis par une demande de développement.

## 12. Ordre de construction conseillé

1. « résume la journée » et « que dois-je valider ? » (assemblent ce qui existe)
2. Ventes : chiffre d'affaires, factures échues, devis sans suite, bons de livraison non facturés
3. Trésorerie : encaissements du jour, soldes des comptes, écarts et sessions de caisse
4. Stock : valeur du stock, produits dormants, transferts en attente
5. Catalogue : doublons, marges, produits jamais vendus
6. Tiers : clients inactifs, seuil de crédit
7. Brouillons ✍️ : relance de devis, réapprovisionnement, rapprochement de paiements
