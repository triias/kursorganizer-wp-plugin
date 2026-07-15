#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STACK_ID="ko-updater-${PPID}-$$"
NETWORK="${STACK_ID}-network"
DB_CONTAINER="${STACK_ID}-db"
WP_CONTAINER="${STACK_ID}-wp"
WP_VOLUME="${STACK_ID}-wordpress"
OLD_ZIP="${TMPDIR:-/tmp}/${STACK_ID}-v1.2.2.zip"
WORDPRESS_IMAGE="${WORDPRESS_IMAGE:-wordpress:php8.1-apache}"
WORDPRESS_CLI_IMAGE="${WORDPRESS_CLI_IMAGE:-wordpress:cli-php8.1}"

cleanup() {
    docker rm -f "$WP_CONTAINER" "$DB_CONTAINER" >/dev/null 2>&1 || true
    docker volume rm "$WP_VOLUME" >/dev/null 2>&1 || true
    docker network rm "$NETWORK" >/dev/null 2>&1 || true
    rm -f "$OLD_ZIP"
}
trap cleanup EXIT

docker network create "$NETWORK" >/dev/null
docker volume create "$WP_VOLUME" >/dev/null

docker run -d --name "$DB_CONTAINER" --network "$NETWORK" \
    -e MARIADB_DATABASE=wordpress \
    -e MARIADB_USER=wordpress \
    -e MARIADB_PASSWORD=wordpress-test \
    -e MARIADB_ROOT_PASSWORD=root-test \
    mariadb:10.11 >/dev/null

docker run -d --name "$WP_CONTAINER" --network "$NETWORK" \
    --network-alias ko-updater-wp \
    -e WORDPRESS_DB_HOST="$DB_CONTAINER" \
    -e WORDPRESS_DB_USER=wordpress \
    -e WORDPRESS_DB_PASSWORD=wordpress-test \
    -e WORDPRESS_DB_NAME=wordpress \
    -e WORDPRESS_DEBUG=1 \
    -v "$WP_VOLUME:/var/www/html" \
    "$WORDPRESS_IMAGE" >/dev/null

database_ready=false
for _ in $(seq 1 45); do
    if docker exec "$DB_CONTAINER" mariadb \
        -uwordpress -pwordpress-test wordpress \
        -e 'SELECT 1' >/dev/null 2>&1; then
        database_ready=true
        break
    fi
    sleep 1
done
if [ "$database_ready" != true ]; then
    echo "MariaDB did not become ready." >&2
    exit 1
fi

wordpress_ready=false
for _ in $(seq 1 45); do
    if docker exec "$WP_CONTAINER" test -f /var/www/html/wp-settings.php; then
        wordpress_ready=true
        break
    fi
    sleep 1
done
if [ "$wordpress_ready" != true ]; then
    echo "WordPress files did not become ready." >&2
    exit 1
fi

wp_cli() {
    docker run --rm --network "$NETWORK" --volumes-from "$WP_CONTAINER" \
        --user 33:33 \
        -e WORDPRESS_DB_HOST="$DB_CONTAINER" \
        -e WORDPRESS_DB_USER=wordpress \
        -e WORDPRESS_DB_PASSWORD=wordpress-test \
        -e WORDPRESS_DB_NAME=wordpress \
        "$WORDPRESS_CLI_IMAGE" wp "$@"
}

wp_cli core install \
    --url=http://ko-updater-wp \
    --title="KursOrganizer updater integration" \
    --admin_user=admin \
    --admin_password=integration-test-only \
    --admin_email=integration@example.invalid \
    --skip-email >/dev/null

git -C "$ROOT_DIR" archive --format=zip \
    --prefix=kursorganizer-wp-plugin-main/ \
    --output="$OLD_ZIP" v1.2.2

bash "$ROOT_DIR/scripts/build-release.sh" >/dev/null

docker cp "$OLD_ZIP" "$WP_CONTAINER:/var/www/html/ko-v1.2.2.zip"
docker cp "$ROOT_DIR/dist/kursorganizer-wp-plugin.zip" "$WP_CONTAINER:/var/www/html/ko-update-package.zip"
docker exec "$WP_CONTAINER" mkdir -p /var/www/html/wp-content/mu-plugins
docker cp "$ROOT_DIR/tests/integration/mock-release.php" \
    "$WP_CONTAINER:/var/www/html/wp-content/mu-plugins/mock-release.php"
docker cp "$ROOT_DIR/tests/integration/bridge-update.php" \
    "$WP_CONTAINER:/var/www/html/wp-content/bridge-update.php"
docker cp "$ROOT_DIR/tests/integration/assert-cron-update.php" \
    "$WP_CONTAINER:/var/www/html/wp-content/assert-cron-update.php"
docker exec "$WP_CONTAINER" chown -R 33:33 \
    /var/www/html/ko-v1.2.2.zip \
    /var/www/html/ko-update-package.zip \
    /var/www/html/wp-content/mu-plugins \
    /var/www/html/wp-content/bridge-update.php \
    /var/www/html/wp-content/assert-cron-update.php

wp_cli plugin install /var/www/html/ko-v1.2.2.zip --activate >/dev/null
wp_cli option update kursorganizer_settings \
    '{"github_token":"stale-token-must-not-be-used"}' --format=json >/dev/null
wp_cli option update ko_mock_release_version 1.2.7 >/dev/null

installed_before="$(wp_cli plugin get kursorganizer-wp-plugin-main --field=version)"
if [ "$installed_before" != "1.2.2" ]; then
    echo "Expected legacy version 1.2.2, got $installed_before." >&2
    exit 1
fi

wp_cli eval-file /var/www/html/wp-content/bridge-update.php

installed_after="$(wp_cli plugin get kursorganizer-wp-plugin-main --field=version)"
status_after="$(wp_cli plugin get kursorganizer-wp-plugin-main --field=status)"
if [ "$installed_after" != "1.2.7" ] || [ "$status_after" != "active" ]; then
    echo "Legacy update did not preserve the active plugin: version=$installed_after status=$status_after" >&2
    exit 1
fi

if docker exec "$WP_CONTAINER" test -d /var/www/html/wp-content/plugins/kursorganizer-wp-plugin; then
    echo "The canonical package was left behind as a duplicate plugin directory." >&2
    exit 1
fi

wp_cli eval-file /var/www/html/wp-content/assert-cron-update.php
wp_cli core version
echo "Updater integration test passed: 1.2.2 -> 1.2.7 in the legacy folder."
