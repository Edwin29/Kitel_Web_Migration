<?php
require_once __DIR__ . '/../_bootstrap.php';
$user = require_admin();

// 대여/반납의 "동방 와이파이에서만" 제한이 실제로 어떻게 판정되는지 확인하는 화면.
// 예전에는 같은 목적의 public/whereami.php 가 인증 없이 웹에 열려 있었다.
// 여기서는 관리자만 볼 수 있고, 판정에 실제로 쓰이는 값을 함께 보여준다.

$remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '(없음)';
$forwarded = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '(없음)';
$realIp = isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : '(없음)';
$via = isset($_SERVER['HTTP_VIA']) ? $_SERVER['HTTP_VIA'] : '(없음)';
$resolved = client_ip();
$onTrusted = client_on_trusted_network();
$enforcing = (bool)config('require_trusted_network_for_rental');
$cidrs = config('trusted_network_cidrs');
$proxies = config('trusted_proxies');

render_header('네트워크 점검', true);
admin_nav();
?>
<h1>네트워크 점검</h1>
<p class="muted">대여·반납을 동방 와이파이에서만 허용하는 제한이 지금 이 접속을 어떻게 판정하는지 보여줍니다. 동방에서 한 번, 휴대폰 데이터 등 외부에서 한 번 열어 값을 비교해 보세요.</p>

<section class="card">
  <h2>판정 결과</h2>
  <p>
    <span class="badge <?php echo $onTrusted ? 'available' : 'broken'; ?>">
      <?php echo $onTrusted ? '신뢰 네트워크로 인식됨 — 대여/반납 가능' : '신뢰 네트워크 아님 — 대여/반납 차단'; ?>
    </span>
  </p>
  <p class="muted">
    제한 기능: <strong><?php echo $enforcing ? '켜짐' : '꺼짐'; ?></strong>
    <?php if (!$enforcing): ?>(꺼져 있으면 IP와 무관하게 항상 통과합니다)<?php endif; ?>
  </p>
</section>

<section class="card table-wrap">
  <h2>서버가 본 값</h2>
  <table>
    <tbody>
      <tr><th>판정에 사용된 IP</th><td><strong><?php echo e($resolved); ?></strong></td></tr>
      <tr><th>REMOTE_ADDR</th><td><?php echo e($remote); ?></td></tr>
      <tr><th>X-Forwarded-For</th><td><?php echo e($forwarded); ?></td></tr>
      <tr><th>X-Real-IP</th><td><?php echo e($realIp); ?></td></tr>
      <tr><th>Via</th><td><?php echo e($via); ?></td></tr>
      <tr><th>접속 호스트</th><td><?php echo e(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '(없음)'); ?></td></tr>
    </tbody>
  </table>
</section>

<section class="card table-wrap">
  <h2>현재 설정</h2>
  <table>
    <tbody>
      <tr>
        <th>신뢰 네트워크 대역</th>
        <td><?php echo $cidrs ? e(implode(', ', $cidrs)) : '(없음 — 아무도 통과하지 못합니다)'; ?></td>
      </tr>
      <tr>
        <th>신뢰하는 프록시</th>
        <td>
          <?php echo $proxies ? e(implode(', ', $proxies)) : '(없음 — X-Forwarded-For를 무시하고 REMOTE_ADDR만 사용)'; ?>
        </td>
      </tr>
    </tbody>
  </table>
</section>

<section class="card">
  <h2>읽는 법</h2>
  <p class="muted">
    동방에서 열었을 때 <code>REMOTE_ADDR</code>이 <code>192.168.x.x</code> 같은 사설 IP로 나오고 외부에서 열었을 때와 값이 다르면,
    그 대역을 <code>KITEL_RENTAL_TRUSTED_CIDRS</code>에 넣으면 됩니다.
  </p>
  <p class="muted">
    두 경우 모두 <code>127.0.0.1</code>처럼 같은 값이 나온다면 앞단 프록시를 거치고 있는 것입니다.
    그때는 <code>X-Forwarded-For</code>에 실제 IP가 들어 있는지 확인하고, 프록시 주소를
    <code>KITEL_RENTAL_TRUSTED_PROXIES</code>에 넣어야 그 헤더가 반영됩니다.
    <strong>프록시를 등록하지 않은 채로 헤더만 믿으면 누구나 헤더를 위조해 제한을 우회할 수 있으므로</strong>,
    기본값은 헤더를 무시하도록 되어 있습니다.
  </p>
  <p class="muted">
    환경변수를 넣기 어려운 구성이면 <code>app/config.php</code>의 해당 값을 직접 수정해도 됩니다.
  </p>
</section>
<?php render_footer(); ?>
