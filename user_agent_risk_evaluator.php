<?php
/**
 * UserAgentRiskEvaluator は
 * コンストラクターで
 * インターフェイス
 * RequestContent と
 * UaRepository の実装
 * を受け取り、
 * その情報をもとに
 * ユーザーエージェントの
 * リスク評価を$scoreSessionId と $scoreIp で行います。
 * RiskEvaluationResult クラスを使って
 * リスク評価の結果を＄decisionと＄securityLevelで返します。
 * @package student
 */

class UserAgentRiskEvaluator {

    const ACCESS_THRESHOLD_SESSION = 60;
    const ACCESS_THRESHOLD_IP      = 600;

    private RequestContent $requestContent; // インターフェイス RequestContent の実装
    private UaRepository   $uaRepository;   // インターフェイス UaRepository の実装


    private string $sessionId; // セッションID
    private string $ipAddress; // IPアドレス

    private int $previousScoreSession; // セッションIDごとの前回のスコア
    private int $previousScoreIp;      // IPアドレスごとの前回の


    // private int $scoreSessionId; // セッションIDごとのスコア
    // private int $scoreIp;        // IPアドレスごとのスコア


    private int $accessCountSession;  // セッションIDごとのアクセス回数
    private int $accessCountIp;       // IPアドレスごとのアクセス回数
    
    private int $isNoUa;  // User-Agentがない場合のフラグ
    private int $isUaMismatch; // User-Agent不一致のフラグ

    private int $isOverThresholdSession; // セッションIDごとのアクセス回数が閾値を超えているか
    private int $isOverThresholdIp;      // IPアドレスごとのアクセス回数が閾値を超えているか

    private int $isDecreasedSession; // スコアが減少したかどうかのフラグ
    private int $isDecreasedIp;      // IPアドレスごとのスコアが減少したかどうかのフラグ

    private int $isNoAnomalySession; // セッションIDに異常がない場合のフラグ
    private int $isNoAnomalyIp;      // IPアドレスに異常がない場合のフラグ

    private int $recaptchaSolved; // reCAPTCHAを解いたかどうかのフラグ

    private int $isSuspiciousAccess; // 疑わしいアクセスかどうかのフラグ

    public function __construct(RequestContent $requestContent, UaRepository $uaRepository) {
        $this->requestContent = $requestContent;
        $this->uaRepository   = $uaRepository;

        // $this->sessionId = $this->requestContent->getSessionId();
        // $this->ipAddress = $this->requestContent->getIpAddress();

        $this->previousScoreSession = $this->uaRepository->getScoreSession();
        $this->previousScoreIp      = $this->uaRepository->getScoreIp();

        $this->accessCountSession = 
        $this->uaRepository->getAccessCountSession();
        $this->accessCountIp      = 
        $this->uaRepository->getAccessCountIp();
        
        $this->isOverThresholdSession = 
        $this->isOverThreshold($this->accessCountSession,
                                self::ACCESS_THRESHOLD_SESSION);
        $this->isOverThresholdIp = 
        $this->isOverThreshold($this->accessCountIp,
                                self::ACCESS_THRESHOLD_IP);

        $this->isNoUa = 
        $this->requestContent->getIsNoUa();
        $this->isUaMismatch = 
        $this->requestContent->getIsUaMismatch();

        $this->isDecreasedSession = 
        $this->uaRepository->getIsDecreasedSession();
        $this->isDecreasedIp = 
        $this->uaRepository->getIsDecreasedIp();

        $this->isNoAnomalySession = 
        $this->uaRepository->getIsNoAnomalySession();
        $this->isNoAnomalyIp = 
        $this->uaRepository->getIsNoAnomalyIp();

        $this->recaptchaSolved = 
        $this->requestContent->getRecaptchaSolved();

        $this->isSuspiciousAccess = 
        
        $this->getIsSuspiciousAccess();
    }
    /**
     * アクセス回数が閾値を超えているかどうかを判定するメソッド
     * @param int $accessCount アクセス回数
     * @param int $threshold 閾値
     * @return int 閾値を超えている場合は1、そうでない場合は0を返す
     */
    public function isOverThreshold(int $accessCount, int $threshold): int {
        if ($accessCount >= $threshold) {
            return 1;
        } else {
            return 0
            ;
        }
    }

    public function getIsSuspiciousAccess(): int {
        if ($this->isNoUa === 1) {
            return 1;
        } 
        if ($this->isUaMismatch === 1) {
            return 1;
        }
        if ($this->isOverThresholdSession === 1) {
            return 1;
        }
        if ($this->isOverThresholdIp === 1) {
            return 1;
        }
        return 0;
    }

    public function evaluate(): RiskEvaluationResult {
        // スコアを取得
        $currentScoreSession  = $this->previousScoreSession;
        $currentScoreIp       = $this->previousScoreIp;

        if ($this->isNoUa===1) {
            $currentScoreSession += 1;
            $currentScoreIp      += 1;
        }

        if ($this->isUaMismatch===1) {
            $currentScoreSession += 2;
            $currentScoreIp      += 2;
        }

        if ($this->isOverThresholdSession === 1) {
            $currentScoreSession += 3;
        }

        if ($this->isOverThresholdIp === 1) {
            $currentScoreIp += 3;
        }

        if ($this->isDecreasedSession !== 1) {
            if ($this->isNoAnomalySession === 1) {
                $currentScoreSession -= 1;
                //  ゼロ以下にならないようにする
                if ($currentScoreSession < 0) {
                    $currentScoreSession = 0;
                }
            }
            if ($this->recaptchaSolved === 1) {
                $currentScoreSession -= 4;
                //  ゼロ以下にならないようにする
                if ($currentScoreSession < 0) {
                    $currentScoreSession = 0;
                }
            }    
        }

        if ($this->isDecreasedIp !== 1) {
            if ($this->isNoAnomalyIp === 1) {
                $currentScoreIp -= 1;
                //  ゼロ以下にならないようにする
                if ($currentScoreIp < 0) {
                    $currentScoreIp = 0;
                }
            }
            if ($this->recaptchaSolved === 1) {
                $currentScoreIp -= 4;
                //  ゼロ以下にならないようにする
                if ($currentScoreIp < 0) {
                    $currentScoreIp = 0;
                }
            }    
        }

        return new RiskEvaluationResult($currentScoreSession, $currentScoreIp);
    }

    public function getIsOverThresholdSession(): int {
        return $this->isOverThresholdSession;
    }

    
}