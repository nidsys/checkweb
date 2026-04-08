<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="../assets/js/randkey.js"></script>

<script>
// 앱 실행 후 푸시 토큰 받으면 서버로 전송
 function sendTokenToServer(pushToken) {
    const tempKey = getOrCreateTempKey();

    $.post(
        'save_token_proc.php',
        {
            fcm_token: pushToken,
            device_type: 'android',
            temp_key: tempKey
        }
        , function(res){

        }
    )
}
sendTokenToServer('<?= $_REQUEST['fcm_token']??'' ?>');
</script>
