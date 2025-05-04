<?php

namespace Sybil\Tests\Publications\Liturgy;

class CurlManager {
    private $ch;
    private $logger;

    public function __construct($logger = null) {
        $this->ch = curl_init();
        $this->logger = $logger;
        
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Sybil/1.0'
        ]);
    }

    public function get($url) {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        
        $response = curl_exec($this->ch);
        $error = curl_error($this->ch);
        $info = curl_getinfo($this->ch);
        
        if ($error) {
            if ($this->logger) {
                $this->logger->error("CURL Error: $error");
            }
            throw new \Exception("CURL Error: $error");
        }
        
        if ($this->logger) {
            $this->logger->debug("CURL Response", [
                'url' => $url,
                'status' => $info['http_code'],
                'time' => $info['total_time']
            ]);
        }
        
        return $response;
    }

    public function post($url, $data) {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
        
        $response = curl_exec($this->ch);
        $error = curl_error($this->ch);
        $info = curl_getinfo($this->ch);
        
        if ($error) {
            if ($this->logger) {
                $this->logger->error("CURL Error: $error");
            }
            throw new \Exception("CURL Error: $error");
        }
        
        if ($this->logger) {
            $this->logger->debug("CURL Response", [
                'url' => $url,
                'status' => $info['http_code'],
                'time' => $info['total_time']
            ]);
        }
        
        return $response;
    }

    public function __destruct() {
        if ($this->ch) {
            curl_close($this->ch);
        }
    }
} 