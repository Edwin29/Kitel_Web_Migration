<?php
/** Local-only homework dashboard demonstration data. Dry-run by default. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';

$apply = in_array('--apply', $argv, true);
$cleanup = in_array('--cleanup', $argv, true);
if (count(array_diff(array_slice($argv, 1), ['--apply', '--cleanup']))) {
    throw new RuntimeException('Use no arguments, --apply, --cleanup, or --cleanup --apply.');
}

[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
$db = $config['db']['master'];
$prefix = $db['prefix'];
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
    $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$privateRoot = getenv('KITEL_HOMEWORK_PRIVATE_ROOT') ?: dirname(dirname($root)) . '/kitel-homework-private';
if (!preg_match('~^(?:[a-zA-Z]:[/\\\\]|/)~', $privateRoot)) throw new RuntimeException('Private root must be absolute.');
$storageDir = rtrim($privateRoot, '/\\') . '/149';
if (!is_dir($storageDir) && $apply && !mkdir($storageDir, 0700, true)) {
    throw new RuntimeException('Cannot create private Homework storage.');
}
if ($apply && (!is_dir($storageDir) || is_link($storageDir) || strcasecmp(str_replace('\\', '/', realpath($storageDir)), str_replace('\\', '/', $storageDir)) !== 0 || str_starts_with(str_replace('\\', '/', $storageDir) . '/', str_replace('\\', '/', $root) . '/'))) {
    throw new RuntimeException('Refusing an unexpected homework attachment directory.');
}

$module = $pdo->query("SELECT module_srl, module FROM {$prefix}modules WHERE mid = 'homework'")->fetch(PDO::FETCH_ASSOC);
$group = $pdo->query("SELECT title FROM {$prefix}member_group WHERE group_srl = 3")->fetch(PDO::FETCH_ASSOC);
if (!$module || (int)$module['module_srl'] !== 149 || $module['module'] !== 'homework' || !$group || $group['title'] !== '준회원') {
    throw new RuntimeException('Refusing unexpected homework module or junior group.');
}

$marker = 'KITEL_PHASE6_DASHBOARD_DEMO';
$taskTitle = '[P6DEMO] 과제 제출 시연';
$members = [
    ['p6demo_junior_01', '99기_데모A', true],
    ['p6demo_junior_02', '99기_데모B', true],
    ['p6demo_junior_03', '99기_데모C', false],
    ['p6demo_junior_04', '99기_데모D', false],
    ['p6demo_junior_05', '99기_데모E', true],
    ['p6demo_junior_06', '99기_데모F', true],
    ['p6demo_junior_07', '99기_데모G', false],
    ['p6demo_junior_08', '99기_데모H', false],
];
$ids = array_column($members, 0);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$find = $pdo->prepare("SELECT member_srl, user_id, email_address, description, is_admin FROM {$prefix}member WHERE user_id IN ($placeholders)");
$find->execute($ids);
$existing = $find->fetchAll(PDO::FETCH_ASSOC);
$findTask = $pdo->prepare("SELECT task_srl, title, description, module_srl FROM {$prefix}homework_task WHERE title = ?");
$findTask->execute([$taskTitle]);
$demoTasks = $findTask->fetchAll(PDO::FETCH_ASSOC);
if (count($demoTasks) > 1) throw new RuntimeException('Multiple demo task titles found.');
$demoTask = $demoTasks[0] ?? null;
$admin = $pdo->query("SELECT member_srl FROM {$prefix}member WHERE user_id = 'admin' AND is_admin = 'Y'")->fetch(PDO::FETCH_ASSOC);
if (!$admin) throw new RuntimeException('The local development admin account is missing.');

if ($cleanup) {
    $attachmentFiles = [];
    if ($demoTask && ($demoTask['description'] !== $marker || (int)$demoTask['module_srl'] !== 149)) {
        throw new RuntimeException('Refusing to delete a task without the exact fixture marker.');
    }
    foreach ($existing as $row) {
        if ($row['description'] !== $marker || $row['is_admin'] !== 'N' ||
            $row['email_address'] !== $row['user_id'] . '@p6demo.invalid') {
            throw new RuntimeException('Refusing to delete an account without the exact fixture marker: ' . $row['user_id']);
        }
        $other = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}homework_submission WHERE member_srl = ? AND (module_srl <> 149 OR content NOT LIKE '[P6DEMO] %')");
        $other->execute([(int)$row['member_srl']]);
        if ((int)$other->fetchColumn()) throw new RuntimeException('Fixture account has non-demo homework submissions: ' . $row['user_id']);
        $other = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}member_group_member WHERE member_srl = ? AND group_srl <> 3");
        $other->execute([(int)$row['member_srl']]);
        if ((int)$other->fetchColumn()) throw new RuntimeException('Fixture account has another group: ' . $row['user_id']);
        $files = $pdo->prepare("SELECT stored_filename FROM {$prefix}homework_submission WHERE member_srl = ? AND module_srl = 149 AND stored_filename IS NOT NULL");
        $files->execute([(int)$row['member_srl']]);
        foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $filename) {
            if (!preg_match('/^p6demo-[0-9]+\.txt$/', $filename)) throw new RuntimeException('Unexpected demo attachment filename.');
            $attachmentFiles[] = $storageDir . '/' . $filename;
        }
    }
    if ($demoTask) {
        $fixtureSrls = array_map(static fn($row) => (int)$row['member_srl'], $existing);
        $findTaskSubmissions = $pdo->prepare("SELECT member_srl, content FROM {$prefix}homework_submission WHERE task_srl = ?");
        $findTaskSubmissions->execute([(int)$demoTask['task_srl']]);
        foreach ($findTaskSubmissions->fetchAll(PDO::FETCH_ASSOC) as $submission) {
            if (!in_array((int)$submission['member_srl'], $fixtureSrls, true) || !str_starts_with($submission['content'], '[P6DEMO] ')) {
                throw new RuntimeException('Demo task contains a non-demo submission; cleanup refused.');
            }
        }
    }
    printf("%s cleanup: %d demo accounts and %d demo task.\n", $apply ? 'APPLY' : 'DRY RUN', count($existing), $demoTask ? 1 : 0);
    if (!$apply || (!$existing && !$demoTask)) exit;
    $pdo->beginTransaction();
    try {
        $deleteSubmissions = $pdo->prepare("DELETE FROM {$prefix}homework_submission WHERE member_srl = ? AND module_srl = 149 AND content LIKE '[P6DEMO] %'");
        $deleteGroup = $pdo->prepare("DELETE FROM {$prefix}member_group_member WHERE member_srl = ? AND group_srl = 3");
        $deleteMember = $pdo->prepare("DELETE FROM {$prefix}member WHERE member_srl = ? AND user_id = ? AND description = ?");
        foreach ($existing as $row) {
            $srl = (int)$row['member_srl'];
            $deleteSubmissions->execute([$srl]);
            $deleteGroup->execute([$srl]);
            $deleteMember->execute([$srl, $row['user_id'], $marker]);
        }
        if ($demoTask) {
            $deleteTask = $pdo->prepare("DELETE FROM {$prefix}homework_task WHERE task_srl = ? AND module_srl = 149 AND title = ? AND description = ?");
            $deleteTask->execute([(int)$demoTask['task_srl'], $taskTitle, $marker]);
        }
        $pdo->commit();
        foreach ($attachmentFiles as $path) {
            if (is_file($path)) unlink($path);
        }
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
    exit;
}

if ($existing || $demoTask) throw new RuntimeException('Demo fixture already exists. Inspect it before creating or use --cleanup.');
printf("%s create: 1 hidden demo task, %d demo junior accounts, 4 submissions.\n", $apply ? 'APPLY' : 'DRY RUN', count($members));
if (!$apply) exit;

$createdAttachment = null;
$pdo->beginTransaction();
try {
    $nextSequence = $pdo->prepare("INSERT INTO {$prefix}sequence (seq) VALUES (NULL)");
    $insertMember = $pdo->prepare("INSERT INTO {$prefix}member (member_srl, user_id, password, email_address, email_id, email_host, user_name, nick_name, allow_mailing, allow_message, is_admin, denied, status, regdate, description, list_order) VALUES (?, ?, ?, ?, ?, 'p6demo.invalid', ?, ?, 'N', 'N', 'N', 'N', 'APPROVED', ?, ?, ?)");
    $insertGroup = $pdo->prepare("INSERT INTO {$prefix}member_group_member (site_srl, group_srl, member_srl, regdate) VALUES (0, 3, ?, ?)");
    $insertSubmission = $pdo->prepare("INSERT INTO {$prefix}homework_submission (submission_srl, module_srl, task_srl, member_srl, content, answers, is_late, regdate, source_filename, stored_filename) VALUES (?, 149, ?, ?, ?, '{}', ?, ?, ?, ?)");
    $now = date('YmdHis');
    $nextSequence->execute();
    $taskSrl = (int)$pdo->lastInsertId();
    $insertTask = $pdo->prepare("INSERT INTO {$prefix}homework_task (task_srl, module_srl, title, description, answer_fields, is_visible, deadline, member_srl, regdate) VALUES (?, 149, ?, ?, '[]', 'N', ?, ?, ?)");
    $insertTask->execute([$taskSrl, $taskTitle, $marker, date('Ymd235959', strtotime('-1 day')), (int)$admin['member_srl'], $now]);
    foreach ($members as [$userId, $nickname, $submitted]) {
        $nextSequence->execute();
        $memberSrl = (int)$pdo->lastInsertId();
        // Random, undisclosed password: these accounts are for visual QA, not login.
        $password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $insertMember->execute([$memberSrl, $userId, $password, $userId . '@p6demo.invalid', $userId, $nickname, $nickname, $now, $marker, -$memberSrl]);
        $insertGroup->execute([$memberSrl, $now]);
        if (!$submitted) continue;
        $nextSequence->execute();
        $submissionSrl = (int)$pdo->lastInsertId();
        $content = '[P6DEMO] ' . $nickname . '의 예시 답안입니다.';
        $onTime = in_array($userId, ['p6demo_junior_01', 'p6demo_junior_05'], true);
        $submittedAt = $onTime ? date('Ymd100000', strtotime('-2 days')) : $now;
        $isLate = $onTime ? 'N' : 'Y';
        $filename = $userId === 'p6demo_junior_01' ? 'p6demo-' . $submissionSrl . '.txt' : null;
        $insertSubmission->execute([$submissionSrl, $taskSrl, $memberSrl, $content, $isLate, $submittedAt, $filename ? '시연답안.txt' : null, $filename]);
        if ($filename) {
            $createdAttachment = $storageDir . '/' . $filename;
            if (file_exists($createdAttachment) || file_put_contents($createdAttachment, "KITEL Phase 6 local dashboard demo\n") === false) {
                throw new RuntimeException('Could not create the demo attachment.');
            }
        }
    }
    $pdo->commit();
} catch (Throwable $error) {
    $pdo->rollBack();
    if ($createdAttachment && is_file($createdAttachment)) unlink($createdAttachment);
    throw $error;
}
echo "Created local-only dashboard demo. Use --cleanup --apply to remove it.\n";
