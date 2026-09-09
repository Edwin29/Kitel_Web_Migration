INSERT INTO kitel_rental_allowed_groups
  (group_srl, group_title_snapshot, permission_type, active, created_at, updated_at)
VALUES
  (3, '정회원', 'user', 1, NOW(), NOW()),
  (1, '관리그룹', 'admin', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  group_title_snapshot = VALUES(group_title_snapshot),
  active = VALUES(active),
  updated_at = NOW();

INSERT INTO kitel_rental_categories
  (name, slug, next_serial, description, is_active, created_by_member_srl, created_at, updated_at)
VALUES
  ('니퍼', 'nipper', 1, '공구류', 1, 4, NOW(), NOW()),
  ('롱노즈', 'long-nose', 1, '공구류', 1, 4, NOW(), NOW()),
  ('와이어 스트리퍼', 'wire-stripper', 1, '공구류', 1, 4, NOW(), NOW()),
  ('인두기', 'soldering-iron', 1, '납땜 장비', 1, 4, NOW(), NOW()),
  ('오실로스코프', 'oscilloscope', 1, '계측 장비', 1, 4, NOW(), NOW()),
  ('파워서플라이', 'power-supply', 1, '전원 장비', 1, 4, NOW(), NOW()),
  ('함수발생기', 'function-generator', 1, '계측 장비', 1, 4, NOW(), NOW()),
  ('3D 프린터', '3d-printer', 1, '제작 장비', 1, 4, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  description = VALUES(description),
  is_active = VALUES(is_active),
  updated_at = NOW();
