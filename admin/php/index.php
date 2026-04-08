<?php
// AntiSpam Shield — PHP Admin Panel Router
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Language switch
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    $path = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . basePath($path));
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// Serve static assets
if (preg_match('/\.(css|js|png|jpg|gif|svg|ico)$/', $uri)) {
    return false;
}

// Auth check (skip for login page)
$publicRoutes = ['/', '/login'];
if (!in_array($uri, $publicRoutes) && empty($_SESSION['user_id'])) {
    header('Location: ' . basePath('/login'));
    exit;
}

// Router
switch (true) {
    case $uri === '/' || $uri === '/login':
        require __DIR__ . '/auth.php';
        break;
    case $uri === '/logout':
        session_destroy();
        header('Location: ' . basePath('/login'));
        exit;
    case $uri === '/projects' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create':
        require __DIR__ . '/projects.php';
        break;
    case $uri === '/projects' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete':
        require __DIR__ . '/projects.php';
        break;
    case $uri === '/projects':
        require __DIR__ . '/projects.php';
        break;
    case preg_match('#^/projects/(\d+)/rules$#', $uri, $m) === 1:
        $_GET['id'] = $m[1];
        require __DIR__ . '/rules.php';
        break;
    case preg_match('#^/projects/(\d+)/logs$#', $uri, $m) === 1:
        $_GET['id'] = $m[1];
        require __DIR__ . '/logs.php';
        break;
    case preg_match('#^/projects/(\d+)/fields$#', $uri, $m) === 1:
        $_GET['id'] = $m[1];
        require __DIR__ . '/fields.php';
        break;
    case preg_match('#^/projects/(\d+)/stats$#', $uri, $m) === 1:
        $_GET['id'] = $m[1];
        require __DIR__ . '/stats.php';
        break;
    case preg_match('#^/projects/(\d+)$#', $uri, $m) === 1:
        $_GET['id'] = $m[1];
        require __DIR__ . '/detail.php';
        break;
    default:
        http_response_code(404);
        echo '404 Not Found';
        break;
}

// HTML layout functions
function layoutStart(string $title = ''): void {
    $pageTitle = $title ? "$title — " . APP_NAME : APP_NAME;
    $lang = currentLang();
    $otherLang = $lang === 'ru' ? 'en' : 'ru';
    $otherLangLabel = $lang === 'ru' ? 'EN' : 'RU';
    echo '<!DOCTYPE html>
<html lang="' . $lang . '">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . htmlspecialchars($pageTitle) . '</title>
  <link rel="stylesheet" href="' . basePath('/assets/style.css') . '">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
  <header class="app-header">
    <a href="/" class="header-left" style="text-decoration:none;color:inherit;">
      <span class="header-logo">&#x1f6e1;</span>
      <div>
        <div class="header-title">' . t('app_title') . '</div>
        <div class="header-subtitle">' . t('app_subtitle') . ' (PHP)</div>
      </div>
    </a>
    <div class="header-right">';
    if (!empty($_SESSION['user_id'])) {
        echo '<a href="' . basePath('/projects') . '" class="nav-link">' . t('nav_projects') . '</a>';
        echo '<a href="?lang=' . $otherLang . '" class="lang-btn">' . $otherLangLabel . '</a>';
        echo '<span class="username">' . htmlspecialchars($_SESSION['username']) . '</span>';
        echo '<a href="' . basePath('/logout') . '" class="logout-btn">' . t('nav_logout') . '</a>';
    } else {
        echo '<a href="?lang=' . $otherLang . '" class="lang-btn">' . $otherLangLabel . '</a>';
    }
    echo '</div>
  </header>
  <main class="main-content">';
}

function layoutEnd(): void {
    echo '</main>
  <script>
  function copyCode(btn) {
    var block = btn.closest(".code-block");
    var code = block.querySelector("code").innerText;
    navigator.clipboard.writeText(code).then(function() {
      btn.textContent = "' . t('copied') . '";
      setTimeout(function() { btn.textContent = "' . t('copy') . '"; }, 2000);
    });
  }
  </script>
</body>
</html>';
}

function codeBlock(string $code, string $lang = ''): void {
    echo '<div class="code-block">';
    if ($lang) {
        echo '<div class="code-header"><span class="code-lang">' . htmlspecialchars($lang) . '</span><button class="copy-btn" onclick="copyCode(this)">' . t('copy') . '</button></div>';
    } else {
        echo '<div class="code-header"><button class="copy-btn" onclick="copyCode(this)">' . t('copy') . '</button></div>';
    }
    echo '<pre><code>' . htmlspecialchars($code) . '</code></pre></div>';
}
