-- 이미 tools/install.php로 설치를 마친 운영 DB에 새 컬럼을 추가하기 위한 마이그레이션입니다.
-- 신규 설치라면 database/schema.sql에 이미 반영되어 있으니 이 파일을 실행할 필요 없습니다.
-- MariaDB 10.5+/MySQL 8.0.29+에서 지원하는 IF NOT EXISTS 구문을 사용해 반복 실행해도 안전합니다.

ALTER TABLE kitel_rental_categories
  ADD COLUMN IF NOT EXISTS tracking_mode VARCHAR(10) NOT NULL DEFAULT 'unique' AFTER description,
  ADD COLUMN IF NOT EXISTS max_per_user INT UNSIGNED NULL AFTER tracking_mode;
