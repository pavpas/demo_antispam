<?php
// AntiSpam Shield — PHP Statistics

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: ' . basePath('/projects'));
    exit;
}

$days = (int)($_GET['days'] ?? 7);
if (!in_array($days, [7, 30, 90])) $days = 7;

// Custom date range
$customFrom = trim($_GET['from'] ?? '');
$customTo = trim($_GET['to'] ?? '');

if ($customFrom !== '' && $customTo !== '') {
    $from = $customFrom;
    $to = $customTo;
} else {
    $from = date('Y-m-d', strtotime("-{$days} days"));
    $to = date('Y-m-d');
}

// Load project fields
$fieldsStmt = $db->prepare('SELECT * FROM project_fields WHERE project_id = ? ORDER BY id ASC');
$fieldsStmt->execute([$id]);
$projectFields = $fieldsStmt->fetchAll();

// Get distinct values for each field and current filter values
$fieldValues = [];
$fieldFilters = [];
foreach ($projectFields as $pf) {
    $fname = $pf['field_name'];
    $valStmt = $db->prepare('SELECT DISTINCT lfv.field_value FROM log_field_values lfv JOIN verification_logs vl ON vl.id = lfv.log_id WHERE vl.project_id = ? AND lfv.field_name = ? ORDER BY lfv.field_value');
    $valStmt->execute([$id, $fname]);
    $fieldValues[$fname] = $valStmt->fetchAll(PDO::FETCH_COLUMN);
    $fieldFilters[$fname] = trim($_GET['field_' . $fname] ?? '');
}

// Build field filter WHERE clauses
$fieldWhere = '';
$fieldParams = [];
foreach ($fieldFilters as $fname => $fval) {
    if ($fval !== '') {
        $fieldWhere .= ' AND EXISTS (SELECT 1 FROM log_field_values lfv2 WHERE lfv2.log_id = vl.id AND lfv2.field_name = ? AND lfv2.field_value = ?)';
        $fieldParams[] = $fname;
        $fieldParams[] = $fval;
    }
}

// Total stats
$stmt = $db->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN vl.is_human THEN 1 ELSE 0 END) as success, SUM(CASE WHEN NOT vl.is_human THEN 1 ELSE 0 END) as rejected FROM verification_logs vl WHERE vl.project_id = ? AND vl.created_at >= ? AND vl.created_at <= ?' . $fieldWhere);
$stmt->execute(array_merge([$id, $from . ' 00:00:00', $to . ' 23:59:59'], $fieldParams));
$totals = $stmt->fetch();

// Daily breakdown
$stmt = $db->prepare("SELECT DATE(vl.created_at) as date, COUNT(*) as total, SUM(CASE WHEN vl.is_human THEN 1 ELSE 0 END) as success, SUM(CASE WHEN NOT vl.is_human THEN 1 ELSE 0 END) as rejected FROM verification_logs vl WHERE vl.project_id = ? AND vl.created_at >= ? AND vl.created_at <= ?" . $fieldWhere . " GROUP BY DATE(vl.created_at) ORDER BY date");
$stmt->execute(array_merge([$id, $from . ' 00:00:00', $to . ' 23:59:59'], $fieldParams));
$daily = $stmt->fetchAll();

$chartLabels = [];
$chartSuccess = [];
$chartRejected = [];
foreach ($daily as $d) {
    $chartLabels[] = $d['date'];
    $chartSuccess[] = (int)$d['success'];
    $chartRejected[] = (int)$d['rejected'];
}

// Build field params for URLs
$fieldQueryParams = [];
foreach ($fieldFilters as $fname => $fval) {
    if ($fval !== '') {
        $fieldQueryParams['field_' . $fname] = $fval;
    }
}

function statsUrl($extraParams = []) {
    global $id, $fieldQueryParams;
    $params = array_merge($extraParams, $fieldQueryParams);
    return basePath('/projects/' . $id . '/stats?' . http_build_query($params));
}

layoutStart(t('stats_title') . ' — ' . $project['name']);
?>

<div class="page-card">
  <div class="detail-header">
    <a href="<?= basePath('/projects') ?>" class="btn-back">&#8592; <?= t('back') ?></a>
    <h1><?= t('stats_title') ?>: <?= htmlspecialchars($project['name']) ?></h1>
    <span class="domain-badge"><?= htmlspecialchars($project['domain']) ?></span>
  </div>

  <div class="period-selector">
    <span><?= t('stats_period') ?>:</span>
    <a href="<?= statsUrl(['days' => 7]) ?>" class="btn-sm <?= $days === 7 && $customFrom === '' ? 'active' : '' ?>"><?= t('stats_7d') ?></a>
    <a href="<?= statsUrl(['days' => 30]) ?>" class="btn-sm <?= $days === 30 && $customFrom === '' ? 'active' : '' ?>"><?= t('stats_30d') ?></a>
    <a href="<?= statsUrl(['days' => 90]) ?>" class="btn-sm <?= $days === 90 && $customFrom === '' ? 'active' : '' ?>"><?= t('stats_90d') ?></a>
  </div>

  <div class="period-selector" style="margin-top:8px;">
    <form method="GET" action="<?= basePath('/projects/' . $id . '/stats') ?>" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <?php foreach ($fieldQueryParams as $k => $v): ?>
        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
      <?php endforeach; ?>
      <span><?= t('stats_custom_range') ?>:</span>
      <label style="font-size:0.85em;"><?= t('stats_from') ?></label>
      <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" style="padding:4px 8px;border-radius:4px;border:1px solid #d1d5db;">
      <label style="font-size:0.85em;"><?= t('stats_to') ?></label>
      <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" style="padding:4px 8px;border-radius:4px;border:1px solid #d1d5db;">
      <button type="submit" class="btn-sm"><?= t('stats_show') ?></button>
    </form>
  </div>

  <?php if (!empty($projectFields)): ?>
  <div class="period-selector" style="margin-top:8px;">
    <?php foreach ($projectFields as $pf): ?>
      <?php $fname = $pf['field_name']; ?>
      <?php if (!empty($fieldValues[$fname])): ?>
      <form method="GET" action="<?= basePath('/projects/' . $id . '/stats') ?>" style="display:inline-flex;align-items:center;gap:4px;margin-right:12px;">
        <?php if ($customFrom !== '' && $customTo !== ''): ?>
          <input type="hidden" name="from" value="<?= htmlspecialchars($customFrom) ?>">
          <input type="hidden" name="to" value="<?= htmlspecialchars($customTo) ?>">
        <?php else: ?>
          <input type="hidden" name="days" value="<?= $days ?>">
        <?php endif; ?>
        <?php foreach ($fieldFilters as $fn2 => $fv2): ?>
          <?php if ($fv2 !== '' && $fn2 !== $fname): ?>
            <input type="hidden" name="field_<?= htmlspecialchars($fn2) ?>" value="<?= htmlspecialchars($fv2) ?>">
          <?php endif; ?>
        <?php endforeach; ?>
        <label style="font-weight:600;font-size:0.85em;"><?= htmlspecialchars($fname) ?>:</label>
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

  <div class="stats-cards">
    <div class="stat-card">
      <div class="stat-value"><?= (int)($totals['total'] ?? 0) ?></div>
      <div class="stat-label"><?= t('stats_total') ?></div>
    </div>
    <div class="stat-card success">
      <div class="stat-value"><?= (int)($totals['success'] ?? 0) ?></div>
      <div class="stat-label"><?= t('stats_success') ?></div>
    </div>
    <div class="stat-card danger">
      <div class="stat-value"><?= (int)($totals['rejected'] ?? 0) ?></div>
      <div class="stat-label"><?= t('stats_rejected') ?></div>
    </div>
  </div>

  <h3><?= t('stats_chart') ?></h3>
  <?php if (empty($daily)): ?>
    <p class="empty-state"><?= t('stats_no_data') ?></p>
  <?php else: ?>
    <div class="chart-container">
      <canvas id="statsChart"></canvas>
    </div>
    <script>
    new Chart(document.getElementById('statsChart'), {
      type: 'line',
      data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
          { label: '<?= t('stats_success') ?>', data: <?= json_encode($chartSuccess) ?>, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.1)', fill: true, tension: 0.3 },
          { label: '<?= t('stats_rejected') ?>', data: <?= json_encode($chartRejected) ?>, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.1)', fill: true, tension: 0.3 }
        ]
      },
      options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
    </script>
  <?php endif; ?>
</div>

<?php layoutEnd(); ?>
