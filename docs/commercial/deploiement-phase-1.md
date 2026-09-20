# Déploiement de la phase 1 — abonnement réel

> À lire en entier avant de déployer. Cette phase introduit un middleware qui
> **refuse les écritures** d'un tenant dont l'abonnement est échu. Mal déployée,
> elle coupe l'accès aux clients actuels.
>
> Rappel `CLAUDE.md` : rien n'est poussé ni déployé sans demande explicite.

---

## 1. Ce que la phase change en production

| Avant | Après |
|---|---|
| L'essai de 14 jours ne se terminait jamais | `subscriptions:check` fait basculer les statuts chaque matin à 7 h |
| `is_active` n'était lu nulle part | `EnsureTenantActive` l'applique à chaque requête tenant |
| La colonne `plan` ne décidait de rien | Les capacités sont dérivées de `config/plans.php` |
| `PackageService` répondait « basic » pour tout le monde | Supprimé ; l'import OCR suit désormais la formule |
| `/api/package-info` était **public** | Derrière `auth:sanctum` |
| Aucune trace des règlements | Table `tenant_payments` + bouton « Encaisser » |

---

## 2. Avant de pousser

```bash
php vendor/bin/phpunit
```

```bash
npm run build
```

Les deux doivent passer. Au 2026-09-20 : 626 tests verts (54 Unit + 572 Feature).

Vérifier aussi que `ADMIN_NOTIFICATION_EMAIL` est bien renseigné dans le `.env`
du serveur : c'est l'adresse qui reçoit les demandes de formule des clients.
Sans elle, `config('mail.admin_notification_to')` retombe sur `mail.from.address`.

---

## 3. Sur le VPS, dans cet ordre

### 3.1 Rafraîchir la config AVANT de migrer

> Corrigé après le déploiement du 2026-09-20, où l'ordre inverse a été suivi.

`bootstrap/cache/config.php` existe en production. Un fichier `config/*.php`
**neuf** — ici `config/plans.php` — y est donc invisible tant que `config:cache`
n'a pas tourné. Or `php artisan migrate` lit cette config cachée : lancée avant,
la migration de reprise s'exécute avec `config('plans.plans')` à `null`, et
enregistre des dérogations fausses. C'est exactement ce qui s'est produit le
2026-09-20 (corrigé ensuite à la main, voir §3.3).

```bash
cd /var/www/O3_app && sudo -u www-data php artisan config:cache
```

### 3.2 Migrations

```bash
cd /var/www/O3_app && sudo -u www-data php artisan migrate --force
```

Trois migrations s'exécutent :

1. `add_subscription_columns_to_tenants_table` — colonnes `status` et `subscription_ends_at`
2. `backfill_tenant_subscription_state` — **la reprise des tenants existants**
3. `create_tenant_payments_table` — table des règlements

### 3.3 Vérifier la reprise AVANT d'aller plus loin

C'est le point de non-retour : le middleware n'est pas encore actif tant que
les routes ne sont pas recachées. Contrôler que chaque tenant réel est ressorti
`active` avec une échéance dans le futur :

```bash
cd /var/www/O3_app && sudo -u www-data php artisan tinker --execute="App\Models\Tenant::all(['id','plan','status','subscription_ends_at','is_active'])->each(fn(\$t) => print(\$t->id.' | '.\$t->plan.' | '.\$t->status->value.' | '.\$t->subscription_ends_at.' | '.(\$t->is_active ? 'actif' : 'INACTIF').PHP_EOL));"
```

Attendu pour `demo`, `jadema`, `teliphoni` : `active`, échéance à un an, `actif`.
Si l'un d'eux sort autrement, **s'arrêter là** et corriger avant de cacher les
routes — à ce stade rien n'est encore appliqué.

Vérifier aussi que les modules ouverts à la main ont bien été figés en
dérogations :

```bash
cd /var/www/O3_app && sudo -u www-data php artisan tinker --execute="App\Models\Tenant::all()->each(fn(\$t) => print(\$t->id.' → '.json_encode(app(App\Services\PlanService::class)->overridesOf(\$t)).PHP_EOL));"
```

### 3.4 Simuler le cron avant de l'armer

```bash
cd /var/www/O3_app && sudo -u www-data php artisan subscriptions:check --dry-run
```

Aucune écriture, aucun email. La sortie annonce les bascules et les relances qui
partiraient. Si elle annonce une suspension sur un client réel, l'échéance de
l'étape 3.2 est mauvaise.

### 3.5 Opcache

```bash
cd /var/www/O3_app && systemctl reload php8.2-fpm
```

**Le cache de routes n'existe plus en production** (constaté le 2026-09-20 :
`bootstrap/cache/routes-v7.php` absent). Les routes s'appliquent donc dès le
`git pull`, et il ne faut pas créer ce cache sans raison. Vérifier plutôt que
supposer :

```bash
test -f /var/www/O3_app/bootstrap/cache/routes-v7.php && echo PRESENT || echo ABSENT
```

S'il est présent, `sudo -u www-data php artisan route:cache` redevient
obligatoire — sans lui la modification reste inerte, sans le moindre message.

### 3.6 Assets

Suivre la procédure `build-new` du guide VPS (`vps_access.md`) : un
`npm run build` direct supprime les assets pendant environ une minute.

### 3.7 Vérifier que le planificateur tourne

Le planificateur d'O3_app passe par **systemd**, pas par la crontab : la seule
ligne `schedule:run` de la crontab root concerne `worldcup2026-app`, ce qui prête
à confusion.

```bash
systemctl list-timers o3-scheduler.timer --no-pager && cd /var/www/O3_app && sudo -u www-data php artisan schedule:list | grep subscriptions
```

`config/app.php` étant en `UTC`, le créneau de 7 h tombe à **8 h heure
marocaine** — juste avant l'ouverture.

---

## 4. Vérifications après déploiement

1. Se connecter sur un tenant réel : la saisie fonctionne normalement, aucun
   bandeau ne s'affiche (abonnement actif à un an).
2. Ouvrir `/abonnement` sur ce tenant : les trois formules s'affichent aux prix
   du catalogue.
3. Au back-office central, ouvrir la fiche d'un tenant : la carte « Abonnement »
   affiche le statut et l'échéance.
4. Vérifier que `/api/package-info` sans jeton renvoie bien 401 — il répondait
   200 à tout le monde avant cette phase.

---

## 5. Revenir en arrière

Le risque n'est pas la base, c'est le middleware. Pour neutraliser le filtre
sans toucher aux données, retirer `'tenant.active'` des trois endroits où il est
posé (`routes/api.php` × 2, `routes/tenant.php`) puis :

```bash
cd /var/www/O3_app && sudo -u www-data php artisan route:cache
```

Les colonnes et la table des règlements peuvent rester en place : elles ne
gênent rien tant que le middleware ne les lit pas.

---

## 6. Points à décider avant la mise en service commerciale

1. **Échéances réelles** de `jadema` et `teliphoni` — la reprise leur a posé un
   an par sécurité, ce n'est pas une décision commerciale.
2. **Entité de facturation** — bloquant pour la phase 2, pas pour celle-ci.
3. **Annexe 1.C du contrat** (`docs/legal/contrat-services-saas.md`) : y reporter
   les tarifs de `config/plans.php`. Sans elle, le contrat n'est pas signable,
   quel que soit l'état du code.
