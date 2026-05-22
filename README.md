# Yiimp — Multi-Algorithm Mining Pool

A fork of the original Yaamp project. Supports a large number of PoW mining algorithms, auto-exchange payouts, and an optional hash-power renting system.

> **Security notice**: the default configuration is intended for private/LAN use.  
> Before exposing to the public internet add firewall rules, restrict admin access, and review all settings in `serverconfig.php`.

---

## Architecture

| Component | Path | Technology |
|-----------|------|-----------|
| Stratum server | `stratum/` | C++ — handles miner connections per algo |
| Legacy web app | `web/` | Yii 1.1 / PHP 8 — pool logic, payouts, markets |
| Modern web app | `yiimp2/` | Yii 2.0 / PHP 8 — in-progress migration target |
| Background jobs | `yiimp2/jobs/` | yii2-queue delayed jobs (replaces shell loops) |
| Config templates | `config/` | Supervisor, Apache vhosts, HAProxy |

The pool currently runs both applications side by side.  
`web/` handles all production logic; `yiimp2/` is the migration target and already serves the admin panel, stats, and API on port 8090.

---

## Requirements

- Linux host (Ubuntu 24.04 recommended)
- MySQL / MariaDB (external to the container)
- Docker **or** Podman
- A `config/` directory with your customised config files (see below)

---

## Build

```bash
# Production image
make build
# or directly:
podman build --tag yiimp --target image-prod -f Dockerfile.yiimp

# Development image (includes Xdebug)
make build-devel
```

---

## First-time database setup

```sql
-- Run as MySQL root
CREATE DATABASE yaamp CHARACTER SET utf8mb4;

-- Web/PHP user
CREATE USER 'yiimp_web'@'localhost' IDENTIFIED BY 'choose-a-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON yaamp.* TO 'yiimp_web'@'localhost';

-- Stratum user (needs write access for shares)
CREATE USER 'yiimp_stratum'@'localhost' IDENTIFIED BY 'choose-a-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON yaamp.* TO 'yiimp_stratum'@'localhost';

FLUSH PRIVILEGES;
```

Import the base schema from `sql/2024-03-06-complete_export.sql.gz`, then apply all migration scripts in date order:

```bash
zcat sql/2024-03-06-complete_export.sql.gz | mysql -u root -p yaamp
for f in sql/2024-*.sql sql/2025-*.sql sql/2026-*.sql; do
    mysql -u root -p yaamp < "$f"
done
```

### yii2-queue table (required for the Yii2 background job system)

Apply the dedicated migration file with a privileged account before starting the container:

```bash
mysql -u root -p yaamp < sql/2026-05-22-add_queue_table.sql
```

---

## Configuration

### 1. Create your config directory

```bash
mkdir -p ./config ./log
```

Copy and edit the templates:

```bash
cp web/serverconfig.sample.php config/serverconfig.php
cp config/supervisord.conf config/supervisord.conf   # already a template
```

### 2. `config/serverconfig.php` — key settings

All constants use the `YIIMP_` prefix. The `web/` (Yii 1.1) legacy code used the old `YAAMP_` prefix; backward-compatible shims are automatically defined at the bottom of the file — do not add separate `YAAMP_` defines.

```php
// Database
define('YIIMP_DBHOST',     'host-ip-or-hostname');
define('YIIMP_DBNAME',     'yaamp');
define('YIIMP_DBUSER',     'yiimp_web');
define('YIIMP_DBPASSWORD', 'your-password');

// Site
define('YIIMP_SITE_URL',  'pool.example.com');
define('YIIMP_SITE_NAME', 'My Pool');

// Admin
define('YIIMP_ADMIN_EMAIL', 'admin@example.com');
define('YIIMP_ADMIN_USER',  'admin');
define('YIIMP_ADMIN_PASS',  'strong-password');
define('YIIMP_ADMIN_IP',    '');   // restrict to IP(s) if desired

// Payouts
define('YIIMP_PAYMENTS_FREQ', 3*60*60);   // every 3 h
define('YIIMP_PAYMENTS_MINI', 0.001);     // minimum payout amount

// Pool fees (%)
define('YIIMP_FEES_MINING',   0.5);
define('YIIMP_FEES_SOLO',     1.0);
define('YIIMP_FEES_EXCHANGE', 2.0);

// Features
define('YIIMP_RENTAL',         false);
define('YIIMP_ALLOW_EXCHANGE', false);
define('YIIMP_PRODUCTION',     true);

// NiceHash v2 (optional — pool buys hash power from NiceHash)
// define('YIIMP_USE_NICEHASH_API', true);
// define('NICEHASH_API_KEY',       'uuid-key');       // API key UUID
// define('NICEHASH_API_SECRET',    'hmac-secret');    // signing secret
// define('NICEHASH_ORG_ID',        'uuid-org');       // organisation UUID
```

Full reference: `web/serverconfig.sample.php`.

### 3. `config/supervisord.conf`

The container uses supervisord to manage all pool processes.  
The file in `config/supervisord.conf` is the authoritative template — copy it, then:

- **Add stratum processes** for each algorithm you want to mine (copy the `[program:stratum-scrypt]` block, rename, and point to the correct `.conf` file)
- The three background job queue workers (`yiimp-queue-seed`, `yiimp-queue-blocks`, `yiimp-queue-general`) are pre-configured and replace the old `main.sh` / `loop2.sh` / `blocks.sh` shell loops

Example stratum entry:

```ini
[program:stratum-x11]
command=stratum /etc/yiimp/stratum/x11.conf
autostart=true
autorestart=true
user=root
```

Stratum config files go in `config/stratum/`. Use `config/stratum/scrypt.conf` as a template.

---

## Run

```bash
make run
```

or directly:

```bash
podman run -dt --name=yiimp --network=host \
  -v ./config:/etc/yiimp \
  -v ./config/supervisord.conf:/etc/supervisor/conf.d/supervisord.conf \
  -v ./log:/var/log/apache2 \
  -v ./log:/var/log/yiimp \
  yiimp
```

On first start, `yiimp-queue-seed` runs once and pushes all 22 recurring background jobs into the queue. Subsequent restarts are idempotent (existing jobs are not duplicated).

### Development mode

```bash
make run-devel
```

This mounts the local source directories into the container so changes take effect immediately without a rebuild.

---

## Supervisor management

The supervisor web UI is available at **http://localhost:8900** (credentials: `yiimp` / `supervisor` by default — change in `supervisord.conf`).

From the command line inside the container:

```bash
supervisorctl -u yiimp -p supervisor -s http://127.0.0.1:8900 status
supervisorctl -u yiimp -p supervisor -s http://127.0.0.1:8900 start stratum-x11
supervisorctl -u yiimp -p supervisor -s http://127.0.0.1:8900 stop  stratum-x11
```

Or from the host if the container uses `--network=host`:

```bash
supervisorctl -u yiimp -p supervisor -s http://127.0.0.1:8900 status
```

---

## Background jobs (yii2-queue)

The legacy `main.sh` / `loop2.sh` / `blocks.sh` shell loops have been replaced by **yii2-queue delayed jobs**.  
Three supervisor processes manage them:

| Process | Workers | Purpose |
|---------|---------|---------|
| `yiimp-queue-seed` | 1 (run-once) | Pushes all 22 jobs into the queue on startup |
| `yiimp-queue-blocks` | 1 (persistent) | Block pipeline — 1s poll, time-critical |
| `yiimp-queue-general` | 2 (persistent) | All other jobs — coins, stats, payouts, markets |

The admin **Jobs dashboard** at `/admin/jobs` shows live status for all 22 jobs and provides pause / resume / run-now controls.

---

## Web interfaces

| URL | Description |
|-----|-------------|
| `http://host:8080/` | Legacy Yii1 pool frontend |
| `http://host:8090/` | Yii2 modern frontend (admin, stats, API docs) |
| `http://host:8900/` | Supervisord web UI |

**Admin login**: navigate to `/admin` and enter the credentials from `YIIMP_ADMIN_USER` / `YIIMP_ADMIN_PASS` in `serverconfig.php`.

**API documentation**: `/site/api` — interactive Swagger UI documenting all public REST endpoints (`/api/wallet`, `/api/status`, `/api/currencies`, etc.).

---

## SSL / TLS (optional)

The container includes HAProxy and Certbot (Let's Encrypt).

```bash
# Initial certificate issuance
make run-init-letsencrypt MAILADDRESS=admin@example.com DOMAINNAME=pool.example.com
```

After a certificate is issued, enable HAProxy in `supervisord.conf` by setting `autostart=true` on the `[program:haproxy]` block and restart the container.

HAProxy listens on port 443 for HTTPS and can also terminate TLS for stratum connections (port 25+ for SSL stratum, configurable in the HAProxy config).

---

## Stratum configuration

Each algorithm needs its own `.conf` file in `config/stratum/`. Minimal example (`config/stratum/x11.conf`):

```ini
[server]
stratumhost = pool.example.com
stratumport = 3533
workername  = x11
rpcpasswd   = stratumpassword   # must match YAAMP_STRATUM_PASSWD in serverconfig.php

[mysql]
dbhost     = 127.0.0.1
dbport     = 3306
dbname     = yaamp
dbuser     = yiimp_stratum
dbpasswd   = your-stratum-db-password
```

Coin daemons should be configured to send block notifications to the stratum:

```
blocknotify=blocknotify pool.example.com:port coinid %s
```

---

## Database migrations

New algorithm and schema changes are shipped as dated SQL files in `sql/`. Apply them in order after pulling:

```bash
mysql -u root -p yaamp < sql/2026-03-29-add_algo_hoohash_pepew.sql
```

Notable migration files:

| File | Purpose |
|------|---------|
| `sql/2024-03-06-complete_export.sql.gz` | Base schema (import first) |
| `sql/2026-05-22-add_queue_table.sql` | yii2-queue table for Yii2 background jobs |
| `sql/2026-03-29-add_algo_hoohash_pepew.sql` | Latest algorithm addition (most recent) |

---

## Project layout

```
stratum/        C++ stratum server source
web/            Yii 1.1 pool application (production)
yiimp2/         Yii 2.0 migration (admin, API, jobs)
  commands/     Console commands (queue management, etc.)
  components/   RPC clients (Bitcoin, Ethereum, Monero), utilities
  controllers/  Web controllers
  jobs/         yii2-queue job classes (22 jobs across 7 domains)
  models/       ActiveRecord models
  services/     Backend service classes (payments, coins, stats, …)
  views/        Blade/PHP templates
config/         Container config templates (supervisor, Apache, HAProxy)
sql/            Database schema and incremental migration files
bin/            CLI utilities (blocknotify, letsencrypt helpers)
docs/           Technical planning documents
```

---

## Credits

Original Yaamp by globalzon.  
Forked and maintained by [tpfuemp](https://github.com/tpfuemp).

Donations welcome:

```
DOGE : DNQdyeLu9DtRfsZCFvy1GfJTwjWJoSWHLh
```
