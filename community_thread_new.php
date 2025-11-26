<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureCommunityTables($pdo);
ensureNavigationTable($pdo);

$user = currentUser();
$blocked = $user['id'] ? isCommunityBlocked($pdo, (int)$user['id']) : null;
$errors = [];
$categories = $pdo->query('SELECT * FROM community_thread_categories ORDER BY name ASC')->fetchAll();

if (!$user['id']) {
    $_SESSION['flash_error'] = 'Prisijunkite, kad sukurtumėte temą.';
    header('Location: /login.php');
    exit;
}

if ($blocked) {
    $_SESSION['flash_error'] = 'Temos kūrimas apribotas iki ' . ($blocked['banned_until'] ?? 'neribotai');
    header('Location: /community_discussions.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    if (!$title || !$body) {
        $errors[] = 'Užpildykite pavadinimą ir žinutę.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO community_threads (user_id, category_id, title, body) VALUES (?, ?, ?, ?)');
        $stmt->execute([$user['id'], $categoryId ?: null, $title, $body]);
        $_SESSION['flash_success'] = 'Diskusija sukurta';
        header('Location: /community_discussions.php');
        exit;
    }
}

?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nauja tema | Cukrinukas</title>
  <?php echo headerStyles(); ?>
</head>
<body>
  <?php renderHeader($pdo, 'community'); ?>
<main style="max-width:900px;margin:40px auto;padding:0 18px 60px;">
  <section style="background:#fff;border:1px solid #e0e6f6;border-radius:18px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,0.06);">
    <h1 style="margin-top:0;">Nauja tema</h1>
    <p class="muted" style="margin-top:0;">Pasidalykite klausimu ar patarimu su bendruomene.</p>
    <?php foreach ($errors as $err): ?>
      <div style="background:#fff1f1;border:1px solid #f3b7b7;padding:10px;border-radius:10px;">&times; <?php echo htmlspecialchars($err); ?></div>
    <?php endforeach; ?>
    <form method="post" style="display:flex;flex-direction:column;gap:14px;margin-top:14px;">
      <?php echo csrfField(); ?>
      <label style="display:flex;flex-direction:column;gap:6px;">
        <span>Pavadinimas</span>
        <input name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
      </label>
      <label style="display:flex;flex-direction:column;gap:6px;">
        <span>Žinutė</span>
        <textarea name="body" style="min-height:200px;" required><?php echo htmlspecialchars($_POST['body'] ?? ''); ?></textarea>
      </label>
      <label style="display:flex;flex-direction:column;gap:6px;">
        <span>Kategorija (pasirinktinai)</span>
        <select name="category_id">
          <option value="">Be kategorijos</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?php echo (int)$cat['id']; ?>" <?php echo (isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div style="display:flex;gap:10px;align-items:center;">
        <button class="btn" style="background:#0b0b0b;color:#fff;border-color:#0b0b0b;">Kurti</button>
        <a class="btn btn-secondary" href="/community_discussions.php">Grįžti</a>
      </div>
    </form>
  </section>
</main>
  <?php renderFooter($pdo); ?>
</body>
</html>
