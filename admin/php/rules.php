<?php
// AntiSpam Shield — PHP Filter Rules Management (multi-condition)

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: ' . basePath('/projects'));
    exit;
}

$ruleTypes = [
    'ip' => t('rules_type_ip'),
    'ip_range' => t('rules_type_ip_range'),
    'user_agent' => t('rules_type_user_agent'),
    'header' => t('rules_type_header'),
    'score' => t('rules_type_score'),
];

$error = '';
$editRule = null;
$editConditions = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $ruleAction = $_POST['rule_action'] ?? 'block';
        $priority = (int)($_POST['priority'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $conditionTypes = $_POST['condition_type'] ?? [];
        $conditionValues = $_POST['condition_value'] ?? [];
        $headerNames = $_POST['header_name'] ?? [];
        $headerValues = $_POST['header_value'] ?? [];

        // Build conditions array
        $conditions = [];
        for ($i = 0; $i < count($conditionTypes); $i++) {
            $ct = $conditionTypes[$i] ?? '';
            if ($ct === '') continue;

            if ($ct === 'header') {
                $hn = trim($headerNames[$i] ?? '');
                $hv = trim($headerValues[$i] ?? '');
                if ($hn === '' && $hv === '') continue;
                $cv = json_encode(['name' => $hn, 'value' => $hv]);
            } else {
                $cv = trim($conditionValues[$i] ?? '');
                if ($cv === '') continue;
            }

            $conditions[] = ['type' => $ct, 'value' => $cv];
        }

        if ($name && !empty($conditions)) {
            if ($action === 'create') {
                $stmt = $db->prepare('INSERT INTO filter_rules (project_id, name, rule_type, rule_value, action, is_active, priority) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$id, $name, $conditions[0]['type'], $conditions[0]['value'], $ruleAction, $isActive, $priority]);
                $ruleId = $db->lastInsertId();

                // Insert conditions
                try {
                    $stmtCond = $db->prepare('INSERT INTO rule_conditions (rule_id, condition_type, condition_value) VALUES (?, ?, ?)');
                    foreach ($conditions as $cond) {
                        $stmtCond->execute([$ruleId, $cond['type'], $cond['value']]);
                    }
                } catch (Exception $e) {}
            } else {
                $ruleId = (int)$_POST['rule_id'];
                $stmt = $db->prepare('UPDATE filter_rules SET name = ?, action = ?, is_active = ?, priority = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND project_id = ?');
                $stmt->execute([$name, $ruleAction, $isActive, $priority, $ruleId, $id]);

                // Replace conditions: delete old, insert new
                try {
                    $stmtDel = $db->prepare('DELETE FROM rule_conditions WHERE rule_id = ?');
                    $stmtDel->execute([$ruleId]);

                    $stmtCond = $db->prepare('INSERT INTO rule_conditions (rule_id, condition_type, condition_value) VALUES (?, ?, ?)');
                    foreach ($conditions as $cond) {
                        $stmtCond->execute([$ruleId, $cond['type'], $cond['value']]);
                    }
                } catch (Exception $e) {}
            }
            header('Location: ' . basePath('/projects/' . $id . '/rules'));
            exit;
        } else {
            $error = $name ? t('rules_add_condition') : 'All fields are required';
        }
    } elseif ($action === 'delete') {
        $ruleId = (int)$_POST['rule_id'];
        try {
            $stmtDel = $db->prepare('DELETE FROM rule_conditions WHERE rule_id = ?');
            $stmtDel->execute([$ruleId]);
        } catch (Exception $e) {}
        $stmt = $db->prepare('DELETE FROM filter_rules WHERE id = ? AND project_id = ?');
        $stmt->execute([$ruleId, $id]);
        header('Location: ' . basePath('/projects/' . $id . '/rules'));
        exit;
    } elseif ($action === 'toggle') {
        $ruleId = (int)$_POST['rule_id'];
        $stmt = $db->prepare('UPDATE filter_rules SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND project_id = ?');
        $stmt->execute([$ruleId, $id]);
        header('Location: ' . basePath('/projects/' . $id . '/rules'));
        exit;
    }
}

// Check if editing
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM filter_rules WHERE id = ? AND project_id = ?');
    $stmt->execute([$editId, $id]);
    $editRule = $stmt->fetch();

    if ($editRule) {
        try {
            $stmtCond = $db->prepare('SELECT * FROM rule_conditions WHERE rule_id = ? ORDER BY id ASC');
            $stmtCond->execute([$editRule['id']]);
            $editConditions = $stmtCond->fetchAll();
        } catch (Exception $e) {
            $editConditions = [];
        }
    }
}

// Fetch all rules
$stmt = $db->prepare('SELECT * FROM filter_rules WHERE project_id = ? ORDER BY priority DESC, id ASC');
$stmt->execute([$id]);
$rules = $stmt->fetchAll();

// Load conditions for all rules
$ruleConditions = [];
if (!empty($rules)) {
    try {
        $ruleIds = array_column($rules, 'id');
        $placeholders = implode(',', array_fill(0, count($ruleIds), '?'));
        $stmtCond = $db->prepare('SELECT * FROM rule_conditions WHERE rule_id IN (' . $placeholders . ') ORDER BY id ASC');
        $stmtCond->execute($ruleIds);
        $allConditions = $stmtCond->fetchAll();
        foreach ($allConditions as $cond) {
            $ruleConditions[$cond['rule_id']][] = $cond;
        }
    } catch (Exception $e) {
        // rule_conditions table may not exist yet
    }
}

layoutStart($project['name'] . ' — ' . t('rules_title'));
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
    <a href="<?= basePath('/projects/' . $id . '/rules') ?>" class="tab active"><?= t('tab_rules') ?></a>
    <a href="<?= basePath('/projects/' . $id . '/logs') ?>" class="tab"><?= t('tab_logs') ?></a>
    <a href="<?= basePath('/projects/' . $id . '/fields') ?>" class="tab"><?= t('tab_fields') ?></a>
    <a href="<?= basePath('/projects/' . $id . '?tab=testing') ?>" class="tab"><?= t('tab_testing') ?></a>
  </div>

  <div class="tab-content">
    <div class="tab-header-row">
      <h3><?= t('rules_title') ?></h3>
      <?php if (!$editRule && !isset($_GET['add'])): ?>
        <a href="<?= basePath('/projects/' . $id . '/rules?add=1') ?>" class="btn-primary">+ <?= t('rules_add') ?></a>
      <?php endif; ?>
    </div>

    <?php if ($error): ?>
      <div class="warning-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['add']) || $editRule): ?>
    <?php
      // Prepare conditions for the form (existing or default empty)
      $formConditions = [];
      if ($editRule && !empty($editConditions)) {
          foreach ($editConditions as $ec) {
              $formConditions[] = [
                  'type' => $ec['condition_type'],
                  'value' => $ec['condition_value'],
              ];
          }
      }
      if (empty($formConditions)) {
          $formConditions[] = ['type' => 'ip', 'value' => ''];
      }
    ?>
    <div class="rule-form-box">
      <h4><?= $editRule ? t('rules_edit') : t('rules_add') ?></h4>
      <form method="POST" onsubmit="return validateConditions()">
        <input type="hidden" name="action" value="<?= $editRule ? 'update' : 'create' ?>">
        <?php if ($editRule): ?><input type="hidden" name="rule_id" value="<?= $editRule['id'] ?>"><?php endif; ?>

        <div class="form-group">
          <label><?= t('rules_name') ?></label>
          <input type="text" name="name" value="<?= htmlspecialchars($editRule['name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label><?= t('rules_conditions') ?></label>
          <div class="conditions-hint"><?= t('rules_conditions_hint') ?></div>
          <div id="conditionsList" class="conditions-list">
            <?php foreach ($formConditions as $idx => $fc): ?>
            <?php
              $isHeader = ($fc['type'] === 'header');
              $headerName = '';
              $headerValue = '';
              $plainValue = $fc['value'];
              if ($isHeader) {
                  $parsed = json_decode($fc['value'], true);
                  $headerName = $parsed['name'] ?? '';
                  $headerValue = $parsed['value'] ?? '';
                  $plainValue = '';
              }
            ?>
            <div class="condition-row">
              <div class="form-group" style="max-width:180px;">
                <label><?= t('rules_type') ?></label>
                <select name="condition_type[]" onchange="toggleConditionHeader(this)">
                  <?php foreach ($ruleTypes as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $fc['type'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group condition-value-field" style="<?= $isHeader ? 'display:none;' : '' ?>">
                <label><?= t('rules_value') ?></label>
                <input type="text" name="condition_value[]" value="<?= htmlspecialchars($plainValue) ?>">
              </div>
              <div class="condition-header-fields" style="<?= $isHeader ? '' : 'display:none;' ?>">
                <div class="form-group">
                  <label>Header Name</label>
                  <input type="text" name="header_name[]" value="<?= htmlspecialchars($headerName) ?>" placeholder="X-Custom-Header">
                </div>
                <div class="form-group">
                  <label>Header Value</label>
                  <input type="text" name="header_value[]" value="<?= htmlspecialchars($headerValue) ?>" placeholder="suspicious-value">
                </div>
              </div>
              <button type="button" class="condition-remove-btn" onclick="removeCondition(this)" title="Remove">&times;</button>
            </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn-add-condition" onclick="addCondition()">+ <?= t('rules_add_condition') ?></button>
        </div>

        <div class="form-group">
          <label><?= t('rules_action') ?></label>
          <select name="rule_action">
            <option value="block" <?= ($editRule['action'] ?? 'block') === 'block' ? 'selected' : '' ?>><?= t('rules_block') ?></option>
            <option value="allow" <?= ($editRule['action'] ?? '') === 'allow' ? 'selected' : '' ?>><?= t('rules_allow') ?></option>
          </select>
        </div>
        <div class="form-group">
          <label><?= t('rules_priority') ?></label>
          <input type="number" name="priority" value="<?= (int)($editRule['priority'] ?? 0) ?>" min="0">
        </div>
        <div class="form-group">
          <label class="toggle-switch-label">
            <div class="toggle-switch">
              <input type="checkbox" name="is_active" value="1" <?= ($editRule === null || !empty($editRule['is_active'])) ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </div>
            <span><?= t('rules_active') ?></span>
          </label>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= t('rules_save') ?></button>
          <a href="<?= basePath('/projects/' . $id . '/rules') ?>" class="btn-secondary"><?= t('rules_cancel') ?></a>
        </div>
      </form>
    </div>

    <script>
    var ruleTypesJson = <?= json_encode($ruleTypes) ?>;

    function buildConditionRow(type, value, headerName, headerValue) {
      type = type || 'ip';
      value = value || '';
      headerName = headerName || '';
      headerValue = headerValue || '';
      var isHeader = (type === 'header');

      var row = document.createElement('div');
      row.className = 'condition-row';

      // Type select
      var typeGroup = document.createElement('div');
      typeGroup.className = 'form-group';
      typeGroup.style.maxWidth = '180px';
      typeGroup.innerHTML = '<label><?= t('rules_type') ?></label>';
      var sel = document.createElement('select');
      sel.name = 'condition_type[]';
      sel.onchange = function() { toggleConditionHeader(this); };
      for (var k in ruleTypesJson) {
        var opt = document.createElement('option');
        opt.value = k;
        opt.textContent = ruleTypesJson[k];
        if (k === type) opt.selected = true;
        sel.appendChild(opt);
      }
      typeGroup.appendChild(sel);
      row.appendChild(typeGroup);

      // Plain value field
      var valGroup = document.createElement('div');
      valGroup.className = 'form-group condition-value-field';
      valGroup.style.display = isHeader ? 'none' : '';
      valGroup.innerHTML = '<label><?= t('rules_value') ?></label><input type="text" name="condition_value[]" value="' + escapeHtml(value) + '">';
      row.appendChild(valGroup);

      // Header fields
      var headerGroup = document.createElement('div');
      headerGroup.className = 'condition-header-fields';
      headerGroup.style.display = isHeader ? '' : 'none';
      headerGroup.innerHTML =
        '<div class="form-group"><label>Header Name</label><input type="text" name="header_name[]" value="' + escapeHtml(headerName) + '" placeholder="X-Custom-Header"></div>' +
        '<div class="form-group"><label>Header Value</label><input type="text" name="header_value[]" value="' + escapeHtml(headerValue) + '" placeholder="suspicious-value"></div>';
      row.appendChild(headerGroup);

      // Remove button
      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'condition-remove-btn';
      removeBtn.title = 'Remove';
      removeBtn.innerHTML = '&times;';
      removeBtn.onclick = function() { removeCondition(this); };
      row.appendChild(removeBtn);

      return row;
    }

    function addCondition() {
      var list = document.getElementById('conditionsList');
      var row = buildConditionRow('ip', '', '', '');
      list.appendChild(row);
    }

    function removeCondition(btn) {
      var list = document.getElementById('conditionsList');
      var rows = list.querySelectorAll('.condition-row');
      if (rows.length <= 1) return; // keep at least 1
      btn.closest('.condition-row').remove();
    }

    function toggleConditionHeader(select) {
      var row = select.closest('.condition-row');
      var isHeader = (select.value === 'header');
      row.querySelector('.condition-value-field').style.display = isHeader ? 'none' : '';
      row.querySelector('.condition-header-fields').style.display = isHeader ? '' : 'none';
    }

    function validateConditions() {
      var rows = document.querySelectorAll('#conditionsList .condition-row');
      if (rows.length === 0) {
        alert('<?= t('rules_add_condition') ?>');
        return false;
      }
      return true;
    }

    function escapeHtml(str) {
      var div = document.createElement('div');
      div.appendChild(document.createTextNode(str));
      return div.innerHTML;
    }
    </script>
    <?php endif; ?>

    <?php if (empty($rules)): ?>
      <div class="empty-state"><?= t('rules_empty') ?></div>
    <?php else: ?>
      <table class="data-table">
        <thead>
          <tr>
            <th><?= t('rules_name') ?></th>
            <th><?= t('rules_conditions') ?></th>
            <th><?= t('rules_action') ?></th>
            <th><?= t('rules_priority') ?></th>
            <th><?= t('logs_status') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rules as $rule): ?>
          <tr class="<?= empty($rule['is_active']) ? 'inactive-row' : '' ?>">
            <td><?= htmlspecialchars($rule['name']) ?></td>
            <td class="conditions-cell">
              <?php
              $conds = $ruleConditions[$rule['id']] ?? [];
              if (!empty($conds)) {
                  foreach ($conds as $cond) {
                      $typeLabel = $ruleTypes[$cond['condition_type']] ?? $cond['condition_type'];
                      $displayVal = $cond['condition_value'];
                      if ($cond['condition_type'] === 'header') {
                          $parsed = json_decode($cond['condition_value'], true);
                          if ($parsed) {
                              $displayVal = ($parsed['name'] ?? '') . ': ' . ($parsed['value'] ?? '');
                          }
                      }
                      echo '<span class="condition-tag"><strong>' . htmlspecialchars($typeLabel) . ':</strong> ' . htmlspecialchars(mb_substr($displayVal, 0, 40)) . '</span> ';
                  }
              } else {
                  // Fallback: show old rule_type/rule_value if no conditions found
                  $typeLabel = $ruleTypes[$rule['rule_type']] ?? $rule['rule_type'];
                  $displayVal = $rule['rule_value'];
                  if ($rule['rule_type'] === 'header') {
                      $parsed = json_decode($rule['rule_value'], true);
                      if ($parsed) {
                          $displayVal = ($parsed['name'] ?? '') . ': ' . ($parsed['value'] ?? '');
                      }
                  }
                  echo '<span class="condition-tag"><strong>' . htmlspecialchars($typeLabel) . ':</strong> ' . htmlspecialchars(mb_substr($displayVal, 0, 40)) . '</span>';
              }
              ?>
            </td>
            <td><span class="action-badge <?= $rule['action'] ?>"><?= $rule['action'] === 'block' ? t('rules_block') : t('rules_allow') ?></span></td>
            <td><?= (int)$rule['priority'] ?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                <label class="toggle-switch" onclick="event.preventDefault(); this.closest('form').submit()">
                  <input type="checkbox" <?= !empty($rule['is_active']) ? 'checked' : '' ?>>
                  <span class="toggle-slider"></span>
                </label>
              </form>
            </td>
            <td class="actions-cell">
              <a href="<?= basePath('/projects/' . $id . '/rules?edit=' . $rule['id']) ?>" class="btn-icon" title="Edit">&#9998;</a>
              <form method="POST" style="display:inline;" onsubmit="return confirm('<?= t('rules_delete_confirm') ?>?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                <button type="submit" class="btn-icon danger">&#10005;</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php layoutEnd(); ?>
