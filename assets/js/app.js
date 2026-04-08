// =============================================
// assets/js/app.js - Site Monitor 메인 JS
// =============================================

$(function () {

    // =========================================
    // 공통 유틸
    // =========================================

    const Toast = {
        show(msg, type = 'info', duration = 3500) {
            const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
            const $t = $('<div>', { class: `toast ${type}`, html: `<span>${icons[type] || ''}</span><span>${msg}</span>` });
            $('#toast-container').append($t);
            setTimeout(() => $t.fadeOut(300, () => $t.remove()), duration);
        }
    };

    const Modal = {
        open(id) {
            $('#' + id).addClass('active');
            $('body').css('overflow', 'hidden');
        },
        close(id) {
            $('#' + id).removeClass('active');
            $('body').css('overflow', '');
        },
        closeAll() {
            $('.modal-overlay').removeClass('active');
            $('body').css('overflow', '');
        }
    };

    // 오버레이 클릭 시 닫기
    $(document).on('click', '.modal-overlay', function (e) {
        if ($(e.target).is('.modal-overlay')) Modal.closeAll();
    });
    $(document).on('click', '.modal-close, [data-dismiss="modal"]', function () {
        Modal.closeAll();
    });

    // 체크 주기 표시
    const intervalLabels = { 1: '1분', 5: '5분', 30: '30분', 60: '1시간', 360: '6시간', 1440: '24시간' };
    const errorTypeLabels = {
        http_error: 'HTTP 오류',
        element_missing: '요소 없음',
        img_broken: '이미지 오류',
        class_empty: '클래스 빈값',
        connection_error: '연결 오류',
    };
    const conditionTypeLabels = {
        http_status: 'HTTP 상태 체크',
        element_exists: '요소 존재 체크',
        class_empty: '클래스 빈값 체크',
        img_src: 'img src 유효성',
        class_first_img: '클래스 첫번째 이미지',
    };

    function timeAgo(dateStr) {
        if (!dateStr) return '-';
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60)    return '방금 전';
        if (diff < 3600)  return `${Math.floor(diff/60)}분 전`;
        if (diff < 86400) return `${Math.floor(diff/3600)}시간 전`;
        return `${Math.floor(diff/86400)}일 전`;
    }

    function escapeHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // =========================================
    // 사이드바 토글
    // =========================================
    //let sidebarOpen = $(window).width() > 768;
	let sidebarOpen = false;

    function applySidebar() {
        if ($(window).width() <= 768) {
            if (sidebarOpen) {
                $('.sidebar').addClass('mobile-open');
                $('.sidebar-overlay').addClass('active');
            } else {
                $('.sidebar').removeClass('mobile-open');
                $('.sidebar-overlay').removeClass('active');
            }
            $('.main-wrap').removeClass('sidebar-collapsed');
        } else {
            $('.sidebar').removeClass('mobile-open');
            if (sidebarOpen) {
                $('.sidebar').removeClass('collapsed');
                $('.main-wrap').removeClass('sidebar-collapsed');
            } else {
                $('.sidebar').addClass('collapsed');
                $('.main-wrap').addClass('sidebar-collapsed');
            }
            $('.sidebar-overlay').removeClass('active');
        }
    }

    $('#btn-toggle-sidebar').on('click', function () {
        sidebarOpen = !sidebarOpen;
        applySidebar();
    });

    $('.sidebar-overlay').on('click', function () {
        sidebarOpen = false;
        applySidebar();
    });

    $(window).on('resize', applySidebar);
    applySidebar();

    // =========================================
    // 메뉴 네비게이션
    // =========================================
    function showPage(page) {
        $('.page').hide();
        $('#page-' + page).show();
        $('.nav-item').removeClass('active');
        $(`[data-page="${page}"]`).addClass('active');
        $('.header-title').text($(`[data-page="${page}"]`).find('.nav-label').text() || 'Dashboard');

        if (page === 'dashboard') loadDashboard();
        if (page === 'sites')     loadSites();
        if (page === 'errors')    loadErrors(1);
    }

    $(document).on('click', '.nav-item[data-page]', function () {
        showPage($(this).data('page'));
        if ($(window).width() <= 768) {
            sidebarOpen = false;
            applySidebar();
        }
    });

    // 초기 페이지
    showPage('dashboard');

    // =========================================
    // 로그아웃
    // =========================================
    $('#btn-logout').on('click', function () {
        if (!confirm('로그아웃 하시겠습니까?')) return;
        $.post('api/auth.php?action=logout', function () {
            location.href = 'index.php';
        });
    });

    // =========================================
    // 대시보드
    // =========================================
    function loadDashboard() {
        $.get('api/errors.php?action=summary', function (res) {
            if (!res.success) return;
            $('#stat-total').text(res.total_sites);
            $('#stat-ok').text(res.ok_sites);
            $('#stat-error').text(res.error_sites);
            $('#stat-today').text(res.today_errors);

            const $tbody = $('#recent-errors-list');
            $tbody.empty();
            if (!res.recent_errors.length) {
                $tbody.append('<tr><td colspan="5" class="text-center text-muted" style="padding:28px">최근 오류가 없습니다 🎉</td></tr>');
            } else {
                res.recent_errors.forEach(e => {
                    $tbody.append(`
                        <tr>
                            <td><a href="#" class="go-errors">${escapeHtml(e.site_name)}</a></td>
                            <td><span class="badge badge-warning">${errorTypeLabels[e.error_type] || e.error_type}</span></td>
                            <td class="text-ellipsis" style="max-width:300px" title="${escapeHtml(e.error_message)}">${escapeHtml(e.error_message)}</td>
                            <td>${e.http_status || '-'}</td>
                            <td class="text-muted">${timeAgo(e.checked_at)}</td>
                        </tr>`);
                });
            }
        });
    }

    $(document).on('click', '.go-errors', function (e) {
        e.preventDefault();
        showPage('errors');
    });

    // =========================================
    // 사이트 관리
    // =========================================
    let editSiteId = null;

    function loadSites() {
        $.get('api/sites.php?action=list', function (res) {
            if (!res.success) return;
            const $tbody = $('#sites-list');
            $tbody.empty();

            if (!res.data.length) {
                $tbody.append(`<tr><td colspan="7">
                    <div class="empty-state">
                        <span class="empty-icon">🔍</span>
                        <h3>등록된 사이트가 없습니다</h3>
                        <p>사이트를 등록하여 모니터링을 시작하세요.</p>
                        <button class="btn btn-primary" id="btn-add-site-empty">+ 사이트 등록</button>
                    </div>
                </td></tr>`);
                return;
            }

            res.data.forEach(site => {
                const statusBadge = {
                    ok:      '<span class="badge badge-ok">✅ 정상</span>',
                    error:   '<span class="badge badge-error">❌ 오류</span>',
                    unknown: '<span class="badge badge-unknown">⏳ 미확인</span>',
                }[site.last_status] || '';

                const activeBadge = site.is_active == 1
                    ? '<span class="badge badge-ok">활성</span>'
                    : '<span class="badge badge-unknown">비활성</span>';

                $tbody.append(`
                    <tr data-id="${site.id}">
                        <td>
                            <div style="font-weight:600">${escapeHtml(site.name)}</div>
                            <div class="text-muted text-ellipsis" title="${escapeHtml(site.url)}" style="max-width:200px">${escapeHtml(site.url)}</div>
                        </td>
                        <td>${statusBadge}</td>
                        <td>${activeBadge}</td>
                        <td>${intervalLabels[site.check_interval] || site.check_interval + '분'}</td>
                        <td class="text-muted">${timeAgo(site.last_checked)}</td>
                        <td>${site.error_count > 0 ? `<span class="badge badge-error">${site.error_count}</span>` : '<span class="text-muted">0</span>'}</td>
                        <td>
                            <div class="flex-center">
                                <button class="btn btn-sm btn-info btn-check-now" data-id="${site.id}" title="즉시 체크">▶</button>
                                <button class="btn btn-sm btn-secondary btn-edit-site" data-id="${site.id}" title="수정">✏️</button>
                                <button class="btn btn-sm btn-outline btn-toggle-site" data-id="${site.id}" title="${site.is_active ? '비활성화' : '활성화'}">${site.is_active ? '⏸' : '▶'}</button>
                                <button class="btn btn-sm btn-danger btn-delete-site" data-id="${site.id}" title="삭제">🗑</button>
                            </div>
                        </td>
                    </tr>`);
            });
        });
    }

    // 사이트 등록 모달 열기
    $(document).on('click', '#btn-add-site, #btn-add-site-empty', function () {
        editSiteId = null;
        $('#modal-site-title').text('사이트 등록');
        $('#site-form')[0].reset();
        $('#conditions-list').empty();
        renderConditionsEmpty();
        Modal.open('modal-site');
    });

    // 수정 버튼
    $(document).on('click', '.btn-edit-site', function () {
        const id = $(this).data('id');
        $.get(`api/sites.php?action=get&id=${id}`, function (res) {
            if (!res.success) return Toast.show(res.message, 'error');
            const site = res.data;
            editSiteId = site.id;
            $('#modal-site-title').text('사이트 수정');
            $('#site-name').val(site.name);
            $('#site-url').val(site.url);
            $('#site-interval').val(site.check_interval);
            // 조건 렌더링
            $('#conditions-list').empty();
            if (site.check_conditions && site.check_conditions.length) {
                site.check_conditions.forEach(c => addConditionRow(c));
            } else {
                renderConditionsEmpty();
            }
            Modal.open('modal-site');
        });
    });

    // 즉시 체크
    $(document).on('click', '.btn-check-now', function () {
        const id = $(this).data('id');
        const $btn = $(this).prop('disabled', true).text('...');
        $.post('api/sites.php?action=check', { id }, function (res) {
            $btn.prop('disabled', false).text('▶');
            if (res.pass) {
                Toast.show(`정상 확인 (HTTP ${res.http_status})`, 'success');
            } else {
                const msgs = res.errors.map(e => e.error_msg).join('\n');
                Toast.show(`오류 ${res.errors.length}건 발생`, 'error', 5000);
            }
            loadSites();
            if ($('#page-dashboard').is(':visible')) loadDashboard();
        }).fail(() => {
            $btn.prop('disabled', false).text('▶');
            Toast.show('체크 중 오류가 발생했습니다.', 'error');
        });
    });

    // 활성/비활성 토글
    $(document).on('click', '.btn-toggle-site', function () {
        const id = $(this).data('id');
        $.post('api/sites.php?action=toggle', { id }, function (res) {
            if (res.success) { Toast.show(res.message, 'success'); loadSites(); }
            else Toast.show(res.message, 'error');
        });
    });

    // 삭제
    $(document).on('click', '.btn-delete-site', function () {
        const id = $(this).data('id');
        if (!confirm('사이트를 삭제하면 관련 오류 로그도 모두 삭제됩니다. 계속하시겠습니까?')) return;
        $.post('api/sites.php?action=delete', { id }, function (res) {
            if (res.success) { Toast.show(res.message, 'success'); loadSites(); loadDashboard(); }
            else Toast.show(res.message, 'error');
        });
    });

    // 사이트 폼 저장
    $('#site-form').on('submit', function (e) {
        e.preventDefault();
        const conditions = collectConditions();
        const payload = {
            name:             $('#site-name').val().trim(),
            url:              $('#site-url').val().trim(),
            check_interval:   $('#site-interval').val(),
            check_conditions: conditions,
        };
        if (editSiteId) payload.id = editSiteId;

        const action = editSiteId ? 'update' : 'create';
        const $btn = $('#btn-save-site').prop('disabled', true).html('<span class="spinner"></span> 저장 중...');

        $.ajax({
            url:         `api/sites.php?action=${action}`,
            method:      'POST',
            contentType: 'application/json',
            data:        JSON.stringify(payload),
            success(res) {
                $btn.prop('disabled', false).text('저장');
                if (res.success) {
                    Toast.show(res.message, 'success');
                    Modal.closeAll();
                    loadSites();
                    loadDashboard();
                } else {
                    Toast.show(res.message, 'error');
                }
            },
            error() {
                $btn.prop('disabled', false).text('저장');
                Toast.show('저장 중 오류가 발생했습니다.', 'error');
            }
        });
    });

    // =========================================
    // 체크 조건 빌더
    // =========================================
    const conditionTypes = {
        http_status:    { label: 'HTTP 상태 체크 (200)',        hasSelector: false },
        element_exists: { label: '요소 존재 여부',              hasSelector: true  },
        class_empty:    { label: '클래스 빈값 체크',            hasSelector: true  },
        img_src:        { label: 'img src 유효성',              hasSelector: true  },
        class_first_img:{ label: '클래스 내 첫번째 이미지 확인', hasSelector: true  },
    };

    function renderConditionsEmpty() {
        if ($('#conditions-list .condition-item').length === 0) {
            $('#conditions-empty').show();
        } else {
            $('#conditions-empty').hide();
        }
    }

    function addConditionRow(data = {}) {
        $('#conditions-empty').hide();
        const type        = data.type || 'http_status';
        const selector    = data.selector || '';
        const description = data.description || '';
        const hasSelector = conditionTypes[type]?.hasSelector ?? false;

        const typeOptions = Object.entries(conditionTypes)
            .map(([v, c]) => `<option value="${v}" ${v === type ? 'selected' : ''}>${c.label}</option>`)
            .join('');

        const $row = $(`
            <div class="condition-item">
                <div class="condition-fields">
                    <div>
                        <label style="font-size:11px;color:#6b7280;font-weight:600">조건 유형</label>
                        <select class="form-control form-control-sm cond-type" style="margin-top:4px">
                            ${typeOptions}
                        </select>
                    </div>
                    <div class="cond-desc-wrap">
                        <label style="font-size:11px;color:#6b7280;font-weight:600">설명 (선택)</label>
                        <input type="text" class="form-control form-control-sm cond-desc" placeholder="예: 메인 이미지" value="${escapeHtml(description)}" style="margin-top:4px">
                    </div>
                    <div class="cond-selector-wrap" style="${hasSelector ? '' : 'display:none'}">
                        <label style="font-size:11px;color:#6b7280;font-weight:600">CSS 셀렉터 <span style="color:#ef4444">*</span></label>
                        <input type="text" class="form-control form-control-sm cond-selector" placeholder="예: .main-img, #header, .gallery" value="${escapeHtml(selector)}" style="margin-top:4px">
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-danger btn-remove-cond" style="flex-shrink:0;margin-top:18px">✕</button>
            </div>`);

        $row.find('.cond-type').on('change', function () {
            const t = $(this).val();
            const needs = conditionTypes[t]?.hasSelector ?? false;
            $row.find('.cond-selector-wrap').toggle(needs);
            $row.find('.condition-fields').toggleClass('full', !needs);
        });

        $row.find('.cond-type').trigger('change');

        $('#conditions-list').append($row);
    }

    $(document).on('click', '#btn-add-condition', function () {
        addConditionRow();
    });

    $(document).on('click', '.btn-remove-cond', function () {
        $(this).closest('.condition-item').remove();
        renderConditionsEmpty();
    });

    function collectConditions() {
        const conditions = [];
        $('#conditions-list .condition-item').each(function () {
            const type     = $(this).find('.cond-type').val();
            const selector = $(this).find('.cond-selector').val().trim();
            const desc     = $(this).find('.cond-desc').val().trim();
            const needs    = conditionTypes[type]?.hasSelector ?? false;
            const cond     = { type };
            if (needs) cond.selector = selector;
            if (desc)  cond.description = desc;
            conditions.push(cond);
        });
        return conditions;
    }

    // =========================================
    // 오류 목록
    // =========================================
    let currentErrorPage = 1;
    let errorSiteFilter  = '';
    let errorTypeFilter  = '';

    function loadErrors(page = 1) {
        currentErrorPage = page;
        let url = `api/errors.php?action=list&page=${page}`;
        if (errorSiteFilter) url += `&site_id=${errorSiteFilter}`;
        if (errorTypeFilter) url += `&error_type=${errorTypeFilter}`;

        const $tbody = $('#errors-list');
        $tbody.html('<tr><td colspan="6" class="text-center" style="padding:28px"><span class="spinner dark"></span></td></tr>');

        $.get(url, function (res) {
            if (!res.success) return;
            $tbody.empty();

            if (!res.data.length) {
                $tbody.append(`<tr><td colspan="6">
                    <div class="empty-state">
                        <span class="empty-icon">🎉</span>
                        <h3>오류 기록이 없습니다</h3>
                        <p>모니터링 중인 모든 사이트가 정상입니다.</p>
                    </div>
                </td></tr>`);
            } else {
                res.data.forEach(e => {
                    $tbody.append(`
                        <tr>
                            <td>
                                <div style="font-weight:600">${escapeHtml(e.site_name)}</div>
                                <div class="text-muted text-ellipsis" style="max-width:180px" title="${escapeHtml(e.site_url)}">${escapeHtml(e.site_url)}</div>
                            </td>
                            <td><span class="badge badge-warning">${errorTypeLabels[e.error_type] || e.error_type}</span></td>
                            <td style="max-width:280px">
                                <div style="white-space:pre-wrap;font-size:12px">${escapeHtml(e.error_message)}</div>
                                ${e.condition_detail ? `<div class="text-muted" style="font-size:11px;margin-top:3px">${escapeHtml(e.condition_detail)}</div>` : ''}
                            </td>
                            <td>${e.http_status || '-'}</td>
                            <td class="text-muted" style="white-space:nowrap">${new Date(e.checked_at).toLocaleString('ko-KR')}</td>
                            <td>
                                <button class="btn btn-sm btn-danger btn-delete-error" data-id="${e.id}" title="삭제">🗑</button>
                            </td>
                        </tr>`);
                });
            }

            // 페이지네이션
            renderPagination('#error-pagination', res.page, res.pages);
            $('#error-total-count').text(`총 ${res.total}건`);
        });
    }

    // 오류 삭제
    $(document).on('click', '.btn-delete-error', function () {
        const id = $(this).data('id');
        if (!confirm('이 오류 기록을 삭제하시겠습니까?')) return;
        $.post('api/errors.php?action=delete', { id }, function (res) {
            if (res.success) { Toast.show('삭제되었습니다.', 'success'); loadErrors(currentErrorPage); }
        });
    });

    // 전체 삭제
    $('#btn-clear-errors').on('click', function () {
        if (!confirm('모든 오류 기록을 삭제하시겠습니까?')) return;
        $.post('api/errors.php?action=delete_all', {}, function (res) {
            if (res.success) { Toast.show('전체 삭제되었습니다.', 'success'); loadErrors(1); loadDashboard(); }
        });
    });

    // 필터
    $('#filter-error-type').on('change', function () {
        errorTypeFilter = $(this).val();
        loadErrors(1);
    });

    // 사이트 필터 동적 로드
    function loadSiteFilterOptions() {
        $.get('api/sites.php?action=list', function (res) {
            if (!res.success) return;
            const $sel = $('#filter-site');
            $sel.find('option:not(:first)').remove();
            res.data.forEach(s => $sel.append(`<option value="${s.id}">${escapeHtml(s.name)}</option>`));
        });
    }

    $('#filter-site').on('change', function () {
        errorSiteFilter = $(this).val();
        loadErrors(1);
    });

    // 오류 목록 탭 진입 시 사이트 필터 옵션 갱신
    $('[data-page="errors"]').on('click', function () {
        loadSiteFilterOptions();
    });

    // =========================================
    // 페이지네이션
    // =========================================
    function renderPagination(selector, page, pages) {
        const $el = $(selector).empty();
        if (pages <= 1) return;

        const prev = page > 1;
        $el.append(`<button class="page-btn" ${prev ? '' : 'disabled'} data-p="${page - 1}">‹</button>`);

        for (let i = Math.max(1, page - 2); i <= Math.min(pages, page + 2); i++) {
            $el.append(`<button class="page-btn ${i === page ? 'active' : ''}" data-p="${i}">${i}</button>`);
        }

        const next = page < pages;
        $el.append(`<button class="page-btn" ${next ? '' : 'disabled'} data-p="${page + 1}">›</button>`);

        $el.find('.page-btn:not(:disabled)').on('click', function () {
            loadErrors($(this).data('p'));
        });
    }

    // =========================================
    // 자동 새로고침 (30초마다 대시보드 갱신)
    // =========================================
    setInterval(function () {
        if ($('#page-dashboard').is(':visible')) loadDashboard();
    }, 30000);

});
function randomUUID() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}
function getOrCreateTempKey() {
    let tempKey = localStorage.getItem('temp_key');
    
    if (!tempKey) {
        // 없으면 새로 생성
        tempKey = randomUUID(); // 예: "550e8400-e29b-41d4-a716-446655440000"
        localStorage.setItem('temp_key', tempKey);
    }
    
    return tempKey;
}
