<?php


// $contents = [
//         // 'session_id' => 'abc123',
//         // 'ip_address' => '192.168.0.1',
//         // 'simple_ua' => 'Mozilla/5.0',
//         'is_no_ua' => 0,
//         'is_ua_mismatch' => 0,
//         'recaptcha_solved' => 0
//     ];  


    class MockRequestContent1 implements RequestContent {
            // private string $sessionId;
            // private string $ipAddress;
            // private string $simpleUa;
        private int    $isNoUa;
        private int    $isUaMismatch;
        private int    $recaptchaSolved;

        public function __construct(array $content) {
            // $this->sessionId = $content['session_id'];
            // $this->ipAddress = $content['ip_address'];
            // $this->simpleUa = $content['simple_ua'];
            $this->isNoUa = $content['is_no_ua'];
            $this->isUaMismatch = $content['is_ua_mismatch'];
            $this->recaptchaSolved = $content['recaptcha_solved'];
        }

        // public function getSessionId(): string {
        //     return $this->sessionId;
        // }

        // public function getIpAddress(): string {
        //     return $this->ipAddress;
        // }

        // public function getSimpleUa(): string {
        //     return $this->simpleUa;
        // }

        public function getIsNoUa(): int {
            return $this->isNoUa;
        }

        public function getIsUaMismatch(): int {
            return $this->isUaMismatch;
        }

        public function getRecaptchaSolved(): int {
            return $this->recaptchaSolved;
        }
    }
    
    
