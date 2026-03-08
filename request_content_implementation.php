<?php

class RequestContentImplementation implements RequestContentInterface
{

    // interface の実装として、必須のメソッドは３つです。
    	// public function getIsNoUa(): int{}

        // public function getIsUaMismatch(): int{}

        // public function getRecaptchaSolved(): int{}

    // プロパティ
    // private $userAgent; 関数内で定義してもいいですが、
    // プロパティとして定義しておくと便利です。
    private $userAgent;
    // private $firstSimpleUa;
    // private $currentSimpleUa;

    // コンストラクタ
    public function __construct()
    {
        // コンストラクタ内で、fetchUa() を呼び出して
        // $this->userAgent にセットしておくと便利です。
        $this->userAgent = $this->fetchUa();
    }

    	public function getIsNoUa(): int{
            // ユーザーエージェントがない場合は 1 を返す
            // それ以外の場合は 0 を返すことを想定
            $this->userAgent = $this->fetchUa();
            if ($this->userAgent === '') {
                return 1;
            } else {
                return 0;
            }
        }

        public function getIsUaMismatch(): int{
            // ユーザーエージェントが
            // 初回、もしくは前回のアクセスと異なる場合は 1 を返す
            // それ以外の場合は 0 を返すことを想定
            // （実装例）
            $firstSimpleUa = $this->fetchFirstSimpleUa();
            $currentSimpleUa = $this->makeSimpleUa();
            if ($firstSimpleUa !== $currentSimpleUa) {
                return 1;
            } else {
                return 0;
            }
        }

        private function fetchFirstSimpleUa(): string{
            // 初回の simplified ユーザーエージェントを server から取得する実装
            // 最初にアクセスすると想定するページ
            // ログインページなどで、session に
            //  $_SESSION['first_simple_ua'] として
            // 保存しておくとことにします。
            if (isset($_SESSION['first_simple_ua'])) {
                return $_SESSION['first_simple_ua'];
            } else {
                // もし session に保存されていなければ、現在の simplified ユーザーエージェントを返す
                return $this->makeSimpleUa();
            }
        }

        public function getRecaptchaSolved(): int{
            // reCAPTCHA を通過し、かつ
            // IP アドレスと session id が変化していない場合は 1 を返す
            // それ以外の場合は 0 を返すことを想定
        }



    // UA を取得するメソッド
        private function fetchUa(): string{
            // UA がセットされていたら
            // $this->userAgent にセットして返す
            // そうでなければ空文字を返す
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $this->userAgent = $_SERVER['HTTP_USER_AGENT'];
                return $this->userAgent;
            } else {
                return '';
            }

        }



    public function getSessionId(): string
    {
        // セッションIDを安全に取得する実装
    }

    public function getIpAddress(): string
    {
        // ユーザーのIPアドレスを正確に取得する実装
    }
}
