<?php
// =============================================
// api/sites.php - 사이트 CRUD
// =============================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$user   = requireLogin();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method . ':' . $action) {
    case 'GET:list':    getSites($user);        break;
    case 'GET:get':     getSite($user);         break;
    case 'POST:create': createSite($user);      break;
    case 'POST:update': updateSite($user);      break;
    case 'POST:delete': deleteSite($user);      break;
    case 'POST:toggle': toggleSite($user);      break;
    case 'POST:check':  checkSiteNow($user);    break;
    default: jsonResponse(['success' => false, 'message' => '잘못된 요청'], 400);
}

// ----- 목록 조회 -----
function getSites(array $user): void {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT s.*,
               (SELECT COUNT(*) FROM error_logs e WHERE e.site_id = s.id) AS error_count
        FROM sites s
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $sites = $stmt->fetchAll();
    foreach ($sites as &$site) {
        $site['check_conditions'] = json_decode($site['check_conditions'] ?? '[]', true) ?: [];
    }
    jsonResponse(['success' => true, 'data' => $sites]);
}

// ----- 단건 조회 -----
function getSite(array $user): void {
    $id   = (int)($_GET['id'] ?? 0);
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM sites WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    $site = $stmt->fetch();
    if (!$site) jsonResponse(['success' => false, 'message' => '사이트를 찾을 수 없습니다.'], 404);
    $site['check_conditions'] = json_decode($site['check_conditions'] ?? '[]', true) ?: [];
    jsonResponse(['success' => true, 'data' => $site]);
}

// ----- 사이트 등록 -----
function createSite(array $user): void {
    $data = getPostData();
    validateSiteData($data);

    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO sites (user_id, name, url, check_interval, check_conditions)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        $data['name'],
        $data['url'],
        $data['check_interval'],
        json_encode($data['check_conditions'], JSON_UNESCAPED_UNICODE),
    ]);
    jsonResponse(['success' => true, 'message' => '사이트가 등록되었습니다.', 'id' => $db->lastInsertId()]);
}

// ----- 사이트 수정 -----
function updateSite(array $user): void {
    $id   = (int)($_POST['id'] ?? 0);
    $data = getPostData();
    validateSiteData($data);

    $db   = getDB();
    $stmt = $db->prepare("SELECT id FROM sites WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    if (!$stmt->fetch()) jsonResponse(['success' => false, 'message' => '사이트를 찾을 수 없습니다.'], 404);

    $stmt = $db->prepare("
        UPDATE sites SET name=?, url=?, check_interval=?, check_conditions=?, updated_at=NOW()
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([
        $data['name'],
        $data['url'],
        $data['check_interval'],
        json_encode($data['check_conditions'], JSON_UNESCAPED_UNICODE),
        $id,
        $user['id'],
    ]);
    jsonResponse(['success' => true, 'message' => '수정되었습니다.']);
}

// ----- 사이트 삭제 -----
function deleteSite(array $user): void {
    $id   = (int)($_POST['id'] ?? 0);
    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM sites WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    jsonResponse(['success' => true, 'message' => '삭제되었습니다.']);
}

// ----- 활성/비활성 토글 -----
function toggleSite(array $user): void {
    $id   = (int)($_POST['id'] ?? 0);
    $db   = getDB();
    $stmt = $db->prepare("UPDATE sites SET is_active = NOT is_active WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    jsonResponse(['success' => true, 'message' => '변경되었습니다.']);
}

// ----- 즉시 체크 -----
function checkSiteNow(array $user): void {
    require_once __DIR__ . '/../includes/checker.php';

    $id   = (int)($_POST['id'] ?? 0);
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM sites WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    $site = $stmt->fetch();
    if (!$site) jsonResponse(['success' => false, 'message' => '사이트를 찾을 수 없습니다.'], 404);

    $result = checkSite($site);

    // 상태 업데이트
    $status = $result['pass'] ? 'ok' : 'error';
    $stmt   = $db->prepare("UPDATE sites SET last_checked=NOW(), last_status=? WHERE id=?");
    $stmt->execute([$status, $id]);

    // 오류 저장
    if (!$result['pass']) {
        $stmtErr = $db->prepare("
            INSERT INTO error_logs (site_id, user_id, site_name, site_url, http_status, error_type, error_message, condition_detail)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($result['errors'] as $err) {
            $stmtErr->execute([
                $id, $user['id'], $site['name'], $site['url'],
                $result['http_status'],
                $err['error_type'], $err['error_msg'], $err['detail'],
            ]);
        }
    }

    jsonResponse([
        'success'     => true,
        'pass'        => $result['pass'],
        'http_status' => $result['http_status'],
        'errors'      => $result['errors'],
        'status'      => $status,
    ]);
}

// ----- 헬퍼 -----
function getPostData(): array {
    $raw        = file_get_contents('php://input');
    $json       = json_decode($raw, true);
    $conditions = [];

    if ($json) {
        $name       = trim($json['name'] ?? '');
        $url        = trim($json['url'] ?? '');
        $interval   = (int)($json['check_interval'] ?? 5);
        $conditions = $json['check_conditions'] ?? [];
    } else {
        $name     = trim($_POST['name'] ?? '');
        $url      = trim($_POST['url'] ?? '');
        $interval = (int)($_POST['check_interval'] ?? 5);
        $raw_cond = $_POST['check_conditions'] ?? '[]';
        $conditions = is_array($raw_cond) ? $raw_cond : (json_decode($raw_cond, true) ?: []);
    }

    return compact('name', 'url', 'interval') + ['check_interval' => $interval, 'check_conditions' => $conditions];
}

function validateSiteData(array $data): void {
    if (empty($data['name'])) jsonResponse(['success' => false, 'message' => '사이트 이름을 입력하세요.']);
    if (empty($data['url']))  jsonResponse(['success' => false, 'message' => 'URL을 입력하세요.']);
    if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
        jsonResponse(['success' => false, 'message' => '올바른 URL 형식이 아닙니다. (https://... 형태)']);
    }
    $validIntervals = [1, 5, 30, 60, 360, 1440];
    if (!in_array((int)$data['check_interval'], $validIntervals)) {
        jsonResponse(['success' => false, 'message' => '올바른 체크 주기를 선택하세요.']);
    }
}
