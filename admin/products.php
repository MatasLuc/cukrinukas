<?php
// admin/products.php

// Užtikriname parent_id
try { $pdo->query("SELECT parent_id FROM categories LIMIT 1"); } 
catch (Exception $e) { $pdo->exec("ALTER TABLE categories ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER id"); }
$pdo->exec("CREATE TABLE IF NOT EXISTS product_category_relations (product_id INT NOT NULL, category_id INT NOT NULL, PRIMARY KEY (product_id, category_id))");

// Pagalbinė nuotraukoms
function handleNewProductUploads(PDO $pdo, int $productId, array $files): void {
    if (empty($files['name'][0]) || !function_exists('saveUploadedFile')) return;
    $allowedMimeMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    $count = count($files['name']); $hasPrimary = 0;
    for ($i=0; $i<$count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $f = ['name'=>$files['name'][$i],'type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]??0];
        $rel = saveUploadedFile($f, $allowedMimeMap, 'img_');
        if ($rel) {
            $isP = ($hasPrimary===0)?1:0;
            $pdo->prepare('INSERT INTO product_images (product_id,path,is_primary) VALUES (?,?,?)')->execute([$productId,$rel,$isP]);
            if ($isP) { $pdo->prepare('UPDATE products SET image_url=? WHERE id=?')->execute([$rel,$productId]); $hasPrimary=1; }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $action = $_POST['action'] ?? '';

    if ($action === 'new_product') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        $salePrice = isset($_POST['sale_price']) && $_POST['sale_price']!=='' ? (float)$_POST['sale_price'] : null;
        $cats = $_POST['categories'] ?? [];
        $mainCat = !empty($cats) ? (int)$cats[0] : null;

        if ($title && $price > 0) {
            $pdo->prepare("INSERT INTO products (title, subtitle, description, ribbon_text, price, sale_price, quantity, category_id, meta_tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$title, $_POST['subtitle']??'', $desc, $_POST['ribbon_text']??'', $price, $salePrice, $qty, $mainCat, $_POST['meta_tags']??'']);
            $newId = $pdo->lastInsertId();
            
            if ($cats) {
                $relS = $pdo->prepare('INSERT INTO product_category_relations (product_id, category_id) VALUES (?, ?)');
                foreach ($cats as $c) $relS->execute([$newId, (int)$c]);
            }
            if (isset($_FILES['images'])) handleNewProductUploads($pdo, $newId, $_FILES['images']);
            
            // Attributes
            $al = $_POST['attr_label']??[]; $av=$_POST['attr_value']??[];
            $ia = $pdo->prepare("INSERT INTO product_attributes (product_id, label, value) VALUES (?, ?, ?)");
            foreach($al as $k=>$v){ if(trim($v)||$av[$k]) $ia->execute([$newId, trim($v), trim($av[$k]??'')]); }

            // Vars
            $vn = $_POST['variation_name']??[]; $vp=$_POST['variation_price']??[];
            $iv = $pdo->prepare("INSERT INTO product_variations (product_id, name, price_delta) VALUES (?, ?, ?)");
            foreach($vn as $k=>$v){ if(trim($v)) $iv->execute([$newId, trim($v), (float)($vp[$k]??0)]); }

            // Relations
            $rp = $_POST['related_products']??[];
            $ir = $pdo->prepare("INSERT IGNORE INTO product_related (product_id, related_product_id) VALUES (?, ?)");
            foreach($rp as $r) $ir->execute([$newId, (int)$r]);

            echo "<script>window.location='/admin.php?view=products';</script>"; exit;
        }
    }
    if ($action === 'delete_product') {
        $id = (int)$_POST['id'];
        $tables = ['product_images','product_attributes','product_variations','product_related','product_category_relations','products'];
        foreach($tables as $t) $pdo->prepare("DELETE FROM $t WHERE ".($t=='products'?'id':'product_id')."=?")->execute([$id]);
    }
    if ($action === 'featured_remove') { $pdo->prepare("DELETE FROM homepage_featured WHERE product_id=?")->execute([(int)$_POST['remove_id']]); }
    if ($action === 'featured_add') {
        $fid = $pdo->query("SELECT id FROM products WHERE title='".trim($_POST['featured_query']??'')."'")->fetchColumn();
        if ($fid) $pdo->prepare("INSERT IGNORE INTO homepage_featured (product_id) VALUES (?)")->execute([$fid]);
    }
}

// Data fetching
$products = $pdo->query('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.created_at DESC')->fetchAll();
// Fetch Categories for Tree
$allCats = $pdo->query('SELECT * FROM categories ORDER BY parent_id ASC, name ASC')->fetchAll();
$catTree = [];
foreach ($allCats as $c) {
    if (empty($c['parent_id'])) { $catTree[$c['id']]['self']=$c; $catTree[$c['id']]['children']=[]; }
}
foreach ($allCats as $c) {
    if (!empty($c['parent_id']) && isset($catTree[$c['parent_id']])) { $catTree[$c['parent_id']]['children'][]=$c; }
}

$fIds = getFeaturedProductIds($pdo);
$fProds = [];
if ($fIds) {
    $in = implode(',', array_fill(0, count($fIds), '?'));
    $s = $pdo->prepare("SELECT id, title, price FROM products WHERE id IN ($in)");
    $s->execute($fIds);
    $rows = $s->fetchAll(); $m=[]; foreach($rows as $r)$m[$r['id']]=$r;
    foreach($fIds as $i) if(isset($m[$i])) $fProds[]=$m[$i];
}
?>

<style>
    .toolbar { background:#f8f9fa; padding:8px; border:1px solid #d7d7e2; border-bottom:none; border-radius:8px 8px 0 0; display:flex; gap:5px; flex-wrap:wrap; }
    .toolbar button { cursor:pointer; padding:6px 10px; font-weight:bold; border:1px solid #ccc; border-radius:6px; background:#fff; font-size:14px; }
    .rich-editor { min-height:160px; border:1px solid #d7d7e2; border-radius:0 0 12px 12px; padding:12px; background:#fff; margin-bottom:12px; line-height:1.6; }
    .rich-editor ul, .rich-editor ol { padding-left:20px; }
    .mini-editor { min-height:42px; max-height:100px; overflow-y:auto; border:1px solid #d7d7e2; border-radius:8px; padding:8px; background:#fff; font-size:14px; }
    
    .cat-grid { border: 1px solid #d7d7e2; padding: 12px; border-radius: 12px; background: #fff; max-height: 250px; overflow-y: auto; margin-bottom:12px; }
    .cat-group { margin-bottom:8px; border-bottom:1px solid #f0f0f0; padding-bottom:4px; }
    .cat-group:last-child { border-bottom:none; }
    .cat-parent { font-weight:bold; display:block; margin-bottom:4px; font-size:14px; }
    .cat-children { display:grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap:5px; padding-left:20px; }
    .cat-item { display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:normal; font-size:13px; }
    
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
    <input name="subtitle" placeholder="Paantraštė">
    
    <label>Aprašymas</label>
    <div class="toolbar">
        <button type="button" onmousedown="event.preventDefault()" onclick="format('bold')">B</button>
        <button type="button" onmousedown="event.preventDefault()" onclick="format('italic')">I</button>
        <button type="button" onmousedown="event.preventDefault()" onclick="format('insertUnorderedList')">• Sąrašas</button>
        <button type="button" onmousedown="event.preventDefault()" onclick="createLink()">🔗</button>
    </div>
    <div id="new-desc-editor" class="rich-editor" contenteditable="true"></div>
    <textarea name="description" id="new-desc-textarea" hidden></textarea>

    <input name="ribbon_text" placeholder="Juostelė (pvz.: Naujiena)">
    
    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px;">
        <input name="price" type="number" step="0.01" placeholder="Kaina" required>
        <input name="sale_price" type="number" step="0.01" placeholder="Akcija">
        <input name="quantity" type="number" min="0" placeholder="Kiekis" required>
    </div>

    <label>Kategorijos (Tėvinės > Subkategorijos)</label>
    <div class="cat-grid">
      <?php foreach ($catTree as $branch): ?>
        <div class="cat-group">
            <label class="cat-parent cat-item">
                <input type="checkbox" name="categories[]" value="<?php echo (int)$branch['self']['id']; ?>">
                <?php echo htmlspecialchars($branch['self']['name']); ?>
            </label>
            <?php if(!empty($branch['children'])): ?>
            <div class="cat-children">
                <?php foreach ($branch['children'] as $child): ?>
                    <label class="cat-item">
                        <input type="checkbox" name="categories[]" value="<?php echo (int)$child['id']; ?>">
                        <?php echo htmlspecialchars($child['name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <label>SEO žymės</label>
    <input name="meta_tags" placeholder="Raktiniai žodžiai">
    
    <label>Susijusios prekės</label>
    <select name="related_products[]" multiple size="4" style="width:100%; border:1px solid #d7d7e2; border-radius:8px;">
      <?php foreach ($products as $p): ?>
        <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?></option>
      <?php endforeach; ?>
    </select>
    
    <div class="card" style="margin-top:10px; padding:15px; border:1px solid #eee;">
      <h4>Papildomi laukeliai</h4>
      <div id="attrs-create-container">
          <div class="attr-row" style="display:grid; grid-template-columns:1fr 2fr; gap:8px; margin-bottom:8px;">
            <input name="attr_label[]" placeholder="Pavadinimas">
            <div class="mini-editor" contenteditable="true" data-target="new-attr-0"></div>
            <input type="hidden" name="attr_value[]" id="new-attr-0">
          </div>
      </div>
      <button type="button" class="btn" onclick="addNewAttrRow()" style="background:#fff; border-color:#d7d7e2; color:#000;">+ Pridėti</button>
    </div>

    <div class="card" style="margin-top:10px; padding:15px; border:1px solid #eee;">
      <h4>Variacijos</h4>
      <div id="vars-create">
        <div class="input-row">
            <input class="chip-input" name="variation_name[]" placeholder="Pavadinimas">
            <input class="chip-input" name="variation_price[]" type="number" step="0.01" placeholder="Kainos pokytis">
        </div>
      </div>
      <button type="button" class="btn" onclick="addVarRow('vars-create')" style="background:#fff; border-color:#d7d7e2; color:#000;">+ Pridėti</button>
    </div>

    <label style="margin-top:10px;">Nuotraukos</label>
    <input type="file" name="images[]" multiple accept="image/*">
    <button class="btn" type="submit" style="margin-top:15px; width:100%;">Sukurti prekę</button>
  </form>
</div>

<div class="card" style="margin-top:18px;">
  <h3>Prekių sąrašas</h3>
  <table style="width:100%; border-collapse:collapse;">
    <thead><tr style="border-bottom:2px solid #eee;"><th style="text-align:left; padding:8px;">Pavadinimas</th><th>Kateg.</th><th>Kaina</th><th>Vnt.</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($products as $p): ?>
        <tr style="border-bottom:1px solid #f0f0f0;">
          <td style="padding:8px;"><?php echo htmlspecialchars($p['title']); ?></td>
          <td style="color:#666;"><?php echo htmlspecialchars($p['category_name']??'-'); ?></td>
          <td><?php echo number_format($p['sale_price']?:$p['price'], 2); ?> €</td>
          <td><?php echo (int)$p['quantity']; ?></td>
          <td style="text-align:right;">
            <a class="btn" href="/product_edit.php?id=<?php echo $p['id']; ?>" style="font-size:12px; padding:4px 8px;">Redaguoti</a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Trinti?');">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                <button class="btn" style="background:#fff; color:red; border-color:red; font-size:12px; padding:4px 8px;">Trinti</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  
  <div style="margin-top:20px; border-top:1px solid #eee; padding-top:15px;">
    <h4>Pagrindinio puslapio prekės</h4>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <?php foreach ($fProds as $fp): ?>
        <div style="border:1px solid #eee; padding:5px 10px; border-radius:8px; background:#f9f9ff; display:flex; gap:8px; align-items:center;">
          <span><?php echo htmlspecialchars($fp['title']); ?></span>
          <form method="post" style="margin:0;">
            <?php echo csrfField(); ?><input type="hidden" name="action" value="featured_remove"><input type="hidden" name="remove_id" value="<?php echo $fp['id']; ?>">
            <button class="btn" style="padding:2px 6px; font-size:10px; background:#fff; color:#000;">✕</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if(count($fProds)<3): ?>
      <form method="post" style="margin-top:10px; display:flex; gap:5px;">
        <?php echo csrfField(); ?><input type="hidden" name="action" value="featured_add">
        <input name="featured_query" list="pl" placeholder="Prekės pavadinimas" style="flex:1;">
        <datalist id="pl"><?php foreach($products as $p) echo "<option value='".htmlspecialchars($p['title'])."'>"; ?></datalist>
        <button class="btn">Pridėti</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
function format(c,v=null){document.execCommand(c,false,v);}
function createLink(){const u=prompt('URL:');if(u)format('createLink',u);}
function syncNewProductForm(){
    document.getElementById('new-desc-textarea').value=document.getElementById('new-desc-editor').innerHTML;
    document.querySelectorAll('#attrs-create-container .mini-editor').forEach(e=>{
        const t=e.getAttribute('data-target'); if(t)document.getElementById(t).value=e.innerHTML;
    });
    return true;
}
function addNewAttrRow(){
    const c=document.getElementById('attrs-create-container'), id='new-attr-'+Date.now(), d=document.createElement('div');
    d.style.cssText="display:grid; grid-template-columns:1fr 2fr; gap:8px; margin-bottom:10px;";
    d.innerHTML=`<input name="attr_label[]" placeholder="Pavadinimas"><div class="mini-editor" contenteditable="true" data-target="${id}"></div><input type="hidden" name="attr_value[]" id="${id}">`;
    c.appendChild(d);
}
function addVarRow(id){
    const c=document.getElementById(id), d=document.createElement('div'); d.className='input-row';
    d.innerHTML=`<input class="chip-input" name="variation_name[]" placeholder="Pavadinimas"><input class="chip-input" name="variation_price[]" type="number" step="0.01" placeholder="Kainos pokytis">`;
    c.appendChild(d);
}
</script>
