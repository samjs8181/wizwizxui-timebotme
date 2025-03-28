#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}Starting WizWizXUI TimeBot Installation...${NC}"

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root${NC}"
    exit 1
fi

# Update system
echo -e "${YELLOW}Updating system...${NC}"
apt update && apt upgrade -y

# Install required packages
echo -e "${YELLOW}Installing required packages...${NC}"
apt install -y php php-mysql php-curl php-redis php-gd php-mbstring php-xml php-zip unzip git nginx mysql-server redis-server

# Install Composer
echo -e "${YELLOW}Installing Composer...${NC}"
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Create bot directory
echo -e "${YELLOW}Creating bot directory...${NC}"
mkdir -p /var/www/bot
cd /var/www/bot

# Clone the repository
echo -e "${YELLOW}Cloning repository...${NC}"
git clone https://github.com/yourusername/wizwizxui-timebot.git .

# Set permissions
echo -e "${YELLOW}Setting permissions...${NC}"
chown -R www-data:www-data /var/www/bot
chmod -R 755 /var/www/bot
chmod -R 777 /var/www/bot/logs
chmod -R 777 /var/www/bot/cache

# Configure MySQL
echo -e "${YELLOW}Configuring MySQL...${NC}"
mysql_secure_installation

# Create database and user
echo -e "${YELLOW}Creating database and user...${NC}"
mysql -e "CREATE DATABASE IF NOT EXISTS bot_db;"
mysql -e "CREATE USER IF NOT EXISTS 'bot_user'@'localhost' IDENTIFIED BY 'your_secure_password';"
mysql -e "GRANT ALL PRIVILEGES ON bot_db.* TO 'bot_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Import database schema
echo -e "${YELLOW}Importing database schema...${NC}"
mysql bot_db < /var/www/bot/createDB.php

# Configure Nginx
echo -e "${YELLOW}Configuring Nginx...${NC}"
cat > /etc/nginx/sites-available/bot << 'EOL'
server {
    listen 80;
    server_name your_domain.com;
    root /var/www/bot;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOL

ln -s /etc/nginx/sites-available/bot /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default

# Configure PHP
echo -e "${YELLOW}Configuring PHP...${NC}"
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 64M/' /etc/php/7.4/fpm/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 64M/' /etc/php/7.4/fpm/php.ini
sed -i 's/memory_limit = 128M/memory_limit = 256M/' /etc/php/7.4/fpm/php.ini

# Restart services
echo -e "${YELLOW}Restarting services...${NC}"
systemctl restart nginx
systemctl restart php7.4-fpm
systemctl restart mysql
systemctl restart redis-server

# Create systemd service for the bot
echo -e "${YELLOW}Creating systemd service...${NC}"
cat > /etc/systemd/system/bot.service << 'EOL'
[Unit]
Description=WizWizXUI TimeBot
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/bot
ExecStart=/usr/bin/php /var/www/bot/bot.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOL

# Enable and start the bot service
echo -e "${YELLOW}Starting bot service...${NC}"
systemctl daemon-reload
systemctl enable bot
systemctl start bot

# Create configuration file
echo -e "${YELLOW}Creating configuration file...${NC}"
cat > /var/www/bot/settings/values.php << 'EOL'
<?php
$dbUserName = 'bot_user';
$dbPassword = 'your_secure_password';
$dbName = 'bot_db';
$botToken = 'YOUR_BOT_TOKEN';
$admin = 'YOUR_ADMIN_ID';
$botUsername = 'YOUR_BOT_USERNAME';
$botUrl = 'https://your_domain.com';
$nextpayApiKey = 'YOUR_NEXTPAY_API_KEY';
$zarinpalMerchant = 'YOUR_ZARINPAL_MERCHANT';
$nowPaymentApiKey = 'YOUR_NOWPAYMENT_API_KEY';
EOL

echo -e "${GREEN}Installation completed!${NC}"
echo -e "${YELLOW}Please edit /var/www/bot/settings/values.php with your actual configuration values${NC}"
echo -e "${YELLOW}Please edit /etc/nginx/sites-available/bot with your actual domain name${NC}"
echo -e "${YELLOW}Don't forget to set up SSL certificate using Let's Encrypt${NC}" 