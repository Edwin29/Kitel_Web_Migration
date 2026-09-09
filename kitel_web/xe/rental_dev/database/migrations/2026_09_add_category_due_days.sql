-- 카테고리별 대여 기간(일). NULL이면 app/config.php 의 default_due_days 를 쓴다.
-- 기존 카테고리는 전부 NULL로 남으므로 마이그레이션 직후 동작은 이전과 동일하다.
ALTER TABLE kitel_rental_categories
  ADD COLUMN due_days INT UNSIGNED NULL AFTER max_per_user;
