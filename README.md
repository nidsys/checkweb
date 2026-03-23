# 🛰️ Site Monitor

사이트 상태 모니터링 시스템 (PHP + MySQL + Apache)

---

## 📁 파일 구조

```
site_monitor/
├── index.php           # 로그인 페이지 (기본 진입점)
├── register.php        # 회원가입 페이지
├── dashboard.php       # 메인 대시보드 (로그인 필요)
├── config.php          # DB 설정, 상수 정의
├── install.sql         # DB 설치 스크립트
├── .htaccess           # Apache 보안 설정
│
├── includes/
│   ├── db.php          # PDO 연결, 공통 함수
│   └── checker.php     # 사이트 체크 핵심 로직
│
├── api/
│   ├── auth.php        # 로그인/회원가입/로그아웃
│   ├── sites.php       # 사이트 CRUD + 즉시체크
│   └── errors.php      # 오류 로그 조회/삭제
│
├── cron/
│   └── run_checks.php  # 크론 실행 스크립트
│
└── assets/
    ├── css/style.css   # 반응형 스타일
    └── js/app.js       # jQuery 앱 로직
```

---

## ⚙️ 설치 방법

### 1. DB 설정
```sql
mysql -u root -p < install.sql
```

### 2. config.php 수정
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'site_monitor');
define('DB_USER', '실제DB계정');
define('DB_PASS', '실제DB비밀번호');
```

### 3. Apache 설정
DocumentRoot 또는 VirtualHost에서 해당 디렉토리를 바라보도록 설정.  
`mod_rewrite` 활성화 필요.

```apache
<Directory /var/www/html/site_monitor>
    AllowOverride All
</Directory>
```

### 4. 크론 설정
매 1분마다 실행 (체크 주기는 스크립트 내에서 판단):
```bash
* * * * * php /var/www/html/site_monitor/cron/run_checks.php >> /var/log/site_monitor.log 2>&1
```

### 5. PHP 확장 확인
- `curl` - URL 체크에 필요
- `dom` - HTML 파싱에 필요
- `mbstring` - 다국어 처리
- `pdo_mysql` - DB 연결

```bash
php -m | grep -E "curl|dom|mbstring|pdo_mysql"
```

---

## 🔍 체크 조건 유형

| 유형 | 설명 | 셀렉터 필요 |
|------|------|------------|
| `http_status` | HTTP 200 응답 여부 | ❌ |
| `element_exists` | 특정 요소가 HTML에 존재하는지 | ✅ |
| `class_empty` | 특정 요소의 내용이 비어있는지 | ✅ |
| `img_src` | 특정 img의 src가 유효한 이미지인지 | ✅ |
| `class_first_img` | 특정 영역 안의 첫번째 img가 유효한지 | ✅ |

### 셀렉터 예시
- `.main-banner` — class 셀렉터
- `#header-logo` — id 셀렉터
- `div.hero-section` — 태그 + 클래스
- `.product-gallery` — 갤러리 첫번째 이미지 체크에 사용

---

## 📊 오류 유형

| 코드 | 설명 |
|------|------|
| `http_error` | HTTP 상태코드가 200이 아님 |
| `element_missing` | CSS 셀렉터로 요소를 찾지 못함 |
| `img_broken` | img src가 비어있거나 이미지 로딩 실패 |
| `class_empty` | 요소 내용이 비어있음 |
| `connection_error` | URL에 연결 자체가 실패 |

---

## 🔒 보안 참고사항

- 비밀번호는 `bcrypt`로 해시 저장
- PDO prepared statement로 SQL injection 방지
- `.htaccess`로 `includes/`, `cron/` 직접 접근 차단
- 모든 API는 세션 기반 인증 확인
- 운영 환경에서는 `config.php`의 `display_errors`를 off로 설정
