# Email Setup — O3 App on DigitalOcean

End-to-end reference for the SMTP stack as it runs in production.
Written 2026-05-04 after a 4-hour debugging session — every gotcha
documented here is one we hit and that cost real time.

## TL;DR

| Layer | Setting | Value |
|---|---|---|
| Provider | Vendor | **Brevo** (free tier ≤ 300/day) |
| Network | Port | **2525** (DO blocks 25/465/587 outbound) |
| Encryption | Method | STARTTLS |
| DNS | Records on `o3app.ma` | DKIM × 2 + SPF + DMARC + Brevo Code |
| Sender | Address | `noreply@o3app.ma` (domain-authenticated) |
| Whitelist | Brevo IP allowlist | VPS IP `46.101.179.185` must be added |

## Why Brevo and not X

We evaluated and rejected:

- **Gmail SMTP** — 500/day soft cap, deliverability dies at scale, ties
  the SaaS to a single Google account.
- **Self-hosted Postfix on the Droplet** — DO blocks all SMTP outbound
  (25, 465, 587 all timed out in our test); even unblocked, the IP is
  on Spamhaus PBL, deliverability is unfixable without a paid clean IP.
- **Resend / Postmark / SendGrid** — all viable, but Brevo gives us a
  better free tier (300/day vs 100/day) and a French-speaking support
  that matters for a Maroc-targeted SaaS.
- **Amazon SES** — cheapest at scale but heavier setup (sandbox exit
  request, identity verification per region). Worth it >50k emails/mo,
  not before.

Migration path when Brevo's 300/day stops being enough: switch to SES
or Brevo paid (~10 €/mo for 20k). Both are drop-in replacements via
`MAIL_*` env vars.

## DNS records on `o3app.ma` (managed at GeniousDNS)

DNS is hosted at GeniousDNS (the registrar). To send via Brevo we
added 4 records in addition to the existing wildcard `*` and `@` A
records:

| # | Type | Name | Value |
|---|---|---|---|
| 1 | TXT (`SPF (texte)` in panel) | `@` | `brevo-code:ce77ac1d73f2966404ea687662f77a4c` |
| 2 | CNAME | `brevo1._domainkey` | `b1.o3app-ma.dkim.brevo.com` |
| 3 | CNAME | `brevo2._domainkey` | `b2.o3app-ma.dkim.brevo.com` |
| 4 | TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:rua@dmarc.brevo.com` |
| 5 | TXT | `@` | `v=spf1 include:spf.brevo.com ~all` |

GeniousDNS labels TXT records as `SPF (texte)` for legacy reasons —
the type still creates a generic TXT row.

Verification from the VPS:

```bash
dig +short TXT o3app.ma @8.8.8.8
dig +short TXT _dmarc.o3app.ma @8.8.8.8
dig +short CNAME brevo1._domainkey.o3app.ma @8.8.8.8
dig +short CNAME brevo2._domainkey.o3app.ma @8.8.8.8
```

All four should return non-empty.

## Brevo configuration

1. Account → SMTP & API → SMTP keys → generate one named e.g. `prod`.
   The key value is shown **once** at creation. Copy it immediately
   into the password manager — Brevo only ever shows `********chVl9b`
   afterwards.
2. **Senders, domains, IPs** → Domains → Add `o3app.ma` → click
   "Authenticate this email domain" once DNS records propagated.
3. Senders → Add `noreply@o3app.ma` (auto-verified once domain is).
4. **SMTP & API → Authorized IPs** → add `46.101.179.185` (the VPS
   public IP) with description "O3 App VPS Frankfurt". Without this,
   Brevo returns `525 5.7.1 Unauthorized IP address` even with valid
   credentials.

## `.env` on the VPS (`/var/www/O3_app/.env`)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=2525
MAIL_USERNAME=aa13bc001@smtp-brevo.com
MAIL_PASSWORD=xsmtpsib-…              # the SMTP key from step 1
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@o3app.ma
MAIL_FROM_NAME="O3 App"
ADMIN_NOTIFICATION_EMAIL=dev.vurly@gmail.com
```

After editing `.env` you **must** rebuild the config cache as the
`www-data` user:

```bash
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan config:cache
```

`deploy.sh` does this automatically on every deploy.

## Three pitfalls we hit (and you can avoid)

### Pitfall #1 — `php artisan config:cache` as `root` fails silently

`bootstrap/cache/` is owned by `www-data:www-data`. When `root` runs
`config:cache`, the file is **not** written (no error in the artisan
output) and Laravel falls back to defaults from `config/mail.php`,
which include `smtp.mailgun.org` — looks correct in code, totally
broken in prod.

**Always run cache commands as `www-data`:**

```bash
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan cache:clear
```

`deploy.sh` was patched on 2026-05-04 to do this.

### Pitfall #2 — `DynamicConfigServiceProvider` overrides `.env`

`app/Providers/DynamicConfigServiceProvider.php` calls
`DynamicMailService::applySettings()` at boot. That service reads the
`settings` table (rows with `st_domain='email'`) and **overrides** the
`config('mail.mailers.smtp.*')` values that came from `.env`.

If the DB rows hold legacy values (e.g. `mail_host=smtp.gmail.com`
seeded on 2026-03-23), Laravel ignores `.env` even if `.env` is
correct. Symptom: SMTP error talks about `smtp.gmail.com:465`
when your `.env` clearly says Brevo.

**Fix:** keep the DB rows aligned with `.env`. Migration
`2026_05_04_000001_normalize_email_settings_to_brevo.php` does this
once, and the seeder migration was patched to write Brevo defaults
for new tenants.

To inspect the live override:

```bash
mysql o3_app -e "SELECT st_key, st_value FROM settings WHERE st_domain='email'"
mysql tenantteliphoni -e "SELECT st_key, st_value FROM settings WHERE st_domain='email'"
```

### Pitfall #3 — `env()` returns `null` after `config:cache`

Laravel intentionally disables runtime `env()` lookups once the config
is cached, to force you to read from `config()` only. Code like
`env('ADMIN_NOTIFICATION_EMAIL')` inside a controller silently
returns `null` in production.

**Fix:** declare an explicit entry in `config/mail.php`:

```php
'admin_notification_to' => env('ADMIN_NOTIFICATION_EMAIL'),
```

Then read it from the controller via `config('mail.admin_notification_to')`.
The `env()` call is evaluated **once at boot** (when config is built)
and the value is then cached.

`PublicRegistrationController` was patched on 2026-05-04 to do this
for the new-tenant signup notification.

## Onboarding a brand new tenant

Out of the box, a new tenant created via `/central/tenants/create`
or `/register` inherits the Brevo SMTP config from the central `.env`.
No per-tenant action needed.

If a tenant wants their **own** SMTP provider (e.g. their corporate
Microsoft 365), they fill `/settings/app` → Email tab. The values they
enter end up in their `settings` table, and `DynamicConfigServiceProvider`
applies them on top of the inherited config at request time.

## Sanity check after any deploy

```bash
ssh root@o3app.ma 'cd /var/www/O3_app && sudo -u www-data php -r "
require \"vendor/autoload.php\";
\$app = require \"bootstrap/app.php\";
\$app->make(\"Illuminate\\Contracts\\Console\\Kernel\")->bootstrap();
echo \"host: \" . config(\"mail.mailers.smtp.host\") . PHP_EOL;
echo \"port: \" . config(\"mail.mailers.smtp.port\") . PHP_EOL;
echo \"enc:  \" . config(\"mail.mailers.smtp.encryption\") . PHP_EOL;
echo \"from: \" . config(\"mail.from.address\") . PHP_EOL;
echo \"admin notif: \" . config(\"mail.admin_notification_to\") . PHP_EOL;
"'
```

Expected output:

```
host: smtp-relay.brevo.com
port: 2525
enc:  tls
from: noreply@o3app.ma
admin notif: dev.vurly@gmail.com
```

If `host` is `smtp.gmail.com` or `smtp.mailgun.org` — pitfall #1 or #2,
re-read above.
