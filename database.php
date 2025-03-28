<?php
class Database {
    private $connection;
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
        $this->connect();
    }
    
    private function connect() {
        try {
            $this->connection = new mysqli(
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME
            );
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8mb4");
        } catch (Exception $e) {
            $this->logger->logError($e);
            throw $e;
        }
    }
    
    public function query($sql, $params = [], $types = '') {
        try {
            $stmt = $this->connection->prepare($sql);
            
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $this->connection->error);
            }
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            
            return $result;
        } catch (Exception $e) {
            $this->logger->logError($e);
            throw $e;
        }
    }
    
    public function isUserBanned($userId) {
        $sql = "SELECT * FROM `users` WHERE `userid` = ? AND `step` = 'banned'";
        $result = $this->query($sql, [$userId], 'i');
        return $result->num_rows > 0;
    }
    
    public function getSpamTime($userId) {
        $sql = "SELECT `spam_time` FROM `users` WHERE `userid` = ?";
        $result = $this->query($sql, [$userId], 'i');
        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['spam_time'];
        }
        return false;
    }
    
    public function isAdmin($userId) {
        $sql = "SELECT `isAdmin` FROM `users` WHERE `userid` = ?";
        $result = $this->query($sql, [$userId], 'i');
        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['isAdmin'] == 1;
        }
        return false;
    }
    
    public function createPayment($hashId, $userId, $amount) {
        $sql = "INSERT INTO `pays` (`hash_id`, `user_id`, `type`, `plan_id`, `volume`, `day`, `price`, `request_date`, `state`)
                VALUES (?, ?, 'INCREASE_WALLET', '0', '0', '0', ?, ?, 'pending')";
        $time = time();
        return $this->query($sql, [$hashId, $userId, $amount, $time], 'siis');
    }
    
    public function updateUserWallet($userId, $amount, $operation = '+') {
        $sql = "UPDATE `users` SET `wallet` = `wallet` {$operation} ? WHERE `userid` = ?";
        return $this->query($sql, [$amount, $userId], 'ii');
    }
    
    public function getUserInfo($userId) {
        $sql = "SELECT * FROM `users` WHERE `userid` = ?";
        $result = $this->query($sql, [$userId], 'i');
        return $result->fetch_assoc();
    }
    
    public function setUser($userId, $key, $value) {
        $sql = "UPDATE `users` SET `{$key}` = ? WHERE `userid` = ?";
        return $this->query($sql, [$value, $userId], 'si');
    }
    
    public function __destruct() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
} 