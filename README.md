# TiredProd

Photography platform for TiredOfDoinTM.

## Setup

### VPS Requirements
- Ubuntu 22.04/24.04 LTS
- PHP 8.2+
- PostgreSQL (DigitalOcean Managed)
- Nginx

### Quick Deploy

1. SSH into your VPS:
```bash
ssh root@164.92.99.233
```

2. Run setup script:
```bash
curl -sSL https://raw.githubusercontent.com/YOUR_USER/tiredprod/main/scripts/setup-vps.sh | bash
```

3. Clone repo:
```bash
cd /var/www/tiredprod.com
git clone https://github.com/YOUR_USER/tiredprod.git .
```

4. Copy config:
```bash
cp config/.env.example config/.env
nano config/.env  # Edit with your credentials
```

5. Run migrations:
```bash
php migrate.php
```

6. Get SSL:
```bash
certbot --nginx -d tiredprod.com -d www.tiredprod.com
```

## Structure

```
tiredprod/
├── config/          # Configuration files
│   ├── .env         # Environment variables (never commit!)
│   ├── app.php      # App config
│   └── database.php # DB config
├── public/          # Web root
│   ├── index.php    # Main entry point
│   └── assets/      # CSS, JS, images
├── app/             # Application code
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   ├── Middleware/
│   └── Utils/
├── views/           # PHP templates
├── storage/         # Logs, cache
├── migrations/      # SQL migrations
└── scripts/         # Deploy scripts
```

## Features

- Gate system ("67" password protection)
- Email OTP authentication
- Google OAuth
- Gallery management
- Booking system
- Stripe payments
- Contract signing
- Admin dashboard
