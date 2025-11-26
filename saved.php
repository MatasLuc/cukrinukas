<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$pdo = getPdo();
ensureUsersTable($pdo);
ensureProductsTable($pdo);
ensureNewsTable($pdo);
ensureRecipesTable($pdo);
ensureSavedContentTables($pdo);
ensureCartTables($pdo);
ensureOrdersTables($pdo);
ensureNavigationTable($pdo);

$userId = (int)$_SESSION['user_id'];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $removeType = $_POST['remove_type'] ?? '';
    $removeId = (int)($_POST['remove_id'] ?? 0);
    if ($removeType && $removeId) {
        removeSavedItem($pdo, $userId, $removeType, $removeId);
        $messages[] = 'Įrašas pašalintas iš išsaugotų.';
    }
}

$saved = getSavedItems($pdo, $userId);
$productIds = array_map('intval', array_column(array_filter($saved, fn($i) => $i['item_type'] === 'product'), 'item_id'));
$newsIds = array_map('intval', array_column(array_filter($saved, fn($i) => $i['item_type'] === 'news'), 'item_id'));
$recipeIds = array_map('intval', array_column(array_filter($saved, fn($i) => $i['item_type'] === 'recipe'), 'item_id'));

$products = [];
if ($productIds) {
    $in = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare("SELECT id, title, subtitle, image_url, price, sale_price FROM products WHERE id IN ($in)");
    $stmt->execute($productIds);
    foreach ($stmt->fetchAll() as $row) {
        $products[(int)$row['id']] = $row;
    }
}
$news = [];
if ($newsIds) {
    $in = implode(',', array_fill(0, count($newsIds), '?'));
    $stmt = $pdo->prepare("SELECT id, title, image_url FROM news WHERE id IN ($in)");
    $stmt->execute($newsIds);
    foreach ($stmt->fetchAll() as $row) {
        $news[(int)$row['id']] = $row;
    }
}
$recipes = [];
if ($recipeIds) {
    $in = implode(',', array_fill(0, count($recipeIds), '?'));
    $stmt = $pdo->prepare("SELECT id, title, image_url FROM recipes WHERE id IN ($in)");
    $stmt->execute($recipeIds);
    foreach ($stmt->fetchAll() as $row) {
        $recipes[(int)$row['id']] = $row;
    }
}

function priceDisplay(array $row): string {
    $base = (float)$row['price'];
    $sale = $row['sale_price'] !== null ? (float)$row['sale_price'] : null;
    if ($sale !== null && $sale >= 0) {
        return '<span style="color:#0b1224; font-weight:800;">' . number_format($sale, 2) . " €</span> <span style=\"text-decoration:line-through; color:#6b6b7a;\">" . number_format($base, 2) . ' €</span>';
    }
    return '<span style="color:#0b1224; font-weight:800;">' . number_format($base, 2) . ' €</span>';
}
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mano išsaugoti | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text: #0f172a;
      --muted: #52606d;
      --accent: #4338ca;
    }
    body { margin:0; background: var(--bg); color: var(--text); font-family:'Inter', system-ui, -apple-system, sans-serif; }
    a { color:inherit; text-decoration:none; }
    * { box-sizing:border-box; }
    .page { max-width:1100px; margin:0 auto; padding:32px 20px 60px; display:flex; flex-direction:column; gap:18px; }

    .hero { background: linear-gradient(135deg, #eef2ff, #e0f2fe); border:1px solid #e5e7eb; border-radius:28px; padding:22px 20px; box-shadow:0 24px 60px rgba(0,0,0,0.08); display:flex; justify-content:space-between; gap:18px; flex-wrap:wrap; align-items:center; }
    .hero h1 { margin:0; font-size:clamp(26px, 5vw, 34px); letter-spacing:-0.02em; color:#0b1224; }
    .hero p { margin:6px 0 0; color: var(--muted); line-height:1.6; max-width:520px; }
    .pill { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; background:#fff; border:1px solid #e4e7ec; font-weight:700; color:#0b1224; box-shadow:0 12px 26px rgba(0,0,0,0.08); }
    .btn { padding:10px 14px; border-radius:12px; border:1px solid transparent; background: linear-gradient(135deg, #4338ca, #7c3aed); color:#fff; font-weight:700; cursor:pointer; text-decoration:none; box-shadow:0 14px 36px rgba(124,58,237,0.25); transition: transform .18s ease, box-shadow .18s ease; }
    .btn:hover { transform: translateY(-1px); box-shadow:0 18px 52px rgba(67,56,202,0.35); }
    .ghost { background:transparent; color:#4338ca; border:1px solid #c7d2fe; box-shadow:none; }

    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap:16px; }
    .card { background:var(--card); border:1px solid var(--border); border-radius:18px; padding:16px; box-shadow:0 14px 32px rgba(0,0,0,0.06); display:flex; flex-direction:column; gap:10px; }
    .card img { width:100%; height:170px; object-fit:cover; border-radius:14px; border:1px solid #e5e7eb; }
    .type { font-size:13px; color:#6b6b7a; text-transform:uppercase; letter-spacing:0.4px; }
    .actions { display:flex; justify-content:space-between; align-items:center; gap:10px; }
    .muted { color: var(--muted); }
    form { margin:0; }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'saved'); ?>
  <div class="page">
    <section class="hero">
      <div>
        <div class="pill">⭐ Išsaugoti įrašai</div>
        <h1>Mano išsaugoti</h1>
        <p>Greita prieiga prie mėgstamų produktų, receptų ir naujienų – visa tai ant švaraus #f7f7fb fono.</p>
      </div>
      <a class="btn ghost" href="/products.php">Grįžti apsipirkti</a>
    </section>

    <?php foreach ($messages as $msg): ?>
      <div style="background:#edf9f0; border:1px solid #b8e2c4; padding:12px; border-radius:12px; color:#0f5132;">&check; <?php echo htmlspecialchars($msg); ?></div>
    <?php endforeach; ?>

    <div class="grid">
      <?php foreach ($saved as $item): ?>
        <?php
          $type = $item['item_type'];
          $ref = null;
          $link = '#';
          $priceHtml = '';
          $typeLabelMap = [
            'news' => 'Naujiena',
            'recipe' => 'Receptas',
            'product' => 'Produktas'
          ];
          $typeLabel = $typeLabelMap[$type] ?? $type;
          if ($type === 'product' && isset($products[$item['item_id']])) {
              $ref = $products[$item['item_id']];
              $link = '/product.php?id=' . (int)$ref['id'];
              $priceHtml = priceDisplay($ref);
          } elseif ($type === 'news' && isset($news[$item['item_id']])) {
              $ref = $news[$item['item_id']];
              $link = '/news_view.php?id=' . (int)$ref['id'];
          } elseif ($type === 'recipe' && isset($recipes[$item['item_id']])) {
              $ref = $recipes[$item['item_id']];
              $link = '/recipe_view.php?id=' . (int)$ref['id'];
          }
          if (!$ref) { continue; }
        ?>
        <div class="card">
          <div class="type"><?php echo htmlspecialchars($typeLabel); ?></div>
          <?php if (!empty($ref['image_url'])): ?><img src="<?php echo htmlspecialchars($ref['image_url']); ?>" alt=""><?php endif; ?>
          <div style="font-weight:800; font-size:18px; color:#0b1224; line-height:1.3;"><?php echo htmlspecialchars($ref['title']); ?></div>
          <?php if ($type === 'product'): ?>
            <div><?php echo $priceHtml; ?></div>
          <?php endif; ?>
          <div class="actions">
            <a class="btn ghost" href="<?php echo htmlspecialchars($link); ?>">Peržiūrėti</a>
            <form method="post">
              <?php echo csrfField(); ?>
              <input type="hidden" name="remove_type" value="<?php echo htmlspecialchars($type); ?>">
              <input type="hidden" name="remove_id" value="<?php echo (int)$item['item_id']; ?>">
              <button class="btn" type="submit" style="background:#fff; color:#4338ca; border:1px solid #c7d2fe; box-shadow:none;">Šalinti</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!$saved): ?>
      <div class="card" style="align-items:flex-start;">
        <div style="font-weight:700; font-size:18px; margin-bottom:6px;">Kol kas nieko neišsaugojote</div>
        <p class="muted" style="margin:0 0 12px;">Atraskite jums patinkančius produktus, receptus ar naujienas ir pažymėkite juos vėlesniam peržiūrėjimui.</p>
        <a class="btn ghost" href="/products.php">Pradėti naršyti</a>
      </div>
    <?php endif; ?>
  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
