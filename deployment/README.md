# Deployment

This folder is everything the VPS needs to run / update O3_app.

```
deploy.sh             ← idempotent update script (run on every deploy)
nginx/                ← nginx site config
supervisor/           ← (legacy) supervisor configs — only used if supervisor is installed
reverb.service        ← systemd unit for Laravel Reverb
setup-reverb.sh       ← one-time Reverb bootstrap
o3-queue.service      ← systemd unit for the Laravel queue worker
setup-queue.sh        ← one-time queue-worker bootstrap
o3-scheduler.service  ← systemd unit for `php artisan schedule:run` (oneshot)
o3-scheduler.timer    ← timer firing the scheduler every minute
setup-scheduler.sh    ← one-time scheduler bootstrap
backup-db.sh          ← daily mysqldump + gzip + 14-day retention
o3-backup.service     ← systemd unit running backup-db.sh
o3-backup.timer       ← timer firing the backup at 03:30 daily
setup-backup.sh       ← one-time backup bootstrap (writes /root/.my.cnf)
```

## How a deploy fires

`.github/workflows/deploy.yml` watches the **Tests** workflow. When Tests
turns green on `main`, deploy.yml SSHes into the VPS and runs
`deployment/deploy.sh`. A manual `workflow_dispatch` is also available
for re-runs without a new commit (e.g. after editing `.env`).

```
push → Tests (phpunit + vue-tsc + vite build) → Deploy (ssh + deploy.sh)
```

## One-time setup (per VPS)

### 1. Create a deploy SSH key

On any local machine (do NOT commit the private key anywhere):

```bash
ssh-keygen -t ed25519 -C "github-deploy@o3app" -f deploy_key -N ""
```

You now have `deploy_key` (private) and `deploy_key.pub` (public).

### 2. Authorize the public key on the VPS

```bash
# On the VPS, as the user that will run deploy.sh
mkdir -p ~/.ssh && chmod 700 ~/.ssh
echo '<contents of deploy_key.pub>' >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

### 3. Add the secrets in GitHub

Repo → **Settings → Secrets and variables → Actions → New repository secret**:

| Name          | Value                                                |
|---------------|------------------------------------------------------|
| `VPS_HOST`    | `167.99.xxx.xxx` (or `teliphoni.o3app.ma`)           |
| `VPS_USER`    | the user that owns `/var/www/O3_app` on the VPS      |
| `VPS_SSH_KEY` | full contents of the **private** `deploy_key` file   |
| `VPS_PORT`    | optional — only set if SSH isn't on 22               |

### 4. Make sure the VPS user can run deploy.sh

`deploy.sh` calls `sudo` for nginx + supervisor + chown. Either:

- run as `root`, or
- give the deploy user passwordless sudo for those specific commands:

```
# /etc/sudoers.d/o3app-deploy  (use `visudo -f` to edit)
deploy ALL=(ALL) NOPASSWD: /bin/cp, /bin/ln, /bin/rm, /usr/bin/chown, /usr/bin/chmod, /usr/sbin/nginx, /bin/systemctl reload nginx, /usr/bin/supervisorctl
```

## Queue worker (one-time)

The Laravel queue worker runs as a systemd service (`o3-queue`). Bootstrap once per VPS:

```bash
sudo bash deployment/setup-queue.sh
```

Useful afterwards:

```bash
systemctl status o3-queue          # one-shot status
journalctl -u o3-queue -f          # tail logs
php artisan queue:restart          # graceful reload (also called by deploy.sh)
```

The unit uses `--max-time=3600` so the worker self-exits every hour and
systemd restarts it — sidesteps memory leaks and makes `queue:restart`
take effect within at most one loop window.

## Scheduler / cron (one-time)

Laravel's `app/Console/Kernel.php` registers daily tasks (low-stock notifications,
due-invoice reminders, periodic invoice generation). They only fire if a system
process triggers `php artisan schedule:run` every minute.

Instead of a crontab, we use a systemd timer (`o3-scheduler.timer`) that fires the
oneshot service `o3-scheduler.service` once a minute. Bootstrap once per VPS:

```bash
sudo bash deployment/setup-scheduler.sh
```

Useful afterwards:

```bash
systemctl status o3-scheduler.timer  # state + next firing
journalctl -u o3-scheduler -f        # tail every minute's run
php artisan schedule:list            # show registered tasks
```

## Backups DB (one-time)

Daily MySQL backups go to `/var/backups/o3_app/YYYY-MM-DD/`, one gzipped
`.sql.gz` per database (central + every tenant DB, autodiscovered).
Retention: 14 days local. Bootstrap once per VPS:

```bash
sudo bash deployment/setup-backup.sh
```

This reads DB creds from `.env` and writes `/root/.my.cnf` (mode 600) so
`mysqldump` authenticates without leaking credentials in process lists.

Useful afterwards:

```bash
systemctl status o3-backup.timer       # timer state + next run
journalctl -u o3-backup -f             # tail backup runs
systemctl start o3-backup              # run a backup right now (manual trigger)
bash deployment/backup-db.sh --dry-run # see what would be dumped
ls -la /var/backups/o3_app/            # list dated folders
```

**Restore one database** (example: tenant `teliphoni` from yesterday):

```bash
zcat /var/backups/o3_app/2026-04-25/tenantteliphoni-*.sql.gz \
  | mysql tenantteliphoni
```

**Off-site copy** (recommended): rsync the `/var/backups/o3_app/` tree to
another server / S3 / Backblaze B2 nightly. Not configured yet — local-only
backups die with the VPS.

## Manual deploy (unchanged)

If you ever need to bypass GitHub:

```bash
ssh <user>@<vps>
cd /var/www/O3_app
bash deployment/deploy.sh
```

## What deploy.sh checks before running

- Aborts if `.env` has `APP_DEBUG=true` (would expose Ignition stack
  traces in prod).
- Aborts if `.env` has `APP_ENV=local`.
- Warns if `SANCTUM_STATEFUL_DOMAINS` is missing (defaults include
  `localhost`).

These guards were added in the security audit pass. See `.claude/`
session history if you need the why.
