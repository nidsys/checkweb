<?php
require_once(__DIR__.'/config.php');
require_once(__DIR__.'/includes/db.php');

$pdo = getDB();

require __DIR__.'/google_auth.php';

$serviceAccountPath = __DIR__.'/webchecker-c03eb-2a087d2d4439.json';
$projectId = 'webchecker-c03eb';

$targetToken = $_POST['target_token'] ?? null;
$title = $_POST['title'] ?? '';
$body = $_POST['body'] ?? '';
/////////////////////////////////////////////////////////////////////////////

$sql = "SELECT * FROM fcm_token where 1";
$tokenLists = $pdo->query($sql)->fetchAll();

//
$sql = "SELECT * FROM error_logs where sendyn='n'";
$errorlogs = $pdo->query($sql)->fetchAll();

foreach($errorlogs as $errlog) {
    $title = $errlog['site_name'];
    $body = $errlog['error_message']."\n".$errlog['condition_detail'];
    
    $accessToken = getAccessToken($serviceAccountPath, 'https://www.googleapis.com/auth/firebase.messaging');

    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    
    foreach($tokenLists as $tokenList) {
        $fields = [
            'message' => [
                'token' => $tokenList['fcm_token'],
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
            ]
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$accessToken}",
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($fields)
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode($response, true);
        /////////////////////////////////////////////////////////////
    }
}
$sql = "update error_logs set sendyn='y' where sendyn='n'";
$pdo->query($sql);
