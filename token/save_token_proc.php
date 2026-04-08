<?php
error_reporting(E_ALL);

ini_set('display_errors', '1');

header('Content-Type: application/json');

require_once __DIR__. "/../config.php";
require_once __DIR__. "/../includes/db.php";

$db = getDB();

// ⭐ 1. 요청 본문(raw data) 전체를 가져옵니다. ⭐
$json_data = file_get_contents('php://input');

// ⭐ 2. JSON 문자열을 PHP 배열/객체로 디코딩합니다. ⭐
$data = json_decode($json_data, true);


$fp = fopen("log.txt","a+");
$r = print_r($data, true);
fwrite($fp, "\n".$_SERVER['REMOTE_ADDR']."\n".$r);
fclose($fp);

if(empty($data['fcm_token'])) $data['fcm_token'] = "";
if(empty($data['temp_key'])) $data['temp_key'] = "";

$sql = "SELECT id FROM fcm_token where fcm_token='{$data['fcm_token']}'";
$duple = $db->query($sql)->fetchAll();
if(empty($duple[0]['id'])) {
    $sql= "insert into fcm_token set
        fcm_token='{$data['fcm_token']}',
        temp_key='{$data['temp_key']}',
        user_id=0,
        created_at=now()
    ";
    $db->query($sql);
}else{
    $sql= "update fcm_token set
        temp_key='{$data['temp_key']}',
        created_at=now()
        where id={$duple[0]['id']}
    ";
    $db->query($sql);
}
