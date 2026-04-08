<?php
// AntiSpam Shield — PHP Auth (Login)

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
    $stmt->execute([$_POST['username']]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['password'], $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header('Location: ' . basePath('/projects'));
        exit;
    }
    $error = t('login_error');
}

// If already logged in, redirect
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . basePath('/projects'));
    exit;
}

$lang = currentLang();
$otherLang = $lang === 'ru' ? 'en' : 'ru';
$otherLangLabel = $lang === 'ru' ? 'EN' : 'RU';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= t('login_title') ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= basePath('/assets/style.css') ?>">
</head>
<body class="login-page">
  <a href="?lang=<?= $otherLang ?>" class="login-lang-btn"><?= $otherLangLabel ?></a>
  <div class="login-container">
    <div class="login-logo">&#x1f6e1;</div>
    <h1><?= APP_NAME ?></h1>
    <p class="login-subtitle"><?= t('app_subtitle') ?> (PHP)</p>
    <?php if ($error): ?>
      <div class="login-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="<?= basePath('/login') ?>">
      <div class="form-group">
        <label><?= t('login_username') ?></label>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="form-group">
        <label><?= t('login_password') ?></label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn-primary"><?= t('login_submit') ?></button>
    </form>
  </div>
</body>
</html>
