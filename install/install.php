<?php
session_start();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Check if already installed
if (file_exists('../settings/values.php') && $step == 1) {
    die('Bot is already installed. Please remove install.php for security reasons.');
}

// Function to check requirements
function checkRequirements() {
    $requirements = [
        'PHP Version (>= 7.4)' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'cURL Extension' => extension_loaded('curl'),
        'Redis Extension' => extension_loaded('redis'),
        'GD Extension' => extension_loaded('gd'),
        'JSON Extension' => extension_loaded('json'),
        'ZIP Extension' => extension_loaded('zip'),
        'config Directory Writable' => is_writable('../settings'),
        'logs Directory Writable' => is_writable('../logs'),
        'cache Directory Writable' => is_writable('../cache')
    ];
    
    return $requirements;
}

// Function to test database connection
function testDatabaseConnection($host, $user, $pass, $name) {
    try {
        $dsn = "mysql:host=$host;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Try to create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");
        
        return true;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
}

// Function to test Redis connection
function testRedisConnection($host, $port, $pass = '') {
    try {
        $redis = new Redis();
        $redis->connect($host, $port);
        if ($pass) {
            $redis->auth($pass);
        }
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // Requirements check
            $requirements = checkRequirements();
            $allMet = true;
            foreach ($requirements as $requirement => $met) {
                if (!$met) {
                    $allMet = false;
                    break;
                }
            }
            if ($allMet) {
                header('Location: install.php?step=2');
                exit;
            } else {
                $error = 'Please fix the requirements before proceeding.';
            }
            break;
            
        case 2:
            // Database configuration
            $dbHost = $_POST['db_host'] ?? 'localhost';
            $dbUser = $_POST['db_user'] ?? '';
            $dbPass = $_POST['db_pass'] ?? '';
            $dbName = $_POST['db_name'] ?? 'bot_db';
            
            $dbTest = testDatabaseConnection($dbHost, $dbUser, $dbPass, $dbName);
            if ($dbTest === true) {
                $_SESSION['db_config'] = [
                    'host' => $dbHost,
                    'user' => $dbUser,
                    'pass' => $dbPass,
                    'name' => $dbName
                ];
                header('Location: install.php?step=3');
                exit;
            } else {
                $error = 'Database connection failed: ' . $dbTest;
            }
            break;
            
        case 3:
            // Redis configuration
            $redisHost = $_POST['redis_host'] ?? 'localhost';
            $redisPort = $_POST['redis_port'] ?? 6379;
            $redisPass = $_POST['redis_pass'] ?? '';
            
            $redisTest = testRedisConnection($redisHost, $redisPort, $redisPass);
            if ($redisTest === true) {
                $_SESSION['redis_config'] = [
                    'host' => $redisHost,
                    'port' => $redisPort,
                    'pass' => $redisPass
                ];
                header('Location: install.php?step=4');
                exit;
            } else {
                $error = 'Redis connection failed: ' . $redisTest;
            }
            break;
            
        case 4:
            // Bot configuration
            $botToken = $_POST['bot_token'] ?? '';
            $adminId = $_POST['admin_id'] ?? '';
            $botUsername = $_POST['bot_username'] ?? '';
            $botUrl = $_POST['bot_url'] ?? '';
            
            if (empty($botToken) || empty($adminId) || empty($botUsername) || empty($botUrl)) {
                $error = 'All fields are required.';
            } else {
                $_SESSION['bot_config'] = [
                    'token' => $botToken,
                    'admin' => $adminId,
                    'username' => $botUsername,
                    'url' => $botUrl
                ];
                header('Location: install.php?step=5');
                exit;
            }
            break;
            
        case 5:
            // Payment gateway configuration
            $nextpayApiKey = $_POST['nextpay_api_key'] ?? '';
            $zarinpalMerchant = $_POST['zarinpal_merchant'] ?? '';
            $nowPaymentApiKey = $_POST['nowpayment_api_key'] ?? '';
            
            $_SESSION['payment_config'] = [
                'nextpay' => $nextpayApiKey,
                'zarinpal' => $zarinpalMerchant,
                'nowpayment' => $nowPaymentApiKey
            ];
            
            // Create configuration file
            $config = "<?php\n";
            $config .= "// Database Configuration\n";
            $config .= "\$dbUserName = '" . $_SESSION['db_config']['user'] . "';\n";
            $config .= "\$dbPassword = '" . $_SESSION['db_config']['pass'] . "';\n";
            $config .= "\$dbName = '" . $_SESSION['db_config']['name'] . "';\n";
            $config .= "\n";
            $config .= "// Bot Configuration\n";
            $config .= "\$botToken = '" . $_SESSION['bot_config']['token'] . "';\n";
            $config .= "\$admin = '" . $_SESSION['bot_config']['admin'] . "';\n";
            $config .= "\$botUsername = '" . $_SESSION['bot_config']['username'] . "';\n";
            $config .= "\$botUrl = '" . $_SESSION['bot_config']['url'] . "';\n";
            $config .= "\n";
            $config .= "// Payment Gateway Configuration\n";
            $config .= "\$nextpayApiKey = '" . $_SESSION['payment_config']['nextpay'] . "';\n";
            $config .= "\$zarinpalMerchant = '" . $_SESSION['payment_config']['zarinpal'] . "';\n";
            $config .= "\$nowPaymentApiKey = '" . $_SESSION['payment_config']['nowpayment'] . "';\n";
            
            if (file_put_contents('../settings/values.php', $config)) {
                // Import database schema
                $dbConfig = $_SESSION['db_config'];
                $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $sql = file_get_contents('../createDB.php');
                $pdo->exec($sql);
                
                $success = 'Installation completed successfully!';
                $step = 6;
            } else {
                $error = 'Failed to create configuration file.';
            }
            break;
    }
}

// HTML Header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WizWizXUI TimeBot Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .install-container { max-width: 800px; margin: 50px auto; }
        .step-indicator { margin-bottom: 30px; }
        .requirement-item { margin-bottom: 10px; }
        .requirement-met { color: #198754; }
        .requirement-failed { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container install-container">
        <h1 class="text-center mb-4">WizWizXUI TimeBot Installation</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <div class="step-indicator">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo ($step / 6) * 100; ?>%"></div>
                    </div>
                </div>
                
                <?php if ($step == 1): ?>
                    <h3>Step 1: System Requirements</h3>
                    <div class="requirements-list">
                        <?php
                        $requirements = checkRequirements();
                        foreach ($requirements as $requirement => $met):
                        ?>
                            <div class="requirement-item">
                                <span class="<?php echo $met ? 'requirement-met' : 'requirement-failed'; ?>">
                                    <?php echo $met ? '✓' : '✗'; ?>
                                </span>
                                <?php echo htmlspecialchars($requirement); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" class="mt-4">
                        <button type="submit" class="btn btn-primary">Continue</button>
                    </form>
                    
                <?php elseif ($step == 2): ?>
                    <h3>Step 2: Database Configuration</h3>
                    <form method="post" class="mt-4">
                        <div class="mb-3">
                            <label class="form-label">Database Host</label>
                            <input type="text" name="db_host" class="form-control" value="localhost" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Username</label>
                            <input type="text" name="db_user" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Password</label>
                            <input type="password" name="db_pass" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Name</label>
                            <input type="text" name="db_name" class="form-control" value="bot_db" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Continue</button>
                    </form>
                    
                <?php elseif ($step == 3): ?>
                    <h3>Step 3: Redis Configuration</h3>
                    <form method="post" class="mt-4">
                        <div class="mb-3">
                            <label class="form-label">Redis Host</label>
                            <input type="text" name="redis_host" class="form-control" value="localhost" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Redis Port</label>
                            <input type="number" name="redis_port" class="form-control" value="6379" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Redis Password (optional)</label>
                            <input type="password" name="redis_pass" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary">Continue</button>
                    </form>
                    
                <?php elseif ($step == 4): ?>
                    <h3>Step 4: Bot Configuration</h3>
                    <form method="post" class="mt-4">
                        <div class="mb-3">
                            <label class="form-label">Bot Token</label>
                            <input type="text" name="bot_token" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Admin ID</label>
                            <input type="text" name="admin_id" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bot Username</label>
                            <input type="text" name="bot_username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bot URL</label>
                            <input type="url" name="bot_url" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Continue</button>
                    </form>
                    
                <?php elseif ($step == 5): ?>
                    <h3>Step 5: Payment Gateway Configuration</h3>
                    <form method="post" class="mt-4">
                        <div class="mb-3">
                            <label class="form-label">NextPay API Key</label>
                            <input type="text" name="nextpay_api_key" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Zarinpal Merchant ID</label>
                            <input type="text" name="zarinpal_merchant" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">NowPayment API Key</label>
                            <input type="text" name="nowpayment_api_key" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary">Complete Installation</button>
                    </form>
                    
                <?php elseif ($step == 6): ?>
                    <h3>Installation Complete!</h3>
                    <div class="alert alert-success">
                        The bot has been installed successfully. Please delete this installation file for security reasons.
                    </div>
                    <div class="mt-4">
                        <a href="../" class="btn btn-primary">Go to Bot</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
