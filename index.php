<?php
// =============================================
// index.php - 로그인 페이지 (기본 진입점)
// =============================================

require_once __DIR__ . '/config.php';
// test

if (session_status() === PHP_SESSION_NONE) session_start();

// 이미 로그인된 경우 대시보드로
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">
            <span class="logo-icon">🛰️</span>
            <h1><?= APP_NAME ?></h1>
            <p>사이트 모니터링 시스템</p>
        </div>

        <div id="login-alert" style="display:none"></div>

        <form id="login-form">
            <div class="form-group">
                <label for="login-id">아이디 <span class="required">*</span></label>
                <input type="text" id="login-id" name="username" class="form-control" placeholder="아이디를 입력하세요" autocomplete="username" required>
            </div>
            <div class="form-group">
                <label for="login-pw">비밀번호 <span class="required">*</span></label>
                <input type="password" id="login-pw" name="password" class="form-control" placeholder="비밀번호를 입력하세요" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg" id="btn-login">로그인</button>
        </form>

        <div class="auth-divider">또는</div>

        <a href="register.php" class="btn btn-outline btn-block" style="justify-content:center">
            회원가입
        </a>
    </div>
</div>

<div id="toast-container"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
$(function () {
    function showAlert(msg, type) {
        $('#login-alert').attr('class', 'alert alert-' + type).html(
            '<span>' + (type === 'danger' ? '⚠️' : '✅') + '</span><span>' + msg + '</span>'
        ).show();
    }

    $('#login-form').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btn-login').prop('disabled', true).html('<span class="spinner"></span> 로그인 중...');
        $('#login-alert').hide();

        $.post('api/auth.php?action=login', {
            username: $('#login-id').val(),
            password: $('#login-pw').val(),
        }, function (res) {
            if (res.success) {
                showAlert('로그인 성공! 이동 중...', 'success');
                setTimeout(() => location.href = 'dashboard.php', 600);
            } else {
                $btn.prop('disabled', false).text('로그인');
                showAlert(res.message, 'danger');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('로그인');
            showAlert('서버 연결에 실패했습니다.', 'danger');
        });
    });

    // Enter 키 처리
    $('#login-id, #login-pw').on('keydown', function (e) {
        if (e.key === 'Enter') $('#login-form').submit();
    });
});
</script>
</body>
</html>
