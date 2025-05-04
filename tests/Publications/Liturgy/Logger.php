<?php

namespace Sybil\Tests\Publications\Liturgy;

class Logger {
    private $logFile;
    private $logLevel;

    public function __construct($logFile = null, $logLevel = 'INFO') {
        $this->logFile = $logFile;
        $this->logLevel = $logLevel;
    }

    public function log($message, $level = 'INFO') {
        if ($this->shouldLog($level)) {
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[$timestamp] [$level] $message\n";
            
            if ($this->logFile) {
                file_put_contents($this->logFile, $logMessage, FILE_APPEND);
            } else {
                echo $logMessage;
            }
        }
    }

    private function shouldLog($level) {
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
        return $levels[$level] >= $levels[$this->logLevel];
    }

    public function debug($message) {
        $this->log($message, 'DEBUG');
    }

    public function info($message) {
        $this->log($message, 'INFO');
    }

    public function warning($message) {
        $this->log($message, 'WARNING');
    }

    public function error($message) {
        $this->log($message, 'ERROR');
    }
} 