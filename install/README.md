# WizWizXUI TimeBot Installation Guide

This guide will help you install the WizWizXUI TimeBot on your Ubuntu server.

## Prerequisites

- Ubuntu 20.04 LTS or later
- Root access to the server
- A domain name (for SSL)
- A Telegram Bot Token (from @BotFather)

## Installation Steps

1. **Download the Installation Script**
```bash
wget https://raw.githubusercontent.com/yourusername/wizwizxui-timebot/main/install/install.sh
```

2. **Make the Script Executable**
```bash
chmod +x install.sh
```

3. **Run the Installation Script**
```bash
sudo ./install.sh
```

4. **Configure the Bot**
After installation, you need to edit the following files:

a. Edit `/var/www/bot/settings/values.php`:
```php
$dbUserName = 'bot_user';  // Database username
$dbPassword = 'your_secure_password';  // Database password
$dbName = 'bot_db';  // Database name
$botToken = 'YOUR_BOT_TOKEN';  // Your Telegram bot token
$admin = 'YOUR_ADMIN_ID';  // Your Telegram user ID
$botUsername = 'YOUR_BOT_USERNAME';  // Your bot's username
$botUrl = 'https://your_domain.com';  // Your domain
$nextpayApiKey = 'YOUR_NEXTPAY_API_KEY';  // NextPay API key
$zarinpalMerchant = 'YOUR_ZARINPAL_MERCHANT';  // Zarinpal merchant ID
$nowPaymentApiKey = 'YOUR_NOWPAYMENT_API_KEY';  // NowPayment API key
```

b. Edit `/etc/nginx/sites-available/bot`:
Replace `your_domain.com` with your actual domain name.

5. **Set up SSL Certificate**
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your_domain.com
```

6. **Check Installation**
```bash
# Check bot service status
sudo systemctl status bot

# Check nginx status
sudo systemctl status nginx

# Check logs
sudo tail -f /var/www/bot/logs/bot.log
```

## Post-Installation

1. **Set up Webhook**
Visit `https://your_domain.com/setup.php` to set up the webhook for your bot.

2. **Configure Payment Gateways**
- Set up your NextPay account and get API key
- Set up your Zarinpal account and get merchant ID
- Set up your NowPayment account and get API key

3. **Security Considerations**
- Change default database password
- Set up firewall rules
- Keep system and packages updated
- Monitor logs regularly

## Troubleshooting

1. **Bot Not Responding**
- Check bot service status: `sudo systemctl status bot`
- Check logs: `sudo tail -f /var/www/bot/logs/bot.log`
- Verify bot token in settings

2. **Database Issues**
- Check MySQL status: `sudo systemctl status mysql`
- Verify database credentials
- Check database connection: `mysql -u bot_user -p`

3. **Web Server Issues**
- Check Nginx status: `sudo systemctl status nginx`
- Check Nginx logs: `sudo tail -f /var/log/nginx/error.log`
- Verify SSL certificate

## Maintenance

1. **Update the Bot**
```bash
cd /var/www/bot
git pull
sudo systemctl restart bot
```

2. **Backup Database**
```bash
mysqldump -u bot_user -p bot_db > backup.sql
```

3. **Monitor Resources**
```bash
# Check disk space
df -h

# Check memory usage
free -m

# Check CPU usage
top
```

## Support

For support, please:
1. Check the documentation
2. Review the logs
3. Contact support through Telegram: @your_support_channel

## License

This project is licensed under the MIT License - see the LICENSE file for details. 