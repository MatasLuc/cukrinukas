<?php
// admin/products.php

// Užtikriname, kad egzistuoja ryšių lentelė kategorijoms
$pdo->exec("CREATE TABLE IF NOT EXISTS product_category_relations (
    product_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (product_id, category_id)
)");

// Pagalbinė funkcija nuotraukų įkėlimui (analogiška kaip product_edit.php)
function handleNewProductUploads(PDO $pdo, int $productId, array $files): void {
    if (empty($files['name'][0])) {
        return;
    }
    // Tikriname, ar helperis pasiekiamas, jei ne - naudojame supaprastintą logiką
    if (!function_exists('saveUploadedFile')) {
        return; 
    }

    $allowedMimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    $count = count($files['name']);
    $hasPrimary = 0; // Naujai prekei dar nėra pagrindinės

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

        // Pirma įkelta nuotrauka tampa pagrindine
        $isPrimary = ($hasPrimary === 0) ? 1 : 0;
        
        $stmt = $pdo->prepare('INSERT INTO product_images (product_id, path, is_primary) VALUES (?, ?, ?)');
        $stmt->execute([$productId, $relativePath, $isPrimary]);
        
        if ($isPrimary) {
            $pdo->prepare('UPDATE products SET image_url = ? WHERE id = ?')->execute([$relativePath, $productId]);
            $hasPrimary = 1;
        }
    }
}

// --- VEIKSMŲ APDOROJIMAS (POST) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $action = $_POST['action'] ?? '';

    // 1. NAUJOS PREKĖS KŪRIMAS
    if ($action === 'new_product') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? ''); // Čia bus HTML iš redaktoriaus
        $price = (float)($_POST['price'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        $subtitle = trim($_POST['subtitle'] ?? '');
        $ribbon = trim($_POST['ribbon_text'] ?? '');
        $salePrice = isset($_POST['sale_price']) && $_POST['sale_price'] !== '' ? (float)$_POST['sale_price'] : null;
        $metaTags = trim($_POST['meta_tags'] ?? '');
        
        // Kategorijos
        $selectedCats = $_POST['categories'] ?? [];
        // Pirmą pasirinktą kategoriją išsaugome kaip pagrindinę (suderinamumui)
        $mainCat = !empty($selectedCats) ? (int)$selectedCats[0] : null;

        if ($title && $price > 0) {
            // Įrašome į products lentelę
            $stmt = $pdo->prepare("INSERT INTO products (title, subtitle, description, ribbon_text, price, sale_price, quantity, category_id, meta_tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title, 
                $subtitle ?: null, 
                $desc, 
                $ribbon ?: null, 
                $price, 
                $salePrice, 
                $qty, 
                $mainCat, 
                $metaTags ?: null
            ]);
            
            $newId = $pdo->lastInsertId();
            
            // Įrašome visas kategorijas į ryšių lentelę
            if (!empty($selectedCats)) {
                $relStmt = $pdo->prepare('INSERT INTO product_category_relations (product_id, category_id) VALUES (?, ?)');
                foreach ($selectedCats as $cid) {
                    $relStmt->execute([$newId, (int)$cid]);
                }
            }
            
            // Nuotraukos
            if (isset($_FILES['images'])) {
                handleNewProductUploads($pdo, $newId, $_FILES['images']);
            }
            
            // Papildomi laukai (Atributai)
            $attrLabels = $_POST['attr_label'] ?? [];
            $attrValues = $_POST['attr_value'] ?? []; // HTML reikšmės
            $insAttr = $pdo->prepare("INSERT INTO product_attributes (product_id, label, value) VALUES (?, ?, ?)");
            foreach ($attrLabels as $k => $lab) {
                $lab = trim($lab);
                $val = trim($attrValues[$k] ?? '');
                if ($lab || $val) {
                    $insAttr->execute([$newId, $lab, $val]);
                }
            }
            
            // Variacijos
            $varNames = $_POST['variation_name'] ?? [];
            $varPrices = $_POST['variation_price'] ?? [];
            $insVar = $pdo->prepare("INSERT INTO product_variations (product_id, name, price_delta) VALUES (?, ?, ?)");
            foreach ($varNames as $k => $vn) {
                $vn = trim($vn);
                if ($vn) {
                    $insVar->execute([$newId, $vn, (float)($varPrices[$k] ?? 0)]);
                }
            }

            // Susijusios prekės
            $rels = $_POST['related_products'] ?? [];
            if (!empty($rels)) {
                $insRel = $pdo->prepare("INSERT IGNORE INTO product_related (product_id, related_product_id) VALUES (?, ?)");
                foreach ($rels as $r) {
                    $insRel->execute([$newId, (int)$r]);
                }
            }

            // Perkrovimas, kad išsivalytų forma
            echo "<script>window.location='/admin.php?view=products';</script>";
            exit;
        }
    }

    // 2. PREKĖS IŠTRYNIMAS
    if ($action === 'delete_product') {
        $id = (int)$_POST['id'];
        // Ištriname susijusius įrašus (DB turėtų turėti ON DELETE CASCADE, bet dėl saugumo..)
        $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM product_attributes WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM product_variations WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM product_related WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM product_category_relations WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    }

    // 3. FEATURED VALDYMAS
    if ($action === 'featured_remove') {
        $remId = (int)$_POST['remove_id'];
        $pdo->prepare("DELETE FROM homepage_featured WHERE product_id = ?")->execute([$remId]);
    }
    
    if ($action === 'featured_add') {
        $query = trim($_POST['featured_query'] ?? '');
        $stmt = $pdo->prepare("SELECT id FROM products WHERE title = ?");
        $stmt->execute([$query]);
        $foundId = $stmt->fetchColumn();
        if ($foundId) {
            $pdo->prepare("INSERT IGNORE INTO homepage_featured (product_id) VALUES (?)")->execute([$foundId]);
        }
    }
}

// --- DUOMENŲ PARUOŠIMAS ---

// Prekių sąrašas
$products = $pdo->query('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.created_at DESC')->fetchAll();

// Kategorijos
$categoryCounts = $pdo->query('SELECT c.* FROM categories c ORDER BY c.name')->fetchAll();

// Featured prekės
$featuredIds = getFeaturedProductIds($pdo);
$featuredProducts = [];
if ($featuredIds) {
    $placeholders = implode(',', array_fill(0, count($featuredIds), '?'));
    $stmt = $pdo->prepare("SELECT id, title, price FROM products WHERE id IN ($placeholders)");
    $stmt->execute($featuredIds);
    $rows = $stmt->fetchAll();
    $map = [];
    foreach ($rows as $row) { $map[$row['id']] = $row; }
    foreach ($featuredIds as $fid) {
        if (!empty($map[$fid])) { $featuredProducts[] = $map[$fid]; }
    }
}
?>

<style>
    /* Stiliai redaktoriui ir formai */
    .toolbar { background:#f8f9fa; padding:8px; border:1px solid #d7d7e2; border-bottom:none; border-radius:8px 8px 0 0; display:flex; gap:5px; flex-wrap:wrap; }
    .toolbar button { cursor:pointer; padding:6px 10px; font-weight:bold; border:1px solid #ccc; border-radius:6px; background:#fff; font-size:14px; }
    .toolbar button:hover { background:#eee; }
    
    .rich-editor { min-height:160px; border:1px solid #d7d7e2; border-radius:0 0 12px 12px; padding:12px; background:#fff; margin-bottom:12px; line-height:1.6; }
    .rich-editor:focus { outline:2px solid #0b0b0b; border-color:transparent; }
    .rich-editor ul, .rich-editor ol { padding-left:20px; }
    
    .mini-editor { min-height:42px; max-height:100px; overflow-y:auto; border:1px solid #d7d7e2; border-radius:8px; padding:8px; background:#fff; font-size:14px; }
    .mini-editor:focus { outline:2px solid #0b0b0b; border-color:transparent; }

    .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; border: 1px solid #d7d7e2; padding: 12px; border-radius: 12px; background: #fff; max-height: 200px; overflow-y: auto; margin-bottom:12px; }
    .cat-item { display:flex; align-items:center; gap:8px; font-size:14px; cursor:pointer; margin:0 !important; padding:4px; border-radius:4px; }
    .cat-item:hover { background:#f4f4f4; }
    
    .chip-input { width:100%; box-sizing:border-box; }
    .input-row { display:flex; gap:10px; margin-bottom:8px; }
</style>

<div class="card">
  <h3>Nauja prekė</h3>
  <form method="post" enctype="multipart/form-data" onsubmit="return syncNewProductForm()">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="new_product">
    
    <label>Pavadinimas</label>
    <input name="title" placeholder="Pavadinimas" required>
    
    <label>Paantraštė</label>
    <input name="subtitle" placeholder="Paantraštė (pvz.: Ekologiškas)">
    
    <label>Aprašymas</label>
    <div class="toolbar">
        <button type="button" onmousedown="event.preventDefault()" onclick="format('bold')" title="Paryškinti"><b>B</b></button>
        <button type="button" onmousedown="event.preventDefault()" onclick="format('italic')" title="Pasviręs"><em>I</em></button>
        <button type="button" onmousedown="event.preventDefault()" onclick="format('underline')" title="Pabrauktas"><u>U</u></button>
        <span style="border-left:1px solid #ccc; margin:0 4px;"></span>
        <button type="button" onmousedown="event.preventDefault()" onclick="format('insertUnorderedList')" title="Sąrašas">• Sąrašas</button>
        <button type="button" onmousedown="event.preventDefault()" onclick="createLink()" title="Nuoroda">🔗</button>
        <button type="button" onmousedown="event.preventDefault()" onclick="format('removeFormat')" title="Išvalyti formatavimą">✕</button>
    </div>
    <div id="new-desc-editor" class="rich-editor" contenteditable="true"></div>
    <textarea name="description" id="new-desc-textarea" hidden></textarea>

    <label>Juostelė ant nuotraukos</label>
    <input name="ribbon_text" placeholder="Pvz.: Naujiena, -20%">
    
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:12px;">
        <div>
            <label>Kaina (€)</label>
            <input name="price" type="number" step="0.01" placeholder="0.00" required>
        </div>
        <div>
            <label>Akcijinė kaina (€)</label>
            <input name="sale_price" type="number" step="0.01" placeholder="Nėra">
        </div>
        <div>
            <label>Kiekis (vnt.)</label>
            <input name="quantity" type="number" min="0" placeholder="0" required>
        </div>
    </div>

    <label>Kategorijos (galima žymėti kelias)</label>
    <div class="cat-grid">
      <?php foreach ($categoryCounts as $cat): ?>
        <label class="cat-item">
            <input type="checkbox" name="categories[]" value="<?php echo (int)$cat['id']; ?>">
            <?php echo htmlspecialchars($cat['name']); ?>
        </label>
      <?php endforeach; ?>
    </div>

    <label>SEO žymės (Meta tags)</label>
    <input name="meta_tags" placeholder="Raktiniai žodžiai, atskirti kableliais">
    
    <label>Susijusios prekės</label>
    <select name="related_products[]" multiple size="4" style="width:100%; border:1px solid #d7d7e2; border-radius:8px; padding:5px;">
      <?php foreach ($products as $product): ?>
        <option value="<?php echo (int)$product['id']; ?>"><?php echo htmlspecialchars($product['title']); ?></option>
      <?php endforeach; ?>
    </select>
    
    <div class="card" style="margin-top:16px; padding:16px; border:1px solid #eee; background:#fbfbff;">
      <h4 style="margin-top:0;">Papildomi laukeliai (Savybės)</h4>
      <p style="font-size:12px; color:#666; margin-bottom:10px;">Tekstą galite formatuoti naudodami viršutinę įrankių juostą (pažymėkite tekstą ir spauskite mygtuką).</p>
      
      <div id="attrs-create-container">
          <div class="attr-row" style="display:grid; grid-template-columns:1fr 2fr; gap:8px; margin-bottom:10px;">
            <input name="attr_label[]" placeholder="Pavadinimas (pvz. Sudėtis)">
            <div class="mini-editor" contenteditable="true" data-target="new-attr-0"></div>
            <input type="hidden" name="attr_value[]" id="new-attr-0">
          </div>
      </div>
      <button type="button" class="btn" style="background:#fff; color:#0b0b0b; border-color:#d7d7e2; font-size:13px;" onclick="addNewAttrRow()">+ Pridėti laukelį</button>
    </div>

    <div class="card" style="margin-top:10px; padding:16px; border:1px solid #eee; background:#fbfbff;">
      <h4 style="margin-top:0;">Variacijos</h4>
      <div id="vars-create">
        <div class="input-row">
            <input class="chip-input" name="variation_name[]" placeholder="Pavadinimas (pvz. Didelis)" style="flex:2;">
            <input class="chip-input" name="variation_price[]" type="number" step="0.01" placeholder="Kainos pokytis (+/-)" style="flex:1;">
        </div>
      </div>
      <button type="button" class="btn" style="margin-top:5px; background:#fff; color:#0b0b0b; border-color:#d7d7e2; font-size:13px;" onclick="addVarRow('vars-create')">+ Pridėti variaciją</button>
    </div>

    <label style="margin-top:16px;">Nuotraukos</label>
    <input type="file" name="images[]" multiple accept="image/*">
    
    <button class="btn" type="submit" style="margin-top:20px; width:100%; background:#0b0b0b; color:#fff;">Sukurti prekę</button>
  </form>
</div>

<div class="card" style="margin-top:24px;">
  <h3>Prekių sąrašas</h3>
  <table style="width:100%; border-collapse:collapse;">
    <thead>
        <tr style="text-align:left; border-bottom:2px solid #eee;">
            <th style="padding:10px;">Pavadinimas</th>
            <th style="padding:10px;">Kategorija</th>
            <th style="padding:10px;">Kaina</th>
            <th style="padding:10px;">Kiekis</th>
            <th style="padding:10px;">Veiksmai</th>
        </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $product): ?>
        <tr style="border-bottom:1px solid #f0f0f0;">
          <td style="padding:10px;">
              <strong><?php echo htmlspecialchars($product['title']); ?></strong>
              <?php if($product['sale_price']): ?>
                <span style="display:inline-block; background:#ffebeb; color:#c0392b; font-size:10px; padding:2px 5px; border-radius:4px;">Akcija</span>
              <?php endif; ?>
          </td>
          <td style="padding:10px; color:#666;"><?php echo htmlspecialchars($product['category_name'] ?? '-'); ?></td>
          <td style="padding:10px;">
              <?php if($product['sale_price']): ?>
                  <s style="color:#999;"><?php echo number_format((float)$product['price'], 2); ?></s> 
                  <strong><?php echo number_format((float)$product['sale_price'], 2); ?> €</strong>
              <?php else: ?>
                  <?php echo number_format((float)$product['price'], 2); ?> €
              <?php endif; ?>
          </td>
          <td style="padding:10px;"><?php echo (int)$product['quantity']; ?> vnt</td>
          <td style="padding:10px; display:flex; gap:6px;">
            <a class="btn" href="/product_edit.php?id=<?php echo (int)$product['id']; ?>" style="font-size:12px; padding:6px 10px;">Redaguoti</a>
            <form method="post" style="margin:0;" onsubmit="return confirm('Ar tikrai norite ištrinti šią prekę?');">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
                <button class="btn" type="submit" style="background:#fff; color:#e74c3c; border-color:#e74c3c; font-size:12px; padding:6px 10px;">Trinti</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div style="margin-top:24px; border-top:1px solid #eee; padding-top:16px;">
    <h4>Rodomos pagrindiniame puslapyje (maks. 3)</h4>
    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px;">
      <?php foreach ($featuredProducts as $fp): ?>
        <div style="border:1px solid #e6e6ef; border-radius:12px; padding:10px 12px; background:#f9f9ff; display:flex; align-items:center; gap:10px;">
          <div>
            <strong><?php echo htmlspecialchars($fp['title']); ?></strong><br>
            <span class="muted"><?php echo number_format((float)$fp['price'], 2); ?> €</span>
          </div>
          <form method="post" style="margin:0;">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="featured_remove">
            <input type="hidden" name="remove_id" value="<?php echo (int)$fp['id']; ?>">
            <button class="btn" type="submit" style="background:#fff; color:#0b0b0b; font-size:11px; padding:4px 8px;">✕</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    
    <?php if (count($featuredProducts) < 3): ?>
      <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="featured_add">
        <input name="featured_query" list="products-list" placeholder="Įveskite prekės pavadinimą" style="flex:1; min-width:240px; margin:0;">
        <datalist id="products-list">
          <?php foreach ($products as $product): ?>
            <option value="<?php echo htmlspecialchars($product['title']); ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <button class="btn" type="submit">Pridėti</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
// --- REDAKTORIAUS FUNKCIJOS ---

function format(cmd, value = null) {
  document.execCommand(cmd, false, value);
}

function createLink() {
  const url = prompt('Įveskite nuorodą:');
  if (url) format('createLink', url);
}

// Sinchronizuoja div turinį su hidden inputais prieš submit
function syncNewProductForm() {
    // 1. Pagrindinis aprašymas
    document.getElementById('new-desc-textarea').value = document.getElementById('new-desc-editor').innerHTML;
    
    // 2. Atributai
    document.querySelectorAll('#attrs-create-container .mini-editor').forEach(ed => {
        const tid = ed.getAttribute('data-target');
        if(tid) {
            document.getElementById(tid).value = ed.innerHTML;
        }
    });
    return true;
}

// Prideda naują atributų eilutę
function addNewAttrRow() {
    const c = document.getElementById('attrs-create-container');
    const id = 'new-attr-' + Date.now();
    const div = document.createElement('div');
    div.className = 'attr-row';
    div.style.cssText = "display:grid; grid-template-columns:1fr 2fr; gap:8px; margin-bottom:10px;";
    div.innerHTML = `
        <input name="attr_label[]" placeholder="Pavadinimas">
        <div class="mini-editor" contenteditable="true" data-target="${id}"></div>
        <input type="hidden" name="attr_value[]" id="${id}">
    `;
    c.appendChild(div);
}

// Prideda naują variacijos eilutę
function addVarRow(containerId) {
    const c = document.getElementById(containerId);
    const div = document.createElement('div');
    div.className = 'input-row';
    div.innerHTML = `
        <input class="chip-input" name="variation_name[]" placeholder="Pavadinimas" style="flex:2;">
        <input class="chip-input" name="variation_price[]" type="number" step="0.01" placeholder="Kainos pokytis" style="flex:1;">
    `;
    c.appendChild(div);
}
</script>
