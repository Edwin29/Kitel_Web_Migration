<?php
/** Local-only member skin and generation field setup. Dry-run unless --apply is supplied. */
declare(strict_types=1);
require_once __DIR__ . '/dev-environment-guard.php';

[$root, $config] = kitelDevConfig('D:/rhymix_dev/www/rhymix');
$db = $config['db']['master'];
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']), $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$prefix = $db['prefix'];
$module = $pdo->query("SELECT module_srl, mid, module, layout_srl, mlayout_srl FROM {$prefix}modules WHERE module='member' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$module || (int)$module['module_srl'] !== 1 || $module['mid'] !== 'member' || $module['module'] !== 'member') throw new RuntimeException('Unexpected local member module.');
$layout = $pdo->query("SELECT layout_srl, layout FROM {$prefix}layouts WHERE layout_srl=166")->fetch(PDO::FETCH_ASSOC);
if (!$layout || $layout['layout'] !== 'kitel_site') throw new RuntimeException('Expected KITEL shared layout 166 is missing.');
$row = $pdo->query("SELECT config FROM {$prefix}module_config WHERE module='member' AND site_srl=0")->fetch(PDO::FETCH_ASSOC);
if (!$row) throw new RuntimeException('Member configuration missing.');
$memberConfig = unserialize($row['config'], ['allowed_classes' => ['stdClass']]);
if (!$memberConfig instanceof stdClass || ($memberConfig->enable_join ?? '') !== 'Y') throw new RuntimeException('Unexpected member configuration.');
$existing = $pdo->query("SELECT * FROM {$prefix}member_join_form WHERE column_name='generation'")->fetchAll(PDO::FETCH_ASSOC);
if (count($existing) > 1 || ($existing && ($existing[0]['column_title'] !== '기수' || $existing[0]['column_type'] !== 'text' || $existing[0]['required'] !== 'Y' || $existing[0]['is_active'] !== 'Y'))) throw new RuntimeException('Conflicting generation field.');
printf("member skin: %s -> kitel_member; layout: %s -> 166; mobile skin: %s -> /USE_RESPONSIVE/; member module mobile layout: %s -> -2; generation: %s\n", $memberConfig->skin ?? 'default', $memberConfig->layout_srl ?? 0, $memberConfig->mskin ?? 'default', $module['mlayout_srl'], $existing ? 'existing' : 'add required text field');
if (!in_array('--apply', $argv, true)) { echo "DRY RUN. Pass --apply for the exact local Rhymix DB only.\n"; exit; }
if (!is_dir($root . '/modules/member/skins/kitel_member')) throw new RuntimeException('Sync the repository member skin before applying.');

$backupDir = 'D:/rhymix_dev/phase10-member-backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0700, true);
$backupPath = $backupDir . '/' . date('Ymd-His') . '-member.json';
file_put_contents($backupPath, json_encode(['module_config' => base64_encode($row['config']), 'member_module' => $module, 'generation_row' => $existing[0] ?? null], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$pdo->beginTransaction();
try {
    if (!$existing) {
        $pdo->exec("INSERT INTO {$prefix}sequence VALUES ()");
        $srl = (int)$pdo->lastInsertId();
        $insert = $pdo->prepare("INSERT INTO {$prefix}member_join_form (member_join_form_srl,column_type,column_name,column_title,required,default_value,options,is_active,description,list_order,regdate) VALUES (?,'text','generation','기수','Y','',NULL,'Y','동아리 기수',?,DATE_FORMAT(NOW(),'%Y%m%d%H%i%s'))");
        $insert->execute([$srl, $srl]);
    } else $srl = (int)$existing[0]['member_join_form_srl'];
    $found = false;
    $standardFields = ['phone_number' => ['isUse' => true, 'required' => true, 'isPublic' => 'N'], 'homepage' => ['isUse' => false, 'required' => false, 'isPublic' => 'N'], 'blog' => ['isUse' => false, 'required' => false, 'isPublic' => 'N']];
    foreach ($memberConfig->signupForm ?? [] as $field) {
        if (isset($standardFields[$field->name ?? ''])) {
            foreach ($standardFields[$field->name] as $property => $value) $field->{$property} = $value;
        }
        if (($field->name ?? null) === 'generation') {
            if ((int)($field->member_join_form_srl ?? 0) !== $srl) throw new RuntimeException('Generation signup configuration mismatch.');
            $field->isUse = true; $field->required = true; $field->isPublic = 'Y'; $found = true;
        }
    }
    if (!$found) $memberConfig->signupForm[] = (object)['name'=>'generation','title'=>'기수','type'=>'text','member_join_form_srl'=>$srl,'required'=>true,'isUse'=>true,'isPublic'=>'Y','description'=>'동아리 기수'];
    $memberConfig->skin = 'kitel_member';
    $memberConfig->phone_number = 'Y';
    $memberConfig->phone_number_default_country = 'KOR';
    $memberConfig->homepage = 'N';
    $memberConfig->blog = 'N';
    $memberConfig->layout_srl = 166;
    $memberConfig->mskin = '/USE_RESPONSIVE/';
    $memberConfig->mlayout_srl = -2;
    $update = $pdo->prepare("UPDATE {$prefix}module_config SET config=? WHERE module='member' AND site_srl=0");
    $update->execute([serialize($memberConfig)]);
    if (!$update) throw new RuntimeException('Member config update failed.');
    $moduleUpdate = $pdo->prepare("UPDATE {$prefix}modules SET layout_srl=166, mlayout_srl=-2 WHERE module_srl=1 AND mid='member' AND module='member'");
    $moduleUpdate->execute();
    $pdo->commit();
    echo "APPLIED to local development DB. Backup: {$backupPath}. Rebuild Rhymix cache.\n";
} catch (Throwable $e) { $pdo->rollBack(); throw $e; }
