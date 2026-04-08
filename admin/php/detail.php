<?php
// AntiSpam Shield — PHP Project Detail (keys, frontend, backend tabs)

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM projects WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: ' . basePath('/projects'));
    exit;
}

$tab = $_GET['tab'] ?? 'keys';
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080');
// Use the main Go server URL for SDK (not the PHP proxy)
$sdkBaseUrl = rtrim($baseUrl, '/');

layoutStart($project['name']);
?>

<div class="page-card">
  <div class="detail-header">
    <a href="<?= basePath('/projects') ?>" class="btn-back">&#8592; <?= t('back') ?></a>
    <h1><?= htmlspecialchars($project['name']) ?></h1>
    <span class="domain-badge"><?= htmlspecialchars($project['domain']) ?></span>
  </div>

  <div class="tabs">
    <a href="<?= basePath('/projects/' . $id . '?tab=keys') ?>" class="tab <?= $tab === 'keys' ? 'active' : '' ?>"><?= t('tab_keys') ?></a>
    <a href="<?= basePath('/projects/' . $id . '?tab=frontend') ?>" class="tab <?= $tab === 'frontend' ? 'active' : '' ?>"><?= t('tab_frontend') ?></a>
    <a href="<?= basePath('/projects/' . $id . '?tab=backend') ?>" class="tab <?= $tab === 'backend' ? 'active' : '' ?>"><?= t('tab_backend') ?></a>
    <a href="<?= basePath('/projects/' . $id . '/rules') ?>" class="tab"><?= t('tab_rules') ?></a>
    <a href="<?= basePath('/projects/' . $id . '/logs') ?>" class="tab"><?= t('tab_logs') ?></a>
    <a href="<?= basePath('/projects/' . $id . '/fields') ?>" class="tab"><?= t('tab_fields') ?></a>
    <a href="<?= basePath('/projects/' . $id . '?tab=testing') ?>" class="tab <?= $tab === 'testing' ? 'active' : '' ?>"><?= t('tab_testing') ?></a>
  </div>

  <div class="tab-content">
  <?php if ($tab === 'keys'): ?>
    <div class="key-display">
      <label><?= t('key_public') ?></label>
      <div class="key-row">
        <code class="key-value"><?= htmlspecialchars($project['public_key']) ?></code>
        <button class="copy-btn" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($project['public_key']) ?>').then(()=>{this.textContent='<?= t('copied') ?>';setTimeout(()=>this.textContent='<?= t('copy') ?>',2000)})"><?= t('copy') ?></button>
      </div>
    </div>
    <div class="key-display">
      <label><?= t('key_private') ?></label>
      <div class="key-row">
        <code class="key-value secret" id="secretKey">••••••••••••••••</code>
        <button class="copy-btn" onclick="var el=document.getElementById('secretKey');if(el.dataset.shown){navigator.clipboard.writeText('<?= htmlspecialchars($project['private_key']) ?>').then(()=>{this.textContent='<?= t('copied') ?>';setTimeout(()=>this.textContent='<?= t('copy') ?>',2000)})}else{el.textContent='<?= htmlspecialchars($project['private_key']) ?>';el.dataset.shown='1';this.textContent='<?= t('copy') ?>'}"><?= t('copy') ?></button>
      </div>
    </div>
    <div class="warning-box">&#x26a0;&#xfe0f; <?= t('key_warning') ?></div>

  <?php elseif ($tab === 'frontend'): ?>
    <h3><?= t('frontend_title') ?></h3>

    <h4><?= t('frontend_auto_title') ?></h4>
    <p><?= t('frontend_auto_desc') ?></p>
    <p><?= t('frontend_auto_step') ?></p>
    <?php codeBlock('<script src="' . $sdkBaseUrl . '/sdk.js?key=' . $project['public_key'] . '"></script>', 'HTML'); ?>

    <div class="divider"></div>

    <h4><?= t('frontend_manual_title') ?></h4>
    <p><?= t('frontend_manual_desc') ?></p>
    <p><?= t('frontend_manual_step') ?></p>
    <?php codeBlock('<script src="' . $sdkBaseUrl . '/sdk.js?key=' . $project['public_key'] . '&auto=0"></script>', 'HTML'); ?>

    <p><?= t('frontend_manual_examples') ?></p>

    <p class="example-label"><?= t('frontend_by_id') ?></p>
    <?php codeBlock("<script>\n  AntiSpam.protect('#contactForm');\n</script>", 'JavaScript'); ?>

    <p class="example-label"><?= t('frontend_by_class') ?></p>
    <?php codeBlock("<script>\n  AntiSpam.protect('.protected-form');\n</script>", 'JavaScript'); ?>

    <p class="example-label"><?= t('frontend_by_selector') ?></p>
    <?php codeBlock("<script>\n  AntiSpam.protect('form[data-antispam]');\n  AntiSpam.protect(document.getElementById('myForm'));\n</script>", 'JavaScript'); ?>

    <div class="info-box"><?= t('frontend_note') ?></div>

  <?php elseif ($tab === 'backend'): ?>
    <?php
    $verifyUrl = $sdkBaseUrl . '/api/v1/verify';
    ?>
    <h3><?= t('backend_title') ?></h3>
    <p><?= t('backend_desc') ?></p>

    <h4><?= t('backend_endpoint') ?></h4>
    <?php codeBlock('POST ' . $verifyUrl); ?>

    <h4><?= t('backend_request') ?></h4>
    <?php codeBlock("{\n  \"secret\": \"" . $project['private_key'] . "\",\n  \"token\": \"TOKEN_FROM_FORM_FIELD_antispam_token\",\n  \"data\": \"optional string data\",\n  \"filters\": {\"form\": \"contact\", \"method\": \"POST\"}\n}", 'JSON'); ?>

    <div class="info-box"><strong>data</strong> &mdash; <?= currentLang() === 'ru' ? 'произвольная строка, которая будет сохранена в логе верификации.' : 'arbitrary string that will be saved in the verification log.' ?><br><strong>filters</strong> &mdash; <?= currentLang() === 'ru' ? 'объект с произвольными ключами для фильтрации логов по пользовательским полям.' : 'object with arbitrary keys for filtering logs by custom fields.' ?></div>

    <h4><?= t('backend_response') ?></h4>
    <?php codeBlock("{\n  \"success\": true,\n  \"score\": 0.87,\n  \"action\": \"submit\",\n  \"timestamp\": \"2026-01-15T12:00:00Z\",\n  \"hostname\": \"" . $project['domain'] . "\"\n}", 'JSON'); ?>

    <div class="info-box"><?= t('backend_score_note') ?></div>

    <h3><?= t('backend_examples') ?></h3>

    <?php codeBlock("<?php\n\$token = \$_POST['_antispam_token'] ?? '';\n\$secret = '" . $project['private_key'] . "';\n\n\$response = file_get_contents('" . $verifyUrl . "', false, stream_context_create([\n    'http' => [\n        'method' => 'POST',\n        'header' => 'Content-Type: application/json',\n        'content' => json_encode(['secret' => \$secret, 'token' => \$token])\n    ]\n]));\n\n\$result = json_decode(\$response, true);\nif (\$result['success'] && \$result['score'] >= 0.5) {\n    // Human - process the form\n} else {\n    // Bot - reject the request\n}\n?>", 'PHP'); ?>

    <?php codeBlock("import requests\n\ntoken = request.form.get('_antispam_token', '')\nresponse = requests.post('" . $verifyUrl . "', json={\n    'secret': '" . $project['private_key'] . "',\n    'token': token\n})\nresult = response.json()\n\nif result.get('success') and result.get('score', 0) >= 0.5:\n    # Human\n    pass\nelse:\n    # Bot\n    pass", 'Python'); ?>

    <?php codeBlock("const token = req.body._antispam_token;\nconst response = await fetch('" . $verifyUrl . "', {\n  method: 'POST',\n  headers: { 'Content-Type': 'application/json' },\n  body: JSON.stringify({\n    secret: '" . $project['private_key'] . "',\n    token: token\n  })\n});\nconst result = await response.json();\n\nif (result.success && result.score >= 0.5) {\n  // Human\n} else {\n  // Bot\n}", 'Node.js'); ?>

  <?php elseif ($tab === 'testing'): ?>
    <h3><?= t('testing_title') ?></h3>
    <p><?= t('testing_desc') ?></p>

    <form class="test-form" id="antispam-test-form" onsubmit="return false;">
      <div class="form-group">
        <label><?= t('testing_name') ?></label>
        <input type="text" name="name" value="Test User" required>
      </div>
      <div class="form-group">
        <label><?= t('testing_email') ?></label>
        <input type="email" name="email" value="test@example.com" required>
      </div>
      <div class="form-group">
        <label><?= t('testing_message') ?></label>
        <textarea name="message" rows="3">Hello, this is a test message.</textarea>
      </div>
      <button type="button" class="btn-submit" id="test-submit-btn" onclick="submitTestForm()"><?= t('testing_submit') ?></button>
    </form>

    <div id="test-result"></div>

    <script src="<?= htmlspecialchars($sdkBaseUrl) ?>/sdk.js?key=<?= htmlspecialchars($project['public_key']) ?>&auto=0"></script>
    <script>
    function submitTestForm() {
      var btn = document.getElementById('test-submit-btn');
      var resultDiv = document.getElementById('test-result');
      btn.disabled = true;
      btn.textContent = '<?= t('testing_verifying') ?>';
      resultDiv.innerHTML = '';
      resultDiv.className = '';

      if (typeof AntiSpam === 'undefined') {
        resultDiv.className = 'test-result error';
        resultDiv.innerHTML = '<strong><?= t('testing_result_error') ?>:</strong> <?= t('testing_no_token') ?>';
        btn.disabled = false;
        btn.textContent = '<?= t('testing_submit') ?>';
        return;
      }

      AntiSpam.getToken(function(err, token) {
        if (err || !token) {
          resultDiv.className = 'test-result error';
          resultDiv.innerHTML = '<strong><?= t('testing_result_error') ?>:</strong> <?= t('testing_no_token') ?>';
          btn.disabled = false;
          btn.textContent = '<?= t('testing_submit') ?>';
          return;
        }

        fetch('<?= $sdkBaseUrl ?>/api/v1/verify', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ secret: '<?= htmlspecialchars($project['private_key']) ?>', token: token })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.success && data.score >= 0.5) {
            resultDiv.className = 'test-result success';
            resultDiv.innerHTML = '<strong><?= t('testing_result_human') ?></strong><pre>' + JSON.stringify(data, null, 2) + '</pre>';
          } else {
            resultDiv.className = 'test-result bot';
            resultDiv.innerHTML = '<strong><?= t('testing_result_bot') ?></strong><pre>' + JSON.stringify(data, null, 2) + '</pre>';
          }
        })
        .catch(function(e) {
          resultDiv.className = 'test-result error';
          resultDiv.innerHTML = '<strong><?= t('testing_result_error') ?>:</strong> ' + e.message;
        })
        .finally(function() {
          btn.disabled = false;
          btn.textContent = '<?= t('testing_submit') ?>';
        });
      });
    }
    </script>

  <?php endif; ?>
  </div>
</div>

<?php layoutEnd(); ?>
