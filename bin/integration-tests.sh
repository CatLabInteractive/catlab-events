#!/usr/bin/env bash
# Runs the Integration testsuite inside the docker-compose webserver container
# against a dedicated catlab_events_test database (dev data is never touched).
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose up -d mysql-db webserver

# Wait for MySQL to accept connections, then ensure the test db exists.
docker compose exec -T mysql-db bash -c \
  'for i in $(seq 1 30); do mysqladmin ping -uroot -p"$MYSQL_ROOT_PASSWORD" --silent && break; sleep 1; done'
docker compose exec -T mysql-db bash -c \
  'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS catlab_events_test"'

docker compose exec -T webserver php vendor/bin/phpunit -c phpunit.integration.xml "$@"
