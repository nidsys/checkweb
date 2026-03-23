<?php
// =============================================
// includes/checker.php - 사이트 체크 핵심 로직
// =============================================

require_once __DIR__ . '/../config.php';

/**
 * URL을 CURL로 가져와서 HTTP 상태코드와 HTML 반환
 */
function fetchUrl(string $url): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => CURL_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'SiteMonitor/1.0 (+https://github.com/site-monitor)',
        CURLOPT_ENCODING       => '',
    ]);

    $html       = curl_exec($ch);
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error      = curl_error($ch);
    curl_close($ch);

    if ($html === false) {
        return ['success' => false, 'http_status' => 0, 'html' => '', 'error' => $error ?: '연결 실패'];
    }

    return ['success' => true, 'http_status' => $httpStatus, 'html' => $html, 'error' => ''];
}

/**
 * HTML에서 DOM 객체 생성
 */
function parseHtml(string $html): DOMDocument {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    return $dom;
}

/**
 * CSS 셀렉터로 요소 찾기 (클래스 또는 ID)
 * 지원: .className, #idName, tagName, tagName.className
 */
function findElements(DOMDocument $dom, string $selector): array {
    $xpath = new DOMXPath($dom);
    $xpathExpr = cssToXpath($selector);
    $nodes = $xpath->query($xpathExpr);
    if ($nodes === false) return [];
    $result = [];
    foreach ($nodes as $node) {
        $result[] = $node;
    }
    return $result;
}

/**
 * 간단한 CSS → XPath 변환
 * 지원 패턴: .class, #id, tag, tag.class, tag#id
 */
function cssToXpath(string $selector): string {
    $selector = trim($selector);

    // #id
    if (preg_match('/^#([\w-]+)$/', $selector, $m)) {
        return "//*[@id='{$m[1]}']";
    }
    // .class
    if (preg_match('/^\.([\w-]+)$/', $selector, $m)) {
        return "//*[contains(concat(' ',normalize-space(@class),' '),' {$m[1]} ')]";
    }
    // tag.class
    if (preg_match('/^([\w]+)\.([\w-]+)$/', $selector, $m)) {
        return "//{$m[1]}[contains(concat(' ',normalize-space(@class),' '),' {$m[2]} ')]";
    }
    // tag#id
    if (preg_match('/^([\w]+)#([\w-]+)$/', $selector, $m)) {
        return "//{$m[1]}[@id='{$m[2]}']";
    }
    // tag만
    if (preg_match('/^[\w]+$/', $selector)) {
        return "//{$selector}";
    }

    // fallback: class 이름으로 시도
    return "//*[contains(concat(' ',normalize-space(@class),' '),' {$selector} ')]";
}

/**
 * IMG 요소의 src가 유효한지 확인
 */
function checkImageSrc(string $src, string $baseUrl): array {
    if (empty($src)) {
        return ['valid' => false, 'reason' => 'src 속성이 비어있습니다.'];
    }
    // 상대 경로 → 절대 경로 변환
    if (!preg_match('/^https?:\/\//', $src)) {
        $parsed = parse_url($baseUrl);
        if (str_starts_with($src, '/')) {
            $src = $parsed['scheme'] . '://' . $parsed['host'] . $src;
        } else {
            $basePath = isset($parsed['path']) ? dirname($parsed['path']) : '';
            $src = $parsed['scheme'] . '://' . $parsed['host'] . $basePath . '/' . $src;
        }
    }

    $result = fetchUrl($src);
    if (!$result['success'] || $result['http_status'] !== 200) {
        return ['valid' => false, 'reason' => "이미지 응답 오류 (HTTP {$result['http_status']}): {$src}"];
    }
    return ['valid' => true, 'reason' => ''];
}

/**
 * 개별 조건 하나를 체크
 * 
 * 조건 타입:
 *   http_status    - HTTP 200 체크
 *   img_src        - selector의 img src가 유효한지 (selector: CSS selector)
 *   class_empty    - selector 요소의 텍스트/자식이 비어있는지
 *   class_first_img - selector 안의 첫번째 img가 유효한지
 *   element_exists - selector 요소가 존재하는지
 */
function checkCondition(array $condition, int $httpStatus, DOMDocument $dom, string $url): array {
    $type        = $condition['type'] ?? '';
    $selector    = $condition['selector'] ?? '';
    $description = $condition['description'] ?? $selector;

    switch ($type) {

        case 'http_status':
            if ($httpStatus === 200) {
                return ['pass' => true];
            }
            return [
                'pass'        => false,
                'error_type'  => 'http_error',
                'error_msg'   => "HTTP 상태코드 오류: {$httpStatus} (기대값: 200)",
                'detail'      => "URL: {$url}, HTTP Status: {$httpStatus}",
            ];

        case 'element_exists':
            $nodes = findElements($dom, $selector);
            if (!empty($nodes)) {
                return ['pass' => true];
            }
            return [
                'pass'       => false,
                'error_type' => 'element_missing',
                'error_msg'  => "요소를 찾을 수 없습니다: [{$description}] selector: `{$selector}`",
                'detail'     => "Selector: {$selector}",
            ];

        case 'class_empty':
            $nodes = findElements($dom, $selector);
            if (empty($nodes)) {
                return [
                    'pass'       => false,
                    'error_type' => 'element_missing',
                    'error_msg'  => "요소를 찾을 수 없습니다: [{$description}] selector: `{$selector}`",
                    'detail'     => "Selector: {$selector}",
                ];
            }
            $node = $nodes[0];
            $text = trim($node->textContent ?? '');
            $childNodes = $node->childNodes;
            $hasContent = false;
            foreach ($childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) { $hasContent = true; break; }
            }
            if (empty($text) && !$hasContent) {
                return [
                    'pass'       => false,
                    'error_type' => 'class_empty',
                    'error_msg'  => "요소 내용이 비어있습니다: [{$description}] selector: `{$selector}`",
                    'detail'     => "Selector: {$selector}, 내용: 빈값",
                ];
            }
            return ['pass' => true];

        case 'img_src':
            $nodes = findElements($dom, $selector);
            if (empty($nodes)) {
                return [
                    'pass'       => false,
                    'error_type' => 'element_missing',
                    'error_msg'  => "이미지 요소를 찾을 수 없습니다: [{$description}] selector: `{$selector}`",
                    'detail'     => "Selector: {$selector}",
                ];
            }
            // selector가 img 태그인지, 아니면 img를 포함하는지 확인
            $imgNode = null;
            if (strtolower($nodes[0]->nodeName) === 'img') {
                $imgNode = $nodes[0];
            } else {
                $imgs = $nodes[0]->getElementsByTagName('img');
                if ($imgs->length > 0) $imgNode = $imgs->item(0);
            }
            if (!$imgNode) {
                return [
                    'pass'       => false,
                    'error_type' => 'img_broken',
                    'error_msg'  => "img 태그를 찾을 수 없습니다: [{$description}] selector: `{$selector}`",
                    'detail'     => "Selector: {$selector}",
                ];
            }
            $src = $imgNode->getAttribute('src');
            $check = checkImageSrc($src, $url);
            if (!$check['valid']) {
                return [
                    'pass'       => false,
                    'error_type' => 'img_broken',
                    'error_msg'  => "이미지 로딩 오류: [{$description}] {$check['reason']}",
                    'detail'     => "Selector: {$selector}, src: {$src}",
                ];
            }
            return ['pass' => true];

        case 'class_first_img':
            $nodes = findElements($dom, $selector);
            if (empty($nodes)) {
                return [
                    'pass'       => false,
                    'error_type' => 'element_missing',
                    'error_msg'  => "요소를 찾을 수 없습니다: [{$description}] selector: `{$selector}`",
                    'detail'     => "Selector: {$selector}",
                ];
            }
            $imgs = $nodes[0]->getElementsByTagName('img');
            if ($imgs->length === 0) {
                return [
                    'pass'       => false,
                    'error_type' => 'img_broken',
                    'error_msg'  => "이미지가 없습니다: [{$description}] selector: `{$selector}` 안에 img 없음",
                    'detail'     => "Selector: {$selector}",
                ];
            }
            $src = $imgs->item(0)->getAttribute('src');
            $check = checkImageSrc($src, $url);
            if (!$check['valid']) {
                return [
                    'pass'       => false,
                    'error_type' => 'img_broken',
                    'error_msg'  => "첫번째 이미지 오류: [{$description}] {$check['reason']}",
                    'detail'     => "Selector: {$selector}, src: {$src}",
                ];
            }
            return ['pass' => true];

        default:
            return ['pass' => true]; // 알 수 없는 타입은 통과
    }
}

/**
 * 사이트 전체 체크 실행
 * 반환: ['pass' => bool, 'http_status' => int, 'errors' => [...]]
 */
function checkSite(array $site): array {
    $url        = $site['url'];
    $conditions = json_decode($site['check_conditions'] ?? '[]', true) ?: [];
    $errors     = [];

    // HTTP 요청
    $fetch = fetchUrl($url);
    if (!$fetch['success']) {
        return [
            'pass'        => false,
            'http_status' => 0,
            'errors'      => [[
                'error_type'  => 'connection_error',
                'error_msg'   => "연결 오류: {$fetch['error']}",
                'detail'      => "URL: {$url}",
            ]],
        ];
    }

    $httpStatus = $fetch['http_status'];
    $html       = $fetch['html'];
    $dom        = parseHtml($html);

    // 각 조건 체크
    foreach ($conditions as $condition) {
        $result = checkCondition($condition, $httpStatus, $dom, $url);
        if (!$result['pass']) {
            $errors[] = [
                'error_type' => $result['error_type'] ?? 'unknown',
                'error_msg'  => $result['error_msg'] ?? '알 수 없는 오류',
                'detail'     => $result['detail'] ?? '',
            ];
        }
    }

    // 조건이 없으면 HTTP 200인지만 체크
    if (empty($conditions)) {
        if ($httpStatus !== 200) {
            $errors[] = [
                'error_type' => 'http_error',
                'error_msg'  => "HTTP 오류: {$httpStatus}",
                'detail'     => "URL: {$url}",
            ];
        }
    }

    return [
        'pass'        => empty($errors),
        'http_status' => $httpStatus,
        'errors'      => $errors,
    ];
}
