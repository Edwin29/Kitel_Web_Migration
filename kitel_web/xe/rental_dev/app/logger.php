<?php
function add_log(&$state, $action, $itemId, $loanId, $before, $after, $memo, $actor)
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $stmt = $pdo->prepare('
            INSERT INTO kitel_rental_logs
                (item_id, loan_id, actor_member_srl, action, before_status, after_status, memo, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute(array(
            $itemId,
            $loanId,
            $actor ? $actor['member_srl'] : null,
            $action,
            $before,
            $after,
            $memo,
            db_now(),
        ));
        return;
    }
    $state['logs'][] = array(
        'log_id' => next_id($state, 'log_id'),
        'item_id' => $itemId,
        'loan_id' => $loanId,
        'actor_member_srl' => $actor ? $actor['member_srl'] : null,
        'action' => $action,
        'before_status' => $before,
        'after_status' => $after,
        'memo' => $memo,
        'created_at' => now_text(),
    );
}

// ---------------------------------------------------------------------------
// 로그 조회 계층
//
// kitel_rental_logs 는 쓸수록 무한히 커지는 테이블이라 통째로 읽지 않는다.
// 화면은 여기를 통해 필요한 만큼만 가져오고, 정렬은 항상 여기서 "최신 우선"으로
// 확정된다 (화면에서 array_reverse() 하지 않는다).
// ---------------------------------------------------------------------------

function log_filters_from_request()
{
    return array(
        'q' => isset($_GET['q']) ? trim($_GET['q']) : '',
        'action' => isset($_GET['action']) ? trim($_GET['action']) : '',
        'actor' => isset($_GET['actor']) ? trim($_GET['actor']) : '',
        'from' => isset($_GET['from']) ? trim($_GET['from']) : '',
        'to' => isset($_GET['to']) ? trim($_GET['to']) : '',
        'item_id' => isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0,
    );
}

function log_filters_defaults(array $filters = array())
{
    return $filters + array('q' => '', 'action' => '', 'actor' => '', 'from' => '', 'to' => '', 'item_id' => 0);
}

function log_filter_sql(array $filters)
{
    $where = array();
    $args = array();

    if ($filters['action'] !== '') {
        $where[] = 'action = ?';
        $args[] = $filters['action'];
    }
    // 처리자 칸에는 member_srl 숫자뿐 아니라 이름/아이디도 넣을 수 있다.
    if ($filters['actor'] !== '') {
        $srls = resolve_actor_filter($filters['actor']);
        $where[] = 'actor_member_srl IN (' . implode(',', array_fill(0, count($srls), '?')) . ')';
        foreach ($srls as $srl) {
            $args[] = $srl;
        }
    }
    if ($filters['from'] !== '') {
        $where[] = 'DATE(created_at) >= ?';
        $args[] = $filters['from'];
    }
    if ($filters['to'] !== '') {
        $where[] = 'DATE(created_at) <= ?';
        $args[] = $filters['to'];
    }
    if ((int)$filters['item_id'] > 0) {
        $where[] = 'item_id = ?';
        $args[] = (int)$filters['item_id'];
    }
    if ($filters['q'] !== '') {
        $where[] = '(action LIKE ? OR memo LIKE ? OR before_status LIKE ? OR after_status LIKE ?'
            . ' OR CAST(item_id AS CHAR) = ? OR CAST(loan_id AS CHAR) = ?)';
        $like = '%' . $filters['q'] . '%';
        $args[] = $like;
        $args[] = $like;
        $args[] = $like;
        $args[] = $like;
        $args[] = $filters['q'];
        $args[] = $filters['q'];
    }

    return array($where ? 'WHERE ' . implode(' AND ', $where) : '', $args);
}

function log_matches_filters($log, array $filters)
{
    if ($filters['action'] !== '' && $log['action'] !== $filters['action']) {
        return false;
    }
    if ($filters['actor'] !== '' && !in_array((int)$log['actor_member_srl'], resolve_actor_filter($filters['actor']), true)) {
        return false;
    }
    if ($filters['from'] !== '' && date_only($log['created_at']) < $filters['from']) {
        return false;
    }
    if ($filters['to'] !== '' && date_only($log['created_at']) > $filters['to']) {
        return false;
    }
    if ((int)$filters['item_id'] > 0 && (int)$log['item_id'] !== (int)$filters['item_id']) {
        return false;
    }
    if ($filters['q'] !== '') {
        $haystack = implode(' ', array(
            $log['action'], $log['item_id'], $log['loan_id'],
            $log['before_status'], $log['after_status'], $log['memo'],
        ));
        if (stripos($haystack, $filters['q']) === false) {
            return false;
        }
    }
    return true;
}

function log_rows_local($state, array $filters)
{
    $rows = array();
    foreach ($state['logs'] as $log) {
        if (log_matches_filters($log, $filters)) {
            $rows[] = $log;
        }
    }
    usort($rows, function ($a, $b) {
        $order = strcmp($b['created_at'], $a['created_at']);
        return $order !== 0 ? $order : ((int)$b['log_id'] <=> (int)$a['log_id']);
    });
    return $rows;
}

function log_query($state, array $filters, $page = 1, $perPage = 100)
{
    $filters = log_filters_defaults($filters);
    $page = max(1, (int)$page);
    $perPage = max(1, (int)$perPage);

    if (config('mode') !== 'local') {
        $pdo = db_connect();
        list($whereSql, $args) = log_filter_sql($filters);

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM kitel_rental_logs ' . $whereSql);
        $countStmt->execute($args);
        $total = (int)$countStmt->fetchColumn();
        if ($total === 0) {
            return rental_page(array(), 0, $page, $perPage);
        }
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);

        $stmt = $pdo->prepare('
            SELECT * FROM kitel_rental_logs ' . $whereSql . '
            ORDER BY created_at DESC, log_id DESC
            LIMIT ' . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage)
        );
        $stmt->execute($args);
        return rental_page($stmt->fetchAll(), $total, $page, $perPage);
    }

    $rows = log_rows_local($state, $filters);
    $total = count($rows);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    return rental_page(array_slice($rows, ($page - 1) * $perPage, $perPage), $total, $page, $perPage);
}

// CSV 내보내기용. 화면과 같은 필터를 쓰되 페이지를 나누지 않고 청크 단위로 흘려보낸다.
function log_each_filtered($state, array $filters, callable $callback, $chunk = 1000)
{
    $filters = log_filters_defaults($filters);

    if (config('mode') !== 'local') {
        $pdo = db_connect();
        list($whereSql, $args) = log_filter_sql($filters);
        $offset = 0;
        do {
            $stmt = $pdo->prepare('
                SELECT * FROM kitel_rental_logs ' . $whereSql . '
                ORDER BY created_at DESC, log_id DESC
                LIMIT ' . (int)$chunk . ' OFFSET ' . (int)$offset
            );
            $stmt->execute($args);
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $callback($row);
            }
            $offset += $chunk;
        } while (count($rows) === $chunk);
        return;
    }

    foreach (log_rows_local($state, $filters) as $row) {
        $callback($row);
    }
}

function logs_for_item($state, $itemId, $page = 1, $perPage = 100)
{
    return log_query($state, array('item_id' => (int)$itemId), $page, $perPage);
}

// 대시보드의 "최근 처리 로그"용. 최신 $limit건.
function log_recent($state, $limit = 10)
{
    $result = log_query($state, array(), 1, max(1, (int)$limit));
    return $result['rows'];
}

// 필터 드롭다운에 채울 액션 목록. 예전에는 전체 로그를 훑어 distinct를 만들었다.
function log_actions($state)
{
    if (config('mode') !== 'local') {
        $pdo = db_connect();
        $rows = $pdo->query('SELECT DISTINCT action FROM kitel_rental_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
        return array_values($rows);
    }
    $actions = array();
    foreach ($state['logs'] as $log) {
        if (!in_array($log['action'], $actions, true)) {
            $actions[] = $log['action'];
        }
    }
    sort($actions);
    return $actions;
}
