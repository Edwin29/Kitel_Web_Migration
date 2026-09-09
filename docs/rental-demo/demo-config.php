<?php
// ── rental_demo 전용 설정 ──
// 이 인스턴스는 스크린샷/체험용 복제본이다. 운영과 완전히 분리되어 있다.
//   - mode = local  → 저장소는 database/local_data.json 파일 하나. MySQL 에 연결하지 않는다.
//   - 인증은 fake_user 자동 로그인 (아래). 로그인 화면을 거치지 않는다.
//   - 이 파일만 운영본(rental_dev/app/config.php)과 다르다.
return array(
    'mode' => 'local',
    // local 모드는 비-localhost 호스트를 500으로 막는다. 데모는 운영과 같은 도메인에서
    // 서비스되므로 그 호스트를 명시적으로 허용한다.
    'allow_local_http_hosts' => array('localhost', '127.0.0.1', '::1', 'kitel.kw.ac.kr'),
    'qr_backend' => 'qrencode',
    'qrencode_path' => 'qrencode',
    'require_qr_on_return' => false,
    // 데모는 어느 IP 에서 접속하든 대여/반납을 시연할 수 있어야 한다.
    'require_trusted_network_for_rental' => false,
    'trusted_network_cidrs' => array('192.168.1.0/24'),
    'trusted_proxies' => array(),
    'base_url' => '/rental_demo/public',
    'canonical_base_url' => 'https://kitel.kw.ac.kr/rental_demo/public',
    'xe_root' => '/volume1/kitel_web/xe',
    'data_file' => __DIR__ . '/../database/local_data.json',
    'default_due_days' => 7,
    // 자동 로그인되는 가상 계정. is_admin=Y 이므로 모든 화면에 접근 가능하다.
    'fake_user' => array(
        'member_srl' => 4,
        'user_id' => 'admin',
        'nick_name' => '정보부장',
        'email_address' => 'admin@example.com',
        'is_admin' => 'Y',
        'groups' => array(1),
    ),
    // 권한 그룹 화면(admin/permissions.php)이 운영과 같게 보이도록 3행을 넣는다.
    // fake_user 가 이미 관리자라 기능상 영향은 없다.
    'allowed_groups' => array(
        array('group_srl' => 3, 'group_title_snapshot' => '정회원', 'permission_type' => 'user', 'active' => 1),
        array('group_srl' => 1, 'group_title_snapshot' => '관리그룹', 'permission_type' => 'admin', 'active' => 1),
        array('group_srl' => 80534, 'group_title_snapshot' => '기술부', 'permission_type' => 'admin', 'active' => 1),
    ),
);
