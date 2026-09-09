<?php
return array(
    'mode' => getenv('KITEL_RENTAL_MODE') ?: 'production',
    'allow_local_http_hosts' => array('localhost', '127.0.0.1', '::1'),
    'qr_backend' => getenv('KITEL_RENTAL_QR_BACKEND') ?: 'qrencode',
    'qrencode_path' => getenv('KITEL_RENTAL_QRENCODE_PATH') ?: 'qrencode',
    'require_qr_on_return' => getenv('KITEL_RENTAL_REQUIRE_QR_ON_RETURN') === '1',
    // 대여/반납은 동방 와이파이(사설 IP 대역)에서만 가능하게 제한한다 (기본 켜짐).
    // 환경변수를 못 넣는 환경(PHP-FPM 등)을 감안해서 기본값 자체를 켜둔다.
    // 끄고 싶으면 KITEL_RENTAL_REQUIRE_TRUSTED_NETWORK=0 을 넣거나 아래 줄을 false로 바꾸면 된다.
    'require_trusted_network_for_rental' => getenv('KITEL_RENTAL_REQUIRE_TRUSTED_NETWORK') !== '0',
    //'require_trusted_network_for_rental' => false,
    // 콤마로 여러 대역 지정 가능. 환경변수를 못 넣는 환경이면 이 기본값을 직접 수정한다.
    'trusted_network_cidrs' => array_values(array_filter(array_map('trim', explode(',', getenv('KITEL_RENTAL_TRUSTED_CIDRS') ?: '192.168.1.0/24')))),
    'base_url' => getenv('KITEL_RENTAL_BASE_URL') ?: '/rental_dev/public',
    'canonical_base_url' => getenv('KITEL_RENTAL_CANONICAL_URL') ?: 'https://kitel.kw.ac.kr/rental_dev/public',
    'xe_root' => getenv('KITEL_XE_ROOT') ?: '/volume1/kitel_web/xe',
    'data_file' => __DIR__ . '/../database/local_data.json',
    'default_due_days' => 7,
    'fake_user' => array(
        'member_srl' => 4,
        'user_id' => 'admin',
        'nick_name' => '정보부장',
        'email_address' => 'admin@example.com',
        'is_admin' => 'Y',
        'groups' => array(1),
    ),
    'allowed_groups' => array(
        array('group_srl' => 3, 'permission_type' => 'user', 'active' => 1),
        array('group_srl' => 1, 'permission_type' => 'admin', 'active' => 1),
    ),
);
