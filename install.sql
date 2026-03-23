-- =============================================
-- Site Monitor - Database Setup
-- Usage: mysql -u root -p < install.sql
-- =============================================

CREATE DATABASE IF NOT EXISTS site_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE site_monitor;

-- 사용자 테이블
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL COMMENT '아이디',
    password VARCHAR(255) NOT NULL COMMENT '비밀번호 (bcrypt)',
    name VARCHAR(100) NOT NULL COMMENT '이름',
    email VARCHAR(100) UNIQUE NOT NULL COMMENT '이메일',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 사이트 목록 테이블
-- check_interval: 분 단위 (1, 5, 30, 60, 360, 1440)
-- check_conditions: JSON 배열로 체크 조건 저장
-- last_status: ok / error / unknown
CREATE TABLE IF NOT EXISTS sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(200) NOT NULL COMMENT '사이트 이름',
    url TEXT NOT NULL COMMENT '체크할 URL',
    check_interval INT NOT NULL DEFAULT 5 COMMENT '체크 주기 (분)',
    check_conditions JSON COMMENT '체크 조건 배열',
    is_active TINYINT(1) DEFAULT 1 COMMENT '활성화 여부',
    last_checked DATETIME NULL COMMENT '마지막 체크 시각',
    last_status VARCHAR(20) DEFAULT 'unknown' COMMENT 'ok/error/unknown',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 오류 로그 테이블
CREATE TABLE IF NOT EXISTS error_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    user_id INT NOT NULL,
    site_name VARCHAR(200) NOT NULL COMMENT '사이트 이름 (스냅샷)',
    site_url TEXT NOT NULL COMMENT 'URL 스냅샷',
    http_status INT NULL COMMENT 'HTTP 상태코드',
    error_type VARCHAR(50) NOT NULL COMMENT 'http_error / element_missing / img_broken / class_empty / connection_error',
    error_message TEXT COMMENT '오류 상세 메시지',
    condition_detail TEXT COMMENT '어떤 조건이 실패했는지',
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 인덱스
CREATE INDEX idx_sites_user ON sites(user_id);
CREATE INDEX idx_sites_last_checked ON sites(last_checked);
CREATE INDEX idx_error_logs_site ON error_logs(site_id);
CREATE INDEX idx_error_logs_user ON error_logs(user_id);
CREATE INDEX idx_error_logs_checked_at ON error_logs(checked_at);
