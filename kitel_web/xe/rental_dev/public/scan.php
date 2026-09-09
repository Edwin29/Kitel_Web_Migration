<?php
require_once __DIR__ . '/_bootstrap.php';
$user = require_borrow_permission();

$mode = (isset($_GET['mode']) && $_GET['mode'] === 'return') ? 'return' : 'rent';
$modeLabel = $mode === 'rent' ? '대여하기' : '반납하기';
$listUrl = $mode === 'rent' ? 'rent_list.php' : 'return_list.php';
$isMobile = is_mobile_user_agent();

render_header($modeLabel);
?>
<h1><?php echo e($modeLabel); ?></h1>

<?php if (!$isMobile): ?>
  <section class="card">
    <p class="muted">카메라 스캔은 모바일에서만 이용할 수 있어요. 이미 담긴 목록을 확인하거나, 아래에서 다른 작업을 할 수 있어요.</p>
    <p>
      <a class="button primary" href="<?php echo e(app_url($listUrl)); ?>"><?php echo $mode === 'rent' ? '대여 목록 보기' : '반납 목록 보기'; ?></a>
      <a class="button" href="<?php echo e(app_url('catalog.php')); ?>">기자재 현황</a>
      <a class="button" href="<?php echo e(app_url('')); ?>">홈으로</a>
    </p>
  </section>
<?php else: ?>
  <section class="card">
    <p class="muted" id="scan-hint">QR 하나를 화면에 크게 채우듯 가까이 대주세요. 스캔되면 확인 창이 뜰 때까지 잠시 멈춥니다.</p>
    <div class="scan-video-wrap">
      <video id="scan-video" playsinline autoplay muted></video>
      <canvas id="scan-overlay"></canvas>
    </div>
    <canvas id="scan-canvas" hidden></canvas>
    <p class="muted" id="scan-status">카메라 준비 중...</p>
    <p id="scan-error" class="import-status error"></p>
  </section>

  <section class="card">
    <div class="page-head">
      <h2>담은 항목 (<span id="scan-count">0</span>)</h2>
      <a class="button primary" href="<?php echo e(app_url($listUrl)); ?>"><?php echo $mode === 'rent' ? '대여 목록으로' : '반납 목록으로'; ?></a>
    </div>
    <ul id="scan-log" class="scan-log"></ul>
  </section>

  <div id="scan-qty-modal" class="scan-modal" hidden>
    <div class="scan-modal-box">
      <h2 id="scan-qty-title">수량 입력</h2>
      <label>담을 개수
        <input type="number" id="scan-qty-input" min="1" value="1">
      </label>
      <div class="scan-modal-actions">
        <button type="button" id="scan-qty-cancel">취소</button>
        <button type="button" id="scan-qty-confirm" class="primary">담기</button>
      </div>
    </div>
  </div>

  <div id="scan-bundle-modal" class="scan-modal" hidden>
    <div class="scan-modal-box scan-bundle-box">
      <h2 id="scan-bundle-title">묶음 선택</h2>
      <p id="scan-bundle-help" class="muted"></p>
      <div id="scan-bundle-options" class="scan-bundle-options"></div>
      <div class="scan-modal-actions">
        <button type="button" id="scan-bundle-cancel">취소</button>
      </div>
    </div>
  </div>

  <div id="scan-result-modal" class="scan-modal" hidden>
    <div class="scan-modal-box">
      <h2 id="scan-result-title">담았습니다</h2>
      <p id="scan-result-message" class="scan-result-message"></p>
      <div class="scan-modal-actions">
        <button type="button" id="scan-result-confirm" class="primary">확인</button>
      </div>
    </div>
  </div>

  <script src="<?php echo e(app_url('assets/jsQR.js')); ?>"></script>
  <script>
  (function () {
    var MODE = <?php echo json_encode($mode); ?>;
    var CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
    var SCAN_ADD_URL = <?php echo json_encode(app_url('scan_add.php')); ?>;

    var video = document.getElementById('scan-video');
    var canvas = document.getElementById('scan-canvas');
    var ctx = canvas.getContext('2d', { willReadFrequently: true });
    var overlay = document.getElementById('scan-overlay');
    var overlayCtx = overlay.getContext('2d');
    var errorBox = document.getElementById('scan-error');
    var statusBox = document.getElementById('scan-status');
    var logList = document.getElementById('scan-log');
    var countLabel = document.getElementById('scan-count');

    var qtyModal = document.getElementById('scan-qty-modal');
    var qtyTitle = document.getElementById('scan-qty-title');
    var qtyInput = document.getElementById('scan-qty-input');
    var qtyCancel = document.getElementById('scan-qty-cancel');
    var qtyConfirm = document.getElementById('scan-qty-confirm');

    var bundleModal = document.getElementById('scan-bundle-modal');
    var bundleTitle = document.getElementById('scan-bundle-title');
    var bundleHelp = document.getElementById('scan-bundle-help');
    var bundleOptions = document.getElementById('scan-bundle-options');
    var bundleCancel = document.getElementById('scan-bundle-cancel');

    var resultModal = document.getElementById('scan-result-modal');
    var resultTitle = document.getElementById('scan-result-title');
    var resultMessage = document.getElementById('scan-result-message');
    var resultConfirm = document.getElementById('scan-result-confirm');

    var scanning = true;
    var pendingCategory = null;
    var frameCount = 0;

    function setError(msg) {
      errorBox.textContent = msg || '';
    }

    function setStatus(msg) {
      statusBox.textContent = msg || '';
    }

    function addLog(message, ok) {
      var li = document.createElement('li');
      li.className = ok ? 'scan-log-ok' : 'scan-log-fail';
      li.textContent = message;
      logList.insertBefore(li, logList.firstChild);
    }

    function clearOverlay() {
      overlayCtx.clearRect(0, 0, overlay.width, overlay.height);
    }

    // jsQR의 location(코너 4점)을 오버레이 캔버스에 그대로 그린다.
    // overlay 캔버스 해상도를 매 프레임 video의 실제 해상도와 맞춰두기 때문에
    // (아래 tick()에서) 좌표 변환 없이 그대로 사용할 수 있다.
    function drawBox(location) {
      clearOverlay();
      overlayCtx.strokeStyle = '#22c55e';
      overlayCtx.lineWidth = Math.max(4, overlay.width * 0.01);
      overlayCtx.beginPath();
      overlayCtx.moveTo(location.topLeftCorner.x, location.topLeftCorner.y);
      overlayCtx.lineTo(location.topRightCorner.x, location.topRightCorner.y);
      overlayCtx.lineTo(location.bottomRightCorner.x, location.bottomRightCorner.y);
      overlayCtx.lineTo(location.bottomLeftCorner.x, location.bottomLeftCorner.y);
      overlayCtx.closePath();
      overlayCtx.stroke();
    }

    function parseQrText(text) {
      // item.php?code=XXXX, category.php?id=N, bundle.php?code=XXXX 패턴만 인식한다.
      var itemMatch = text.match(/item\.php\?code=([A-Za-z0-9\-_.]+)/);
      if (itemMatch) {
        return { type: 'item', code: decodeURIComponent(itemMatch[1]) };
      }
      var categoryMatch = text.match(/category\.php\?id=(\d+)/);
      if (categoryMatch) {
        return { type: 'category', categoryId: parseInt(categoryMatch[1], 10) };
      }
      var bundleMatch = text.match(/bundle\.php\?code=([A-Za-z0-9\-_.]+)/);
      if (bundleMatch) {
        return { type: 'bundle', code: decodeURIComponent(bundleMatch[1]) };
      }
      return null;
    }

    function postScan(payload) {
      var body = new URLSearchParams();
      body.set('csrf_token', CSRF_TOKEN);
      body.set('mode', MODE);
      Object.keys(payload).forEach(function (key) {
        body.set(key, payload[key]);
      });
      return fetch(SCAN_ADD_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      }).then(function (res) { return res.json(); });
    }

    // 스캔 하나를 처리하는 동안(서버 응답 오고, 결과 팝업을 확인하기 전까지)은
    // 계속 멈춰 있는다 - 여러 QR이 동시에 보여도 한 번에 하나씩만 처리하기 위함.
    function showResult(message, ok, count) {
      resultTitle.textContent = ok ? '담았습니다' : '담지 못했습니다';
      resultMessage.textContent = message;
      resultMessage.className = 'scan-result-message ' + (ok ? 'scan-log-ok' : 'scan-log-fail');
      if (ok && typeof count === 'number') { countLabel.textContent = count; }
      addLog(message, ok);
      resultModal.hidden = false;
    }

    resultConfirm.addEventListener('click', function () {
      resultModal.hidden = true;
      clearOverlay();
      scanning = true;
      requestAnimationFrame(tick);
    });

    function handleItem(code) {
      postScan({ target_type: 'item', code: code }).then(function (data) {
        showResult(data.message, data.ok, data.count);
      }).catch(function () {
        showResult('통신 오류가 발생했습니다.', false);
      });
    }

    function openQtyModal(categoryId) {
      pendingCategory = categoryId;
      qtyTitle.textContent = MODE === 'rent' ? '몇 개 대여할까요?' : '몇 개 반납할까요?';
      qtyInput.value = 1;
      qtyModal.hidden = false;
      // scanning은 이미 detect 시점에 false로 되어 있음
    }

    function resumeScanning() {
      clearOverlay();
      scanning = true;
      requestAnimationFrame(tick);
    }

    qtyCancel.addEventListener('click', function () {
      qtyModal.hidden = true;
      pendingCategory = null;
      resumeScanning();
    });

    qtyConfirm.addEventListener('click', function () {
      var quantity = parseInt(qtyInput.value, 10) || 0;
      var categoryId = pendingCategory;
      qtyModal.hidden = true;
      pendingCategory = null;
      if (quantity <= 0) {
        resumeScanning();
        return;
      }
      postScan({ target_type: 'category', category_id: categoryId, quantity: quantity }).then(function (data) {
        showResult(data.message, data.ok, data.count);
      }).catch(function () {
        showResult('통신 오류가 발생했습니다.', false);
      });
    });

    function renderEmptyBundleMessage(message) {
      var p = document.createElement('p');
      p.className = 'muted';
      p.textContent = message;
      bundleOptions.appendChild(p);
    }

    function submitBundleCategory(categoryId, quantity) {
      bundleModal.hidden = true;
      postScan({ target_type: 'category', category_id: categoryId, quantity: quantity }).then(function (data) {
        showResult(data.message, data.ok, data.count);
      }).catch(function () {
        showResult('통신 오류가 발생했습니다.', false);
      });
    }

    function submitBundleItem(itemId) {
      bundleModal.hidden = true;
      postScan({ target_type: 'bundle_item', item_id: itemId }).then(function (data) {
        showResult(data.message, data.ok, data.count);
      }).catch(function () {
        showResult('통신 오류가 발생했습니다.', false);
      });
    }

    function openBundleModal(data) {
      bundleTitle.textContent = data.bundle.name;
      bundleHelp.textContent = MODE === 'rent' ? '대여할 카테고리 또는 물품을 선택하세요.' : '반납할 카테고리 또는 물품을 선택하세요.';
      bundleOptions.innerHTML = '';
      if (!data.categories || data.categories.length === 0) {
        renderEmptyBundleMessage('이 묶음에 포함된 카테고리가 없습니다.');
      }
      data.categories.forEach(function (category) {
        var card = document.createElement('div');
        card.className = 'scan-bundle-category';

        var head = document.createElement('div');
        head.className = 'scan-bundle-category-head';
        var title = document.createElement('strong');
        title.textContent = category.name;
        var meta = document.createElement('span');
        meta.className = 'muted';
        meta.textContent = category.tracking_label + ' · 현재 ' + category.counts.total + '개';
        head.appendChild(title);
        head.appendChild(meta);
        card.appendChild(head);

        if (category.tracking_mode === 'bulk') {
          var available = MODE === 'rent' ? category.counts.available : category.counts.borrowed;
          var line = document.createElement('div');
          line.className = 'scan-bundle-inline';
          var input = document.createElement('input');
          input.type = 'number';
          input.min = '1';
          input.max = String(Math.max(1, available));
          input.value = available > 0 ? '1' : '0';
          input.disabled = available <= 0;
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'primary';
          button.textContent = MODE === 'rent' ? '담기' : '반납 담기';
          button.disabled = available <= 0;
          button.addEventListener('click', function () {
            var quantity = parseInt(input.value, 10) || 0;
            if (quantity <= 0) { return; }
            submitBundleCategory(category.category_id, quantity);
          });
          var hint = document.createElement('span');
          hint.className = 'muted';
          hint.textContent = (MODE === 'rent' ? '대여 가능 ' : '대여 중 ') + available + '개';
          line.appendChild(input);
          line.appendChild(button);
          line.appendChild(hint);
          card.appendChild(line);
        } else {
          var items = document.createElement('div');
          items.className = 'scan-bundle-items';
          if (!category.items || category.items.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'muted';
            empty.textContent = MODE === 'rent' ? '대여 가능한 물품이 없습니다.' : '반납할 수 있는 물품이 없습니다.';
            items.appendChild(empty);
          } else {
            category.items.forEach(function (item) {
              var itemButton = document.createElement('button');
              itemButton.type = 'button';
              itemButton.className = 'scan-bundle-item-button';
              itemButton.textContent = item.label + (item.location ? ' · ' + item.location : '');
              itemButton.addEventListener('click', function () {
                submitBundleItem(item.item_id);
              });
              items.appendChild(itemButton);
            });
          }
          card.appendChild(items);
        }
        bundleOptions.appendChild(card);
      });
      bundleModal.hidden = false;
    }

    bundleCancel.addEventListener('click', function () {
      bundleModal.hidden = true;
      resumeScanning();
    });

    function handleBundle(code) {
      postScan({ target_type: 'bundle_options', code: code }).then(function (data) {
        if (!data.ok) {
          showResult(data.message || '묶음 정보를 불러오지 못했습니다.', false);
          return;
        }
        openBundleModal(data);
      }).catch(function () {
        showResult('통신 오류가 발생했습니다.', false);
      });
    }

    function tick() {
      if (!scanning) { return; }
      try {
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
          frameCount++;
          if (frameCount % 15 === 0) {
            setStatus('스캔 중... (' + video.videoWidth + '×' + video.videoHeight + ')');
          }
          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;
          ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
          overlay.width = video.videoWidth;
          overlay.height = video.videoHeight;
          var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
          if (typeof jsQR !== 'function') {
            setError('QR 인식 라이브러리를 불러오지 못했습니다. 새로고침해 주세요.');
            return;
          }
          var result = jsQR(imageData.data, imageData.width, imageData.height);
          if (result && result.data) {
            // 인식한 즉시 멈추고 테두리를 그린다. 이후 확인 팝업을 닫아야 다시 스캔 재개.
            scanning = false;
            drawBox(result.location);
            var parsed = parseQrText(result.data);
            if (!parsed) {
              showResult('인식할 수 없는 QR입니다.', false);
            } else if (parsed.type === 'item') {
              handleItem(parsed.code);
            } else if (parsed.type === 'category') {
              openQtyModal(parsed.categoryId);
            } else {
              handleBundle(parsed.code);
            }
            return;
          }
        }
      } catch (err) {
        setError('스캔 처리 중 오류: ' + err.message);
      }
      requestAnimationFrame(tick);
    }

    if (typeof jsQR !== 'function') {
      setError('QR 인식 라이브러리를 불러오지 못했습니다. 새로고침해 주세요.');
    } else if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setError('이 브라우저에서는 카메라를 사용할 수 없습니다.');
    } else {
      navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } } })
        .then(function (stream) {
          video.srcObject = stream;
          setStatus('스캔 중...');
          requestAnimationFrame(tick);
        })
        .catch(function (err) {
          setError('카메라를 시작할 수 없습니다: ' + err.message);
        });
    }
  })();
  </script>
<?php endif; ?>

<?php render_footer(); ?>
