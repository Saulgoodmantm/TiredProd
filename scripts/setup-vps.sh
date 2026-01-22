#!/bin/bash
# TiredProd VPS Setup Script
# Run as root on fresh Ubuntu 22.04/24.04

set -e

echo "======================================"
echo "  TiredProd VPS Setup"
echo "======================================"

# Update system
echo "[1/10] Updating system..."
apt update && apt upgrade -y

# Install required packages
echo "[2/10] Installing packages..."
apt install -y \
    nginx \
    php8.2-fpm \
    php8.2-pgsql \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-curl \
    php8.2-zip \
    php8.2-gd \
    php8.2-intl \
    git \
    unzip \
    curl \
    certbot \
    python3-certbot-nginx \
    ufw

# Configure firewall
echo "[3/10] Configuring firewall..."
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

# Create web user
echo "[4/10] Creating deploy user..."
if ! id "deploy" &>/dev/null; then
    useradd -m -s /bin/bash deploy
    usermod -aG www-data deploy
fi

# Create directory structure
echo "[5/10] Creating directories..."
mkdir -p /var/www/tiredprod.com/public
mkdir -p /var/www/tiredofdointm.com/public
mkdir -p /var/www/panel
chown -R deploy:www-data /var/www

# Configure PHP
echo "[6/10] Configuring PHP..."
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 100M/' /etc/php/8.2/fpm/php.ini
sed -i 's/post_max_size = .*/post_max_size = 100M/' /etc/php/8.2/fpm/php.ini
sed -i 's/max_execution_time = .*/max_execution_time = 300/' /etc/php/8.2/fpm/php.ini
systemctl restart php8.2-fpm

# Create Nginx config for tiredprod.com
echo "[7/10] Configuring Nginx..."
cat > /etc/nginx/sites-available/tiredprod.com << 'NGINX'
server {
    listen 80;
    server_name tiredprod.com www.tiredprod.com;
    root /var/www/tiredprod.com/public;
    index index.php index.html;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location ~ /\. {
        deny all;
    }
}
NGINX

# Create Nginx config for tiredofdointm.com
cat > /etc/nginx/sites-available/tiredofdointm.com << 'NGINX'
server {
    listen 80;
    server_name tiredofdointm.com www.tiredofdointm.com;
    root /var/www/tiredofdointm.com/public;
    index index.php index.html;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location ~ /\. {
        deny all;
    }
}
NGINX

# Enable sites
ln -sf /etc/nginx/sites-available/tiredprod.com /etc/nginx/sites-enabled/
ln -sf /etc/nginx/sites-available/tiredofdointm.com /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test and restart Nginx
nginx -t && systemctl restart nginx

# Set permissions
echo "[8/10] Setting permissions..."
chown -R deploy:www-data /var/www
chmod -R 755 /var/www
find /var/www -type f -exec chmod 644 {} \;

# Create deploy script
echo "[9/10] Creating deploy helper..."
cat > /usr/local/bin/deploy-tiredprod << 'DEPLOY'
#!/bin/bash
cd /var/www/tiredprod.com
git pull origin main
chown -R deploy:www-data .
chmod -R 755 .
find . -type f -exec chmod 644 {} \;
php migrate.php
systemctl reload php8.2-fpm
echo "Deploy complete!"
DEPLOY
chmod +x /usr/local/bin/deploy-tiredprod

echo "[10/10] Setup complete!"
echo ""
echo "======================================"
echo "  Next Steps:"
echo "======================================"
echo ""
echo "1. Point your domains to this server:"
echo "   tiredprod.com      -> $(curl -s ifconfig.me)"
echo "   tiredofdointm.com  -> $(curl -s ifconfig.me)"
echo ""
echo "2. Get SSL certificates:"
echo "   certbot --nginx -d tiredprod.com -d www.tiredprod.com"
echo "   certbot --nginx -d tiredofdointm.com -d www.tiredofdointm.com"
echo ""
echo "3. Clone your repo:"
echo "   cd /var/www/tiredprod.com"
echo "   git clone https://github.com/YOUR_USER/tiredprod.git ."
echo ""
echo "4. Run migrations:"
echo "   php migrate.php"
echo ""
echo "======================================"
