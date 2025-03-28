<?php
class Cache {
    private $redis;
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
        $this->connect();
    }
    
    private function connect() {
        try {
            $this->redis = new Redis();
            $this->redis->connect(REDIS_HOST, REDIS_PORT);
            
            if (REDIS_PASSWORD) {
                $this->redis->auth(REDIS_PASSWORD);
            }
        } catch (Exception $e) {
            $this->logger->logError($e);
            // Fallback to file cache if Redis is not available
            $this->useFileCache();
        }
    }
    
    private function useFileCache() {
        $this->redis = null;
        $this->cacheDir = __DIR__ . '/cache/';
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }
    
    public function get($key) {
        try {
            if ($this->redis) {
                return $this->redis->get($key);
            } else {
                $file = $this->cacheDir . md5($key);
                if (file_exists($file)) {
                    $data = unserialize(file_get_contents($file));
                    if ($data['expire'] > time()) {
                        return $data['value'];
                    }
                }
                return false;
            }
        } catch (Exception $e) {
            $this->logger->logError($e);
            return false;
        }
    }
    
    public function set($key, $value, $ttl = 3600) {
        try {
            if ($this->redis) {
                return $this->redis->setex($key, $ttl, $value);
            } else {
                $file = $this->cacheDir . md5($key);
                $data = [
                    'value' => $value,
                    'expire' => time() + $ttl
                ];
                return file_put_contents($file, serialize($data));
            }
        } catch (Exception $e) {
            $this->logger->logError($e);
            return false;
        }
    }
    
    public function has($key) {
        try {
            if ($this->redis) {
                return $this->redis->exists($key);
            } else {
                $file = $this->cacheDir . md5($key);
                if (file_exists($file)) {
                    $data = unserialize(file_get_contents($file));
                    return $data['expire'] > time();
                }
                return false;
            }
        } catch (Exception $e) {
            $this->logger->logError($e);
            return false;
        }
    }
    
    public function delete($key) {
        try {
            if ($this->redis) {
                return $this->redis->del($key);
            } else {
                $file = $this->cacheDir . md5($key);
                if (file_exists($file)) {
                    return unlink($file);
                }
                return true;
            }
        } catch (Exception $e) {
            $this->logger->logError($e);
            return false;
        }
    }
    
    public function increment($key) {
        try {
            if ($this->redis) {
                return $this->redis->incr($key);
            } else {
                $value = $this->get($key) ?: 0;
                $value++;
                $this->set($key, $value);
                return $value;
            }
        } catch (Exception $e) {
            $this->logger->logError($e);
            return false;
        }
    }
    
    public function expire($key, $ttl) {
        try {
            if ($this->redis) {
                return $this->redis->expire($key, $ttl);
            } else {
                $file = $this->cacheDir . md5($key);
                if (file_exists($file)) {
                    $data = unserialize(file_get_contents($file));
                    $data['expire'] = time() + $ttl;
                    return file_put_contents($file, serialize($data));
                }
                return false;
            }
        } catch (Exception $e) {
            $this->logger->logError($e);
            return false;
        }
    }
    
    public function __destruct() {
        if ($this->redis) {
            $this->redis->close();
        }
    }
} 