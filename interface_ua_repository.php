<?php

interface UaRepository
{
    
    //  追跡対象に対するスコアを返す getter
    public function getScoreSession(string $sessionId): int;
    public function getScoreIp(string $ipAddress): int;

    //  data base から score を取得する
    public function fetchScore(string $subjectKey, string $subjectType): array;
    

    // アクセス回数を返す getter
    public function getAccessCountSession(string $sessionId): int;
    public function getAccessCountIp(string $ipAddress): int;

    //  data base からアクセス回数を配列で取得する
    public function fetchAccessCountArray(string $sessionId, string $ipAddress): array;
    //  配列からアクセス回数を取得する
    public function plunkAccessCountSession(array $accessCountArray): int;
    public function plunkAccessCountIp(array $accessCountArray): int;


    // スコアが減少したかどうかを返す getter（1/0想定）
    public function getIsDecreasedSession(string $sessionId): int;
    public function getIsDecreasedIp(string $ipAddress): int;
    
    // data base でスコアが減少したかどうかを確認する
    public function checkIsDecreasedLast30Minutes(string $subjectType, string $subjectKey): int;


    // 追跡対象に異常がない場合は 1 を返す getter
    public function getIsNoAnomalySession(string $sessionId): int;
    public function getIsNoAnomalyIp(string $ipAddress): int;

    // data base で異常フラグがあるレコードの数を配列で取得する
    public function checkIsNoAnomalyLast10Minutes(string $sessionId, string $ipAddress): int;
    // 配列の要素の数から、
    // セッションID/IPアドレスに異常がないかどうかを取得する
    public function plunkIsNoAnomalySession(array $isNoAnomalyArray): int;
    public function plunkIsNoAnomalyIp(array $isNoAnomalyArray): int;


    // UA に関するアクセスログを保存する
    public function recordUserAgentLogs(
        string $sessionId,
        string $ipAddress,
        string $simpleUa,
        int    $isUaMismatch
    ): void;

    // 異常イベントを保存する
    public function recordAnomalyEvents(
        string $sessionId,
        string $ipAddress,
        int    $isNoUa,
        int    $isUaMismatch,
        int    $isOverThresholdSession,
        int    $isOverThresholdIp
    ): void;

    // UA スコアを保存する（session / ip 共通）
    // subjectKey と subjectType が
    // 同じレコードがあれば更新、なければ新規作成する
    public function recordUaScores(
        string $subjectKey,
        string $subjectType,
        int    $score
    ): void;

    // UA スコア履歴を保存する（session / ip 共通）
    public function recordUaScoreHistory(
        string $subjectKey,
        string $subjectType,
        int    $isNoUa,
        int    $isUaMismatch,
        int    $isOverThresholdSession,
        int    $isOverThresholdIp,
        int    $isNoAnomaly,  //last10minutes
        int    $recaptchaSolved,
        int    $isDecreased  //last30minutes
    ): void;
}