<?php
// admin/products.php

// 1. DUOMENŲ BAZĖS ATNAUJINIMAS (Vieną kartą)
try {
    // Patikriname, ar yra is_featured stulpelis
    $pdo->query("SELECT is_featured FROM products LIMIT 1");
} catch (Exception $e) {
    // Jei nėra, sukuriame
    $pdo->exec("ALTER TABLE products ADD COLUMN is_featured TINYINT(1) DEFAULT 0");
}

// 2. DUOMENŲ SURINKIMAS
// ---------------------------------------------------

// Surenkame visas prekes
$stmt = $pdo->query('
    SELECT p.*, c.name AS category_name,
           (SELECT path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p 
    LEFT JOIN categories c ON c.id = p.category_id 
    ORDER BY p.created_at DESC
');
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Papildomai surenkame duomenis (atributus, variacijas, kategorijas, VISAS nuotraukas)
foreach ($products as &$p) {
    // Atributai
    $attrsStmt = $pdo->prepare("SELECT label, value FROM product_attributes WHERE product_id = ?");
    $attrsStmt->execute([$p['id']]);
    $p['attributes'] = $attrsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Variacijos
    $varsStmt = $pdo->prepare("SELECT name, price_delta FROM product_variations WHERE product_id = ?");
    $varsStmt->execute([$p['id']]);
    $p['variations'] = $varsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kategorijų ryšiai
    $catsStmt = $pdo->prepare("SELECT category_id FROM product_category_relations WHERE product_id = ?");
    $catsStmt->execute([$p['id']]);
    $p['category_ids'] = $catsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Susijusios prekės
    $relStmt = $pdo->prepare("SELECT related_product_id FROM product_related WHERE product_id = ?");
    $relStmt->execute([$p['id']]);
    $p['related_ids'] = $relStmt->fetchAll(PDO::FETCH_COLUMN);

    // Visos nuotraukos (redagavimui)
    $imgsStmt = $pdo->prepare("SELECT id, path, is_primary FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, id ASC");
    $imgsStmt->execute([$p['id']]);
    $p['all_images'] = $imgsStmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($p);

// Kategorijų medis
$allCats = $pdo->query('SELECT * FROM categories ORDER BY parent_id ASC, name ASC')->fetchAll();
$catTree = [];
foreach ($allCats as $c) {
    if (empty($c['parent_id'])) { $catTree[$c['id']]['self']=$c; $catTree[$c['id']]['children']=[]; }
}
foreach ($allCats as $c) {
    if (!empty($c['parent_id']) && isset($catTree[$c['parent_id']])) { $catTree[$c['parent_id']]['children'][]=$c; }
}

// Featured prekės (iš tos pačios products lentelės)
$fProds = array_filter($products, function($p) { return $p['is_featured'] == 1; });
?>

<style>
    /* Modal ir Tabs */
    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(3px); z-index: 1000; display: none; align-items: center; justify-content: center; }
    .modal-overlay.open { display: flex; animation: fadeIn 0.2s; }
    .modal-window { background: #fff; width: 95%; max-width: 1000px; height: 90vh; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); display: flex; flex-direction: column; overflow: hidden; }
    .modal-header { padding: 15px 24px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fcfcfc; }
    .modal-body { padding: 0; overflow-y: auto; flex: 1; display: flex; flex-direction: column; }
    .modal-footer { padding: 15px 24px; border-top: 1px solid #eee; background: #f9f9ff; text-align: right; }
    .product-tabs { display: flex; background: #fff; border-bottom: 1px solid #eee; position: sticky; top: 0; z-index: 10; padding: 0 24px; }
    .tab-btn { padding: 16px 20px; background: transparent; border: none; border-bottom: 2px solid transparent; font-weight: 600; color: #6b7280; cursor: pointer; transition: 0.2s; font-size: 14px; }
    .tab-btn:hover { color: #4f46e5; background: #f9fafb; }
    .tab-btn.active { color: #4f46e5; border-bottom-color: #4f46e5; }
    .tab-content { display: none; padding: 24px; }
    .tab-content.active { display: block; animation: slideUp 0.3s ease; }
    
    /* Formos elementai */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .full-width { grid-column: span 2; }
    .input-group label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
    
    /* Image Manager */
    .img-manager-item { position: relative; width: 100px; height: 120px; border: 1px solid #eee; border-radius: 6px; padding: 4px; display:flex; flex-direction:column; align-items:center; }
    .img-manager-item img { width: 100%; height: 80px; object-fit: cover; border-radius: 4px; }
    .img-actions { margin-top: 5px; display: flex; justify-content: space-between; width: 100%; align-items: center; font-size: 11px; }
    .star-btn { cursor: pointer; color: #ccc; font-size: 16px; border: none; background: none; }
    .star-btn.active { color: #f59e0b; }
    .del-btn { cursor: pointer; color: #ef4444; border: none; background: none; font-weight: bold; }

    /* Kiti stiliai */
    .cat-box { border: 1px solid #d1d5db; border-radius: 6px; padding: 10px; max-height: 200px; overflow-y: auto; background: #fff; }
    .cat-item { display: block; margin-bottom: 6px; cursor: pointer; font-size: 14px; }
    .cat-child { margin-left: 20px; border-left: 2px solid #eee; padding-left: 8px; }
    .rich-editor-wrapper { border: 1px solid #d1d5db; border-radius: 6px; overflow: hidden; background: #fff; }
    .editor-toolbar { background: #f3f4f6; border-bottom: 1px solid #d1d5db; padding: 6px; display: flex; gap: 4px; flex-wrap: wrap; }
    .editor-btn { border: 1px solid transparent; background: transparent; cursor: pointer; padding: 4px 6px; border-radius: 4px; font-size: 14px; color: #374151; }
    .editor-btn:hover { background: #e5e7eb; border-color: #d1d5db; }
    .editor-content { min-height: 150px; padding: 12px; outline: none; overflow-y: auto; font-size: 14px; line-height: 1.5; }
    .mini-editor .editor-content { min-height: 60px; }
    .attr-row { display: grid; grid-template-columns: 200px 1fr 40px; gap: 10px; margin-bottom: 12px; align-items: start; background: #fdfdfd; padding: 10px; border: 1px solid #eee; border-radius: 6px; }
    .bulk-actions { display: none; align-items: center; gap: 10px; background: #eff6ff; padding: 8px 16px; border-radius: 8px; border: 1px solid #dbeafe; margin-left: 16px; }
    .bulk-actions.visible { display: flex; }
    .product-thumb { width: 40px; height: 40px; border-radius: 4px; object-fit: cover; background: #eee; }
    .stock-badge { padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
    .in-stock { background: #dcfce7; color: #166534; }
    .out-of-stock { background: #fee2e2; color: #991b1b; }

    @keyframes fadeIn { from {opacity:0} to {opacity:1} }
    @keyframes slideUp { from {opacity:0; transform:translateY(10px)} to {opacity:1; transform:translateY(0)} }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div style="display:flex; align-items:center;">
        <div>
            <h2>Prekių valdymas</h2>
            <p class="muted" style="margin:0;">Viso prekių: <?php echo count($products); ?></p>
        </div>
        <div id="bulkActionsPanel" class="bulk-actions">
            <span style="font-weight:600; font-size:13px; color:#1d4ed8;">Pasirinkta: <span id="selectedCount">0</span></span>
            <button type="button" class="btn" style="background:#ef4444; border-color:#ef4444; padding:6px 12px; font-size:12px;" onclick="submitBulkDelete()">Ištrinti pasirinktus</button>
        </div>
    </div>
    <button class="btn" onclick="openProductModal('create')">+ Nauja prekė</button>
</div>

<div class="card" style="margin-bottom:20px; border:1px dashed #4f46e5; background:#f5f6ff;">
    <h4 style="margin-top:0; font-size:14px; text-transform:uppercase; color:#4338ca;">Pagrindinio puslapio prekės (Featured)</h4>
    <p class="muted" style="font-size:12px; margin-bottom:12px;">Šios prekės rodomos pagrindiniame puslapyje. Rekomenduojama 3 vnt.</p>
    
    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <?php foreach ($fProds as $fp): ?>
            <div style="background:#fff; border:1px solid #c7d2fe; padding:6px 12px; border-radius:20px; font-size:13px; display:flex; align-items:center; gap:8px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                <span style="font-weight:600; color:#3730a3;"><?php echo htmlspecialchars($fp['title']); ?></span>
                <form method="post" style="margin:0;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="toggle_featured">
                    <input type="hidden" name="product_id" value="<?php echo $fp['id']; ?>">
                    <input type="hidden" name="set_featured" value="0">
                    <button type="submit" style="border:none; background:none; color:#ef4444; font-weight:bold; cursor:pointer; font-size:16px; line-height:1;" title="Pašalinti iš titulinio">&times;</button>
                </form>
            </div>
        <?php endforeach; ?>
        
        <form method="post" style="display:flex; gap:6px;">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="add_featured_by_name">
            <input name="featured_title" list="prodList" placeholder="Įveskite prekės pavadinimą..." class="form-control" style="width:250px; padding:6px 10px; font-size:13px; background:#fff;" required autocomplete="off">
            <datalist id="prodList">
                <?php foreach($products as $p) echo "<option value='".htmlspecialchars($p['title'])."'>"; ?>
            </datalist>
            <button class="btn secondary" style="padding:6px 12px; font-size:13px;">Pridėti</button>
        </form>
    </div>
</div>

<form id="productsListForm" method="post">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="bulk_delete_products">
    
    <div class="card" style="padding:0; overflow:hidden;">
        <div style="padding:15px; border-bottom:1px solid #eee; display:flex; justify-content:space-between;">
            <h3 style="margin:0;">Prekių sąrašas</h3>
            <input type="text" id="tableSearch" placeholder="Greita paieška..." class="form-control" style="width:250px; padding:6px 10px;" onkeyup="filterTable()">
        </div>

        <table id="productsTable">
            <thead>
                <tr style="background:#f9fafb; font-size:12px; text-transform:uppercase; color:#6b7280;">
                    <th class="checkbox-col"><input type="checkbox" onchange="toggleAll(this)"></th>
                    <th style="width:60px; padding-left:10px;">Foto</th>
                    <th>Pavadinimas</th>
                    <th>Kategorija</th>
                    <th>Kaina</th>
                    <th>Likutis</th>
                    <th style="text-align:right; padding-right:20px;">Veiksmai</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td class="checkbox-col"><input type="checkbox" name="selected_ids[]" value="<?php echo $p['id']; ?>" class="prod-check" onchange="updateBulkUI()"></td>
                        <td style="padding:10px 0 10px 10px;">
                            <?php $imgSrc = $p['primary_image'] ?: ($p['image_url'] ?: 'https://placehold.co/100'); ?>
                            <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="product-thumb" alt="">
                        </td>
                        <td>
                            <div style="font-weight:600; font-size:14px; color:#111;"><?php echo htmlspecialchars($p['title']); ?></div>
                            <?php if($p['is_featured']): ?><span style="font-size:10px; color:#4f46e5; font-weight:700;">[Titulinio]</span><?php endif; ?>
                            <?php if($p['sale_price']): ?><span style="font-size:10px; color:#ef4444; font-weight:700;">[Akcija]</span><?php endif; ?>
                        </td>
                        <td style="font-size:13px; color:#666;"><?php echo htmlspecialchars($p['category_name'] ?? '-'); ?></td>
                        <td>
                            <?php if($p['sale_price']): ?>
                                <div style="color:#ef4444; font-weight:700;"><?php echo number_format($p['sale_price'], 2); ?> €</div>
                                <div style="text-decoration:line-through; font-size:11px; color:#999;"><?php echo number_format($p['price'], 2); ?> €</div>
                            <?php else: ?>
                                <div style="font-weight:600;"><?php echo number_format($p['price'], 2); ?> €</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($p['quantity'] > 0): ?>
                                <span class="stock-badge in-stock"><?php echo $p['quantity']; ?> vnt.</span>
                            <?php else: ?>
                                <span class="stock-badge out-of-stock">0 vnt.</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right; padding-right:20px;">
                            <button type="button" class="btn secondary" style="padding:4px 10px; font-size:12px;" onclick='openProductModal("edit", <?php echo json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Redaguoti</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>

<div id="productModal" class="modal-overlay">
    <form method="post" enctype="multipart/form-data" class="modal-window" onsubmit="return syncEditors()">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" id="formAction" value="save_product">
        <input type="hidden" name="id" id="productId" value="">

        <div class="modal-header">
            <h3 style="margin:0;" id="modalTitle">Nauja prekė</h3>
            <button type="button" onclick="closeProductModal()" style="border:none; background:none; font-size:24px; cursor:pointer;">&times;</button>
        </div>

        <div class="product-tabs">
            <button type="button" class="tab-btn active" onclick="switchTab('basic')">Pagrindinė info</button>
            <button type="button" class="tab-btn" onclick="switchTab('specs')">Specifikacijos</button>
            <button type="button" class="tab-btn" onclick="switchTab('prices')">Kaina ir Variacijos</button>
            <button type="button" class="tab-btn" onclick="switchTab('media')">Nuotraukos</button>
            <button type="button" class="tab-btn" onclick="switchTab('seo')">SEO</button>
        </div>

        <div class="modal-body">
            <div id="tab-basic" class="tab-content active">
                <div class="form-grid">
                    <div class="full-width input-group">
                        <label>Prekės pavadinimas *</label>
                        <input name="title" id="p_title" class="form-control" required placeholder="pvz. Gliukometras X">
                    </div>
                    <div class="full-width input-group">
                        <label>Paantraštė (Trumpas aprašymas)</label>
                        <input name="subtitle" id="p_subtitle" class="form-control">
                    </div>
                    <div class="full-width input-group">
                        <label>Išsamus aprašymas</label>
                        <div class="rich-editor-wrapper">
                            <div class="editor-toolbar" id="mainDescToolbar"></div>
                            <div id="mainDescEditor" class="editor-content" contenteditable="true"></div>
                            <textarea name="description" id="p_description" hidden></textarea>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Kategorijos (galima kelias)</label>
                        <div class="cat-box">
                            <?php foreach ($catTree as $branch): ?>
                                <label class="cat-item" style="font-weight:700;">
                                    <input type="checkbox" name="categories[]" value="<?php echo $branch['self']['id']; ?>" class="cat-check">
                                    <?php echo htmlspecialchars($branch['self']['name']); ?>
                                </label>
                                <?php foreach ($branch['children'] as $child): ?>
                                    <label class="cat-item cat-child">
                                        <input type="checkbox" name="categories[]" value="<?php echo $child['id']; ?>" class="cat-check">
                                        <?php echo htmlspecialchars($child['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Etiketė (Ribbon)</label>
                        <input name="ribbon_text" id="p_ribbon" class="form-control" placeholder="pvz. Naujiena">
                    </div>
                    <div class="full-width input-group">
                        <label style="display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="is_featured" id="p_featured" value="1">
                            Rodyti pagrindiniame puslapyje (Featured)
                        </label>
                    </div>
                </div>
            </div>

            <div id="tab-specs" class="tab-content">
                <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                    <label>Techninės savybės</label>
                    <button type="button" class="btn secondary" style="font-size:12px;" onclick="addRichAttrRow()">+ Eilutė</button>
                </div>
                <div id="attributesContainer"></div>
            </div>

            <div id="tab-prices" class="tab-content">
                <div class="form-grid">
                    <div class="input-group"><label>Kaina (€) *</label><input type="number" step="0.01" name="price" id="p_price" class="form-control" required></div>
                    <div class="input-group"><label>Akcijos kaina (€)</label><input type="number" step="0.01" name="sale_price" id="p_sale_price" class="form-control"></div>
                    <div class="input-group"><label>Kiekis (vnt.) *</label><input type="number" name="quantity" id="p_quantity" class="form-control" value="0" required></div>
                </div>
                <hr style="margin:20px 0; border:0; border-top:1px dashed #eee;">
                <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                    <label>Variacijos</label>
                    <button type="button" class="btn secondary" style="font-size:12px;" onclick="addVarRow()">+ Variacija</button>
                </div>
                <div id="variationsContainer"></div>
            </div>

            <div id="tab-media" class="tab-content">
                <div class="input-group">
                    <label>Įkelti naujas nuotraukas</label>
                    <input type="file" name="images[]" multiple accept="image/*" class="form-control" onchange="previewModalImages(this)">
                </div>
                <div id="modalImgPreview" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;"></div>
                
                <div id="existingImages" style="margin-top:20px;">
                    <label>Esamos nuotraukos (Žvaigždutė = Pagrindinė)</label>
                    <div id="existingImgContainer" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;"></div>
                </div>
            </div>

            <div id="tab-seo" class="tab-content">
                <div class="input-group">
                    <label>Meta žymos (Tags)</label>
                    <input name="meta_tags" id="p_meta_tags" class="form-control">
                </div>
                <div class="input-group">
                    <label>Susijusios prekės</label>
                    <select name="related_products[]" id="p_related" multiple class="form-control" style="height:150px;">
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn secondary" onclick="closeProductModal()">Atšaukti</button>
            <button type="submit" class="btn">Išsaugoti</button>
        </div>
    </form>
</div>

<script>
    // JS FUNKCIJOS
    document.addEventListener('DOMContentLoaded', () => { createToolbar('mainDescToolbar'); });

    function createToolbar(containerId) {
        const c = document.getElementById(containerId);
        if(!c) return;
        const tools = [ {c:'bold',l:'B'}, {c:'italic',l:'I'}, {c:'insertUnorderedList',l:'• List'}, {c:'createLink',l:'🔗'} ];
        let h=''; tools.forEach(t=>{ 
            if(t.c=='createLink') h+=`<span class="editor-btn" onclick="let u=prompt('URL:');if(u)document.execCommand('${t.c}',false,u)">${t.l}</span>`;
            else h+=`<span class="editor-btn" onclick="document.execCommand('${t.c}',false,null)">${t.l}</span>`; 
        });
        c.innerHTML = h;
    }

    // Pataisyta funkcija Variacijoms
    window.addVarRow = function(name='', price='') {
        const c = document.getElementById('variationsContainer');
        const d = document.createElement('div');
        d.style.cssText = "display:grid; grid-template-columns: 1fr 100px 40px; gap:10px; margin-bottom:8px;";
        d.innerHTML = `
            <input name="variation_name[]" class="form-control" placeholder="Pavadinimas" value="${name}">
            <input name="variation_price[]" type="number" step="0.01" class="form-control" placeholder="+/- €" value="${price}">
            <button type="button" onclick="this.parentElement.remove()" style="color:red; border:none; cursor:pointer; background:none;">&times;</button>
        `;
        c.appendChild(d);
    }

    window.addRichAttrRow = function(label='', val='') {
        const c = document.getElementById('attributesContainer');
        const uid = 'ae_'+Date.now()+Math.random();
        const d = document.createElement('div');
        d.className = 'attr-row';
        d.innerHTML = `
            <input name="attr_label[]" class="form-control" placeholder="Savybė" value="${label}">
            <div class="rich-editor-wrapper mini-editor"><div class="editor-toolbar" id="tb_${uid}"></div><div class="editor-content" id="${uid}" contenteditable="true">${val}</div><textarea name="attr_value[]" hidden></textarea></div>
            <button type="button" onclick="this.parentElement.remove()" style="color:red; border:none; cursor:pointer; background:none;">&times;</button>
        `;
        c.appendChild(d);
        createToolbar('tb_'+uid);
    }

    window.openProductModal = function(mode, data=null) {
        const f = document.querySelector('form.modal-window');
        f.reset();
        document.getElementById('productId').value = '';
        document.getElementById('mainDescEditor').innerHTML = '';
        document.getElementById('attributesContainer').innerHTML = '';
        document.getElementById('variationsContainer').innerHTML = '';
        document.getElementById('existingImgContainer').innerHTML = '';
        document.querySelectorAll('.cat-check').forEach(c => c.checked = false);
        window.switchTab('basic');

        if(mode==='create') {
            document.getElementById('modalTitle').innerText = 'Nauja prekė';
            window.addRichAttrRow();
        } else {
            document.getElementById('modalTitle').innerText = 'Redaguoti prekę';
            document.getElementById('productId').value = data.id;
            document.getElementById('p_title').value = data.title;
            document.getElementById('p_subtitle').value = data.subtitle||'';
            document.getElementById('mainDescEditor').innerHTML = data.description||'';
            document.getElementById('p_price').value = data.price;
            document.getElementById('p_sale_price').value = data.sale_price||'';
            document.getElementById('p_quantity').value = data.quantity;
            document.getElementById('p_ribbon').value = data.ribbon_text||'';
            document.getElementById('p_meta_tags').value = data.meta_tags||'';
            
            if(data.is_featured == 1) document.getElementById('p_featured').checked = true;

            // Categories
            if(data.category_ids) data.category_ids.forEach(cid => {
                const cb = document.querySelector(`.cat-check[value="${cid}"]`);
                if(cb) cb.checked = true;
            });

            // Attributes & Vars
            if(data.attributes) data.attributes.forEach(a => window.addRichAttrRow(a.label, a.value));
            if(data.variations) data.variations.forEach(v => window.addVarRow(v.name, v.price_delta));

            // Images with Primary Select
            if(data.all_images) {
                data.all_images.forEach(img => {
                    const div = document.createElement('div');
                    div.className = 'img-manager-item';
                    const isPrim = img.is_primary == 1 ? 'active' : '';
                    div.innerHTML = `
                        <img src="${img.path}">
                        <div class="img-actions">
                            <label class="star-btn ${isPrim}" title="Padaryti pagrindine">
                                ★ <input type="radio" name="primary_image_id" value="${img.id}" ${img.is_primary==1?'checked':''} style="display:none;" onchange="updateStars(this)">
                            </label>
                            <label class="del-btn" title="Ištrinti">
                                &times; <input type="checkbox" name="delete_images[]" value="${img.id}" style="display:none;" onchange="this.parentElement.style.color = this.checked ? 'black' : 'red'; this.closest('.img-manager-item').style.opacity = this.checked ? 0.5 : 1;">
                            </label>
                        </div>
                    `;
                    document.getElementById('existingImgContainer').appendChild(div);
                });
            }
        }
        document.getElementById('productModal').style.display = 'flex';
        setTimeout(() => document.getElementById('productModal').classList.add('open'), 10);
    }

    window.updateStars = function(radio) {
        document.querySelectorAll('.star-btn').forEach(b => b.classList.remove('active'));
        radio.parentElement.classList.add('active');
    }

    window.closeProductModal = function() {
        document.getElementById('productModal').classList.remove('open');
        setTimeout(() => document.getElementById('productModal').style.display = 'none', 200);
    }

    window.syncEditors = function() {
        document.getElementById('p_description').value = document.getElementById('mainDescEditor').innerHTML;
        document.querySelectorAll('.attr-row').forEach(row => {
            row.querySelector('textarea').value = row.querySelector('.editor-content').innerHTML;
        });
        return true;
    }

    window.switchTab = function(id) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-'+id).classList.add('active');
        const btns = document.querySelectorAll('.tab-btn');
        if(id=='basic') btns[0].classList.add('active');
        if(id=='specs') btns[1].classList.add('active');
        if(id=='prices') btns[2].classList.add('active');
        if(id=='media') btns[3].classList.add('active');
        if(id=='seo') btns[4].classList.add('active');
    }
    
    // Bulk & Search
    window.toggleAll = function(s) { document.querySelectorAll('.prod-check').forEach(c=>c.checked=s.checked); updateBulkUI(); }
    window.updateBulkUI = function() {
        const n = document.querySelectorAll('.prod-check:checked').length;
        document.getElementById('selectedCount').innerText = n;
        document.getElementById('bulkActionsPanel').classList.toggle('visible', n>0);
    }
    window.submitBulkDelete = function() { if(confirm('Trinti?')) document.getElementById('productsListForm').submit(); }
    window.filterTable = function() {
        const v = document.getElementById('tableSearch').value.toUpperCase();
        document.querySelectorAll('#productsTable tbody tr').forEach(tr => tr.style.display = tr.innerText.toUpperCase().includes(v) ? '' : 'none');
    }
</script>
