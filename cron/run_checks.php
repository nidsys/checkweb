<?php
// =============================================
// cron/run_checks.php - 크론 체크 실행
//
// 크론 설정 예시 (매 1분마다 실행):
// * * * * * php /var/www/html/site_monitor/cron/run_checks.php >> /var/log/site_monitor.log 2>&1
// =============================================

define('CRON_RUN', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/checker.php';

$startTime = microtime(true);
$now       = date('Y-m-d H:i:s');
echo "[{$now}] Site Monitor Cron 시작\n";

$db = getDB();

// 체크해야 할 사이트 조회
// last_checked가 NULL이거나, (현재시각 - last_checked) >= check_interval분인 사이트
$stmt = $db->prepare("
    SELECT * FROM sites
    WHERE is_active = 1
      AND (
        last_checked IS NULL
        OR TIMESTAMPDIFF(MINUTE, last_checked, NOW()) >= check_interval
      )
    ORDER BY last_checked ASC
    LIMIT 50
");
$stmt->execute();
$sites = $stmt->fetchAll();

echo "체크 대상 사이트: " . count($sites) . "개\n";

$stmtUpdate = $db->prepare("UPDATE sites SET last_checked=NOW(), last_status=? WHERE id=?");
$stmtError  = $db->prepare("
    INSERT INTO error_logs (site_id, user_id, site_name, site_url, http_status, error_type, error_message, condition_detail)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($sites as $site) {
    echo "  체크 중: [{$site['id']}] {$site['name']} ({$site['url']})\n";

    $result = checkSite($site);
    $status = $result['pass'] ? 'ok' : 'error';

    $stmtUpdate->execute([$status, $site['id']]);

    if (!$result['pass']) {
        echo "    ❌ 오류 " . count($result['errors']) . "건\n";
        foreach ($result['errors'] as $err) {
            echo "       - [{$err['error_type']}] {$err['error_msg']}\n";
            $stmtError->execute([
                $site['id'],
                $site['user_id'],
                $site['name'],
                $site['url'],
                $result['http_status'],
                $err['error_type'],
                $err['error_msg'],
                $err['detail'],
            ]);
        }
    } else {
        echo "    ✅ 정상 (HTTP {$result['http_status']})\n";
    }
}

$elapsed = round(microtime(true) - $startTime, 2);
echo "완료 ({$elapsed}초 소요)\n\n";
