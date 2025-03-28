<?php
class RateLimiter {
    private $cache;
    private $logger;
    private $limits = [
        'default' => ['requests' => 100, 'period' => 60], // 100 requests per minute
        'admin' => ['requests' => 1000, 'period' => 60], // 1000 requests per minute
        'payment' => ['requests' => 5, 'period' => 60], // 5 payment requests per minute
        'login' => ['requests' => 3, 'period' => 300], // 3 login attempts per 5 minutes
    ];
    
    public function __construct($cache, $logger) {
        $this->cache = $cache;
        $this->logger = $logger;
    }
    
    public function checkLimit($userId, $type = 'default') {
        try {
            $limit = $this->limits[$type] ?? $this->limits['default'];
            $key = "rate_limit:{$type}:{$userId}";
            
            $current = $this->cache->get($key) ?: 0;
            
            if ($current >= $limit['requests']) {
                $this->logger->logWarning("Rate limit exceeded for user {$userId} ({$type})");
                return false;
            }
            
            $this->cache->increment($key);
            $this->cache->expire($key, $limit['period']);
            
            return true;
        } catch (Exception $e) {
            $this->logger->logError($e);
            // If rate limiting fails, allow the request
            return true;
        }
    }
    
    public function resetLimit($userId, $type = 'default') {
        try {
            $key = "rate_limit:{$type}:{$userId}";
            $this->cache->delete($key);
            return true;
        } catch (Exception $e) {
            $this->logger->logError($e);
            return false;
        }
    }
    
    public function getRemainingRequests($userId, $type = 'default') {
        try {
            $limit = $this->limits[$type] ?? $this->limits['default'];
            $key = "rate_limit:{$type}:{$userId}";
            
            $current = $this->cache->get($key) ?: 0;
            return max(0, $limit['requests'] - $current);
        } catch (Exception $e) {
            $this->logger->logError($e);
            return 0;
        }
    }
    
    public function setLimit($type, $requests, $period) {
        $this->limits[$type] = [
            'requests' => $requests,
            'period' => $period
        ];
    }
    
    public function getLimitInfo($userId, $type = 'default') {
        $limit = $this->limits[$type] ?? $this->limits['default'];
        $remaining = $this->getRemainingRequests($userId, $type);
        
        return [
            'total' => $limit['requests'],
            'remaining' => $remaining,
            'period' => $limit['period']
        ];
    }
    
    public function isLimited($userId, $type = 'default') {
        return !$this->checkLimit($userId, $type);
    }
    
    public function getTimeUntilReset($userId, $type = 'default') {
        try {
            $limit = $this->limits[$type] ?? $this->limits['default'];
            $key = "rate_limit:{$type}:{$userId}";
            
            $ttl = $this->cache->ttl($key);
            return $ttl > 0 ? $ttl : 0;
        } catch (Exception $e) {
            $this->logger->logError($e);
            return 0;
        }
    }
} 