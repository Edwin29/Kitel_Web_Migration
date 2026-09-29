<?php
/** Local fixture helper: mark a QA comment secret or change a QA member's group. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';
[, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
$db = $config['db']['master'];
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']), $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$options = getopt('', ['comment:', 'status:', 'user:', 'group:', 'apply']);
$apply = isset($options['apply']);
if (isset($options['comment'])) {
    $srl = filter_var($options['comment'], FILTER_VALIDATE_INT);
    $find = $pdo->prepare('SELECT c.comment_srl, c.is_secret, c.status, d.title FROM ' . $db['prefix'] . 'comments c JOIN ' . $db['prefix'] . 'documents d ON d.document_srl = c.document_srl WHERE c.comment_srl = ? AND c.member_srl = 5 AND d.module_srl IN (115, 131)');
    $find->execute([$srl]);
    $row = $find->fetch(PDO::FETCH_ASSOC);
    if (!$row || !str_starts_with($row['title'], '[P2QA-')) throw new RuntimeException('Not a known development QA comment.');
    if (isset($options['status'])) {
        $status = ['normal' => 1, 'deleted' => 7, 'admin-deleted' => 8][$options['status']] ?? null;
        if ($status === null) throw new RuntimeException('Invalid QA comment status.');
        printf("comment %d: status %s -> %d\n", $srl, $row['status'], $status);
        if ($apply) {
            $update = $pdo->prepare('UPDATE ' . $db['prefix'] . 'comments SET status = ? WHERE comment_srl = ?');
            $update->execute([$status, $srl]);
        }
    } else {
        printf("comment %d: secret %s -> Y\n", $srl, $row['is_secret']);
        if ($apply) {
            $update = $pdo->prepare('UPDATE ' . $db['prefix'] . 'comments SET is_secret = ? WHERE comment_srl = ?');
            $update->execute(['Y', $srl]);
        }
    }
} elseif (isset($options['user'], $options['group'])) {
    $userId = (string)$options['user'];
    $group = (string)$options['group'];
    if (!preg_match('/^p2qa_[a-z0-9_]+$/', $userId) || !in_array($group, ['pending', 'junior', 'full'], true)) throw new RuntimeException('Invalid QA user or group.');
    $find = $pdo->prepare('SELECT member_srl, is_admin FROM ' . $db['prefix'] . 'member WHERE user_id = ?');
    $find->execute([$userId]);
    $row = $find->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['is_admin'] === 'Y') throw new RuntimeException('Not a known non-admin QA user.');
    $memberSrl = (int)$row['member_srl'];
    printf("user %s (%d): QA group -> %s\n", $userId, $memberSrl, $group);
    if ($apply) {
        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare('DELETE FROM ' . $db['prefix'] . 'member_group_member WHERE member_srl = ? AND group_srl IN (3, 4)');
            $delete->execute([$memberSrl]);
            $target = ['junior' => 3, 'full' => 4][$group] ?? null;
            if ($target) {
                $insert = $pdo->prepare('INSERT INTO ' . $db['prefix'] . 'member_group_member (site_srl, member_srl, group_srl, regdate) VALUES (?, ?, ?, ?)');
                $insert->execute([0, $memberSrl, $target, date('YmdHis')]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
} else {
    throw new RuntimeException('Pass --comment=SRL or --user=ID --group=pending|junior|full.');
}
echo $apply ? "APPLIED to local development DB.\n" : "DRY RUN. Pass --apply to change only this QA fixture.\n";
