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
if (!$user['id']) {
    $_SESSION['flash_error'] = 'Prisijunkite, kad redaguotumėte skelbimą.';
    header('Location: /login.php');
    exit;
}

$listingId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM community_listings WHERE id = ?');
$stmt->execute([$listingId]);
$listing = $stmt->fetch();
if (!$listing) {
    $_SESSION['flash_error'] = 'Skelbimas nerastas.';
    header('Location: /community_market.php');
    exit;
}

$categories = $pdo->query('SELECT * FROM community_listing_categories ORDER BY name ASC')->fetchAll();

if ((int)$listing['user_id'] !== (int)$user['id'] && !$user['is_admin']) {
    $_SESSION['flash_error'] = 'Neturite teisės redaguoti šio skelbimo.';
    header('Location: /community_market.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $sellerEmail = trim($_POST['seller_email'] ?? '');
    $sellerPhone = trim($_POST['seller_phone'] ?? '');
    if (!$title || !$description) {
        $errors[] = 'Užpildykite pavadinimą ir aprašymą.';
    }
    if (!$sellerEmail && !$sellerPhone) {
        $errors[] = 'Nurodykite bent vieną kontaktą (el. paštą arba tel. nr.).';
    }
    $img = uploadImageWithValidation($_FILES['image'] ?? [], 'community_', $errors, null, false);

    if (!$errors) {
        $pdo->prepare('UPDATE community_listings SET title = ?, description = ?, price = ?, status = ?, seller_email = ?, seller_phone = ?, category_id = ? WHERE id = ?')
            ->execute([$title, $description, $price, $status, $sellerEmail ?: null, $sellerPhone ?: null, $categoryId ?: null, $listingId]);
        if ($img) {
            $pdo->prepare('UPDATE community_listings SET image_url = ? WHERE id = ?')->execute([$img, $listingId]);
        }
        $_SESSION['flash_success'] = 'Skelbimas atnaujintas';
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
  <title>Skelbimo redagavimas | Cukrinukas</title>
  <?php echo headerStyles(); ?>
</head>
<body>
  <?php renderHeader($pdo, 'community'); ?>
<main style="max-width:900px;margin:40px auto;padding:0 18px 60px;">
  <section style="background:#fff;border:1px solid #e0e6f6;border-radius:18px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,0.06);">
    <h1 style="margin-top:0;">Redaguoti skelbimą</h1>
    <?php foreach ($errors as $err): ?>
      <div style="background:#fff1f1;border:1px solid #f3b7b7;padding:10px;border-radius:10px;">&times; <?php echo htmlspecialchars($err); ?></div>
    <?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:14px;margin-top:14px;">
      <?php echo csrfField(); ?>
      <label style="display:flex;flex-direction:column;gap:6px;">
        <span>Pavadinimas</span>
        <input name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? $listing['title']); ?>" required>
      </label>
      <label style="display:flex;flex-direction:column;gap:6px;">
        <span>Aprašymas</span>
        <textarea name="description" style="min-height:160px;" required><?php echo htmlspecialchars($_POST['description'] ?? $listing['description']); ?></textarea>
      </label>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <label style="display:flex;flex-direction:column;gap:6px;flex:1;min-width:180px;">
          <span>Kaina (€)</span>
          <input name="price" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['price'] ?? $listing['price']); ?>">
        </label>
        <label style="display:flex;flex-direction:column;gap:6px;flex:1;min-width:200px;">
          <span>Kategorija</span>
          <select name="category_id">
            <option value="">Pasirinkti</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo (int)$cat['id']; ?>" <?php echo (((int)($_POST['category_id'] ?? $listing['category_id'])) === (int)$cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label style="display:flex;flex-direction:column;gap:6px;flex:1;min-width:160px;">
          <span>Statusas</span>
          <select name="status">
            <option value="active" <?php echo (($listing['status'] ?? '') === 'active' || ($_POST['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Aktyvi</option>
            <option value="sold" <?php echo (($listing['status'] ?? '') === 'sold' || ($_POST['status'] ?? '') === 'sold') ? 'selected' : ''; ?>>Parduota</option>
          </select>
        </label>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <label style="display:flex;flex-direction:column;gap:6px;flex:1;min-width:220px;">
          <span>El. paštas</span>
          <input name="seller_email" value="<?php echo htmlspecialchars($_POST['seller_email'] ?? $listing['seller_email']); ?>" placeholder="info@...">
        </label>
        <label style="display:flex;flex-direction:column;gap:6px;flex:1;min-width:180px;">
          <span>Tel. nr.</span>
          <input name="seller_phone" value="<?php echo htmlspecialchars($_POST['seller_phone'] ?? $listing['seller_phone']); ?>" placeholder="+370...">
        </label>
      </div>
      <?php if (!empty($listing['image_url'])): ?>
        <div style="display:flex;align-items:center;gap:12px;">
          <img src="<?php echo htmlspecialchars($listing['image_url']); ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>" style="width:120px;height:80px;object-fit:cover;border-radius:10px;">
          <span class="muted" style="font-size:13px;">Esama nuotrauka</span>
        </div>
      <?php endif; ?>
      <label style="display:flex;flex-direction:column;gap:6px;">
        <span>Nauja nuotrauka</span>
        <input type="file" name="image" accept="image/*">
      </label>
      <div style="display:flex;gap:10px;align-items:center;">
        <button class="btn" style="background:#0b0b0b;color:#fff;border-color:#0b0b0b;">Išsaugoti</button>
        <a class="btn btn-secondary" href="/community_market.php">Atgal</a>
      </div>
    </form>
  </section>
</main>
  <?php renderFooter($pdo); ?>
</body>
</html>
