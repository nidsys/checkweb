<?php


// ⭐ 1. 요청 본문(raw data) 전체를 가져옵니다. ⭐
$json_data = file_get_contents('php://input');

// ⭐ 2. JSON 문자열을 PHP 배열/객체로 디코딩합니다. ⭐
$data = json_decode($json_data, true);


?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="../assets/js/randkey.js"></script>

<script>
// 앱 실행 후 푸시 토큰 받으면 서버로 전송
 function sendTokenToServer(pushToken) {
    const tempKey = getOrCreateTempKey();

     $.ajax({
        url: 'save_token_proc.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            fcm_token: pushToken,
            device_type: 'android',
            temp_key: tempKey
        }),
        success: function(res) {
            console.log('토큰 저장 성공:', res);
        },
        error: function(err) {
            console.error('토큰 저장 실패:', err);
        }
    });

     // $.get(
     //    'save_token_proc.php',JSON.stringify(
     //    {
     //        fcm_token: pushToken,
     //        device_type: 'android',
     //        temp_key: tempKey
     //    })
     //    , function(res){
 
     //    }
     //)
    
    // $.get(
    //     'save_token_proc.php',JSON.stringify(
    //     {
    //         fcm_token: pushToken,
    //         device_type: 'android',
    //         temp_key: tempKey
    //     })
    //     , function(res){

    //     }
    // )
}
sendTokenToServer('<?= $data['fcm_token']??'' ?>');
</script>
