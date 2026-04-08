<?php
// AntiSpam Shield — PHP Projects (list, create, delete)

$db = getDB();

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name = trim($_POST['name'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    if ($name && $domain) {
        $publicKey = 'pk_' . bin2hex(random_bytes(16));
        $privateKey = 'sk_' . bin2hex(random_bytes(16));
        $stmt = $db->prepare('INSERT INTO projects (user_id, name, domain, public_key, private_key) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$_SESSION['user_id'], $name, $domain, $publicKey, $privateKey]);
    }
    header('Location: ' . basePath('/projects'));
    exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $_SESSION['user_id']]);
    }
    header('Location: ' . basePath('/projects'));
    exit;
}

// List projects
$stmt = $db->prepare('SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll();

layoutStart(t('projects_title'));
?>

<div class="page-card">
  <div class="page-header">
    <h2><?= t('projects_title') ?></h2>
    <button class="btn-primary" onclick="document.getElementById('createModal').style.display='flex'"><?= t('projects_add') ?></button>
  </div>

  <?php if (empty($projects)): ?>
    <p class="empty-state"><?= t('projects_empty') ?></p>
  <?php else: ?>
    <table class="projects-table">
      <thead>
        <tr>
          <th><?= t('projects_name') ?></th>
          <th><?= t('projects_domain') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($projects as $p): ?>
        <tr>
          <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
          <td><span class="domain-badge"><?= htmlspecialchars($p['domain']) ?></span></td>
          <td class="actions">
            <a href="<?= basePath('/projects/' . $p['id'] . '/stats') ?>" class="btn-sm btn-stats"><?= t('projects_stats') ?></a>
            <a href="<?= basePath('/projects/' . $p['id']) ?>" class="btn-sm btn-detail"><?= t('projects_details') ?></a>
            <form method="POST" action="<?= basePath('/projects') ?>" style="display:inline" onsubmit="return confirm('<?= t('projects_delete_confirm') ?> &quot;<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>&quot;?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn-sm btn-danger"><?= t('projects_delete') ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Create Modal -->
<div id="createModal" class="modal" style="display:none">
  <div class="modal-content">
    <h3><?= t('new_project_title') ?></h3>
    <form method="POST" action="<?= basePath('/projects') ?>">
      <input type="hidden" name="action" value="create">
      <div class="form-group">
        <label><?= t('projects_name') ?></label>
        <input type="text" name="name" placeholder="<?= t('new_project_name') ?>" required>
      </div>
      <div class="form-group">
        <label><?= t('projects_domain') ?></label>
        <input type="text" name="domain" placeholder="<?= t('new_project_domain') ?>" required>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="document.getElementById('createModal').style.display='none'"><?= t('new_project_cancel') ?></button>
        <button type="submit" class="btn-primary"><?= t('new_project_create') ?></button>
      </div>
    </form>
  </div>
</div>

<?php layoutEnd(); ?>
