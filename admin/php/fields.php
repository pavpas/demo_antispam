<?php
// AntiSpam Shield — PHP Custom Fields Management

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: ' . basePath('/projects'));
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['field_name'] ?? '');
        if ($name !== '') {
            $stmt = $db->prepare('INSERT INTO project_fields (project_id, field_name) VALUES (?, ?)');
            $stmt->execute([$id, $name]);
        }
        header('Location: ' . basePath('/projects/' . $id . '/fields'));
        exit;
    } elseif ($action === 'delete') {
        $fieldId = (int)($_POST['field_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM project_fields WHERE id = ? AND project_id = ?');
        $stmt->execute([$fieldId, $id]);
        header('Location: ' . basePath('/projects/' . $id . '/fields'));
        exit;
    }
}

// Fetch all fields
$stmt = $db->prepare('SELECT * FROM project_fields WHERE project_id = ? ORDER BY id ASC');
$stmt->execute([$id]);
$fields = $stmt->fetchAll();

layoutStart($project['name'] . ' — ' . t('fields_title'));
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
    <a href="<?= basePath('/projects/' . $id . '/logs') ?>" class="tab"><?= t('tab_logs') ?></a>
    <a href="<?= basePath('/projects/' . $id . '/fields') ?>" class="tab active"><?= t('tab_fields') ?></a>
    <a href="<?= basePath('/projects/' . $id . '?tab=testing') ?>" class="tab"><?= t('tab_testing') ?></a>
  </div>

  <div class="tab-content">
    <h3><?= t('fields_title') ?></h3>

    <div class="rule-form-box">
      <h4><?= t('fields_add') ?></h4>
      <form method="POST" class="form-row" style="align-items:flex-end;">
        <input type="hidden" name="action" value="create">
        <div class="form-group" style="flex:1;">
          <label><?= t('fields_name') ?></label>
          <input type="text" name="field_name" placeholder="<?= t('fields_placeholder') ?>" required>
        </div>
        <div class="form-group">
          <button type="submit" class="btn-primary"><?= t('fields_add') ?></button>
        </div>
      </form>
    </div>

    <?php if (empty($fields)): ?>
      <div class="empty-state"><?= t('fields_empty') ?></div>
    <?php else: ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th><?= t('fields_name') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($fields as $i => $field): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($field['field_name']) ?></td>
            <td class="actions-cell">
              <form method="POST" style="display:inline;" onsubmit="return confirm('<?= t('fields_delete_confirm') ?>?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="field_id" value="<?= $field['id'] ?>">
                <button type="submit" class="btn-icon danger" title="<?= t('fields_delete') ?>">&#10005;</button>
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
