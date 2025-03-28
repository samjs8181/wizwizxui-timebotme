<?php
class Logger {
    private $logFile;
    private $logLevel;
    
    public function __construct($logFile = 'bot.log', $logLevel = 'INFO') {
        $this->logFile = __DIR__ . '/logs/' . $logFile;
        $this->logLevel = $logLevel;
        
        if (!file_exists(__DIR__ . '/logs/')) {
            mkdir(__DIR__ . '/logs/', 0777, true);
        }
    }
    
    public function log($message, $level = 'INFO') {
        if (!$this->shouldLog($level)) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    public function logError($error) {
        $message = $error instanceof Exception ? 
            $error->getMessage() . ' in ' . $error->getFile() . ' on line ' . $error->getLine() :
            $error;
        
        $this->log($message, 'ERROR');
    }
    
    public function logInfo($message) {
        $this->log($message, 'INFO');
    }
    
    public function logWarning($message) {
        $this->log($message, 'WARNING');
    }
    
    public function logDebug($message) {
        $this->log($message, 'DEBUG');
    }
    
    private function shouldLog($level) {
        $levels = [
            'DEBUG' => 0,
            'INFO' => 1,
            'WARNING' => 2,
            'ERROR' => 3
        ];
        
        return $levels[$level] >= $levels[$this->logLevel];
    }
    
    public function rotateLogs() {
        if (file_exists($this->logFile) && filesize($this->logFile) > 5 * 1024 * 1024) { // 5MB
            $archiveFile = $this->logFile . '.' . date('Y-m-d');
            rename($this->logFile, $archiveFile);
            
            // Compress old logs
            if (function_exists('gzencode')) {
                $compressedFile = $archiveFile . '.gz';
                $content = file_get_contents($archiveFile);
                file_put_contents($compressedFile, gzencode($content));
                unlink($archiveFile);
            }
            
            // Keep only last 30 days of logs
            $files = glob(__DIR__ . '/logs/*.gz');
            $now = time();
            foreach ($files as $file) {
                if (is_file($file)) {
                    if ($now - filemtime($file) >= 30 * 24 * 60 * 60) { // 30 days
                        unlink($file);
                    }
                }
            }
        }
    }
    
    public function getLogs($lines = 100) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $logs = array_slice(file($this->logFile), -$lines);
        return array_map('trim', $logs);
    }
    
    public function clearLogs() {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }
} 