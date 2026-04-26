# Deployment

This folder is everything the VPS needs to run / update O3_app.

```
deploy.sh             ← idempotent update script (run on every deploy)
nginx/                ← nginx site config
supervisor/           ← queue worker + reverb supervisor configs
reverb.service        ← systemd unit for Laravel Reverb
setup-reverb.sh       ← one-time Reverb bootstrap
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
