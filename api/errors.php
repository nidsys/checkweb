<?php
// =============================================
// api/errors.php - 오류 로그 조회 / 삭제
// =============================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$user   = requireLogin();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method . ':' . $action) {
    case 'GET:list':         getErrors($user);       break;
    case 'GET:summary':      getSummary($user);      break;
    case 'POST:delete':      deleteError($user);     break;
    case 'POST:delete_all':  deleteAllErrors($user); break;
    default: jsonResponse(['success' => false, 'message' => '잘못된 요청'], 400);
}

// ----- 오류 목록 -----
function getErrors(array $user): void {
    $db       = getDB();
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = 20;
    $offset   = ($page - 1) * $limit;
    $siteId   = (int)($_GET['site_id'] ?? 0);
    $errType  = $_GET['error_type'] ?? '';

    $where = ["e.user_id = ?"];
    $params = [$user['id']];

    if ($siteId > 0) {
        $where[] = "e.site_id = ?";
        $params[] = $siteId;
    }
    if (!empty($errType)) {
        $where[] = "e.error_type = ?";
        $params[] = $errType;
    }

    $whereStr = implode(' AND ', $where);

    // 총 개수
    $countStmt = $db->prepare("SELECT COUNT(*) FROM error_logs e WHERE {$whereStr}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // 목록
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $db->prepare("
        SELECT e.*, s.name AS current_site_name
        FROM error_logs e
        LEFT JOIN sites s ON s.id = e.site_id
        WHERE {$whereStr}
        ORDER BY e.checked_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'data'    => $logs,
        'total'   => $total,
        'page'    => $page,
        'pages'   => ceil($total / $limit),
    ]);
}

// ----- 대시보드 요약 -----
function getSummary(array $user): void {
    $db = getDB();

    // 총 사이트 수
    $stmt = $db->prepare("SELECT COUNT(*) FROM sites WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $totalSites = (int)$stmt->fetchColumn();

    // 오류 상태 사이트 수
    $stmt = $db->prepare("SELECT COUNT(*) FROM sites WHERE user_id = ? AND last_status = 'error' AND is_active = 1");
    $stmt->execute([$user['id']]);
    $errorSites = (int)$stmt->fetchColumn();

    // 정상 상태 사이트 수
    $stmt = $db->prepare("SELECT COUNT(*) FROM sites WHERE user_id = ? AND last_status = 'ok' AND is_active = 1");
    $stmt->execute([$user['id']]);
    $okSites = (int)$stmt->fetchColumn();

    // 오늘 오류 수
    $stmt = $db->prepare("SELECT COUNT(*) FROM error_logs WHERE user_id = ? AND DATE(checked_at) = CURDATE()");
    $stmt->execute([$user['id']]);
    $todayErrors = (int)$stmt->fetchColumn();

    // 최근 오류 5건
    $stmt = $db->prepare("
        SELECT e.*, s.name AS current_site_name
        FROM error_logs e
        LEFT JOIN sites s ON s.id = e.site_id
        WHERE e.user_id = ?
        ORDER BY e.checked_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $recentErrors = $stmt->fetchAll();

    jsonResponse([
        'success'      => true,
        'total_sites'  => $totalSites,
        'error_sites'  => $errorSites,
        'ok_sites'     => $okSites,
        'today_errors' => $todayErrors,
        'recent_errors'=> $recentErrors,
    ]);
}

// ----- 단건 삭제 -----
function deleteError(array $user): void {
    $id   = (int)($_POST['id'] ?? 0);
    $db   = getDB();
    $stmt = $db->prepare("DELETE FROM error_logs WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    jsonResponse(['success' => true, 'message' => '삭제되었습니다.']);
}

// ----- 전체 삭제 (사이트별 또는 전체) -----
function deleteAllErrors(array $user): void {
    $siteId = (int)($_POST['site_id'] ?? 0);
    $db     = getDB();

    if ($siteId > 0) {
        $stmt = $db->prepare("DELETE FROM error_logs WHERE site_id = ? AND user_id = ?");
        $stmt->execute([$siteId, $user['id']]);
    } else {
        $stmt = $db->prepare("DELETE FROM error_logs WHERE user_id = ?");
        $stmt->execute([$user['id']]);
    }
    jsonResponse(['success' => true, 'message' => '삭제되었습니다.']);
}
