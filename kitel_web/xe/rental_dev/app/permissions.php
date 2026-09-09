<?php
function group_permissions($type)
{
    static $cache = array();
    if (isset($cache[$type])) {
        return $cache[$type];
    }
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $stmt = $pdo->prepare('
            SELECT group_srl
            FROM kitel_rental_allowed_groups
            WHERE permission_type = ? AND active = 1
        ');
        $stmt->execute(array($type));
        $cache[$type] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        return $cache[$type];
    }
    $groups = array();
    foreach (config('allowed_groups') as $row) {
        if ((int)$row['active'] === 1 && $row['permission_type'] === $type) {
            $groups[] = (int)$row['group_srl'];
        }
    }
    $cache[$type] = $groups;
    return $cache[$type];
}

function user_has_group($user, $allowed)
{
    if (!$user || empty($user['groups'])) {
        return false;
    }
    return count(array_intersect(array_map('intval', $user['groups']), $allowed)) > 0;
}

function user_can_borrow($user)
{
    return user_is_admin($user) || user_has_group($user, group_permissions('user'));
}

function user_is_admin($user)
{
    if (!$user) {
        return false;
    }
    if (isset($user['is_admin']) && $user['is_admin'] === 'Y') {
        return true;
    }
    return user_has_group($user, group_permissions('admin'));
}

function require_borrow_permission()
{
    $user = require_login();
    if (!user_can_borrow($user)) {
        http_response_code(403);
        exit('대여 권한이 없습니다.');
    }
    return $user;
}

function require_admin()
{
    $user = require_login();
    if (!user_is_admin($user)) {
        http_response_code(403);
        exit('관리자 권한이 없습니다.');
    }
    return $user;
}

function allowed_group_rows($state)
{
    if (isset($state['allowed_groups']) && is_array($state['allowed_groups'])) {
        return $state['allowed_groups'];
    }
    return config('allowed_groups');
}

function save_allowed_group(&$state, $groupSrl, $title, $permissionType, $active, $actor)
{
    $groupSrl = (int)$groupSrl;
    $title = trim($title);
    $permissionType = trim($permissionType);
    $active = (int)$active;
    if ($groupSrl <= 0 || $title === '' || !in_array($permissionType, array('user', 'admin', 'staff'), true)) {
        throw new RuntimeException('권한 그룹 입력값을 확인해 주세요.');
    }

    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $stmt = $pdo->prepare('
            INSERT INTO kitel_rental_allowed_groups
                (group_srl, group_title_snapshot, permission_type, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                group_title_snapshot = VALUES(group_title_snapshot),
                active = VALUES(active),
                updated_at = VALUES(updated_at)
        ');
        $now = db_now();
        $stmt->execute(array($groupSrl, $title, $permissionType, $active, $now, $now));
        add_log($state, 'permission.upsert', null, null, null, null, $permissionType . ':' . $groupSrl, $actor);
        return;
    }

    if (!isset($state['allowed_groups']) || !is_array($state['allowed_groups'])) {
        $state['allowed_groups'] = config('allowed_groups');
    }
    $updated = false;
    foreach ($state['allowed_groups'] as &$row) {
        if ((int)$row['group_srl'] === $groupSrl && $row['permission_type'] === $permissionType) {
            $row['group_title_snapshot'] = $title;
            $row['active'] = $active;
            $updated = true;
            break;
        }
    }
    unset($row);
    if (!$updated) {
        $state['allowed_groups'][] = array(
            'group_srl' => $groupSrl,
            'group_title_snapshot' => $title,
            'permission_type' => $permissionType,
            'active' => $active,
        );
    }
    add_log($state, 'permission.upsert', null, null, null, null, $permissionType . ':' . $groupSrl, $actor);
}
