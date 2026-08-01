#!/usr/bin/env bash
# Authzio VPS deploy — Ubuntu 22.04/24.04 (Debian-like).
# Does NOT install or configure PostgreSQL / Redis (point .env at existing services).
#
# First-time (as root):
#   sudo ./deploy.sh --bootstrap --domain=auth.example.com --repo=https://github.com/azodik/authzio.git
#
# Later deploys:
#   sudo ./deploy.sh
#   sudo ./deploy.sh --seed          # also run db:seed
#   sudo ./deploy.sh --skip-build    # skip npm (PHP-only change)
#
set -euo pipefail

# ── defaults (override via flags or env) ──────────────────────────────────────
APP_NAME="${APP_NAME:-authzio}"
APP_USER="${APP_USER:-authzio}"
APP_GROUP="${APP_GROUP:-www-data}"
APP_ROOT="${APP_ROOT:-/var/www/authzio}"
REPO_URL="${REPO_URL:-https://github.com/azodik/authzio.git}"
GIT_BRANCH="${GIT_BRANCH:-main}"
DOMAIN="${DOMAIN:-_}"
PHP_VERSION="${PHP_VERSION:-8.5}"
QUEUE_WORKERS="${QUEUE_WORKERS:-2}"
NODE_MAJOR="${NODE_MAJOR:-24}"
PHP_BIN=""
SSL_EMAIL="${SSL_EMAIL:-}"
ENABLE_SSL=true
PRIMARY_DOMAIN=""
WWW_DOMAIN=""

DO_BOOTSTRAP=false
DO_SEED=false
SKIP_BUILD=false
SKIP_MIGRATE=false

log()  { printf '\n\033[1;36m==>\033[0m %s\n' "$*"; }
ok()   { printf '\033[1;32m✓\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m!\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31m✗\033[0m %s\n' "$*" >&2; exit 1; }

resolve_php_bin() {
  if [[ -x "/usr/bin/php${PHP_VERSION}" ]]; then
    PHP_BIN="/usr/bin/php${PHP_VERSION}"
  elif command -v php >/dev/null 2>&1; then
    PHP_BIN="$(command -v php)"
    warn "Using ${PHP_BIN} (php${PHP_VERSION} binary not found)"
  else
    die "PHP binary not found"
  fi
  ok "PHP binary: ${PHP_BIN} ($("${PHP_BIN}" -r 'echo PHP_VERSION;'))"
}

resolve_domains() {
  if [[ "${DOMAIN}" == "_" || -z "${DOMAIN}" ]]; then
    PRIMARY_DOMAIN="_"
    WWW_DOMAIN="www.invalid"
    ENABLE_SSL=false
    warn "No --domain set; SSL skipped and server_name=_ (set --domain=example.com)"
    return
  fi

  local host="${DOMAIN}"
  host="${host#https://}"
  host="${host#http://}"
  host="${host%%/*}"
  host="${host%.}"
  host="${host#www.}"

  PRIMARY_DOMAIN="${host}"
  WWW_DOMAIN="www.${host}"
  ok "Canonical https://${PRIMARY_DOMAIN}  (www → apex redirect)"
}

usage() {
  cat <<EOF
Usage: sudo $0 [options]

Options:
  --bootstrap         Install packages, create ${APP_USER}, clone repo (first run)
  --domain=HOST       Public hostname (www + apex both served; www → apex)
  --email=ADDR        Let's Encrypt contact email (required for --ssl)
  --ssl               Issue/renew HTTPS certs (default when --domain is set)
  --no-ssl            Skip certificate issuance
  --repo=URL          Git remote (default: ${REPO_URL})
  --branch=NAME       Git branch (default: ${GIT_BRANCH})
  --root=PATH         App directory (default: ${APP_ROOT})
  --user=NAME         App system user (default: ${APP_USER})
  --php=VERSION       PHP major.minor (default: ${PHP_VERSION})
  --workers=N         Queue worker processes (default: ${QUEUE_WORKERS})
  --seed              Run database seeders after migrate
  --skip-build        Skip npm ci / npm run build
  --skip-migrate      Skip php artisan migrate
  -h, --help          Show this help

Postgres and Redis are NOT installed. Configure DB_* / REDIS_* / QUEUE_CONNECTION in .env.
EOF
}

for arg in "$@"; do
  case "$arg" in
    --bootstrap) DO_BOOTSTRAP=true ;;
    --seed) DO_SEED=true ;;
    --skip-build) SKIP_BUILD=true ;;
    --skip-migrate) SKIP_MIGRATE=true ;;
    --ssl) ENABLE_SSL=true ;;
    --no-ssl) ENABLE_SSL=false ;;
    --domain=*) DOMAIN="${arg#*=}" ;;
    --email=*) SSL_EMAIL="${arg#*=}" ;;
    --repo=*) REPO_URL="${arg#*=}" ;;
    --branch=*) GIT_BRANCH="${arg#*=}" ;;
    --root=*) APP_ROOT="${arg#*=}" ;;
    --user=*) APP_USER="${arg#*=}" ;;
    --php=*) PHP_VERSION="${arg#*=}" ;;
    --workers=*) QUEUE_WORKERS="${arg#*=}" ;;
    -h|--help) usage; exit 0 ;;
    *) die "Unknown option: $arg (try --help)" ;;
  esac
done

[[ "$(id -u)" -eq 0 ]] || die "Run as root (sudo)."

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Prefer configs shipped with the repo when already cloned; otherwise after clone.
DEPLOY_DIR="${SCRIPT_DIR}/deploy"
if [[ ! -d "${DEPLOY_DIR}" && -d "${APP_ROOT}/deploy" ]]; then
  DEPLOY_DIR="${APP_ROOT}/deploy"
fi

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "Missing command: $1"
}

detect_os() {
  [[ -f /etc/os-release ]] || die "Unsupported OS (need Debian/Ubuntu)."
  # shellcheck disable=SC1091
  . /etc/os-release
  case "${ID:-}" in
    ubuntu|debian) ok "OS: ${PRETTY_NAME}" ;;
    *) die "This script targets Ubuntu/Debian (got: ${PRETTY_NAME:-unknown})" ;;
  esac
}

ensure_user() {
  log "Ensuring system user ${APP_USER}"
  if ! id -u "${APP_USER}" >/dev/null 2>&1; then
    useradd --system --create-home --shell /usr/sbin/nologin \
      --home-dir "/home/${APP_USER}" "${APP_USER}"
    ok "Created user ${APP_USER} (no login shell, non-sudo)"
  else
    ok "User ${APP_USER} already exists"
  fi
  usermod -aG "${APP_GROUP}" "${APP_USER}" 2>/dev/null || true
}

install_packages() {
  log "Installing production packages (PHP ${PHP_VERSION}, nginx, supervisor, node ${NODE_MAJOR})"
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y --no-install-recommends \
    ca-certificates curl gnupg git unzip zip openssl \
    nginx supervisor certbot \
    software-properties-common apt-transport-https

  # PHP from Ondřej Surý PPA (Ubuntu) / packages.sury.org (Debian)
  if [[ "${ID}" == "ubuntu" ]]; then
    if ! apt-cache show "php${PHP_VERSION}-fpm" >/dev/null 2>&1; then
      add-apt-repository -y ppa:ondrej/php
      apt-get update -y
    fi
  elif [[ "${ID}" == "debian" ]]; then
    if ! apt-cache show "php${PHP_VERSION}-fpm" >/dev/null 2>&1; then
      curl -fsSL https://packages.sury.org/php/apt.gpg \
        | gpg --dearmor -o /usr/share/keyrings/deb.sury.org-php.gpg
      echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ ${VERSION_CODENAME} main" \
        > /etc/apt/sources.list.d/php.list
      apt-get update -y
    fi
  fi

  # tokenizer/json ship inside php-common — do not apt-install phpX.Y-tokenizer (no package).
  apt-get install -y --no-install-recommends \
    "php${PHP_VERSION}-cli" \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-pgsql" \
    "php${PHP_VERSION}-redis" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-intl" \
    "php${PHP_VERSION}-gd" \
    "php${PHP_VERSION}-opcache" \
    "php${PHP_VERSION}-readline"

  # Composer
  if ! command -v composer >/dev/null 2>&1; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
  fi
  ok "Composer: $(composer --version 2>/dev/null | head -1)"

  # Node.js (NodeSource)
  if ! command -v node >/dev/null 2>&1 || [[ "$(node -p "process.versions.node.split('.')[0]")" -lt "${NODE_MAJOR}" ]]; then
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
    apt-get install -y --no-install-recommends nodejs
  fi
  ok "Node: $(node -v) / npm: $(npm -v)"

  mkdir -p /var/log/authzio
  chown "${APP_USER}:${APP_GROUP}" /var/log/authzio
}

clone_or_pull() {
  log "Fetching application code → ${APP_ROOT}"
  mkdir -p "$(dirname "${APP_ROOT}")"

  if [[ ! -d "${APP_ROOT}/.git" ]]; then
    if [[ -d "${SCRIPT_DIR}/.git" && "$(cd "${SCRIPT_DIR}" && pwd)" != "${APP_ROOT}" ]]; then
      # Script lives inside a checkout — rsync/copy into APP_ROOT if different path
      warn "Cloning ${REPO_URL} (branch ${GIT_BRANCH})"
    fi
    git clone --branch "${GIT_BRANCH}" --depth 1 "${REPO_URL}" "${APP_ROOT}"
  else
    pushd "${APP_ROOT}" >/dev/null
    git remote set-url origin "${REPO_URL}" 2>/dev/null || true
    git fetch --prune origin "${GIT_BRANCH}"
    git checkout "${GIT_BRANCH}"
    git reset --hard "origin/${GIT_BRANCH}"
    popd >/dev/null
  fi

  DEPLOY_DIR="${APP_ROOT}/deploy"
  chown -R "${APP_USER}:${APP_GROUP}" "${APP_ROOT}"
  ok "Code at ${APP_ROOT} ($(cd "${APP_ROOT}" && git rev-parse --short HEAD))"
}

write_env() {
  log "Preparing .env"
  pushd "${APP_ROOT}" >/dev/null

  if [[ ! -f .env ]]; then
    cp .env.example .env
    # Production-oriented defaults (DB/Redis still must be filled in).
    # Drop Laravel Cloud log channels — they are not defined in self-hosted logging.php.
    sed -i \
      -e 's/^APP_ENV=.*/APP_ENV=production/' \
      -e 's/^APP_DEBUG=.*/APP_DEBUG=false/' \
      -e 's/^LOG_LEVEL=.*/LOG_LEVEL=error/' \
      -e 's/^LOG_STACK=.*/LOG_STACK=single/' \
      -e 's/^MAIL_MAILER=.*/MAIL_MAILER=smtp/' \
      -e 's/^NIGHTWATCH_ENABLED=.*/NIGHTWATCH_ENABLED=false/' \
      .env
    if ! grep -qE '^NIGHTWATCH_ENABLED=' .env; then
      printf '\nNIGHTWATCH_ENABLED=false\n' >> .env
    fi
    if [[ "${PRIMARY_DOMAIN}" != "_" ]]; then
      sed -i \
        -e "s|^APP_URL=.*|APP_URL=https://${PRIMARY_DOMAIN}|" \
        -e "s|^MARKETING_URL=.*|MARKETING_URL=\"\${APP_URL}\"|" \
        -e "s|^AUTHZIO_DOMAIN_ROOT=.*|AUTHZIO_DOMAIN_ROOT=${PRIMARY_DOMAIN}|" \
        -e "s|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=${PRIMARY_DOMAIN},www.${PRIMARY_DOMAIN}|" \
        -e "s|^DODO_PAYMENTS_RETURN_URL=.*|DODO_PAYMENTS_RETURN_URL=\"\${APP_URL}/console/billing\"|" \
        -e 's/^SESSION_SECURE_COOKIE=.*/SESSION_SECURE_COOKIE=true/' \
        .env
    fi
    ok "Created .env from .env.example — edit DB_* / mail before go-live"
  else
    ok ".env already present (left unchanged)"
  fi

  if ! grep -qE '^APP_KEY=base64:' .env; then
    run_as_app "${PHP_BIN}" artisan key:generate --force
    ok "Generated APP_KEY"
  else
    ok "APP_KEY already set"
  fi

  popd >/dev/null
}

run_as_app() {
  sudo -u "${APP_USER}" -H \
    env HOME="/home/${APP_USER}" \
        PATH="/usr/local/bin:/usr/bin:/bin:${PATH}" \
        "$@"
}

install_app_deps() {
  log "Composer install (no-dev, optimized)"
  pushd "${APP_ROOT}" >/dev/null
  run_as_app composer install \
    --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-ansi

  if [[ "${SKIP_BUILD}" == "false" ]]; then
    log "npm ci && npm run build"
    run_as_app npm ci --no-fund --no-audit
    run_as_app npm run build
  else
    warn "Skipping frontend build"
  fi
  popd >/dev/null
}

db_reachable() {
  # Non-zero if Postgres (or configured DB) cannot be reached with current .env.
  run_as_app "${PHP_BIN}" artisan db:show --quiet >/dev/null 2>&1
}

artisan_production() {
  log "Laravel production steps"
  pushd "${APP_ROOT}" >/dev/null

  run_as_app "${PHP_BIN}" artisan storage:link --force 2>/dev/null || true

  if [[ "${SKIP_MIGRATE}" == "false" ]]; then
    if db_reachable; then
      if [[ "${DO_SEED}" == "true" ]]; then
        run_as_app "${PHP_BIN}" artisan migrate --force --seed
        ok "Migrated + seeded"
      else
        run_as_app "${PHP_BIN}" artisan migrate --force
        ok "Migrated"
      fi
    else
      warn "Database unreachable with current .env — skipping migrate/seed"
      warn "Configure DB_* (and Redis if used), then re-run: sudo $0"
      if [[ "${DO_SEED}" == "true" ]]; then
        warn "--seed ignored until the database is reachable"
      fi
    fi
  else
    warn "Skipping migrate"
  fi

  run_as_app "${PHP_BIN}" artisan config:cache
  run_as_app "${PHP_BIN}" artisan route:cache
  run_as_app "${PHP_BIN}" artisan view:cache
  run_as_app "${PHP_BIN}" artisan event:cache 2>/dev/null || true

  chown -R "${APP_USER}:${APP_GROUP}" storage bootstrap/cache
  find storage bootstrap/cache -type d -exec chmod 775 {} \;
  find storage bootstrap/cache -type f -exec chmod 664 {} \;

  popd >/dev/null
}

install_php_config() {
  log "PHP ${PHP_VERSION} production settings"
  local conf_dir ini_dir pool
  conf_dir="/etc/php/${PHP_VERSION}/fpm/conf.d"
  ini_dir="/etc/php/${PHP_VERSION}/cli/conf.d"
  pool="/etc/php/${PHP_VERSION}/fpm/pool.d/authzio.conf"

  [[ -d "${conf_dir}" ]] || die "PHP-FPM conf.d missing — is php${PHP_VERSION}-fpm installed?"

  install -m 0644 "${DEPLOY_DIR}/php/99-authzio.ini" "${conf_dir}/99-authzio.ini"
  install -m 0644 "${DEPLOY_DIR}/php/99-authzio.ini" "${ini_dir}/99-authzio.ini"

  # Dedicated pool running as APP_USER
  sed \
    -e "s|__APP_USER__|${APP_USER}|g" \
    -e "s|__APP_GROUP__|${APP_GROUP}|g" \
    -e "s|__APP_ROOT__|${APP_ROOT}|g" \
    -e "s|__PHP_VERSION__|${PHP_VERSION}|g" \
    "${DEPLOY_DIR}/php/pool-authzio.conf" > "${pool}"

  # Disable default www pool if present (avoid port clash / unused worker)
  if [[ -f "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf" ]]; then
    mv "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf" \
       "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf.disabled" 2>/dev/null || true
  fi

  systemctl enable "php${PHP_VERSION}-fpm"
  systemctl restart "php${PHP_VERSION}-fpm"
  ok "php-fpm pool authzio"
}

harden_permissions() {
  log "Hardening filesystem permissions (.env not world-readable)"
  # App owned by deploy user; nginx only needs public/
  chown -R "${APP_USER}:${APP_GROUP}" "${APP_ROOT}"
  # Directories traversable by group (php-fpm user); others no access to tree roots
  chmod 750 "${APP_ROOT}"
  find "${APP_ROOT}" -type d -exec chmod 750 {} \;
  find "${APP_ROOT}" -type f -exec chmod 640 {} \;

  # public/ must be readable by nginx (www-data)
  chmod 755 "${APP_ROOT}/public"
  find "${APP_ROOT}/public" -type d -exec chmod 755 {} \;
  find "${APP_ROOT}/public" -type f -exec chmod 644 {} \;

  # Writable runtime paths
  chmod -R ug+rwx "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache"
  find "${APP_ROOT}/storage" "${APP_ROOT}/bootstrap/cache" -type d -exec chmod 770 {} \;

  # Secrets — owner only
  if [[ -f "${APP_ROOT}/.env" ]]; then
    chown "${APP_USER}:${APP_USER}" "${APP_ROOT}/.env"
    chmod 600 "${APP_ROOT}/.env"
  fi
  # Block accidental web exposure of VCS / deploy templates
  chmod 700 "${APP_ROOT}/.git" 2>/dev/null || true
  chmod 750 "${APP_ROOT}/deploy" 2>/dev/null || true

  # Ensure no symlink of .env into public/
  rm -f "${APP_ROOT}/public/.env" "${APP_ROOT}/public/.env.*" 2>/dev/null || true

  ok ".env mode 600; document root ${APP_ROOT}/public only"
}

render_nginx() {
  local template="$1"
  local dest="$2"
  local cert="${3:-}"
  local key="${4:-}"

  sed \
    -e "s|__PRIMARY_DOMAIN__|${PRIMARY_DOMAIN}|g" \
    -e "s|__WWW_DOMAIN__|${WWW_DOMAIN}|g" \
    -e "s|__APP_ROOT__|${APP_ROOT}|g" \
    -e "s|__PHP_VERSION__|${PHP_VERSION}|g" \
    -e "s|__SSL_CERT__|${cert}|g" \
    -e "s|__SSL_KEY__|${key}|g" \
    "${template}" > "${dest}"
}

install_nginx_http() {
  log "Nginx HTTP (ACME + app) for ${PRIMARY_DOMAIN} + ${WWW_DOMAIN}"
  local site="/etc/nginx/sites-available/authzio"
  render_nginx "${DEPLOY_DIR}/nginx/authzio-http.conf" "${site}"
  ln -sfn "${site}" /etc/nginx/sites-enabled/authzio
  rm -f /etc/nginx/sites-enabled/default
  nginx -t
  systemctl enable nginx
  systemctl reload nginx
}

install_nginx_https() {
  local cert="/etc/letsencrypt/live/${PRIMARY_DOMAIN}/fullchain.pem"
  local key="/etc/letsencrypt/live/${PRIMARY_DOMAIN}/privkey.pem"
  [[ -f "${cert}" && -f "${key}" ]] || die "TLS files missing at ${cert}"

  # Certbot SSL options (created on first certbot run)
  if [[ ! -f /etc/letsencrypt/options-ssl-nginx.conf ]]; then
    curl -fsSL https://raw.githubusercontent.com/certbot/certbot/master/certbot-nginx/certbot_nginx/_internal/tls_configs/options-ssl-nginx.conf \
      -o /etc/letsencrypt/options-ssl-nginx.conf
  fi
  if [[ ! -f /etc/letsencrypt/ssl-dhparams.pem ]]; then
    openssl dhparam -out /etc/letsencrypt/ssl-dhparams.pem 2048
  fi

  log "Nginx HTTPS (HSTS, www→apex, secure headers)"
  local site="/etc/nginx/sites-available/authzio"
  render_nginx "${DEPLOY_DIR}/nginx/authzio.conf" "${site}" "${cert}" "${key}"
  nginx -t
  systemctl reload nginx
  ok "https://${PRIMARY_DOMAIN}  (www redirects here)"
}

install_ssl() {
  if [[ "${ENABLE_SSL}" != "true" ]]; then
    warn "SSL disabled (--no-ssl or no domain)"
    return
  fi
  if [[ "${PRIMARY_DOMAIN}" == "_" ]]; then
    warn "Skipping SSL: pass --domain=example.com"
    return
  fi
  if [[ -z "${SSL_EMAIL}" ]]; then
    die "HTTPS requires --email=you@example.com (Let's Encrypt contact)"
  fi

  log "Let's Encrypt certificate for ${PRIMARY_DOMAIN} + ${WWW_DOMAIN}"
  # Ensure HTTP config is live for ACME
  install_nginx_http

  if ! certbot certonly \
    --webroot \
    --webroot-path "${APP_ROOT}/public" \
    -d "${PRIMARY_DOMAIN}" \
    -d "${WWW_DOMAIN}" \
    --email "${SSL_EMAIL}" \
    --agree-tos \
    --non-interactive \
    --keep-until-expiring \
    --rsa-key-size 2048; then
    warn "Let's Encrypt failed (DNS A/AAAA for ${PRIMARY_DOMAIN} + ${WWW_DOMAIN} must point here)."
    warn "HTTP nginx left in place — re-run deploy after DNS propagates."
    return
  fi

  install_nginx_https

  # Auto-renew: systemd timer (preferred) + deploy hook
  mkdir -p /etc/letsencrypt/renewal-hooks/deploy
  cat > /etc/letsencrypt/renewal-hooks/deploy/authzio-reload-nginx.sh <<'HOOK'
#!/usr/bin/env bash
systemctl reload nginx
HOOK
  chmod 755 /etc/letsencrypt/renewal-hooks/deploy/authzio-reload-nginx.sh

  if systemctl list-unit-files | grep -q '^certbot.timer'; then
    systemctl enable --now certbot.timer
    ok "certbot.timer enabled (auto-renew)"
  else
    cat > /etc/cron.d/authzio-certbot <<EOF
# Authzio TLS renewal — twice daily (Let's Encrypt recommendation)
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
0 */12 * * * root certbot renew --quiet --deploy-hook "systemctl reload nginx"
EOF
    chmod 644 /etc/cron.d/authzio-certbot
    ok "cron renew installed (/etc/cron.d/authzio-certbot)"
  fi

  # Dry-run renew once to validate hooks (non-fatal)
  certbot renew --dry-run >/dev/null 2>&1 && ok "certbot renew dry-run OK" || warn "certbot dry-run skipped/failed (DNS must point here)"
}

install_nginx() {
  if [[ "${ENABLE_SSL}" == "true" && "${PRIMARY_DOMAIN}" != "_" ]]; then
    local cert="/etc/letsencrypt/live/${PRIMARY_DOMAIN}/fullchain.pem"
    if [[ -f "${cert}" ]]; then
      install_nginx_https
    else
      install_nginx_http
    fi
  else
    install_nginx_http
  fi
}

nightwatch_token() {
  local raw
  raw="$(grep -E '^NIGHTWATCH_TOKEN=' "${APP_ROOT}/.env" 2>/dev/null | tail -1 | cut -d= -f2- || true)"
  raw="${raw%\"}"
  raw="${raw#\"}"
  raw="${raw%\'}"
  raw="${raw#\'}"
  printf '%s' "${raw}"
}

install_supervisor() {
  log "Supervisor queue workers (${QUEUE_WORKERS}) + schedule:work"
  mkdir -p /var/log/authzio
  chown "${APP_USER}:${APP_GROUP}" /var/log/authzio

  local nw_autostart="false"
  local nw_token
  nw_token="$(nightwatch_token)"
  if [[ -n "${nw_token}" ]]; then
    nw_autostart="true"
  fi

  sed \
    -e "s|__APP_USER__|${APP_USER}|g" \
    -e "s|__APP_ROOT__|${APP_ROOT}|g" \
    -e "s|__PHP_BIN__|${PHP_BIN}|g" \
    -e "s|__QUEUE_WORKERS__|${QUEUE_WORKERS}|g" \
    -e "s|__NIGHTWATCH_AUTOSTART__|${nw_autostart}|g" \
    "${DEPLOY_DIR}/supervisor/authzio.conf" > /etc/supervisor/conf.d/authzio.conf

  systemctl enable supervisor
  systemctl restart supervisor
  sleep 1
  supervisorctl reread
  supervisorctl update
  supervisorctl start authzio-queue:* 2>/dev/null || supervisorctl restart authzio-queue:*
  supervisorctl start authzio-scheduler 2>/dev/null || supervisorctl restart authzio-scheduler
  if [[ "${nw_autostart}" == "true" ]]; then
    supervisorctl start authzio-nightwatch 2>/dev/null || supervisorctl restart authzio-nightwatch
    ok "Queue + scheduler + Nightwatch agent running"
  else
    supervisorctl stop authzio-nightwatch 2>/dev/null || true
    ok "Queue + scheduler running (Nightwatch idle — set NIGHTWATCH_TOKEN to enable)"
  fi
}

print_summary() {
  cat <<EOF

┌─────────────────────────────────────────────────────────────
│ Authzio deploy complete
├─────────────────────────────────────────────────────────────
│ App:        ${APP_ROOT}
│ User:       ${APP_USER} (non-sudo)
│ PHP:        ${PHP_VERSION}-fpm (pool authzio)
│ Canonical:  https://${PRIMARY_DOMAIN}
│ Also:       https://${WWW_DOMAIN} → 301 → apex
│ .env:       mode 600 (not web-accessible; root is public/)
│ Queues:     supervisor authzio-queue (${QUEUE_WORKERS} workers)
│ Scheduler:  supervisor authzio-scheduler
│ Nightwatch: set NIGHTWATCH_TOKEN in .env then re-run deploy
│ TLS renew:  certbot.timer / cron + nginx reload hook
│
│ Next steps:
│  1. Edit ${APP_ROOT}/.env — DB_*, mail (Postgres must exist; Redis optional with QUEUE/CACHE=database)
│  2. DNS A/AAAA: ${PRIMARY_DOMAIN} + ${WWW_DOMAIN} → this VPS
│  3. Re-run:  sudo ${APP_ROOT}/deploy.sh --domain=${PRIMARY_DOMAIN} --email=ops@example.com
│  4. Status:  sudo supervisorctl status && sudo certbot certificates
└─────────────────────────────────────────────────────────────
EOF
}

# ── main ──────────────────────────────────────────────────────────────────────
detect_os
resolve_domains

if [[ "${DO_BOOTSTRAP}" == "true" ]]; then
  ensure_user
  install_packages
  resolve_php_bin
  clone_or_pull
else
  [[ -d "${APP_ROOT}/.git" ]] || die "App not found at ${APP_ROOT}. Run with --bootstrap first."
  ensure_user
  resolve_php_bin
  clone_or_pull
fi

[[ -d "${DEPLOY_DIR}" ]] || die "Missing deploy configs at ${DEPLOY_DIR}"

write_env
install_app_deps
artisan_production
harden_permissions
install_php_config
install_nginx
install_ssl
install_supervisor
print_summary
