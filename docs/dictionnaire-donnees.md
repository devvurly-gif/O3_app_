# Dictionnaire des données O3 (base d'une entreprise)

**57 tables, 652 colonnes.** Généré le 06/10/2026 à partir du schéma issu des migrations (base de test complète). Une base par entreprise (tenant) ; les tables de la plateforme (tenants, domaines, abonnements) sont à part et ne sont pas détaillées ici.

## Produits et catalogue

### `products`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `p_title` | varchar(255) | oui |  |  |  |
| `p_code` | varchar(255) | non |  | unique |  |
| `p_description` | mediumtext | non |  |  |  |
| `p_long_description` | text | non |  |  |  |
| `p_sku` | varchar(255) | oui |  | unique |  |
| `p_ean13` | varchar(255) | non |  |  |  |
| `p_imei` | varchar(50) | non |  |  |  |
| `p_purchasePrice` | decimal(15,2) | oui | `0.00` |  |  |
| `p_salePrice` | decimal(15,2) | oui | `0.00` |  |  |
| `p_cost` | decimal(15,2) | oui | `0.00` |  |  |
| `p_status` | tinyint(1) | oui | `1` |  |  |
| `is_ecom` | tinyint(1) | oui | `0` |  |  |
| `p_slug` | varchar(255) | non |  | unique |  |
| `p_notes` | text | non |  |  |  |
| `p_taxRate` | decimal(5,2) | oui | `20.00` |  |  |
| `p_unit` | varchar(255) | oui | `pièce` |  |  |
| `category_id` | bigint unsigned | oui |  | → categories.id |  |
| `brand_id` | bigint unsigned | non |  | → brands.id |  |
| `structure_id` | bigint unsigned | non |  | → structure_incrementors.id |  |
| `deleted_at` | timestamp | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `product_images`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `title` | varchar(255) | non |  |  |  |
| `altContent` | varchar(255) | non |  |  |  |
| `url` | varchar(255) | oui |  |  |  |
| `isPrimary` | tinyint(1) | oui | `0` |  |  |
| `product_id` | bigint unsigned | oui |  | → products.id |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `product_documents`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `title` | varchar(255) | non |  |  |  |
| `file_name` | varchar(255) | non |  |  |  |
| `url` | varchar(255) | oui |  |  |  |
| `mime_type` | varchar(255) | non |  |  |  |
| `size` | bigint unsigned | non |  |  |  |
| `product_id` | bigint unsigned | oui |  | → products.id |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `product_videos`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `title` | varchar(255) | non |  |  |  |
| `url` | varchar(255) | oui |  |  |  |
| `product_id` | bigint unsigned | oui |  | → products.id |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `product_variants`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `product_id` | bigint unsigned | oui |  | → products.id |  |
| `label` | varchar(255) | oui |  |  |  |
| `attributes` | json | oui |  |  |  |
| `sku` | varchar(255) | non |  |  |  |
| `price` | decimal(12,2) | non |  |  |  |
| `stock` | decimal(12,2) | oui | `0.00` |  |  |
| `is_active` | tinyint(1) | oui | `1` |  |  |
| `position` | int unsigned | oui | `0` |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `variant_option_types`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `name` | varchar(255) | oui |  |  |  |
| `slug` | varchar(255) | oui |  | unique |  |
| `position` | int unsigned | oui | `0` |  |  |
| `is_active` | tinyint(1) | oui | `1` |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `variant_option_values`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `variant_option_type_id` | bigint unsigned | oui |  | → variant_option_types.id |  |
| `key` | varchar(255) | oui |  |  |  |
| `value` | varchar(255) | oui |  |  |  |
| `position` | int unsigned | oui | `0` |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `product_suppliers`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `product_id` | bigint unsigned | oui |  | → products.id |  |
| `third_partner_id` | bigint unsigned | oui |  | → third_partners.id |  |
| `supplier_sku` | varchar(255) | non |  |  |  |
| `purchase_price` | decimal(15,2) | non |  |  |  |
| `priority` | int unsigned | oui | `1` |  |  |
| `lead_time_days` | int unsigned | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `categories`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `ctg_title` | varchar(255) | oui |  |  |  |
| `ctg_code` | varchar(255) | non |  | unique |  |
| `ctg_status` | tinyint(1) | oui | `1` |  |  |
| `is_ecom` | tinyint(1) | oui | `1` |  |  |
| `structure_id` | bigint unsigned | non |  | → structure_incrementors.id |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `brands`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `br_title` | varchar(255) | oui |  |  |  |
| `br_code` | varchar(255) | non |  | unique |  |
| `br_status` | tinyint(1) | oui | `1` |  |  |
| `is_ecom` | tinyint(1) | oui | `1` |  |  |
| `structure_id` | bigint unsigned | non |  | → structure_incrementors.id |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `price_lists`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `name` | varchar(255) | oui |  |  |  |
| `description` | varchar(255) | non |  |  |  |
| `channel` | enum | oui | `all` |  | valeurs : all,pos,ecom |
| `is_default` | tinyint(1) | oui | `0` |  |  |
| `is_active` | tinyint(1) | oui | `1` |  |  |
| `priority` | int unsigned | oui | `0` |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `price_list_items`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `price_list_id` | bigint unsigned | oui |  | → price_lists.id |  |
| `product_id` | bigint unsigned | oui |  | → products.id |  |
| `price_ht` | decimal(15,2) | oui |  |  |  |
| `price_ttc` | decimal(15,2) | oui |  |  |  |
| `min_qty` | int unsigned | oui | `1` |  |  |
| `valid_from` | date | non |  |  |  |
| `valid_to` | date | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `promotions`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `name` | varchar(255) | oui |  |  |  |
| `slug` | varchar(255) | oui |  | unique |  |
| `description` | text | non |  |  |  |
| `type` | enum | oui | `percentage` |  | valeurs : percentage,fixed_amount |
| `value` | decimal(10,2) | oui |  |  |  |
| `min_purchase` | decimal(10,2) | non |  |  |  |
| `max_discount` | decimal(10,2) | non |  |  |  |
| `banner_image` | varchar(255) | non |  |  |  |
| `banner_text` | varchar(255) | non |  |  |  |
| `starts_at` | timestamp | non |  |  |  |
| `ends_at` | timestamp | non |  |  |  |
| `is_active` | tinyint(1) | oui | `1` |  |  |
| `priority` | int | oui | `0` |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `promotion_product`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `promotion_id` | bigint unsigned | oui |  | → promotions.id |  |
| `product_id` | bigint unsigned | oui |  | → products.id |  |
| `promo_price` | decimal(10,2) | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `slides`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `title` | varchar(255) | oui |  |  |  |
| `subtitle` | varchar(255) | non |  |  |  |
| `image` | varchar(255) | oui |  |  |  |
| `button_text` | varchar(255) | non |  |  |  |
| `link_type` | enum | oui | `none` |  | valeurs : promotion,category,product,url,none |
| `link_id` | bigint unsigned | non |  |  |  |
| `link_url` | varchar(255) | non |  |  |  |
| `position` | enum | oui | `hero` |  | valeurs : hero,sidebar,popup |
| `sort_order` | int | oui | `0` |  |  |
| `starts_at` | timestamp | non |  |  |  |
| `ends_at` | timestamp | non |  |  |  |
| `is_active` | tinyint(1) | oui | `1` |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

## Stock et entrepôts

### `warehouses`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `wh_title` | varchar(255) | oui |  |  |  |
| `wh_code` | varchar(255) | oui |  | unique |  |
| `wh_status` | tinyint(1) | oui | `1` |  |  |
| `structure_id` | bigint unsigned | non |  | → structure_incrementors.id |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `warehouse_has_stock`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `warehouse_id` | bigint unsigned | oui |  | → warehouses.id |  |
| `product_id` | bigint unsigned | oui |  | → products.id |  |
| `variant_id` | bigint unsigned | non |  | → product_variants.id |  |
| `stockLevel` | decimal(10,2) | oui | `0.00` |  |  |
| `stockAtTime` | timestamp | non |  |  |  |
| `wh_average` | decimal(15,2) | oui | `0.00` |  |  |
| `user_id` | bigint unsigned | non |  | → users.id |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `stock_mouvements`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `product_id` | bigint unsigned | oui |  | → products.id |  |
| `variant_id` | bigint unsigned | non |  | → product_variants.id |  |
| `warehouse_id` | bigint unsigned | oui |  | → warehouses.id |  |
| `document_header_id` | bigint unsigned | non |  | → document_headers.id |  |
| `document_reference` | varchar(255) | non |  |  |  |
| `document_type` | varchar(255) | non |  |  |  |
| `direction` | enum | oui |  |  | valeurs : in,out |
| `reason` | enum | oui |  |  | valeurs : purchase,sale,return_in,return_out,transfer_in,transfer_out,adjustment_in,adjustment_out,loss,initial,manual_entry,manual_exit,inventory_adjustment,purchase_receipt,sale_delivery,stock_entry,stock_exit,stock_adjustment,stock_transfer_out,stock_transfer_in,pos_sale,pos_void,cancellation |
| `quantity` | decimal(10,2) | oui |  |  |  |
| `unit_cost` | decimal(15,2) | oui | `0.00` |  |  |
| `stock_before` | decimal(10,2) | oui | `0.00` |  |  |
| `stock_after` | decimal(10,2) | oui | `0.00` |  |  |
| `user_id` | bigint unsigned | oui |  | → users.id |  |
| `notes` | text | non |  |  |  |
| `status` | enum | oui | `applied` |  | valeurs : pending,applied,cancelled |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `warehouse_transfers`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `from_warehouse_id` | bigint unsigned | oui |  | → warehouses.id |  |
| `to_warehouse_id` | bigint unsigned | oui |  | → warehouses.id |  |
| `product_id` | bigint unsigned | oui |  | → products.id |  |
| `quantity` | decimal(10,2) | oui |  |  |  |
| `status` | enum | oui | `pending` |  | valeurs : pending,completed,cancelled |
| `user_id` | bigint unsigned | oui |  | → users.id |  |
| `notes` | text | non |  |  |  |
| `transferred_at` | timestamp | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

## Tiers (clients, fournisseurs)

### `third_partners`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `tp_title` | varchar(255) | oui |  |  |  |
| `tp_code` | varchar(255) | non |  | unique |  |
| `tp_Ice_Number` | varchar(255) | non |  |  |  |
| `tp_Rc_Number` | varchar(255) | non |  |  |  |
| `tp_patente_Number` | varchar(255) | non |  |  |  |
| `tp_IdenFiscal` | varchar(255) | non |  |  |  |
| `tp_Role` | enum | oui | `customer` |  | valeurs : customer,supplier,both |
| `tp_status` | tinyint(1) | oui | `1` |  |  |
| `tp_phone` | varchar(255) | non |  |  |  |
| `tp_email` | varchar(255) | non |  |  |  |
| `tp_address` | text | non |  |  |  |
| `tp_city` | varchar(255) | non |  |  |  |
| `encours_actuel` | decimal(15,2) | oui | `0.00` |  |  |
| `seuil_credit` | decimal(15,2) | oui | `0.00` |  |  |
| `type_compte` | enum | oui | `normal` |  | valeurs : normal,en_compte |
| `frequence_facturation` | enum | non |  |  | valeurs : mensuelle,trimestrielle,semestrielle |
| `structure_id` | bigint unsigned | non |  | → structure_incrementors.id |  |
| `price_list_id` | bigint unsigned | non |  | → price_lists.id |  |
| `deleted_at` | timestamp | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |
| `order_pin_hash` | varchar(255) | non |  |  |  |
| `order_pin_set_at` | timestamp | non |  |  |  |
| `order_pin_failures` | tinyint unsigned | oui | `0` |  |  |
| `order_pin_locked_at` | timestamp | non |  |  |  |

## Documents commerciaux

### `document_headers`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `document_incrementor_id` | bigint unsigned | oui |  | → document_incrementors.id |  |
| `reference` | varchar(255) | oui |  | unique |  |
| `document_type` | varchar(255) | oui |  |  |  |
| `document_title` | varchar(255) | non |  |  |  |
| `parent_id` | bigint unsigned | non |  | → document_headers.id |  |
| `thirdPartner_id` | bigint unsigned | non |  | → third_partners.id |  |
| `company_role` | varchar(255) | non |  |  |  |
| `user_id` | bigint unsigned | oui |  | → users.id |  |
| `warehouse_id` | bigint unsigned | non |  | → warehouses.id |  |
| `warehouse_dest_id` | bigint unsigned | non |  | → warehouses.id |  |
| `pos_session_id` | bigint unsigned | non |  | → pos_sessions.id |  |
| `status` | enum | non | `draft` |  | valeurs : draft,confirmed,sent,delivered,received,pending,paid,partial,cancelled,converted,applied |
| `issued_at` | date | non |  |  |  |
| `due_at` | date | non |  |  |  |
| `notes` | text | non |  |  |  |
| `ship_name` | varchar(255) | non |  |  |  |
| `ship_phone` | varchar(255) | non |  |  |  |
| `ship_address` | varchar(500) | non |  |  |  |
| `ship_city` | varchar(255) | non |  |  |  |
| `deleted_at` | timestamp | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `document_lignes`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `document_header_id` | bigint unsigned | oui |  | → document_headers.id |  |
| `product_id` | bigint unsigned | non |  | → products.id |  |
| `variant_id` | bigint unsigned | non |  | → product_variants.id |  |
| `sort_order` | int | oui | `0` |  |  |
| `line_type` | enum | oui | `product` |  | valeurs : product,comment,discount |
| `designation` | varchar(255) | oui |  |  |  |
| `reference` | varchar(255) | non |  |  |  |
| `quantity` | decimal(10,2) | oui | `1.00` |  |  |
| `unit` | varchar(255) | non |  |  |  |
| `unit_price` | decimal(15,2) | oui | `0.00` |  |  |
| `reference_price` | decimal(15,2) | non |  |  |  |
| `discount_percent` | decimal(5,2) | oui | `0.00` |  |  |
| `tax_percent` | decimal(5,2) | oui | `20.00` |  |  |
| `total_ligne_ht` | decimal(15,2) | oui | `0.00` |  |  |
| `total_tax` | decimal(15,2) | oui | `0.00` |  |  |
| `total_ttc` | decimal(15,2) | oui | `0.00` |  |  |
| `status` | enum | oui | `active` |  | valeurs : active,cancelled |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `document_footers`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `document_header_id` | bigint unsigned | oui |  | unique → document_headers.id |  |
| `total_ht` | decimal(15,2) | oui | `0.00` |  |  |
| `total_discount` | decimal(15,2) | oui | `0.00` |  |  |
| `total_tax` | decimal(15,2) | oui | `0.00` |  |  |
| `total_ttc` | decimal(15,2) | oui | `0.00` |  |  |
| `amount_paid` | decimal(15,2) | oui | `0.00` |  |  |
| `amount_due` | decimal(15,2) | oui | `0.00` |  |  |
| `change_given` | decimal(15,2) | non |  |  |  |
| `payment_method` | enum | non |  |  | valeurs : cash,bank_transfer,cheque,effet,credit |
| `payment_date` | date | non |  |  |  |
| `total_in_words` | varchar(255) | non |  |  |  |
| `is_signed` | tinyint(1) | oui | `0` |  |  |
| `stamp_path` | varchar(255) | non |  |  |  |
| `is_printed` | tinyint(1) | oui | `0` |  |  |
| `is_sent` | tinyint(1) | oui | `0` |  |  |
| `sent_via` | json | non |  |  |  |
| `bank_details` | text | non |  |  |  |
| `legal_mentions` | text | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `document_incrementors`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `di_title` | varchar(255) | oui |  |  |  |
| `di_model` | varchar(255) | oui |  |  |  |
| `di_domain` | varchar(255) | oui |  |  |  |
| `template` | varchar(255) | oui |  |  |  |
| `nextTrick` | int | oui | `1` |  |  |
| `status` | tinyint(1) | oui | `1` |  |  |
| `operatorSens` | enum | oui | `in` |  | valeurs : in,out,neutral,both |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `structure_incrementors`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `si_title` | varchar(255) | oui |  |  |  |
| `si_model` | varchar(255) | oui |  |  |  |
| `si_template` | varchar(255) | oui |  |  |  |
| `si_nextTrick` | int | oui | `1` |  |  |
| `si_status` | tinyint(1) | oui | `1` |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `payments`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `payment_code` | varchar(255) | non |  | unique |  |
| `document_header_id` | bigint unsigned | oui |  | → document_headers.id |  |
| `amount` | decimal(15,2) | oui |  |  |  |
| `method` | enum | oui |  |  | valeurs : cash,bank_transfer,cheque,effet,credit,card |
| `paid_at` | date | oui |  |  |  |
| `reference` | varchar(255) | non |  |  |  |
| `user_id` | bigint unsigned | oui |  | → users.id |  |
| `notes` | text | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `payment_reminders`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `document_header_id` | bigint unsigned | oui |  |  |  |
| `third_partner_id` | bigint unsigned | non |  |  |  |
| `level` | tinyint unsigned | oui |  |  |  |
| `channel` | varchar(12) | oui | `whatsapp` |  |  |
| `message` | text | oui |  |  |  |
| `amount_due` | decimal(14,2) | oui |  |  |  |
| `days_overdue` | smallint unsigned | oui |  |  |  |
| `status` | varchar(12) | oui | `draft` |  |  |
| `error` | varchar(255) | non |  |  |  |
| `reason` | varchar(255) | non |  |  |  |
| `event_id` | bigint unsigned | non |  |  |  |
| `decided_by` | bigint unsigned | non |  |  |  |
| `sent_at` | timestamp | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `purchase_imports`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `external_id` | varchar(40) | oui |  | unique |  |
| `status` | varchar(20) | oui |  |  |  |
| `payload_hash` | char(64) | oui |  |  |  |
| `payload` | json | oui |  |  |  |
| `response` | json | oui |  |  |  |
| `document_id` | bigint unsigned | non |  |  |  |
| `document_reference` | varchar(40) | non |  |  |  |
| `user_id` | bigint unsigned | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `whatsapp_order_imports`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `external_id` | varchar(40) | oui |  | unique |  |
| `status` | varchar(20) | oui |  |  |  |
| `payload_hash` | char(64) | oui |  |  |  |
| `payload` | json | oui |  |  |  |
| `response` | json | oui |  |  |  |
| `document_id` | bigint unsigned | non |  |  |  |
| `document_reference` | varchar(40) | non |  |  |  |
| `user_id` | bigint unsigned | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `order_messages`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `channel` | varchar(20) | oui |  |  |  |
| `direction` | varchar(3) | oui |  |  |  |
| `phone` | varchar(30) | non |  |  |  |
| `third_partner_id` | bigint unsigned | non |  |  |  |
| `user_id` | bigint unsigned | non |  |  |  |
| `body` | text | oui |  |  |  |
| `provider_message_id` | varchar(64) | non |  | unique |  |
| `parse_method` | varchar(10) | non |  |  |  |
| `status` | varchar(20) | non |  |  |  |
| `document_id` | bigint unsigned | non |  |  |  |
| `reply_to_id` | bigint unsigned | non |  |  |  |
| `meta` | json | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

## Caisse (POS) et trésorerie

### `pos_terminals`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `name` | varchar(100) | oui |  |  |  |
| `code` | varchar(50) | oui |  | unique |  |
| `warehouse_id` | bigint unsigned | oui |  | → warehouses.id |  |
| `is_active` | tinyint(1) | oui | `1` |  |  |
| `printer_name` | varchar(150) | non |  |  |  |
| `auto_print` | tinyint(1) | oui | `1` |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `pos_sessions`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `pos_terminal_id` | bigint unsigned | oui |  | → pos_terminals.id |  |
| `user_id` | bigint unsigned | oui |  | → users.id |  |
| `opened_at` | timestamp | oui |  |  |  |
| `closed_at` | timestamp | non |  |  |  |
| `opening_cash` | decimal(12,2) | oui | `0.00` |  |  |
| `closing_cash` | decimal(12,2) | non |  |  |  |
| `expected_cash` | decimal(12,2) | non |  |  |  |
| `cash_difference` | decimal(12,2) | non |  |  |  |
| `validated_at` | timestamp | non |  |  |  |
| `validated_by` | bigint unsigned | non |  | → users.id |  |
| `variance_reason` | text | non |  |  |  |
| `variance_transaction_id` | bigint unsigned | non |  | → cash_transactions.id |  |
| `notes` | text | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `cash_accounts`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `ca_title` | varchar(255) | oui |  |  |  |
| `ca_code` | varchar(255) | non |  | unique |  |
| `ca_type` | enum | oui | `cash` |  | valeurs : cash,bank,cheque,other |
| `ca_payment_method` | varchar(255) | non |  | unique |  |
| `ca_initial_balance` | decimal(15,2) | oui | `0.00` |  |  |
| `ca_status` | tinyint(1) | oui | `1` |  |  |
| `ca_notes` | text | non |  |  |  |
| `structure_id` | bigint unsigned | non |  | → structure_incrementors.id |  |
| `deleted_at` | timestamp | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `cash_categories`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `cc_title` | varchar(255) | oui |  |  |  |
| `cc_code` | varchar(255) | non |  | unique |  |
| `cc_direction` | enum | oui | `out` |  | valeurs : in,out,both |
| `cc_color` | varchar(20) | non |  |  |  |
| `cc_status` | tinyint(1) | oui | `1` |  |  |
| `deleted_at` | timestamp | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `cash_transactions`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `ct_code` | varchar(255) | non |  | unique |  |
| `cash_account_id` | bigint unsigned | oui |  | → cash_accounts.id |  |
| `cash_category_id` | bigint unsigned | non |  | → cash_categories.id |  |
| `cash_recurrence_id` | bigint unsigned | non |  | → cash_recurrences.id |  |
| `ct_direction` | enum | oui |  |  | valeurs : in,out |
| `ct_amount` | decimal(15,2) | oui |  |  |  |
| `ct_date` | date | oui |  |  |  |
| `ct_label` | varchar(255) | oui |  |  |  |
| `ct_method` | varchar(255) | non |  |  |  |
| `ct_reference` | varchar(255) | non |  |  |  |
| `thirdPartner_id` | bigint unsigned | non |  | → third_partners.id |  |
| `document_header_id` | bigint unsigned | non |  | → document_headers.id |  |
| `ct_transfer_group` | char(36) | non |  |  |  |
| `ct_attachment_path` | varchar(255) | non |  |  |  |
| `ct_attachment_name` | varchar(255) | non |  |  |  |
| `ct_notes` | text | non |  |  |  |
| `ct_status` | enum | oui | `active` |  | valeurs : active,cancelled |
| `user_id` | bigint unsigned | non |  | → users.id |  |
| `deleted_at` | timestamp | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `cash_recurrences`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `cr_label` | varchar(255) | oui |  |  |  |
| `cr_direction` | enum | oui |  |  | valeurs : in,out |
| `cr_amount` | decimal(15,2) | oui |  |  |  |
| `cash_account_id` | bigint unsigned | oui |  | → cash_accounts.id |  |
| `cash_category_id` | bigint unsigned | non |  | → cash_categories.id |  |
| `thirdPartner_id` | bigint unsigned | non |  | → third_partners.id |  |
| `cr_method` | varchar(255) | non |  |  |  |
| `cr_frequency` | enum | oui | `monthly` |  | valeurs : weekly,monthly,quarterly,yearly |
| `cr_anchor_day` | tinyint unsigned | oui | `1` |  |  |
| `cr_start_date` | date | oui |  |  |  |
| `cr_end_date` | date | non |  |  |  |
| `cr_next_run_at` | date | oui |  |  |  |
| `cr_status` | tinyint(1) | oui | `1` |  |  |
| `cr_notes` | text | non |  |  |  |
| `deleted_at` | timestamp | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

## Boutique en ligne et clients

### `customer_chat_codes`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `phone` | varchar(30) | oui |  |  |  |
| `third_partner_id` | bigint unsigned | oui |  |  |  |
| `code_hash` | varchar(255) | oui |  |  |  |
| `attempts` | tinyint unsigned | oui | `0` |  |  |
| `expires_at` | timestamp | oui |  |  |  |
| `consumed_at` | timestamp | non |  |  |  |
| `ip` | varchar(45) | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `customer_chat_sessions`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `third_partner_id` | bigint unsigned | oui |  |  |  |
| `token_hash` | char(64) | oui |  | unique |  |
| `expires_at` | timestamp | oui |  |  |  |
| `last_used_at` | timestamp | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

## Utilisateurs, rôles, réglages

### `users`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `name` | varchar(255) | oui |  |  |  |
| `user_code` | varchar(255) | non |  | unique |  |
| `email` | varchar(255) | oui |  | unique |  |
| `phone` | varchar(30) | non |  |  |  |
| `email_verified_at` | timestamp | non |  |  |  |
| `password` | varchar(255) | oui |  |  |  |
| `remember_token` | varchar(100) | non |  |  |  |
| `dashboard_hidden_widgets` | json | non |  |  |  |
| `role_id` | bigint unsigned | oui |  | → roles.id |  |
| `is_active` | tinyint(1) | oui | `1` |  |  |
| `avatar` | varchar(255) | non |  |  |  |
| `structure_id` | bigint unsigned | non |  | → structure_incrementors.id |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |
| `deleted_at` | timestamp | non |  |  |  |

### `roles`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `name` | varchar(255) | oui |  | unique |  |
| `display_name` | varchar(255) | oui |  |  |  |
| `description` | varchar(255) | non |  |  |  |
| `is_system` | tinyint(1) | oui | `0` |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `permissions`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `name` | varchar(255) | oui |  | unique |  |
| `module` | varchar(255) | oui |  |  |  |
| `action` | varchar(255) | oui |  |  |  |
| `display_name` | varchar(255) | oui |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `role_permission`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `role_id` | bigint unsigned | oui |  | → roles.id |  |
| `permission_id` | bigint unsigned | oui |  | → permissions.id |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `personal_access_tokens`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `tokenable_type` | varchar(255) | oui |  |  |  |
| `tokenable_id` | bigint unsigned | oui |  |  |  |
| `name` | varchar(255) | oui |  |  |  |
| `token` | varchar(64) | oui |  | unique |  |
| `abilities` | text | non |  |  |  |
| `last_used_at` | timestamp | non |  |  |  |
| `expires_at` | timestamp | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `settings`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `st_domain` | varchar(255) | oui |  |  |  |
| `st_key` | varchar(255) | oui |  |  |  |
| `st_value` | text | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `notifications`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | char(36) | oui |  | clé primaire |  |
| `type` | varchar(255) | oui |  |  |  |
| `notifiable_type` | varchar(255) | oui |  |  |  |
| `notifiable_id` | bigint unsigned | oui |  |  |  |
| `data` | text | oui |  |  |  |
| `read_at` | timestamp | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `push_subscriptions`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `subscribable_type` | varchar(255) | oui |  |  |  |
| `subscribable_id` | bigint unsigned | oui |  |  |  |
| `endpoint` | varchar(500) | oui |  | unique |  |
| `public_key` | varchar(255) | non |  |  |  |
| `auth_token` | varchar(255) | non |  |  |  |
| `content_encoding` | varchar(255) | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `activity_log`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `log_name` | varchar(255) | non |  |  |  |
| `description` | text | oui |  |  |  |
| `subject_type` | varchar(255) | non |  |  |  |
| `event` | varchar(255) | non |  |  |  |
| `subject_id` | bigint unsigned | non |  |  |  |
| `causer_type` | varchar(255) | non |  |  |  |
| `causer_id` | bigint unsigned | non |  |  |  |
| `properties` | json | non |  |  |  |
| `batch_uuid` | char(36) | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

## Agents IA et orchestrateur

### `agents`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `domain` | varchar(30) | oui |  | unique |  |
| `name` | varchar(80) | oui |  |  |  |
| `kind` | varchar(12) | oui | `builtin` |  |  |
| `mission` | text | non |  |  |  |
| `scopes` | json | non |  |  |  |
| `created_by` | bigint unsigned | non |  |  |  |
| `user_id` | bigint unsigned | non |  |  |  |
| `ability` | varchar(60) | non |  |  |  |
| `default_level` | varchar(12) | oui | `approval` |  |  |
| `is_active` | tinyint(1) | oui | `1` |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `agent_events`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `type` | varchar(40) | non |  |  |  |
| `source` | varchar(20) | oui |  |  |  |
| `payload` | json | non |  |  |  |
| `entities` | json | non |  |  |  |
| `case_id` | bigint unsigned | non |  |  |  |
| `parent_event_id` | bigint unsigned | non |  |  |  |
| `order_message_id` | bigint unsigned | non |  |  |  |
| `agent_id` | bigint unsigned | non |  |  |  |
| `priority` | smallint unsigned | oui | `0` |  |  |
| `status` | varchar(12) | oui | `new` |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `agent_actions`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `agent_id` | bigint unsigned | oui |  |  |  |
| `event_id` | bigint unsigned | non |  |  |  |
| `case_id` | bigint unsigned | non |  |  |  |
| `action` | varchar(60) | oui |  |  |  |
| `level` | varchar(12) | oui |  |  |  |
| `input` | json | non |  |  |  |
| `result` | json | non |  |  |  |
| `document_id` | bigint unsigned | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |

### `agent_cases`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `third_partner_id` | bigint unsigned | non |  |  |  |
| `status` | varchar(12) | oui | `open` |  |  |
| `refs` | json | non |  |  |  |
| `opened_at` | timestamp | non |  |  |  |
| `closed_at` | timestamp | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `agent_approvals`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `action_id` | bigint unsigned | oui |  |  |  |
| `proposal` | json | oui |  |  |  |
| `decision` | varchar(12) | non |  |  |  |
| `modifications` | json | non |  |  |  |
| `decided_by` | bigint unsigned | non |  |  |  |
| `decided_at` | timestamp | non |  |  |  |
| `reason` | varchar(255) | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `agent_thresholds`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `agent_domain` | varchar(30) | oui |  |  |  |
| `action_type` | varchar(60) | oui |  |  |  |
| `parameter` | varchar(40) | oui |  |  |  |
| `value` | decimal(14,2) | non |  |  |  |
| `unit` | varchar(10) | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `agent_routing_rules`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `event_type` | varchar(40) | oui |  |  |  |
| `conditions` | json | non |  |  |  |
| `agent_domain` | varchar(30) | oui |  |  |  |
| `priority` | smallint unsigned | oui | `100` |  |  |
| `is_active` | tinyint(1) | oui | `1` |  |  |
| `phase` | tinyint unsigned | oui | `1` |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `agent_routines`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `name` | varchar(120) | oui |  |  |  |
| `steps` | json | oui |  |  |  |
| `schedule` | json | oui |  |  |  |
| `trigger` | json | non |  |  |  |
| `last_event_id` | bigint unsigned | non |  |  |  |
| `agent_id` | bigint unsigned | non |  |  |  |
| `is_active` | tinyint(1) | oui | `1` |  |  |
| `created_by` | bigint unsigned | oui |  |  |  |
| `last_run_at` | timestamp | non |  |  |  |
| `next_run_at` | timestamp | non |  |  |  |
| `last_status` | varchar(12) | non |  |  |  |
| `last_summary` | text | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `agent_directives`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `body` | varchar(400) | oui |  |  |  |
| `is_active` | tinyint(1) | oui | `1` |  |  |
| `created_by` | bigint unsigned | oui |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

### `orchestrator_messages`

| Colonne | Type | Obligatoire | Défaut | Clé / référence | Détail |
| --- | --- | --- | --- | --- | --- |
| `id` | bigint unsigned | oui |  | clé primaire | auto-incrément |
| `user_id` | bigint unsigned | oui |  |  |  |
| `role` | varchar(12) | oui |  |  |  |
| `body` | text | oui |  |  |  |
| `meta` | json | non |  |  |  |
| `created_at` | timestamp | non |  |  |  |
| `updated_at` | timestamp | non |  |  |  |

