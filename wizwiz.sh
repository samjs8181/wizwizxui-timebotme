#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}WizWizXUI Time Bot Installer${NC}"
echo -e "${YELLOW}==========================${NC}"

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root${NC}"
    exit 1
fi

# Check system requirements
echo -e "${YELLOW}Checking system requirements...${NC}"
if ! command -v php &> /dev/null; then
    echo -e "${RED}PHP is not installed. Installing PHP...${NC}"
    apt update
    apt install -y php php-cli php-mysql php-json php-opcache php-curl php-zip php-gd php-mbstring php-xml php-bcmath
fi

if ! command -v mysql &> /dev/null; then
    echo -e "${RED}MySQL is not installed. Installing MySQL...${NC}"
    apt install -y mysql-server
    systemctl start mysql
    systemctl enable mysql
fi

# Create installation directory
INSTALL_DIR="/var/www/wizwizxui-timebot"
echo -e "${YELLOW}Creating installation directory...${NC}"
mkdir -p $INSTALL_DIR

# Download and extract files
echo -e "${YELLOW}Downloading bot files...${NC}"
cd $INSTALL_DIR
wget -q https://github.com/samjs8181/wizwizxui-timebotme/archive/main.zip
unzip -q main.zip
mv wizwizxui-timebotme-main/* .
rm -rf wizwizxui-timebotme-main main.zip

# Set permissions
echo -e "${YELLOW}Setting permissions...${NC}"
chown -R www-data:www-data $INSTALL_DIR
chmod -R 755 $INSTALL_DIR
chmod -R 777 $INSTALL_DIR/cache
chmod -R 777 $INSTALL_DIR/temp

# Create database
echo -e "${YELLOW}Creating database...${NC}"
mysql -e "CREATE DATABASE IF NOT EXISTS wizwizxui_timebot;"
mysql -e "CREATE USER IF NOT EXISTS 'wizwizxui_timebot'@'localhost' IDENTIFIED BY 'wizwizxui_timebot';"
mysql -e "GRANT ALL PRIVILEGES ON wizwizxui_timebot.* TO 'wizwizxui_timebot'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Import database schema
echo -e "${YELLOW}Importing database schema...${NC}"
mysql wizwizxui_timebot < $INSTALL_DIR/database.sql

# Configure bot
echo -e "${YELLOW}Configuring bot...${NC}"
cp $INSTALL_DIR/config.example.php $INSTALL_DIR/config.php
sed -i "s/your_bot_token/$(openssl rand -hex 16)/g" $INSTALL_DIR/config.php
sed -i "s/your_admin_id/$(openssl rand -hex 8)/g" $INSTALL_DIR/config.php

# Create systemd service
echo -e "${YELLOW}Creating systemd service...${NC}"
cat > /etc/systemd/system/wizwizxui-timebot.service << EOL
[Unit]
Description=WizWizXUI Time Bot
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=$INSTALL_DIR
ExecStart=/usr/bin/php $INSTALL_DIR/bot.php
Restart=always

[Install]
WantedBy=multi-user.target
EOL

# Enable and start service
echo -e "${YELLOW}Starting bot service...${NC}"
systemctl daemon-reload
systemctl enable wizwizxui-timebot
systemctl start wizwizxui-timebot

echo -e "${GREEN}Installation completed successfully!${NC}"
echo -e "${YELLOW}Please edit $INSTALL_DIR/config.php with your bot token and admin ID${NC}"
echo -e "${YELLOW}Bot service is running. Check status with: systemctl status wizwizxui-timebot${NC}"
