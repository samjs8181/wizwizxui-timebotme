<?php
class Security {
    private $db;
    private $cache;
    private $logger;
    
    public function __construct($db, $cache, $logger) {
        $this->db = $db;
        $this->cache = $cache;
        $this->logger = $logger;
    }
    
    public function checkRequest() {
        $this->validateInput();
        $this->checkCSRF();
        $this->checkRateLimit();
    }
    
    public function validateUser($update) {
        if ($this->isUserBanned($update->message->from->id)) {
            return false;
        }
        return true;
    }
    
    public function isSpamming($update) {
        $userId = $update->message->from->id;
        $cacheKey = "spam_check_{$userId}";
        
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }
        
        $spamTime = $this->db->getSpamTime($userId);
        if ($spamTime) {
            $this->cache->set($cacheKey, $spamTime, 3600);
            return $spamTime;
        }
        
        return false;
    }
    
    public function isAdmin($userId) {
        return $this->db->isAdmin($userId);
    }
    
    private function validateInput() {
        foreach ($_POST as $key => $value) {
            if (is_string($value)) {
                $_POST[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }
    }
    
    private function checkCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                throw new Exception('CSRF token validation failed');
            }
        }
    }
    
    private function checkRateLimit() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $cacheKey = "rate_limit_{$ip}";
        
        if ($this->cache->has($cacheKey)) {
            $count = $this->cache->get($cacheKey);
            if ($count > 100) { // 100 requests per minute
                throw new Exception('Rate limit exceeded');
            }
        }
        
        $this->cache->increment($cacheKey);
        $this->cache->expire($cacheKey, 60); // 1 minute
    }
    
    private function isUserBanned($userId) {
        return $this->db->isUserBanned($userId);
    }
    
    public function generateHash() {
        return bin2hex(random_bytes(32));
    }
    
    public function validatePhoneNumber($phone) {
        return preg_match('/^\+98(\d+)$/', $phone) || 
               preg_match('/^98(\d+)$/', $phone) || 
               preg_match('/^0098(\d+)$/', $phone);
    }
} 