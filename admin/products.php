<?php
// admin/products.php

// 1. DB Migracijos ir saugikliai (paliekame originalią logiką)
try { 
    $pdo->query("SELECT parent_id FROM categories LIMIT 1"); 
} catch (Exception $e) { 
    $pdo->exec("ALTER TABLE categories ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER id"); 
}
$pdo->exec("CREATE TABLE IF NOT EXISTS product_category_relations (product_id INT NOT NULL, category_id INT NOT NULL, PRIMARY KEY (product_id, category_id))");

// 2. Pagalbinė funkcija nuotraukoms (Server-side)
function handleNewProductUploads(PDO $pdo, int $productId, array $files): void {
    if (empty($files['name'][0]) || !function_exists('saveUploadedFile')) return;
    $allowedMimeMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    $count = count($files['name']); 
    
    // Tikriname, ar prekė jau turi pagrindinę nuotrauką
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_primary = 1");
    $stmt->execute([$productId]);
    $hasPrimary = $stmt->fetchColumn() > 0 ? 1 : 0;

    for ($i=0; $i<$count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        
        $f = [
            'name'=>$files['name'][$i],
            'type'=>$files['type'][$i]??'',
            'tmp_name'=>$files['tmp_name'][$i],
            'error'=>$files['error'][$i],
            'size'=>$files['size'][$i]??0
        ];
        
        $rel = saveUploadedFile($f, $allowedMimeMap, 'img_');
        
        if ($rel) {
            $isP = ($hasPrimary === 0) ? 1 : 0;
            $pdo->prepare('INSERT INTO product_images (product_id,path,is_primary) VALUES (?,?,?)')->execute([$productId,$rel,$isP]);
            
            if ($isP) { 
                $pdo->prepare('UPDATE products SET image_url=? WHERE id=?')->execute([$rel,$productId]); 
                $hasPrimary = 1; 
            }
        }
    }
}

// 3. Duomenų paruošimas atvaizdavimui
$products = $pdo->query('
    SELECT p.*, c.name AS category_name,
           (SELECT path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p 
    LEFT JOIN categories c ON c.id = p.category_id 
    ORDER BY p.created_at DESC
')->fetchAll();

// Kategorijų medis
$allCats = $pdo->query('SELECT * FROM categories ORDER BY parent_id ASC, name ASC')->fetchAll();
$catTree = [];
foreach ($allCats as $c) {
    if (empty($c['parent_id'])) { $catTree[$c['id']]['self']=$c; $catTree[$c['id']]['children']=[]; }
}
foreach ($allCats as $c) {
    if (!empty($c['parent_id']) && isset($catTree[$c['parent_id']])) { $catTree[$c['parent_id']]['children'][]=$c; }
}

// "Featured" prekės
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
    /* Tabų stilius */
    .product-tabs { display: flex; gap: 2px; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px; }
    .tab-trigger {
        padding: 10px 20px; background: transparent; border: none; border-bottom: 2px solid transparent;
        font-weight: 600; color: #6b7280; cursor: pointer; transition: 0.2s; font-size: 14px; margin-bottom: -2px;
    }
    .tab-trigger:hover { color: #4f46e5; background: #f9fafb; }
    .tab-trigger.active { color: #4f46e5; border-bottom-color: #4f46e5; background: #fff; }
    
    .tab-pane { display: none; animation: fadeIn 0.3s ease; }
    .tab-pane.active { display: block; }

    /* Formos elementai */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .full-width { grid-column: span 2; }
    .input-group { margin-bottom: 15px; }
    .input-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 5px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; transition: 0.2s; }
    .form-control:focus { border-color: #4f46e5; outline: none; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
    
    /* Kategorijų medis */
    .cat-box { border: 1px solid #d1d5db; border-radius: 8px; padding: 12px; max-height: 200px; overflow-y: auto; background: #f9fafb; }
    .cat-item { display: block; margin-bottom: 4px; font-size: 13px; cursor: pointer; }
    .cat-child { margin-left: 20px; border-left: 2px solid #e5e7eb; padding-left: 8px; }

    /* Nuotraukų peržiūra */
    .img-upload-area { border: 2px dashed #d1d5db; padding: 20px; text-align: center; border-radius: 8px; cursor: pointer; transition: 0.2s; background: #f9fafb; }
    .img-upload-area:hover { border-color: #4f46e5; background: #eff6ff; }
    .preview-grid { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
    .preview-img { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #e5e7eb; }

    /* Lentelės patobulinimai */
    .product-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; background: #f3f4f6; }
    .stock-badge { padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; }
    .in-stock { background: #dcfce7; color: #166534; }
    .out-of-stock { background: #fee2e2; color: #991b1b; }
    
    .toolbar { background:#f8f9fa; padding:8px; border:1px solid #d1d5db; border-bottom:none; border-radius:6px 6px 0 0; display:flex; gap:5px; flex-wrap:wrap; }
    .toolbar button { cursor:pointer; padding:4px 8px; border:1px solid #ccc; border-radius:4px; background:#fff; font-size:12px; font-weight:600; }
    .rich-editor { min-height:120px; border:1px solid #d1d5db; border-radius:0 0 6px 6px; padding:12px; background:#fff; margin-bottom:12px; }

    @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .full-width { grid-column: span 1; } }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <h2>Prekių valdymas</h2>
        <p class="muted" style="margin:0;">Kurkite, redaguokite ir valdykite asortimentą.</p>
    </div>
    <button class="btn" onclick="document.getElementById('newProductForm').scrollIntoView({behavior: 'smooth'})">+ Nauja prekė</button>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h3>Visos prekės</h3>
        <input type="text" id="tableSearch" placeholder="Ieškoti prekės..." class="form-control" style="width:250px;" onkeyup="filterTable()">
    </div>

    <table id="productsTable">
        <thead>
            <tr style="border-bottom:2px solid #eee; text-transform:uppercase; font-size:12px; color:#6b7280;">
                <th style="width:60px;">Foto</th>
                <th>Pavadinimas</th>
                <th>Kategorija</th>
                <th>Kaina</th>
                <th>Likutis</th>
                <th style="text-align:right;">Veiksmai</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr style="border-bottom:1px solid #f9fafb;">
                    <td style="padding:10px 0;">
                        <?php 
                            $imgSrc = $p['primary_image'] ? $p['primary_image'] : ($p['image_url'] ? $p['image_url'] : 'https://placehold.co/100?text=No+Img');
                        ?>
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="product-thumb" alt="">
                    </td>
                    <td>
                        <div style="font-weight:600; font-size:14px;"><?php echo htmlspecialchars($p['title']); ?></div>
                        <?php if($p['sale_price']): ?>
                            <span style="font-size:11px; color:#ef4444; font-weight:600;">Išpardavimas</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#6b7280; font-size:13px;"><?php echo htmlspecialchars($p['category_name'] ?? '-'); ?></td>
                    <td>
                        <?php if($p['sale_price']): ?>
                            <div style="color:#ef4444; font-weight:700;"><?php echo number_format($p['sale_price'], 2); ?> €</div>
                            <div style="text-decoration:line-through; font-size:11px; color:#9ca3af;"><?php echo number_format($p['price'], 2); ?> €</div>
                        <?php else: ?>
                            <div style="font-weight:600;"><?php echo number_format($p['price'], 2); ?> €</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($p['quantity'] > 0): ?>
                            <span class="stock-badge in-stock"><?php echo $p['quantity']; ?> vnt.</span>
                        <?php else: ?>
                            <span class="stock-badge out-of-stock">Išparduota</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <a href="/product_edit.php?id=<?php echo $p['id']; ?>" class="btn secondary" style="padding:4px 8px; font-size:12px;">Redaguoti</a>
                        <form method="post" style="display:inline-block;" onsubmit="return confirm('Ar tikrai norite ištrinti šią prekę?');">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="delete_product">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <button class="btn" style="padding:4px 8px; font-size:12px; background:#fff1f1; color:#b91c1c; border-color:#fecaca;">&times;</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div style="margin-top:24px; padding-top:16px; border-top:1px dashed #e5e7eb;">
        <h4 style="font-size:13px; text-transform:uppercase; color:#6b7280; margin-bottom:10px;">Pagrindinio puslapio prekės (Max 3)</h4>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <?php foreach ($fProds as $fp): ?>
                <div style="border:1px solid #e5e7eb; padding:6px 12px; border-radius:20px; background:#fff; font-size:13px; display:flex; align-items:center; gap:8px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                    <span style="font-weight:600;"><?php echo htmlspecialchars($fp['title']); ?></span>
                    <form method="post" style="margin:0;">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="featured_remove">
                        <input type="hidden" name="remove_id" value="<?php echo $fp['id']; ?>">
                        <button type="submit" style="border:none; background:none; color:#ef4444; font-weight:bold; cursor:pointer; font-size:14px; line-height:1;">&times;</button>
                    </form>
                </div>
            <?php endforeach; ?>
            
            <?php if(count($fProds) < 3): ?>
                <form method="post" style="display:flex; gap:6px;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="featured_add">
                    <input name="featured_query" list="prodList" placeholder="Prekės pavadinimas..." class="form-control" style="width:200px; padding:4px 8px; font-size:12px;">
                    <datalist id="prodList">
                        <?php foreach($products as $p) echo "<option value='".htmlspecialchars($p['title'])."'>"; ?>
                    </datalist>
                    <button class="btn secondary" style="padding:4px 10px; font-size:12px;">Pridėti</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card" id="newProductForm" style="margin-top:24px; border-top: 4px solid #4f46e5;">
    <h3>Sukurti naują prekę</h3>
    
    <form method="post" enctype="multipart/form-data" onsubmit="return syncNewProductForm()">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="new_product">

        <div class="product-tabs">
            <button type="button" class="tab-trigger active" onclick="openTab(event, 'tab-basic')">1. Pagrindinė info</button>
            <button type="button" class="tab-trigger" onclick="openTab(event, 'tab-price')">2. Kainos ir Likutis</button>
            <button type="button" class="tab-trigger" onclick="openTab(event, 'tab-images')">3. Nuotraukos ir Kategorijos</button>
            <button type="button" class="tab-trigger" onclick="openTab(event, 'tab-attrs')">4. Atributai ir Variacijos</button>
            <button type="button" class="tab-trigger" onclick="openTab(event, 'tab-seo')">5. SEO ir Ryšiai</button>
        </div>

        <div id="tab-basic" class="tab-pane active">
            <div class="form-grid">
                <div class="full-width input-group">
                    <label>Prekės pavadinimas <span style="color:red">*</span></label>
                    <input name="title" class="form-control" placeholder="pvz. Gliukometras X" required>
                </div>
                <div class="full-width input-group">
                    <label>Paantraštė (trumpas aprašymas po pavadinimu)</label>
                    <input name="subtitle" class="form-control" placeholder="pvz. Tikslus ir greitas matavimas">
                </div>
                <div class="full-width input-group">
                    <label>Išsamus aprašymas</label>
                    <div class="toolbar">
                        <button type="button" onmousedown="event.preventDefault()" onclick="format('bold')">B</button>
                        <button type="button" onmousedown="event.preventDefault()" onclick="format('italic')">I</button>
                        <button type="button" onmousedown="event.preventDefault()" onclick="format('insertUnorderedList')">• Sąrašas</button>
                        <button type="button" onmousedown="event.preventDefault()" onclick="createLink()">🔗 Nuoroda</button>
                    </div>
                    <div id="new-desc-editor" class="rich-editor" contenteditable="true"></div>
                    <textarea name="description" id="new-desc-textarea" hidden></textarea>
                </div>
            </div>
        </div>

        <div id="tab-price" class="tab-pane">
            <div class="form-grid">
                <div class="input-group">
                    <label>Kaina (€) <span style="color:red">*</span></label>
                    <input name="price" type="number" step="0.01" class="form-control" placeholder="0.00" required>
                </div>
                <div class="input-group">
                    <label>Akcijos kaina (€) <small class="muted">(palikti tuščią, jei nėra)</small></label>
                    <input name="sale_price" type="number" step="0.01" class="form-control" placeholder="0.00">
                </div>
                <div class="input-group">
                    <label>Kiekis sandėlyje (vnt.) <span style="color:red">*</span></label>
                    <input name="quantity" type="number" min="0" class="form-control" value="0" required>
                </div>
                <div class="input-group">
                    <label>Juostelė ant nuotraukos</label>
                    <input name="ribbon_text" class="form-control" placeholder="pvz. Naujiena, -20%, Išpardavimas">
                </div>
            </div>
        </div>

        <div id="tab-images" class="tab-pane">
            <div class="form-grid">
                <div class="input-group">
                    <label>Priskirti kategorijoms</label>
                    <div class="cat-box">
                        <?php foreach ($catTree as $branch): ?>
                            <div style="margin-bottom:8px;">
                                <label class="cat-item" style="font-weight:700;">
                                    <input type="checkbox" name="categories[]" value="<?php echo (int)$branch['self']['id']; ?>">
                                    <?php echo htmlspecialchars($branch['self']['name']); ?>
                                </label>
                                <?php if(!empty($branch['children'])): ?>
                                    <?php foreach ($branch['children'] as $child): ?>
                                        <label class="cat-item cat-child">
                                            <input type="checkbox" name="categories[]" value="<?php echo (int)$child['id']; ?>">
                                            <?php echo htmlspecialchars($child['name']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="input-group">
                    <label>Prekės nuotraukos</label>
                    <div class="img-upload-area" onclick="document.getElementById('imgInput').click()">
                        <div style="font-size:24px; margin-bottom:5px;">📷</div>
                        <div style="color:#6b7280; font-size:13px;">Paspauskite, kad įkeltumėte nuotraukas</div>
                        <input type="file" name="images[]" id="imgInput" multiple accept="image/*" style="display:none;" onchange="previewImages(this)">
                    </div>
                    <div id="imagePreviewContainer" class="preview-grid"></div>
                </div>
            </div>
        </div>

        <div id="tab-attrs" class="tab-pane">
            <div class="form-grid">
                <div class="full-width input-group">
                    <label>Variacijos (pvz. Spalva, Dydis)</label>
                    <div id="vars-create" style="margin-bottom:10px;">
                        <div class="input-row" style="display:flex; gap:10px; margin-bottom:8px;">
                            <input class="form-control" name="variation_name[]" placeholder="Pavadinimas (pvz. Raudona)">
                            <input class="form-control" name="variation_price[]" type="number" step="0.01" placeholder="+/- Kaina (€)">
                        </div>
                    </div>
                    <button type="button" class="btn secondary" onclick="addVarRow('vars-create')" style="font-size:12px;">+ Pridėti variaciją</button>
                </div>

                <div class="full-width input-group" style="border-top:1px dashed #e5e7eb; padding-top:15px;">
                    <label>Techninės specifikacijos (Lentelė)</label>
                    <div id="attrs-create-container" style="margin-bottom:10px;">
                        <div class="attr-row" style="display:grid; grid-template-columns:1fr 2fr; gap:10px; margin-bottom:8px;">
                            <input name="attr_label[]" class="form-control" placeholder="Savybė (pvz. Svoris)">
                            <input name="attr_value[]" class="form-control" placeholder="Reikšmė (pvz. 1.5 kg)">
                        </div>
                    </div>
                    <button type="button" class="btn secondary" onclick="addNewAttrRow()" style="font-size:12px;">+ Pridėti savybę</button>
                </div>
            </div>
        </div>

        <div id="tab-seo" class="tab-pane">
            <div class="form-grid">
                <div class="full-width input-group">
                    <label>SEO Meta žymos (atskirti kableliais)</label>
                    <input name="meta_tags" class="form-control" placeholder="pvz. gliukometras, akcija, diabetas">
                </div>
                
                <div class="full-width input-group">
                    <label>Susijusios prekės (laikyti Ctrl/Cmd keliems pasirinkti)</label>
                    <select name="related_products[]" multiple size="6" class="form-control" style="height:auto;">
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?> (<?php echo number_format($p['price'], 2); ?> €)</option>
                        <?php endforeach; ?>
                    </select>
                    <p class="muted" style="font-size:11px; margin-top:4px;">Šios prekės bus rodomos puslapio apačioje kaip "Jums gali patikti".</p>
                </div>
            </div>
        </div>

        <div style="margin-top:20px; text-align:right; border-top:1px solid #e5e7eb; padding-top:15px;">
            <button class="btn" type="submit" style="padding:10px 24px; font-size:16px;">Išsaugoti prekę</button>
        </div>
    </form>
</div>

<script>
    // Tabų logika
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-pane");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.remove("active");
        }
        tablinks = document.getElementsByClassName("tab-trigger");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("active");
    }

    // Teksto redaktorius
    function format(c, v = null) { document.execCommand(c, false, v); }
    function createLink() { const u = prompt('Įveskite nuorodą:'); if (u) format('createLink', u); }
    
    // Formos sinchronizacija
    function syncNewProductForm() {
        document.getElementById('new-desc-textarea').value = document.getElementById('new-desc-editor').innerHTML;
        return true;
    }

    // Dinaminiai laukai
    function addNewAttrRow() {
        const c = document.getElementById('attrs-create-container');
        const d = document.createElement('div');
        d.style.cssText = "display:grid; grid-template-columns:1fr 2fr; gap:10px; margin-bottom:8px;";
        d.innerHTML = `
            <input name="attr_label[]" class="form-control" placeholder="Savybė">
            <input name="attr_value[]" class="form-control" placeholder="Reikšmė">
        `;
        c.appendChild(d);
    }

    function addVarRow(id) {
        const c = document.getElementById(id);
        const d = document.createElement('div');
        d.className = 'input-row';
        d.style.cssText = "display:flex; gap:10px; margin-bottom:8px;";
        d.innerHTML = `
            <input class="form-control" name="variation_name[]" placeholder="Pavadinimas">
            <input class="form-control" name="variation_price[]" type="number" step="0.01" placeholder="+/- Kaina">
        `;
        c.appendChild(d);
    }

    // Nuotraukų preview
    function previewImages(input) {
        const container = document.getElementById('imagePreviewContainer');
        container.innerHTML = '';
        if (input.files) {
            Array.from(input.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'preview-img';
                    container.appendChild(img);
                }
                reader.readAsDataURL(file);
            });
        }
    }

    // Lentelės paieška
    function filterTable() {
        const input = document.getElementById("tableSearch");
        const filter = input.value.toUpperCase();
        const table = document.getElementById("productsTable");
        const tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) { // Start from 1 to skip header
            let tdTitle = tr[i].getElementsByTagName("td")[1]; // Pavadinimas
            let tdCat = tr[i].getElementsByTagName("td")[2];   // Kategorija
            if (tdTitle || tdCat) {
                let txtValue = (tdTitle.textContent || tdTitle.innerText) + " " + (tdCat.textContent || tdCat.innerText);
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }
</script>
