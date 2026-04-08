<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="assets/js/app.js"></script>

<script>
// 앱 실행 후 푸시 토큰 받으면 서버로 전송
async function sendTokenToServer(pushToken) {
    const tempKey = getOrCreateTempKey();

    const response = await fetch('save_token_proc.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            fcm_token: pushToken,
            device_type: 'android', // 또는 'ios'
            temp_key: tempKey
        })
    });

    const result = await response.json();
    console.log(result);
}
sendTokenToServer('<?= $_REQUEST['fcm_token']??'' ?>');
</script>
