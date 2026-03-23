<?php
// =============================================
// api/auth.php - 로그인 / 회원가입 / 로그아웃
// =============================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'login':   doLogin();    break;
    case 'register': doRegister(); break;
    case 'logout':  doLogout();   break;
    default:        jsonResponse(['success' => false, 'message' => '잘못된 요청'], 400);
}

// ----- 로그인 -----
function doLogin(): void {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        jsonResponse(['success' => false, 'message' => '아이디와 비밀번호를 입력하세요.']);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT id, username, password, name FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(['success' => false, 'message' => '아이디 또는 비밀번호가 올바르지 않습니다.']);
    }

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['name']     = $user['name'];

    jsonResponse(['success' => true, 'message' => '로그인 성공', 'name' => $user['name']]);
}

// ----- 회원가입 -----
function doRegister(): void {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    if (empty($username) || empty($password) || empty($name) || empty($email)) {
        jsonResponse(['success' => false, 'message' => '모든 항목을 입력하세요.']);
    }
    if (strlen($username) < 4 || strlen($username) > 20) {
        jsonResponse(['success' => false, 'message' => '아이디는 4~20자로 입력하세요.']);
    }
    if (strlen($password) < 6) {
        jsonResponse(['success' => false, 'message' => '비밀번호는 6자 이상 입력하세요.']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => '올바른 이메일 형식이 아닙니다.']);
    }

    $db = getDB();

    // 중복 체크
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => '이미 사용 중인 아이디 또는 이메일입니다.']);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (username, password, name, email) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $hash, $name, $email]);

    jsonResponse(['success' => true, 'message' => '회원가입이 완료되었습니다.']);
}

// ----- 로그아웃 -----
function doLogout(): void {
    $_SESSION = [];
    session_destroy();
    jsonResponse(['success' => true]);
}
