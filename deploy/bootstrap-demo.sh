#!/usr/bin/env bash
#
# One-time bootstrap for the demo Litstack deployment.
# Run manually on the VPS once: bash deploy/bootstrap-demo.sh
# Ongoing deploys are handled by .github/workflows/deploy.yml — this
# script only needs to be re-run if the server-level config below changes.

set -e

APP_PATH="/var/www/litstack-demo"
DOMAIN="demo.litstack.app"
REPO="git@github.com:ben-james-dodwell/litstack.git"
PHP_VERSION="8.3"
NGINX_SITE="litstack-demo"
WORKER_NAME="litstack-demo-worker"

echo "🚀 Bootstrapping demo Litstack..."

# ----------------------------------------
# LOAD DEPLOY CONFIG (DB_NAME, DB_USER, DB_PASS)
# ----------------------------------------
if [ ! -f ~/litstack/deploy-demo.env ]; then
  echo "❌ Missing ~/litstack/deploy-demo.env"
  exit 1
fi
source ~/litstack/deploy-demo.env

# ----------------------------------------
# 1. CLONE OR UPDATE CODE
# ----------------------------------------
if [ ! -d "$APP_PATH" ]; then
  sudo -u deploy git clone "$REPO" "$APP_PATH"
else
  cd "$APP_PATH"
  sudo -u deploy git pull
fi

cd "$APP_PATH"

# ----------------------------------------
# 2. BACKEND DEPENDENCIES
# ----------------------------------------
sudo -u deploy composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# ----------------------------------------
# 3. ENV SETUP
# ----------------------------------------
if [ ! -f .env ]; then
  cp .env.example .env
fi
sed -i "s/^APP_NAME=.*/APP_NAME=Litstack Demo/" .env
sed -i "s/^APP_ENV=.*/APP_ENV=production/" .env
sed -i "s/^APP_DEBUG=.*/APP_DEBUG=false/" .env
sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
sed -i "s/^SESSION_DOMAIN=.*/SESSION_DOMAIN=null/" .env

sed -i "s/^[[:space:]]*#*[[:space:]]*DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env
sed -i "s/^[[:space:]]*#*[[:space:]]*DB_HOST=.*/DB_HOST=127.0.0.1/" .env
sed -i "s/^[[:space:]]*#*[[:space:]]*DB_PORT=.*/DB_PORT=3306/" .env
sed -i "s/^[[:space:]]*#*[[:space:]]*DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
sed -i "s/^[[:space:]]*#*[[:space:]]*DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" .env
sed -i "s/^[[:space:]]*#*[[:space:]]*DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env

# This instance is specifically the public demo — unlike live, it must have
# demo login enabled.
sed -i "s/^[[:space:]]*#*[[:space:]]*DEMO_ENABLED=.*/DEMO_ENABLED=true/" .env
sed -i "s/^[[:space:]]*#*[[:space:]]*DEMO_EMAIL=.*/DEMO_EMAIL=demo@litstack.app/" .env

# Only ever generate the app key once. Regenerating it invalidates existing
# sessions and any encrypted data, so this must not run on every deploy.
if grep -q "^APP_KEY=$" .env; then
  php artisan key:generate --force
else
  echo "✅ APP_KEY already set, skipping key:generate"
fi

# ----------------------------------------
# 4. DATABASE + SEED DATA
# ----------------------------------------
echo "🗄️ Ensuring database exists..."

sudo mysql -e "
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
"

php artisan migrate --force

# Unlike live, this instance wants the full DatabaseSeeder -- lookup tables,
# the "Demo User" account, and the pre-populated shelf. All of it is
# idempotent (firstOrCreate/updateOrCreate throughout), safe to re-run.
php artisan db:seed --force

php artisan storage:link

# ----------------------------------------
# 5. FRONTEND BUILD (VITE)
# ----------------------------------------
sudo -u deploy npm ci
sudo -u deploy npm run build

php artisan optimize

# ----------------------------------------
# 6. PERMISSIONS
# ----------------------------------------
sudo chown -R deploy:www-data "$APP_PATH"
sudo chmod -R 775 storage bootstrap/cache

# ----------------------------------------
# 7. NGINX CONFIG
# ----------------------------------------
echo "🌐 Configuring Nginx..."

sudo tee /etc/nginx/sites-available/${NGINX_SITE} > /dev/null <<EOF
server {
    listen 80;
    server_name ${DOMAIN};

    root ${APP_PATH}/public;
    index index.php;

    # Vite build output: filenames are content-hashed, safe to cache indefinitely.
    location ^~ /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Cached cover images: filename is the book ID, not content-hashed, so a
    # stale image could exist under the same URL -- cache briefly, not forever.
    location ^~ /storage/covers/ {
        expires 7d;
        add_header Cache-Control "public";
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

sudo ln -sf /etc/nginx/sites-available/${NGINX_SITE} /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# ----------------------------------------
# 8. HTTPS (CERTBOT)
# ----------------------------------------
echo "🔐 Configuring SSL..."

# Always run certbot after (re)writing the nginx config above, so it re-adds
# the SSL directives to whatever was just written -- --keep-until-expiring
# avoids reissuing a certificate that's still valid.
sudo certbot --nginx -d "$DOMAIN" \
  --non-interactive --agree-tos -m admin@${DOMAIN} \
  --keep-until-expiring

sudo nginx -t
sudo systemctl reload nginx

# ----------------------------------------
# 9. QUEUE WORKER (SUPERVISOR)
# ----------------------------------------
echo "⚙️ Setting up queue worker..."

sudo tee /etc/supervisor/conf.d/${WORKER_NAME}.conf > /dev/null <<EOF
[program:${WORKER_NAME}]
command=/usr/bin/php ${APP_PATH}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=${APP_PATH}
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/${WORKER_NAME}.log
EOF

sudo touch /var/log/${WORKER_NAME}.log
sudo chown www-data:www-data /var/log/${WORKER_NAME}.log

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart ${WORKER_NAME} || true

# ----------------------------------------
# 10. SCHEDULER (deploy user's crontab)
# ----------------------------------------
echo "⏱️ Setting up Laravel scheduler..."
CRON_JOB="* * * * * cd ${APP_PATH} && php artisan schedule:run >> /dev/null 2>&1"
( sudo -u deploy crontab -l 2>/dev/null | grep -v -F "$CRON_JOB" ; echo "$CRON_JOB" ) | sudo -u deploy crontab -

echo "✅ Bootstrap complete for demo Litstack"
echo "🌍 Live at: https://${DOMAIN}"
