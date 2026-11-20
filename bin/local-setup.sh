#!/usr/bin/env bash
# Sets up a local copy of the site with test data, all inside docker.
# See docs/local-development.md.
#
#   bin/local-setup.sh           first setup, or bring an existing copy up to date
#   bin/local-setup.sh --fresh   also wipe the database and load the test data again
#
# Needs only docker (with docker compose) and git. PHP, Composer, Node and
# MySQL all run in the containers.
set -euo pipefail
cd "$(dirname "$0")/.."

FRESH=0
for arg in "$@"; do
  case "$arg" in
    --fresh) FRESH=1 ;;
    -h|--help) sed -n '2,9p' "$0"; exit 0 ;;
    *) echo "Unknown option: $arg" >&2; exit 1 ;;
  esac
done

step() { printf '\n==> %s\n' "$*"; }

command -v docker >/dev/null || { echo "docker is not installed." >&2; exit 1; }
docker compose version >/dev/null 2>&1 || { echo "docker compose is not available." >&2; exit 1; }

# Sets KEY=VALUE in .env: replaces the line, or adds it when missing.
set_env() {
  if grep -q "^$1=" .env; then
    sed -i.bak "s#^$1=.*#$1=$2#" .env && rm -f .env.bak
  else
    printf '%s=%s\n' "$1" "$2" >> .env
  fi
}

if [ ! -f .env ]; then
  step "Creating .env from .env.example"
  cp .env.example .env
  set_env APP_URL http://localhost:8095
  # Images of the test data, served by the docker compose "assets" service.
  set_env CENTRALSTORAGE_FRONT http://localhost:8099
  # Some listings (the calendar) group in ways MySQL strict mode rejects.
  # The migrations below turn strict mode back on for themselves.
  set_env DB_STRICT false
  set_env SEO_INDEXABLE false
else
  step "Keeping the existing .env"
fi

if grep -Eq '^CATLAB_CLIENT_ID=.+' .env; then
  echo "Note: CATLAB_CLIENT_ID is set in .env, so login goes through CatLab accounts"
  echo "and the demo login (demo@example.com / password) does not work."
  echo "Empty CATLAB_CLIENT_ID in .env to use the demo login."
fi

step "Building and starting the containers (the first build takes several minutes)"
docker compose up -d --build

step "Waiting for MySQL"
for i in $(seq 1 60); do
  if docker compose exec -T mysql-db sh -c 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent' >/dev/null 2>&1; then
    break
  fi
  if [ "$i" = 60 ]; then echo "MySQL did not start. See: docker compose logs mysql-db" >&2; exit 1; fi
  sleep 2
done

run() { docker compose exec -T webserver "$@"; }

# The web server container installs the PHP packages when it starts
# (docker-compose.yml), and only then starts Apache.
step "Waiting for the web server (it installs the PHP packages first)"
for i in $(seq 1 300); do
  if run sh -c 'curl -s -o /dev/null http://127.0.0.1/robots.txt' >/dev/null 2>&1; then
    break
  fi
  if ! docker compose ps --status running --services | grep -qx webserver; then
    echo "The web server container stopped. See: docker compose logs webserver" >&2
    exit 1
  fi
  if [ "$i" = 300 ]; then echo "The web server did not start. See: docker compose logs webserver" >&2; exit 1; fi
  sleep 2
done

if ! grep -Eq '^APP_KEY=.+' .env; then
  step "Generating the application key"
  run php artisan key:generate --no-interaction
fi

step "Installing Node packages and building CSS and JavaScript"
run npm install --no-audit --no-fund
run env NODE_OPTIONS=--openssl-legacy-provider npm run dev

step "Updating the database"
# Migrations need strict mode (one of them cannot make a foreign key column
# NOT NULL without it). A real environment variable wins over .env.
if [ "$FRESH" = 1 ]; then
  run env DB_STRICT=true php artisan migrate:fresh --force --no-interaction
else
  run env DB_STRICT=true php artisan migrate --force --no-interaction
fi

ORGANISATIONS=$(run php artisan tinker --execute='echo \App\Models\Organisation::count();' | tr -dc '0-9')
if [ "${ORGANISATIONS:-0}" = 0 ]; then
  step "Loading the test data"
  run php artisan db:seed --force --no-interaction
else
  echo "The database already has data: test data not loaded again (use --fresh to reset)."
fi

cat <<'DONE'

==> Done

  Site:        http://localhost:8095
  Admin panel: http://localhost:8095/admin
  Login:       demo@example.com / password
  Images:      http://localhost:8099 (stand-in for CentralStorage)

Rebuild CSS and JavaScript after a change in resources/assets:
  docker compose exec webserver npm run dev
DONE
