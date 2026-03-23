<?php
// =============================================
// dashboard.php - 메인 대시보드 (로그인 필요)
// =============================================

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userName = htmlspecialchars($_SESSION['name'] ?? '');
$userId   = htmlspecialchars($_SESSION['username'] ?? '');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- ============ Sidebar Overlay (mobile) ============ -->
<div class="sidebar-overlay"></div>

<!-- ============ App Layout ============ -->
<div class="app-layout">

    <!-- ============ Sidebar ============ -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <span class="logo-icon">🛰️</span>
                <h2><?= APP_NAME ?></h2>
            </div>
        </div>

        <div class="sidebar-user">
            <div class="user-name">👤 <?= $userName ?></div>
            <div class="user-id">@<?= $userId ?></div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-title">메뉴</div>

            <div class="nav-item active" data-page="dashboard">
                <span class="nav-icon">📊</span>
                <span class="nav-label">대시보드</span>
            </div>

            <div class="nav-item" data-page="sites">
                <span class="nav-icon">🌐</span>
                <span class="nav-label">사이트 관리</span>
            </div>

            <div class="nav-item" data-page="errors">
                <span class="nav-icon">🚨</span>
                <span class="nav-label">오류 목록</span>
            </div>
        </nav>

        <div class="sidebar-footer">
            <button class="btn btn-outline btn-block" id="btn-logout" style="color:#fff;border-color:rgba(255,255,255,.2)">
                🚪 로그아웃
            </button>
        </div>
    </aside>

    <!-- ============ Main Wrap ============ -->
    <div class="main-wrap" id="main-wrap">

        <!-- ---- Header ---- -->
        <header class="main-header">
            <button class="btn-toggle-sidebar" id="btn-toggle-sidebar" title="사이드바 토글">
                ☰
            </button>
            <h1 class="header-title">대시보드</h1>
            <div class="header-actions">
                <span class="text-muted" style="font-size:12px" id="header-username">
                    <?= $userName ?> 님
                </span>
            </div>
        </header>

        <!-- ---- Page Content ---- -->
        <main class="page-content">

            <!-- ======================================
                 PAGE: 대시보드
                 ====================================== -->
            <div class="page" id="page-dashboard">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">🌐</div>
                        <div class="stat-info">
                            <div class="stat-value" id="stat-total">-</div>
                            <div class="stat-label">전체 사이트</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">✅</div>
                        <div class="stat-info">
                            <div class="stat-value" id="stat-ok">-</div>
                            <div class="stat-label">정상</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red">❌</div>
                        <div class="stat-info">
                            <div class="stat-value" id="stat-error">-</div>
                            <div class="stat-label">오류 사이트</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon yellow">🚨</div>
                        <div class="stat-info">
                            <div class="stat-value" id="stat-today">-</div>
                            <div class="stat-label">오늘 오류 수</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">🕐 최근 오류</span>
                        <button class="btn btn-sm btn-secondary go-errors">전체 보기</button>
                    </div>
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>사이트</th>
                                    <th>오류 유형</th>
                                    <th>오류 내용</th>
                                    <th>HTTP</th>
                                    <th>발생 시각</th>
                                </tr>
                            </thead>
                            <tbody id="recent-errors-list">
                                <tr><td colspan="5" class="text-center text-muted" style="padding:28px">
                                    <span class="spinner dark"></span>
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ======================================
                 PAGE: 사이트 관리
                 ====================================== -->
            <div class="page" id="page-sites" style="display:none">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">🌐 사이트 목록</span>
                        <button class="btn btn-primary btn-sm" id="btn-add-site">+ 사이트 등록</button>
                    </div>
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>사이트</th>
                                    <th>상태</th>
                                    <th>활성</th>
                                    <th>체크 주기</th>
                                    <th>마지막 체크</th>
                                    <th>오류 수</th>
                                    <th>관리</th>
                                </tr>
                            </thead>
                            <tbody id="sites-list">
                                <tr><td colspan="7" class="text-center text-muted" style="padding:28px">
                                    <span class="spinner dark"></span>
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ======================================
                 PAGE: 오류 목록
                 ====================================== -->
            <div class="page" id="page-errors" style="display:none">
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">🚨 오류 목록</span>
                        <div class="flex-center">
                            <span class="text-muted" id="error-total-count"></span>
                            <button class="btn btn-sm btn-danger" id="btn-clear-errors">전체 삭제</button>
                        </div>
                    </div>
                    <div class="card-body" style="padding-bottom:0">
                        <div class="filter-bar">
                            <select class="form-control" id="filter-site">
                                <option value="">전체 사이트</option>
                            </select>
                            <select class="form-control" id="filter-error-type">
                                <option value="">전체 오류 유형</option>
                                <option value="http_error">HTTP 오류</option>
                                <option value="element_missing">요소 없음</option>
                                <option value="img_broken">이미지 오류</option>
                                <option value="class_empty">클래스 빈값</option>
                                <option value="connection_error">연결 오류</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>사이트</th>
                                    <th>오류 유형</th>
                                    <th>오류 내용</th>
                                    <th>HTTP</th>
                                    <th>발생 시각</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="errors-list">
                                <tr><td colspan="6" class="text-center text-muted" style="padding:28px">
                                    <span class="spinner dark"></span>
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="error-pagination" class="pagination"></div>
                </div>
            </div>

        </main>
    </div><!-- .main-wrap -->
</div><!-- .app-layout -->


<!-- ======================================
     MODAL: 사이트 등록/수정
     ====================================== -->
<div class="modal-overlay" id="modal-site">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-site-title">사이트 등록</h3>
            <button class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <form id="site-form">
                <div class="form-group">
                    <label>사이트 이름 <span class="required">*</span></label>
                    <input type="text" id="site-name" class="form-control" placeholder="예: 회사 메인 홈페이지" required>
                </div>
                <div class="form-group">
                    <label>URL <span class="required">*</span></label>
                    <input type="url" id="site-url" class="form-control" placeholder="https://example.com" required>
                </div>
                <div class="form-group">
                    <label>체크 주기 <span class="required">*</span></label>
                    <select id="site-interval" class="form-control">
                        <option value="1">1분</option>
                        <option value="5" selected>5분</option>
                        <option value="30">30분</option>
                        <option value="60">1시간</option>
                        <option value="360">6시간</option>
                        <option value="1440">24시간</option>
                    </select>
                </div>

                <hr class="divider">

                <div class="flex-center mb-3" style="justify-content:space-between">
                    <label style="font-weight:700;font-size:14px">🔍 체크 조건</label>
                    <button type="button" class="btn btn-sm btn-primary" id="btn-add-condition">+ 조건 추가</button>
                </div>

                <div id="conditions-list"></div>
                <div id="conditions-empty" class="conditions-empty" style="display:none">
                    <p>⚙️ 조건이 없으면 HTTP 200 여부만 체크합니다.</p>
                    <p style="margin-top:4px"><a href="#" id="add-first-condition">조건을 추가하려면 클릭하세요 →</a></p>
                </div>

                <small class="text-muted mt-2" style="display:block">
                    💡 셀렉터 예시: <code>.main-image</code>, <code>#banner</code>, <code>div.hero</code>
                </small>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-dismiss="modal">취소</button>
            <button class="btn btn-primary" id="btn-save-site" onclick="$('#site-form').submit()">저장</button>
        </div>
    </div>
</div>


<!-- Toast Container -->
<div id="toast-container"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
// 조건 추가 바로가기
$(document).on('click', '#add-first-condition', function(e) {
    e.preventDefault();
    $('#btn-add-condition').trigger('click');
});
// 초기 conditions empty 표시
$('#conditions-empty').show();
</script>
</body>
</html>
