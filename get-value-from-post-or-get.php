<?php

/**
 * リクエストメソッドに応じて値を取得する関数
 *
 * - POSTなら $_POST[$key]
 * - GETなら  $_SESSION[$key]
 * - それ以外は InvalidRequestMethodException
 * - データが存在しなければ SecurityException
 *
 * @param string $key 取得したいキー
 * @return mixed 取得した値
 * @throws InvalidRequestMethodException
 * @throws SecurityException
 */
function getRequestValue(string $key): mixed {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';

    if ($method === 'POST') {
        if (!isset($_POST[$key])) {
            throw new SecurityException("POST value for '{$key}' is missing");
        }
        return $_POST[$key];

    } elseif ($method === 'GET') {
        if (!isset($_SESSION[$key])) {
            throw new SecurityException("SESSION value for '{$key}' is missing");
        }
        return $_SESSION[$key];

    } else {
        throw InvalidRequestMethodException::fromCurrentRequest(['POST', 'GET']);
    }
}

// 使用例
// session_start();

// try {
//     // 例: POSTなら $_POST['username'] を取得、GETなら $_SESSION['username']
//     $username = getRequestValue('username');
//     echo "取得したユーザー名: " . htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

// } catch (InvalidRequestMethodException $e) {
//     $message = $e->getLogMessage();
//     error_log("[InvalidRequestMethod] " . $message);
//     http_response_code(405);
//     die("Method Not Allowed");

// } catch (SecurityException $e) {
//     error_log("[SecurityException] " . $e->getMessage());
//     http_response_code(400);
//     die("Bad Request");

// } catch (Exception $e) {
//     // その他の想定外エラー
//     error_log("[Unexpected] " . $e->getMessage());
//     http_response_code(500);
//     die("Internal Server Error");
// }

//
//  