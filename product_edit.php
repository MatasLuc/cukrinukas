<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login.php');
    exit;
}

$pdo = getPdo();
ensureUsersTable($pdo);
ensureCategoriesTable($pdo);
ensureProductsTable($pdo);
ensureProductRelations($pdo);
ensureAdminAccount($pdo);

function setPrimaryImageForProduct(PDO $pdo, int $productId, int $imageId): void {
    $pdo->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = ?')->execute([$productId]);
    $pdo->prepare('UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?')->execute([$imageId, $productId]);
    $path = $pdo->prepare('SELECT path FROM product_images WHERE id = ? AND product_id = ?');
    $path->execute([$imageId, $productId]);
    $file = $path->fetchColumn();
    if ($file) {
        $pdo->prepare('UPDATE products SET image_url = ? WHERE id = ?')->execute([$file, $productId]);
    }
}

function deleteProductImageForProduct(PDO $pdo, int $productId, int $imageId): void {
    $stmt = $pdo->prepare('SELECT path, is_primary FROM product_images WHERE id = ? AND product_id = ?');
    $stmt->execute([$imageId, $productId]);
    $image = $stmt->fetch();
    if (!$image) {
        return;
    }
    $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imageId]);
    $filePath = __DIR__ . '/' . ltrim($image['path'], '/');
    if (is_file($filePath)) {
        @unlink($filePath);
    }
    if ((int)$image['is_primary'] === 1) {
        $fallback = $pdo->prepare('SELECT id, path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id DESC LIMIT 1');
        $fallback->execute([$productId]);
        $newMain = $fallback->fetch();
        if ($newMain) {
            $pdo->prepare('UPDATE product_images SET is_primary = 1 WHERE id = ?')->execute([$newMain['id']]);
            $pdo->prepare('UPDATE products SET image_url = ? WHERE id = ?')->execute([$newMain['path'], $productId]);
        } else {
            $pdo->prepare('UPDATE products SET image_url = ? WHERE id = ?')->execute(['https://placehold.co/600x400?text=Preke', $productId]);
        }
    }
}

function storeProductUploads(PDO $pdo, int $productId, array $files): void {
    if (empty($files['name'][0])) {
        return;
    }
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_primary = 1');
    $countStmt->execute([$productId]);
    $hasPrimary = (int)$countStmt->fetchColumn();

    $allowedMimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $file = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i] ?? 0,
        ];

        $relativePath = saveUploadedFile($file, $allowedMimeMap, 'img_');
        if (!$relativePath) {
            continue;
        }

        $isPrimary = ($hasPrimary === 0 && $i === 0) ? 1 : 0;
        $stmt = $pdo->prepare('INSERT INTO product_images (product_id, path, is_primary) VALUES (?, ?, ?)');
        $stmt->execute([$productId, $relativePath, $isPrimary]);
        if ($isPrimary) {
            $pdo->prepare('UPDATE products SET image_url = ? WHERE id = ?')->execute([$relativePath, $productId]);
            $hasPrimary = 1;
        }
    }
}

$productId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product) {
    http_response_code(404);
    echo 'Prekė nerasta';
    exit;
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $action = $_POST['action'] ?? '';
    if ($action === 'edit_product') {
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $ribbon = trim($_POST['ribbon_text'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $salePrice = isset($_POST['sale_price']) && $_POST['sale_price'] !== '' ? (float)$_POST['sale_price'] : null;
        $qty = (int)($_POST['quantity'] ?? 0);
        $catId = (int)($_POST['category_id'] ?? 0);
        $metaTags = trim($_POST['meta_tags'] ?? '');
        if ($title && $description) {
            $pdo->prepare('UPDATE products SET category_id = ?, title = ?, subtitle = ?, description = ?, ribbon_text = ?, price = ?, sale_price = ?, quantity = ?, meta_tags = ? WHERE id = ?')
                ->execute([$catId ?: null, $title, $subtitle ?: null, $description, $ribbon ?: null, $price, $salePrice, $qty, $metaTags ?: null, $productId]);
            storeProductUploads($pdo, $productId, $_FILES['images'] ?? []);

            // Related products
            $related = array_filter(array_map('intval', $_POST['related_products'] ?? []));
            $pdo->prepare('DELETE FROM product_related WHERE product_id = ?')->execute([$productId]);
            if ($related) {
                $insertRel = $pdo->prepare('INSERT IGNORE INTO product_related (product_id, related_product_id) VALUES (?, ?)');
                foreach ($related as $rel) {
                    if ($rel !== $productId) {
                        $insertRel->execute([$productId, $rel]);
                    }
                }
            }

            // Custom attributes
            $pdo->prepare('DELETE FROM product_attributes WHERE product_id = ?')->execute([$productId]);
            $attrNames = $_POST['attr_label'] ?? [];
            $attrValues = $_POST['attr_value'] ?? [];
            $insertAttr = $pdo->prepare('INSERT INTO product_attributes (product_id, label, value) VALUES (?, ?, ?)');
            foreach ($attrNames as $idx => $label) {
                $label = trim($label);
                $val = trim($attrValues[$idx] ?? '');
                if ($label && $val) {
                    $insertAttr->execute([$productId, $label, $val]);
                }
            }

            // Variations
            $pdo->prepare('DELETE FROM product_variations WHERE product_id = ?')->execute([$productId]);
            $varNames = $_POST['variation_name'] ?? [];
            $varPrices = $_POST['variation_price'] ?? [];
            $insertVar = $pdo->prepare('INSERT INTO product_variations (product_id, name, price_delta) VALUES (?, ?, ?)');
            foreach ($varNames as $idx => $vName) {
                $vName = trim($vName);
                $delta = isset($varPrices[$idx]) ? (float)$varPrices[$idx] : 0;
                if ($vName !== '') {
                    $insertVar->execute([$productId, $vName, $delta]);
                }
            }
            $messages[] = 'Prekė atnaujinta';
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
        } else {
            $errors[] = 'Užpildykite visus laukus';
        }
    }
    if ($action === 'set_primary_image') {
        $imageId = (int)($_POST['image_id'] ?? 0);
        setPrimaryImageForProduct($pdo, $productId, $imageId);
        $messages[] = 'Pagrindinė nuotrauka atnaujinta';
    }
    if ($action === 'delete_image') {
        $imageId = (int)($_POST['image_id'] ?? 0);
        deleteProductImageForProduct($pdo, $productId, $imageId);
        $messages[] = 'Nuotrauka pašalinta';
    }
}

$imageStmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id DESC');
$imageStmt->execute([$productId]);
$images = $imageStmt->fetchAll();
$relatedProducts = $pdo->prepare('SELECT related_product_id FROM product_related WHERE product_id = ?');
$relatedProducts->execute([$productId]);
$relatedIds = array_map('intval', $relatedProducts->fetchAll(PDO::FETCH_COLUMN));
$allProducts = $pdo->query('SELECT id, title FROM products ORDER BY title')->fetchAll();
$attributes = $pdo->prepare('SELECT * FROM product_attributes WHERE product_id = ?');
$attributes->execute([$productId]);
$attributes = $attributes->fetchAll();
$variations = $pdo->prepare('SELECT * FROM product_variations WHERE product_id = ?');
$variations->execute([$productId]);
$variations = $variations->fetchAll();
$categoryCounts = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Redaguoti prekę | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    :root { --color-bg: #f7f7fb; --color-primary: #0b0b0b; }
    * { box-sizing: border-box; }
    a { color:inherit; text-decoration:none; }
    .page { max-width:1200px; margin:0 auto; padding:24px; }
    .card { background:#fff; border-radius:16px; padding:20px; box-shadow:0 12px 24px rgba(0,0,0,0.08); }
    label { display:block; margin-bottom:8px; font-weight:600; }
    input, textarea, select { width:100%; padding:12px; border-radius:12px; border:1px solid #d7d7e2; margin-bottom:10px; }
    textarea { min-height:140px; }
    .btn { padding:10px 14px; border-radius:12px; border:1px solid #0b0b0b; background:#0b0b0b; color:#fff; font-weight:600; cursor:pointer; }
    .image-list { display:flex; gap:10px; flex-wrap:wrap; }
    .image-tile { border:1px solid #e6e6ef; border-radius:12px; padding:8px; width:160px; text-align:center; background:#f9f9ff; }
    .image-tile img { width:100%; height:110px; object-fit:cover; border-radius:10px; }

  </style>
</head>
<body>
  <?php renderHeader($pdo, 'admin'); ?>
  <div class="page">
    <a href="/admin.php?view=products" style="display:inline-block; margin-bottom:12px; font-weight:600;">↩ Atgal į prekių sąrašą</a>
    <div class="card">
      <h1 style="margin-top:0;">Redaguoti prekę</h1>
      <?php foreach ($messages as $msg): ?>
        <div style="background:#edf9f0; border:1px solid #b8e2c4; padding:12px; border-radius:12px; color:#0f5132; margin-bottom:10px;">&check; <?php echo htmlspecialchars($msg); ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $err): ?>
        <div style="background:#fff1f1; border:1px solid #f3b7b7; padding:12px; border-radius:12px; color:#991b1b; margin-bottom:10px;">&times; <?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>
      <form id="product-form" method="post" enctype="multipart/form-data" style="display:grid; grid-template-columns: 2fr 1fr; gap:16px; align-items:start;">
        <?php echo csrfField(); ?>
<input type="hidden" name="action" value="edit_product">
        <div>
          <label>Pavadinimas</label>
          <input name="title" value="<?php echo htmlspecialchars($product['title']); ?>" required>
          <label>Paantraštė</label>
          <input name="subtitle" value="<?php echo htmlspecialchars($product['subtitle'] ?? ''); ?>" placeholder="Trumpa papildoma eilutė">
          <label>Aprašymas</label>
          <textarea name="description" required><?php echo htmlspecialchars($product['description']); ?></textarea>
          <label>Juostelės tekstas (rodoma ant nuotraukos, jei įvesta)</label>
          <input name="ribbon_text" value="<?php echo htmlspecialchars($product['ribbon_text'] ?? ''); ?>" placeholder="Pvz.: Nauja, Populiaru">
          <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:10px;">
            <label>Kaina<input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" required></label>
            <label>Kaina su nuolaida<input type="number" step="0.01" name="sale_price" value="<?php echo htmlspecialchars($product['sale_price'] ?? ''); ?>" placeholder="Palikite tuščią jei nėra"></label>
            <label>Kiekis<input type="number" name="quantity" min="0" value="<?php echo (int)$product['quantity']; ?>" required></label>
          </div>
          <label>Kategorija</label>
          <select name="category_id">
            <option value="">Be kategorijos</option>
            <?php foreach ($categoryCounts as $cat): ?>
              <option value="<?php echo (int)$cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
          </select>
          <label>Pridėti nuotraukų</label>
          <input type="file" name="images[]" multiple accept="image/*">
          <label>SEO žymės</label>
          <textarea name="meta_tags" placeholder="raktiniai žodžiai, SEO aprašai" style="min-height:80px;"><?php echo htmlspecialchars($product['meta_tags'] ?? ''); ?></textarea>

          <h3>Susijusios prekės</h3>
          <select name="related_products[]" multiple size="6" style="width:100%; padding:10px; border-radius:12px; border:1px solid #d7d7e2;">
            <?php foreach ($allProducts as $p): ?>
              <?php if ((int)$p['id'] === (int)$productId) { continue; } ?>
              <option value="<?php echo (int)$p['id']; ?>" <?php echo in_array((int)$p['id'], $relatedIds, true) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['title']); ?></option>
            <?php endforeach; ?>
          </select>

          <h3>Papildomi laukeliai</h3>
          <div id="attributes" style="display:flex; flex-direction:column; gap:10px;">
            <?php if ($attributes): foreach ($attributes as $attr): ?>
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                <input name="attr_label[]" placeholder="Pavadinimas" value="<?php echo htmlspecialchars($attr['label']); ?>">
                <input name="attr_value[]" placeholder="Aprašymas" value="<?php echo htmlspecialchars($attr['value']); ?>">
              </div>
            <?php endforeach; endif; ?>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
              <input name="attr_label[]" placeholder="Pavadinimas">
              <input name="attr_value[]" placeholder="Aprašymas">
            </div>
          </div>

          <h3>Variacijos</h3>
          <div id="variations" style="display:flex; flex-direction:column; gap:10px;">
            <?php if ($variations): foreach ($variations as $var): ?>
              <div style="display:grid; grid-template-columns:2fr 1fr; gap:8px;">
                <input name="variation_name[]" placeholder="Variacijos pavadinimas" value="<?php echo htmlspecialchars($var['name']); ?>">
                <input name="variation_price[]" type="number" step="0.01" value="<?php echo htmlspecialchars($var['price_delta']); ?>" placeholder="Δ kaina">
              </div>
            <?php endforeach; endif; ?>
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:8px;">
              <input name="variation_name[]" placeholder="Variacijos pavadinimas">
              <input name="variation_price[]" type="number" step="0.01" placeholder="Δ kaina">
            </div>
          </div>
          <button class="btn" type="submit">Išsaugoti</button>
        </div>
      </form>
      <div style="margin-top:16px;">
        <h3>Nuotraukos</h3>
        <div class="image-list">
          <?php foreach ($images as $img): ?>
            <div class="image-tile">
              <img src="<?php echo htmlspecialchars($img['path']); ?>" alt="">
              <form method="post" style="margin-top:6px;">
                <?php echo csrfField(); ?>
<input type="hidden" name="action" value="set_primary_image">
                <input type="hidden" name="image_id" value="<?php echo (int)$img['id']; ?>">
                <button class="btn" type="submit" style="width:100%; <?php echo $img['is_primary'] ? '' : 'background:#fff; color:#0b0b0b;'; ?>">
                  <?php echo $img['is_primary'] ? 'Pagrindinė' : 'Padaryti pagr.'; ?>
                </button>
              </form>
              <form method="post" onsubmit="return confirm('Pašalinti nuotrauką?');">
                <?php echo csrfField(); ?>
<input type="hidden" name="action" value="delete_image">
                <input type="hidden" name="image_id" value="<?php echo (int)$img['id']; ?>">
                <button class="btn" type="submit" style="width:100%; background:#fff; color:#0b0b0b; margin-top:6px;">Trinti</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
