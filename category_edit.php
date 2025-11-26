<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login.php');
    exit;
}

$pdo = getPdo();
ensureCategoriesTable($pdo);
ensureAdminAccount($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
$stmt->execute([$id]);
$category = $stmt->fetch();
if (!$category) {
    http_response_code(404);
    echo 'Kategorija nerasta';
    exit;
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if ($name && $slug) {
        $pdo->prepare('UPDATE categories SET name = ?, slug = ? WHERE id = ?')->execute([$name, $slug, $id]);
        $messages[] = 'Kategorija atnaujinta';
        $stmt->execute([$id]);
        $category = $stmt->fetch();
    } else {
        $errors[] = 'Įveskite pavadinimą ir slug';
    }
}
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Redaguoti kategoriją | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    :root { --color-bg: #f7f7fb; --color-primary: #0b0b0b; }
    * { box-sizing: border-box; }
    a { color:inherit; text-decoration:none; }
    .page { max-width:800px; margin:0 auto; padding:24px; }
    .card { background:#fff; border-radius:16px; padding:20px; box-shadow:0 12px 24px rgba(0,0,0,0.08); }
    label { display:block; font-weight:600; margin-bottom:6px; }
    input { width:100%; padding:12px; border-radius:12px; border:1px solid #d7d7e2; margin-bottom:12px; }
    .btn { padding:10px 14px; border-radius:12px; border:1px solid #0b0b0b; background:#0b0b0b; color:#fff; font-weight:600; cursor:pointer; }

  </style>
</head>
<body>
  <?php renderHeader($pdo, 'admin'); ?>
  <div class="page">
    <a href="/admin.php?view=categories" style="display:inline-block; margin-bottom:12px; font-weight:600;">↩ Atgal į kategorijas</a>
    <div class="card">
      <h1 style="margin-top:0;">Redaguoti kategoriją</h1>
      <?php foreach ($messages as $msg): ?>
        <div style="background:#edf9f0; border:1px solid #b8e2c4; padding:12px; border-radius:12px; color:#0f5132; margin-bottom:10px;">&check; <?php echo htmlspecialchars($msg); ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $err): ?>
        <div style="background:#fff1f1; border:1px solid #f3b7b7; padding:12px; border-radius:12px; color:#991b1b; margin-bottom:10px;">&times; <?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>
      <form method="post">
        <?php echo csrfField(); ?>
<label>Pavadinimas</label>
        <input name="name" value="<?php echo htmlspecialchars($category['name']); ?>" required>
        <label>Slug</label>
        <input name="slug" value="<?php echo htmlspecialchars($category['slug']); ?>" required>
        <button class="btn" type="submit">Išsaugoti</button>
      </form>
    </div>
  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
