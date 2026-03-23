<?php
// =============================================
// config.php - 환경 설정
// =============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'checkweb');
define('DB_USER', 'checkweb');         // 실제 DB 계정으로 변경
define('DB_PASS', '1234');             // 실제 DB 비밀번호로 변경
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Site Monitor');
define('SESSION_LIFETIME', 3600 * 8); // 8시간

// 체크 주기 옵션
define('CHECK_INTERVALS', [
    1    => '1분',
    5    => '5분',
    30   => '30분',
    60   => '1시간',
    360  => '6시간',
    1440 => '24시간',
]);

// 오류 타입 레이블
define('ERROR_TYPES', [
    'http_error'       => 'HTTP 오류',
    'element_missing'  => '요소 없음',
    'img_broken'       => '이미지 오류',
    'class_empty'      => '클래스 빈값',
    'connection_error' => '연결 오류',
]);

// CURL 타임아웃 (초)
define('CURL_TIMEOUT', 15);

ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_set_cookie_params(SESSION_LIFETIME);
