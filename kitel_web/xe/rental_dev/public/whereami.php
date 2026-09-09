<?php
// 임시 진단 페이지입니다. 동방 와이파이/외부에서 각각 접속했을 때
// 서버가 인식하는 IP를 비교하기 위한 용도로만 쓰고, 확인이 끝나면 이 파일을 지워주세요.
require_once __DIR__ . '/_bootstrap.php';
header('Content-Type: text/html; charset=UTF-8');

$remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '(알 수 없음)';
$forwardedFor = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '(없음)';
$realIp = isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : '(없음)';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '(없음)';
$via = isset($_SERVER['HTTP_VIA']) ? $_SERVER['HTTP_VIA'] : '(없음)';
$now = date('Y-m-d H:i:s');
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>서버가 보는 내 접속 정보</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { font-family: -apple-system, sans-serif; background: #eef3f9; margin: 0; }
  main { max-width: 480px; margin: 48px auto; background: #fff; padding: 28px; border-radius: 14px; box-shadow: 0 12px 34px rgba(20,30,50,.08); }
  h1 { font-size: 1.3rem; margin: 0 0 6px; }
  p.note { color: #667; font-size: .88rem; line-height: 1.5; }
  dt { font-weight: 700; margin-top: 16px; font-size: .85rem; color: #445; }
  dd { margin: 4px 0 0; font-family: ui-monospace, monospace; font-size: 1.05rem; background: #f4f6fb; padding: 9px 11px; border-radius: 7px; word-break: break-all; }
  .time { color: #99a; font-size: .78rem; margin-top: 18px; }
</style>
</head>
<body>
<main>
  <h1>서버가 보는 내 접속 정보</h1>
  <p class="note">동방 와이파이 IP 대역을 확인하기 위한 임시 페이지입니다. 확인이 끝나면 이 파일(<code>public/whereami.php</code>)을 삭제해 주세요.</p>
  <dl>
    <dt>REMOTE_ADDR — 서버가 최종적으로 인식한 접속 IP</dt>
    <dd><?php echo htmlspecialchars($remoteAddr, ENT_QUOTES, 'UTF-8'); ?></dd>

    <dt>X-Forwarded-For — 앞단에 프록시가 있으면 원래 IP가 여기 남기도 함</dt>
    <dd><?php echo htmlspecialchars($forwardedFor, ENT_QUOTES, 'UTF-8'); ?></dd>

    <dt>X-Real-IP</dt>
    <dd><?php echo htmlspecialchars($realIp, ENT_QUOTES, 'UTF-8'); ?></dd>

    <dt>Via</dt>
    <dd><?php echo htmlspecialchars($via, ENT_QUOTES, 'UTF-8'); ?></dd>

    <dt>접속한 호스트명</dt>
    <dd><?php echo htmlspecialchars($host, ENT_QUOTES, 'UTF-8'); ?></dd>
  </dl>
  <p class="note">
    <strong>확인 방법</strong>: 동방 와이파이에 연결한 채로 이 페이지를 열어서 값을 적어두고,
    이후 휴대폰 데이터나 다른 와이파이로 다시 열어서 값을 비교해 보세요.<br><br>
    동방 와이파이일 때 <code>REMOTE_ADDR</code>이 항상 <code>192.168.x.x</code>같은 사설 IP로 나오고
    외부에서 접속했을 때와 값이 다르다면, IP 기반 제한을 걸 수 있어요.
    반대로 두 경우 값이 똑같이 나온다면(공유기의 NAT 루프백 때문에 그럴 수 있어요),
    IP만으로는 안 되고 다른 방법을 찾아야 해요.
  </p>
  <p class="time">확인 시각: <?php echo htmlspecialchars($now, ENT_QUOTES, 'UTF-8'); ?></p>
</main>
</body>
</html>
