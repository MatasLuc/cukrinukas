<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureCommunityTables($pdo);
ensureNavigationTable($pdo);

$user = currentUser();
$blocked = $user['id'] ? isCommunityBlocked($pdo, (int)$user['id']) : null;
$errors = [];
$categories = $pdo->query('SELECT * FROM community_listing_categories ORDER BY name ASC')->fetchAll();

if (!$user['id']) {
    $_SESSION['flash_error'] = 'Prisijunkite, kad įkeltumėte skelbimą.';
    header('Location: /login.php');
    exit;
}

if ($blocked) {
    $_SESSION['flash_error'] = 'Skelbimų kėlimas apribotas iki ' . ($blocked['banned_until'] ?? 'neribotai');
    header('Location: /community_market.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $sellerEmail = trim($_POST['seller_email'] ?? '');
    $sellerPhone = trim($_POST['seller_phone'] ?? '');
    $img = uploadImageWithValidation($_FILES['image'] ?? [], 'community_', $errors, null, false);

    if (!$title || !$description) {
        $errors[] = 'Įrašykite pavadinimą ir aprašymą.';
    }
    if (!$sellerEmail && !$sellerPhone) {
        $errors[] = 'Nurodykite bent vieną kontaktą (el. paštą arba tel. nr.).';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO community_listings (user_id, category_id, title, description, price, seller_email, seller_phone, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $categoryId ?: null, $title, $description, $price, $sellerEmail ?: null, $sellerPhone ?: null, $img]);
        $_SESSION['flash_success'] = 'Skelbimas pridėtas';
        header('Location: /community_market.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Naujas skelbimas | Cukrinukas</title>
  <?php echo headerStyles(); ?>
</head>
<body>
  <?php renderHeader($pdo, 'community'); ?>
<main style="max-width:900px;margin:40px auto;padding:0 18px 60px;">
  <section style="background:#fff;border:1px solid #e0e6f6;border-radius:18px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,0.06);">
    <h1 style="margin-top:0;">Naujas skelbimas</h1>
    <p class="muted" style="margin-top:0;">Įkelkite daiktą, kurį norite pasiūlyti bendruomenei.</p>
    <?php foreach ($errors as $err): ?>
      <div style="background:#fff1f1;border:1px solid #f3b7b7;padding:10px;border-radius:10px;">&times; <?php echo htmlspecialchars($err); ?></div>
    <?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:14px;margin-top:14px;">
      <?php echo csrfField(); ?>
      <label style="display:flex;flex-direction:column;gap:6px;">
        <span>Pavadinimas</span>
        <input name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
      </label>
      <label style="display:flex;flex-direction:column;gap:6px;">
        <span>Aprašymas</span>
        <textarea name="description" style="min-height:160px;" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
      </label>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <label style="display:flex;flex-direction:column;gap:6px;flex:1;min-width:180px;">
          <span>Kaina (€)</span>
          <input name="price" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['price'] ?? '0'); ?>">
        </label>
        <label style="display:flex;flex-direction:column;gap:6px;flex:1;min-width:200px;">
          <span>Kategorija</span>
          <select name="category_id">
            <option value="">Pasirinkti</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo (int)$cat['id']; ?>" <?php echo (isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label style="display:flex;flex-direction:column;gap:6px;flex:1;min-width:220px;">
          <span>El. paštas</span>
          <input name="seller_email" value="<?php echo htmlspecialchars($_POST['seller_email'] ?? $user['email'] ?? ''); ?>" placeholder="info@...">
        </label>
        <label style="display:flex;flex-direction:column;gap:6px;flex:1;min-width:180px;">
          <span>Tel. nr.</span>
          <input name="seller_phone" value="<?php echo htmlspecialchars($_POST['seller_phone'] ?? ''); ?>" placeholder="+370...">
        </label>
      </div>
      <label style="display:flex;flex-direction:column;gap:6px;">
        <span>Nuotrauka</span>
        <input type="file" name="image" accept="image/*">
      </label>
  <div style="display:flex;gap:10px;align-items:center;">
    <button class="btn" style="background:#0b0b0b;color:#fff;border-color:#0b0b0b;">Pridėti</button>
    <a class="btn btn-secondary" href="/community_market.php">Grįžti</a>
  </div>
  </form>
  </section>
</main>
  <?php renderFooter($pdo); ?>
</body>
</html>
