<?php
// =============================================
// register.php - 회원가입 페이지
// =============================================

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

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
    <title>회원가입 - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">
            <span class="logo-icon">🛰️</span>
            <h1>회원가입</h1>
            <p><?= APP_NAME ?></p>
        </div>

        <div id="reg-alert" style="display:none"></div>

        <form id="reg-form">
            <div class="form-group">
                <label for="reg-username">아이디 <span class="required">*</span></label>
                <input type="text" id="reg-username" class="form-control" placeholder="4~20자 영문/숫자" required minlength="4" maxlength="20">
                <small class="text-muted">영문자, 숫자만 사용 가능 (4~20자)</small>
            </div>
            <div class="form-group">
                <label for="reg-password">비밀번호 <span class="required">*</span></label>
                <input type="password" id="reg-password" class="form-control" placeholder="6자 이상" required minlength="6">
            </div>
            <div class="form-group">
                <label for="reg-password2">비밀번호 확인 <span class="required">*</span></label>
                <input type="password" id="reg-password2" class="form-control" placeholder="비밀번호 재입력" required>
            </div>
            <div class="form-group">
                <label for="reg-name">이름 <span class="required">*</span></label>
                <input type="text" id="reg-name" class="form-control" placeholder="이름을 입력하세요" required>
            </div>
            <div class="form-group">
                <label for="reg-email">이메일 <span class="required">*</span></label>
                <input type="email" id="reg-email" class="form-control" placeholder="example@email.com" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg" id="btn-register">회원가입</button>
        </form>

        <div class="auth-divider">이미 계정이 있으신가요?</div>

        <a href="index.php" class="btn btn-outline btn-block" style="justify-content:center">
            로그인으로 돌아가기
        </a>
    </div>
</div>

<div id="toast-container"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
$(function () {
    function showAlert(msg, type) {
        $('#reg-alert').attr('class', 'alert alert-' + type).html(
            '<span>' + (type === 'danger' ? '⚠️' : '✅') + '</span><span>' + msg + '</span>'
        ).show();
    }

    $('#reg-form').on('submit', function (e) {
        e.preventDefault();
        $('#reg-alert').hide();

        const pw  = $('#reg-password').val();
        const pw2 = $('#reg-password2').val();
        if (pw !== pw2) {
            showAlert('비밀번호가 일치하지 않습니다.', 'danger');
            return;
        }

        const $btn = $('#btn-register').prop('disabled', true).html('<span class="spinner"></span> 처리 중...');

        $.post('api/auth.php?action=register', {
            username: $('#reg-username').val().trim(),
            password: pw,
            name:     $('#reg-name').val().trim(),
            email:    $('#reg-email').val().trim(),
        }, function (res) {
            if (res.success) {
                showAlert('회원가입이 완료되었습니다. 로그인 페이지로 이동합니다...', 'success');
                setTimeout(() => location.href = 'index.php', 1500);
            } else {
                $btn.prop('disabled', false).text('회원가입');
                showAlert(res.message, 'danger');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('회원가입');
            showAlert('서버 연결에 실패했습니다.', 'danger');
        });
    });
});
</script>
</body>
</html>
