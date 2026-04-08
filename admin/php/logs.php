<?php
// AntiSpam Shield — PHP Verification Logs

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: ' . basePath('/projects'));
    exit;
}

$status = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$ipFilter = trim($_GET['ip'] ?? '');

// Load project fields
$fieldsStmt = $db->prepare('SELECT * FROM project_fields WHERE project_id = ? ORDER BY id ASC');
$fieldsStmt->execute([$id]);
$projectFields = $fieldsStmt->fetchAll();

// Get distinct values for each field
$fieldValues = [];
$fieldFilters = [];
foreach ($projectFields as $pf) {
    $fname = $pf['field_name'];
    $valStmt = $db->prepare('SELECT DISTINCT lfv.field_value FROM log_field_values lfv JOIN verification_logs vl ON vl.id = lfv.log_id WHERE vl.project_id = ? AND lfv.field_name = ? ORDER BY lfv.field_value');
    $valStmt->execute([$id, $fname]);
    $fieldValues[$fname] = $valStmt->fetchAll(PDO::FETCH_COLUMN);
    $fieldFilters[$fname] = trim($_GET['field_' . $fname] ?? '');
}

// Build WHERE clause
$where = 'WHERE vl.project_id = ?';
$params = [$id];

$dbProvider = getenv('DB_PROVIDER') ?: 'sqlite';
$trueVal = ($dbProvider === 'postgres') ? 'TRUE' : '1';
$falseVal = ($dbProvider === 'postgres') ? 'FALSE' : '0';

switch ($status) {
    case 'success':
        $where .= " AND vl.is_human = $trueVal AND (vl.filter_action IS NULL OR vl.filter_action = ?)";
        $params[] = 'allowed';
        break;
    case 'blocked':
        $where .= " AND vl.is_human = $falseVal AND vl.filter_action IS NULL";
        break;
    case 'filtered':
        $where .= ' AND vl.filter_action = ?';
        $params[] = 'blocked';
        break;
}

if ($ipFilter !== '') {
    $where .= ' AND vl.ip_address LIKE ?';
    $params[] = '%' . $ipFilter . '%';
}

// Apply field filters
foreach ($fieldFilters as $fname => $fval) {
    if ($fval !== '') {
        $where .= ' AND EXISTS (SELECT 1 FROM log_field_values lfv2 WHERE lfv2.log_id = vl.id AND lfv2.field_name = ? AND lfv2.field_value = ?)';
        $params[] = $fname;
        $params[] = $fval;
    }
}

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) FROM verification_logs vl $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

// Fetch logs
$query = "SELECT vl.id, vl.project_id, vl.token, vl.score, vl.is_human, vl.ip_address, vl.user_agent, vl.signals_summary, vl.filter_action, vl.matched_rule_id, vl.matched_rule_name, vl.custom_data, vl.created_at FROM verification_logs vl $where ORDER BY vl.created_at DESC LIMIT $perPage OFFSET $offset";
$logsStmt = $db->prepare($query);
$logsStmt->execute($params);
$logs = $logsStmt->fetchAll();

$logFieldValues = [];
if (!empty($logs)) {
    $logIds = array_column($logs, 'id');
    $placeholders = implode(',', array_fill(0, count($logIds), '?'));
    $fvStmt = $db->prepare("SELECT log_id, field_name, field_value FROM log_field_values WHERE log_id IN ($placeholders)");
    $fvStmt->execute($logIds);
    foreach ($fvStmt->fetchAll() as $fv) {
        $logFieldValues[$fv['log_id']][$fv['field_name']] = $fv['field_value'];
    }
}

function logsUrl($overrides = []) {
    global $id, $status, $page, $ipFilter, $fieldFilters;
    $params = [
        'status' => $overrides['status'] ?? $status,
        'page' => $overrides['page'] ?? $page,
    ];
    if (!empty($overrides['ip'] ?? $ipFilter)) {
        $params['ip'] = $overrides['ip'] ?? $ipFilter;
    }
    foreach ($fieldFilters as $fname => $fval) {
        if ($fval !== '') {
            $params['field_' . $fname] = $fval;
        }
    }
    return basePath('/projects/' . $id . '/logs?' . http_build_query($params));
}

layoutStart($project['name'] . ' — ' . t('logs_title'));
?>

<div class="page-card">
  <div class="detail-header">
    <a href="<?= basePath('/projects') ?>" class="btn-back">&#8592; <?= t('back') ?></a>
    <h1><?= htmlspecialchars($project['name']) ?></h1>
    <span class="domain-badge"><?= htmlspecialchars($project['domain']) ?></span>
  </div>

  <div class="tabs">
    <a href="<?= basePath('/projects/' . $id . '?tab=keys') ?>" class="tab"><?= t('tab_keys') ?></a>
    <a href="<?= basePath('/projects/' . $id . '?tab=frontend') ?>" class="tab"><?= t('tab_frontend') ?></a>
    <a href="<?= basePath('/projects/' . $id . '?tab=backend') ?>" class="tab"><?= t('tab_backend') ?></a>
    <a href="<?= basePath('/projects/' . $id . '/rules') ?>" class="tab"><?= t('tab_rules') ?></a>
    <a href="<?= basePath('/projects/' . $id . '/logs') ?>" class="tab active"><?= t('tab_logs') ?></a>
    <a href="<?= basePath('/projects/' . $id . '/fields') ?>" class="tab"><?= t('tab_fields') ?></a>
    <a href="<?= basePath('/projects/' . $id . '?tab=testing') ?>" class="tab"><?= t('tab_testing') ?></a>
  </div>

  <div class="tab-content">
    <h3><?= t('logs_title') ?></h3>

    <div class="logs-filters">
      <div class="sub-tabs">
        <?php foreach (['all' => 'logs_all', 'success' => 'logs_successful', 'blocked' => 'logs_blocked', 'filtered' => 'logs_filtered'] as $s => $label): ?>
          <a href="<?= logsUrl(['status' => $s, 'page' => 1]) ?>" class="sub-tab <?= $status === $s ? 'active' : '' ?>"><?= t($label) ?></a>
        <?php endforeach; ?>
      </div>
      <form method="GET" action="<?= basePath('/projects/' . $id . '/logs') ?>" class="ip-filter-form">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        <?php foreach ($fieldFilters as $fname => $fval): ?>
          <?php if ($fval !== ''): ?>
            <input type="hidden" name="field_<?= htmlspecialchars($fname) ?>" value="<?= htmlspecialchars($fval) ?>">
          <?php endif; ?>
        <?php endforeach; ?>
        <input type="text" name="ip" value="<?= htmlspecialchars($ipFilter) ?>" placeholder="<?= t('logs_filter_ip') ?>" class="ip-filter">
        <button type="submit" class="btn-sm">&#128269;</button>
      </form>
    </div>

    <?php if (!empty($projectFields)): ?>
    <div class="logs-filters" style="margin-top:8px;">
      <?php foreach ($projectFields as $pf): ?>
        <?php $fname = $pf['field_name']; ?>
        <?php if (!empty($fieldValues[$fname])): ?>
        <form method="GET" action="<?= basePath('/projects/' . $id . '/logs') ?>" class="ip-filter-form" style="margin-right:12px;">
          <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
          <input type="hidden" name="page" value="1">
          <?php if ($ipFilter !== ''): ?>
            <input type="hidden" name="ip" value="<?= htmlspecialchars($ipFilter) ?>">
          <?php endif; ?>
          <?php foreach ($fieldFilters as $fn2 => $fv2): ?>
            <?php if ($fv2 !== '' && $fn2 !== $fname): ?>
              <input type="hidden" name="field_<?= htmlspecialchars($fn2) ?>" value="<?= htmlspecialchars($fv2) ?>">
            <?php endif; ?>
          <?php endforeach; ?>
          <label style="margin-right:4px;font-weight:600;font-size:0.85em;"><?= htmlspecialchars($fname) ?>:</label>
          <select name="field_<?= htmlspecialchars($fname) ?>" onchange="this.form.submit()" style="padding:4px 8px;border-radius:4px;border:1px solid #d1d5db;">
            <option value=""><?= t('logs_all_fields') ?></option>
            <?php foreach ($fieldValues[$fname] as $val): ?>
              <option value="<?= htmlspecialchars($val) ?>" <?= ($fieldFilters[$fname] ?? '') === $val ? 'selected' : '' ?>><?= htmlspecialchars($val) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($logs)): ?>
      <div class="empty-state"><?= t('logs_no_data') ?></div>
    <?php else: ?>
      <table class="data-table logs-table">
        <thead>
          <tr>
            <th><?= t('logs_date') ?></th>
            <th><?= t('logs_ip') ?></th>
            <th><?= t('logs_ua') ?></th>
            <th><?= t('logs_score') ?></th>
            <th><?= t('logs_status') ?></th>
            <th><?= t('logs_rule') ?></th>
            <th style="width:30px"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <?php $hasDetails = !empty($logFieldValues[$log['id']]) || !empty($log['custom_data']); ?>
            <tr<?= $hasDetails ? ' class="log-row-expandable" onclick="toggleLogDetail(' . $log['id'] . ')"' : '' ?>>
              <td class="date-cell"><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($log['created_at']))) ?></td>
              <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
              <td class="ua-cell" title="<?= htmlspecialchars($log['user_agent'] ?? '') ?>"><?= htmlspecialchars(mb_substr($log['user_agent'] ?? '-', 0, 30)) ?><?= mb_strlen($log['user_agent'] ?? '') > 30 ? '...' : '' ?></td>
              <td><span class="score-value <?= ($log['score'] ?? 0) >= 0.5 ? 'good' : 'bad' ?>"><?= number_format($log['score'] ?? 0, 2) ?></span></td>
              <td>
                <?php if (($log['filter_action'] ?? '') === 'blocked'): ?>
                  <span class="status-badge filtered"><?= t('logs_filter_blocked') ?></span>
                <?php elseif (!empty($log['is_human'])): ?>
                  <span class="status-badge human"><?= t('logs_human') ?></span>
                <?php else: ?>
                  <span class="status-badge bot"><?= t('logs_bot') ?></span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($log['matched_rule_name'] ?? '-') ?></td>
              <td class="expand-cell">
                <?php if ($hasDetails): ?>
                  <span class="log-expand-btn" id="log-arrow-<?= $log['id'] ?>">&#9654;</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php if ($hasDetails): ?>
            <tr class="log-detail-row" id="log-detail-<?= $log['id'] ?>" style="display:none">
              <td colspan="7" class="log-detail-content">
                <?php if (!empty($logFieldValues[$log['id']])): ?>
                  <div class="log-detail-fields">
                    <?php foreach ($logFieldValues[$log['id']] as $fname => $fval): ?>
                      <span class="log-field-tag"><strong><?= htmlspecialchars($fname) ?>:</strong> <?= htmlspecialchars($fval) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($log['custom_data'])): ?>
                  <div class="log-detail-data"><strong>Data:</strong> <?= htmlspecialchars($log['custom_data']) ?></div>
                <?php endif; ?>
              </td>
            </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="<?= logsUrl(['page' => $page - 1]) ?>" class="btn-sm"><?= t('logs_prev') ?></a>
        <?php else: ?>
          <span class="btn-sm disabled"><?= t('logs_prev') ?></span>
        <?php endif; ?>
        <span class="page-info"><?= t('logs_page') ?> <?= $page ?> / <?= $totalPages ?> (<?= $total ?>)</span>
        <?php if ($page < $totalPages): ?>
          <a href="<?= logsUrl(['page' => $page + 1]) ?>" class="btn-sm"><?= t('logs_next') ?></a>
        <?php else: ?>
          <span class="btn-sm disabled"><?= t('logs_next') ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleLogDetail(id) {
  var row = document.getElementById('log-detail-' + id);
  var arrow = document.getElementById('log-arrow-' + id);
  if (row.style.display === 'none') {
    row.style.display = '';
    arrow.classList.add('open');
  } else {
    row.style.display = 'none';
    arrow.classList.remove('open');
  }
}
</script>

<?php layoutEnd(); ?>
