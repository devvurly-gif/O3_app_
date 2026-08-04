# VPS Access Guide

**⚠️ IMPORTANT: Keep actual credentials secure - never commit to git**

## VPS Connection Details

### Production Server
- **Hostname:** O3app-Ubuntu
- **IP Address:** `46.101.179.165`
- **Domain:** `teliphoni.o3app.ma`
- **SSH User:** `root`
- **SSH Key:** `~/.ssh/id_ed25519` (stored locally, NOT committed)

### Alternative VPS IPs
- `167.172.123.88`
- `161.35.19.66`
- `62.84.179.247`

### Provider
- **Cloud Provider:** DigitalOcean
- **OS:** Ubuntu

## SSH Access

### Quick Connect
```bash
# Using IP
ssh -i ~/.ssh/id_ed25519 root@46.101.179.165

# Using domain
ssh -i ~/.ssh/id_ed25519 root@teliphoni.o3app.ma

# Using SSH config alias (if set up)
ssh o3app
```

### SSH Config Setup
Create/edit `~/.ssh/config`:
```
Host o3app
    HostName 46.101.179.165
    User root
    IdentityFile ~/.ssh/id_ed25519
    Port 22
```

## Deployment

### Laravel App Location
```
/var/www/o3app
```

### Common Commands
```bash
# Check app status
ssh o3app "cd /var/www/o3app && php artisan status"

# Run migrations
ssh o3app "cd /var/www/o3app && php artisan migrate"

# View logs
ssh o3app "tail -f /var/www/o3app/storage/logs/laravel.log"

# Restart services
ssh o3app "systemctl restart php-fpm && systemctl restart nginx"
```

## Security Notes
- ✅ SSH private key stored in `~/.ssh/id_ed25519`
- ✅ Private key NOT committed to git
- ✅ Public key (`id_ed25519.pub`) safely on VPS
- ❌ Never store passwords in version control
- ❌ Use `.env` files with `.gitignore` for sensitive data
- ❌ Use environment variables for deployment secrets

## Database Access (if applicable)
- Use SSH tunnel for secure database access
- Never expose database credentials in code

## Backup & Recovery
- SSH keys backed up securely (external storage recommended)
- VPS snapshots available via DigitalOcean dashboard

---
Last updated: 2026-06-06
