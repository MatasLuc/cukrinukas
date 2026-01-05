<?php
// admin/products.php

// 1. DUOMENŲ PARUOŠIMAS
// ---------------------------------------------------

// Užtikriname, kad egzistuoja ryšių lentelė
$pdo->exec("CREATE TABLE IF NOT EXISTS product_category_relations (product_id INT NOT NULL, category_id INT NOT NULL, PRIMARY KEY (product_id, category_id))");
// Užtikriname, kad egzistuoja featured_products lentelė
$pdo->exec("CREATE TABLE IF NOT EXISTS featured_products (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, sort_order INT DEFAULT 0)");

// Surenkame visas prekes
$stmt = $pdo->query('
    SELECT p.*, c.name AS category_name,
           (SELECT path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p 
    LEFT JOIN categories c ON c.id = p.category_id 
    ORDER BY p.created_at DESC
');
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Papildomai surenkame duomenis (atributus, variacijas, kategorijas)
foreach ($products as &$p) {
    // Atributai
    $attrsStmt = $pdo->prepare("SELECT label, value FROM product_attributes WHERE product_id = ?");
    $attrsStmt->execute([$p['id']]);
    $p['attributes'] = $attrsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Variacijos
    $varsStmt = $pdo->prepare("SELECT name, price_delta FROM product_variations WHERE product_id = ?");
    $varsStmt->execute([$p['id']]);
    $p['variations'] = $varsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kategorijų ryšiai (visos priskirtos kategorijos)
    $catsStmt = $pdo->prepare("SELECT category_id FROM product_category_relations WHERE product_id = ?");
    $catsStmt->execute([$p['id']]);
    $p['category_ids'] = $catsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Susijusios prekės
    $relStmt = $pdo->prepare("SELECT related_product_id FROM product_related WHERE product_id = ?");
    $relStmt->execute([$p['id']]);
    $p['related_ids'] = $relStmt->fetchAll(PDO::FETCH_COLUMN);
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

// Featured prekės
// Jei funkcija getFeaturedProductIds neegzistuoja, naudojame tiesioginę užklausą
$fProds = [];
try {
    $fIdsStmt = $pdo->query("SELECT product_id FROM featured_products ORDER BY sort_order ASC LIMIT 3");
    $fIds = $fIdsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if ($fIds) {
        $in = implode(',', array_fill(0, count($fIds), '?'));
        $s = $pdo->prepare("SELECT id, title FROM products WHERE id IN ($in)");
        $s->execute($fIds);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        // Išlaikome rikiavimo tvarką
        $m = array_column($rows, null, 'id');
        foreach($fIds as $i) if(isset($m[$i])) $fProds[] = $m[$i];
    }
} catch (Exception $e) { /* Tyli klaida jei lentelės nėra */ }
?>

<style>
    /* Modal ir Tabs */
    .modal-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5); backdrop-filter: blur(3px); z-index: 1000;
        display: none; align-items: center; justify-content: center;
    }
    .modal-overlay.open { display: flex; animation: fadeIn 0.2s; }
    .modal-window {
        background: #fff; width: 95%; max-width: 1000px; height: 90vh;
        border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        display: flex; flex-direction: column; overflow: hidden;
    }
    .modal-header { padding: 15px 24px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fcfcfc; }
    .modal-body { padding: 0; overflow-y: auto; flex: 1; display: flex; flex-direction: column; }
    .modal-footer { padding: 15px 24px; border-top: 1px solid #eee; background: #f9f9ff; text-align: right; }
    
    .product-tabs { display: flex; background: #fff; border-bottom: 1px solid #eee; position: sticky; top: 0; z-index: 10; padding: 0 24px; }
    .tab-btn {
        padding: 16px 20px; background: transparent; border: none; border-bottom: 2px solid transparent;
        font-weight: 600; color: #6b7280; cursor: pointer; transition: 0.2s; font-size: 14px;
    }
    .tab-btn:hover { color: #4f46e5; background: #f9fafb; }
    .tab-btn.active { color: #4f46e5; border-bottom-color: #4f46e5; }
    
    .tab-content { display: none; padding: 24px; }
    .tab-content.active { display: block; animation: slideUp 0.3s ease; }
    
    /* Formos elementai */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .full-width { grid-column: span 2; }
    .input-group label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; color: #6b7280; margin-bottom: 6px; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
    
    /* Kategorijų box */
    .cat-box { border: 1px solid #d1d5db; border-radius: 6px; padding: 10px; max-height: 200px; overflow-y: auto; background: #fff; }
    .cat-item { display: block; margin-bottom: 6px; cursor: pointer; font-size: 14px; }
    .cat-child { margin-left: 20px; border-left: 2px solid #eee; padding-left: 8px; }

    /* Galingas redaktorius */
    .rich-editor-wrapper { border: 1px solid #d1d5db; border-radius: 6px; overflow: hidden; background: #fff; }
    .editor-toolbar {
        background: #f3f4f6; border-bottom: 1px solid #d1d5db; padding: 6px;
        display: flex; gap: 4px; flex-wrap: wrap;
    }
    .editor-btn {
        border: 1px solid transparent; background: transparent; cursor: pointer; padding: 4px 6px; border-radius: 4px; font-size: 14px; color: #374151;
    }
    .editor-btn:hover { background: #e5e7eb; border-color: #d1d5db; }
    .editor-content {
        min-height: 150px; padding: 12px; outline: none; overflow-y: auto; font-size: 14px; line-height: 1.5;
    }
    .editor-content:empty:before { content: attr(placeholder); color: #9ca3af; }
    .mini-editor .editor-content { min-height: 60px; }

    /* Atributų lentelė */
    .attr-row { display: grid; grid-template-columns: 200px 1fr 40px; gap: 10px; margin-bottom: 12px; align-items: start; background: #fdfdfd; padding: 10px; border: 1px solid #eee; border-radius: 6px; }
    
    /* Masiniai veiksmai */
    .bulk-actions { 
        display: none; align-items: center; gap: 10px; background: #eff6ff; 
        padding: 8px 16px; border-radius: 8px; border: 1px solid #dbeafe; margin-left: 16px; 
    }
    .bulk-actions.visible { display: flex; }

    /* Lentelė */
    .checkbox-col { width: 30px; text-align: center; }
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
            <button type="button" class="btn" style="background:#ef4444; border-color:#ef4444; padding:6px 12px; font-size:12px;" onclick="submitBulkDelete()">
                Ištrinti pasirinktus
            </button>
        </div>
    </div>
    
    <button class="btn" onclick="openProductModal('create')">+ Nauja prekė</button>
</div>

<div class="card" style="margin-bottom:20px; border:1px dashed #4f46e5; background:#f5f6ff;">
    <h4 style="margin-top:0; font-size:14px; text-transform:uppercase; color:#4338ca;">Pagrindinio puslapio prekės (Max 3)</h4>
    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <?php foreach ($fProds as $fp): ?>
            <div style="background:#fff; border:1px solid #c7d2fe; padding:6px 12px; border-radius:20px; font-size:13px; display:flex; align-items:center; gap:8px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                <span style="font-weight:600; color:#3730a3;"><?php echo htmlspecialchars($fp['title']); ?></span>
                <form method="post" style="margin:0;">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="featured_remove">
                    <input type="hidden" name="product_id" value="<?php echo $fp['id']; ?>">
                    <button type="submit" style="border:none; background:none; color:#ef4444; font-weight:bold; cursor:pointer; font-size:16px; line-height:1;">&times;</button>
                </form>
            </div>
        <?php endforeach; ?>
        
        <?php if(count($fProds) < 3): ?>
            <form method="post" style="display:flex; gap:6px;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="featured_add">
                <input name="featured_title" list="prodList" placeholder="Prekės pavadinimas..." class="form-control" style="width:250px; padding:6px 10px; font-size:13px; background:#fff;">
                <datalist id="prodList">
                    <?php foreach($products as $p) echo "<option value='".htmlspecialchars($p['title'])."'>"; ?>
                </datalist>
                <button class="btn secondary" style="padding:6px 12px; font-size:13px;">Pridėti</button>
            </form>
        <?php else: ?>
            <span class="muted" style="font-size:12px;">Pasiektas limitas. Ištrinkite prekę, kad pridėtumėte naują.</span>
        <?php endif; ?>
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
                        <td class="checkbox-col">
                            <input type="checkbox" name="selected_ids[]" value="<?php echo $p['id']; ?>" class="prod-check" onchange="updateBulkUI()">
                        </td>
                        <td style="padding:10px 0 10px 10px;">
                            <?php $imgSrc = $p['primary_image'] ?: ($p['image_url'] ?: 'https://placehold.co/100'); ?>
                            <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="product-thumb" alt="">
                        </td>
                        <td>
                            <div style="font-weight:600; font-size:14px; color:#111;"><?php echo htmlspecialchars($p['title']); ?></div>
                            <?php if($p['sale_price']): ?>
                                <span style="font-size:10px; color:#ef4444; font-weight:700; text-transform:uppercase;">Akcija</span>
                            <?php endif; ?>
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
                            <button type="button" class="btn secondary" style="padding:4px 10px; font-size:12px;" 
                                    onclick='openProductModal("edit", <?php echo json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                Redaguoti
                            </button>
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
            <button type="button" class="tab-btn" onclick="switchTab('specs')">Specifikacijos (Rich Text)</button>
            <button type="button" class="tab-btn" onclick="switchTab('prices')">Kaina ir Variacijos</button>
            <button type="button" class="tab-btn" onclick="switchTab('media')">Nuotraukos</button>
            <button type="button" class="tab-btn" onclick="switchTab('seo')">SEO ir Ryšiai</button>
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
                        <input name="subtitle" id="p_subtitle" class="form-control" placeholder="pvz. Tikslus ir patikimas">
                    </div>
                    
                    <div class="full-width input-group">
                        <label>Išsamus aprašymas (Redaktorius)</label>
                        <div class="rich-editor-wrapper">
                            <div class="editor-toolbar" id="mainDescToolbar"></div>
                            <div id="mainDescEditor" class="editor-content" contenteditable="true" placeholder="Rašykite aprašymą čia..."></div>
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
                        <label>Etiketė ant foto (Ribbon)</label>
                        <input name="ribbon_text" id="p_ribbon" class="form-control" placeholder="pvz. Naujiena">
                    </div>
                </div>
            </div>

            <div id="tab-specs" class="tab-content">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <label style="font-weight:700; text-transform:uppercase; font-size:12px; color:#666;">Techninės savybės</label>
                    <button type="button" class="btn secondary" style="font-size:12px;" onclick="addRichAttrRow()">+ Pridėti eilutę</button>
                </div>
                <p class="muted" style="font-size:12px; margin-bottom:15px;">Kiekviena reikšmė turi savo teksto redaktorių (galima Bold, List ir t.t.).</p>
                
                <div id="attributesContainer"></div>
            </div>

            <div id="tab-prices" class="tab-content">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Kaina (€) *</label>
                        <input type="number" step="0.01" name="price" id="p_price" class="form-control" required>
                    </div>
                    <div class="input-group">
                        <label>Akcijos kaina (€)</label>
                        <input type="number" step="0.01" name="sale_price" id="p_sale_price" class="form-control">
                    </div>
                    <div class="input-group">
                        <label>Kiekis (vnt.) *</label>
                        <input type="number" name="quantity" id="p_quantity" class="form-control" value="0" required>
                    </div>
                </div>

                <hr style="margin:20px 0; border:0; border-top:1px dashed #eee;">
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <label style="font-weight:700; text-transform:uppercase; font-size:12px; color:#666;">Variacijos (Spalva, Dydis)</label>
                    <button type="button" class="btn secondary" style="font-size:12px;" onclick="addVarRow()">+ Pridėti variaciją</button>
                </div>
                <div id="variationsContainer"></div>
            </div>

            <div id="tab-media" class="tab-content">
                <div class="input-group">
                    <label>Įkelti nuotraukas</label>
                    <div style="border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 8px; cursor: pointer; background:#f9f9f9;" 
                         onclick="document.getElementById('modalImgInput').click()">
                        <div style="font-size:24px;">📷</div>
                        <div style="font-size:13px; color:#666;">Paspauskite įkėlimui</div>
                    </div>
                    <input type="file" name="images[]" id="modalImgInput" multiple accept="image/*" style="display:none;" onchange="previewModalImages(this)">
                </div>
                <div id="modalImgPreview" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:15px;"></div>
                
                <div id="existingImages" style="margin-top:20px; border-top:1px solid #eee; padding-top:10px;">
                    <label style="font-size:12px; font-weight:700;">Esamos nuotraukos:</label>
                    <div id="existingImgContainer" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;"></div>
                </div>
            </div>

            <div id="tab-seo" class="tab-content">
                <div class="full-width input-group">
                    <label>Meta žymos (Tags)</label>
                    <input name="meta_tags" id="p_meta_tags" class="form-control" placeholder="pvz. diabetas, sensorius, akcija">
                </div>
                <div class="full-width input-group">
                    <label>Susijusios prekės (Laikyti Ctrl pasirinkimui)</label>
                    <select name="related_products[]" id="p_related" multiple class="form-control" style="height:200px;">
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn secondary" onclick="closeProductModal()">Atšaukti</button>
            <button type="submit" class="btn">Išsaugoti prekę</button>
        </div>
    </form>
</div>

<script>
    // --- RICH TEXT EDITOR ---
    function createToolbar(containerId) {
        const container = document.getElementById(containerId);
        if(!container) return;
        const tools = [
            { cmd: 'bold', label: 'B', title: 'Paryškinti' },
            { cmd: 'italic', label: 'I', title: 'Pasviras' },
            { cmd: 'underline', label: 'U', title: 'Pabraukti' },
            { cmd: 'insertUnorderedList', label: '• Sąrašas', title: 'Sąrašas' },
            { cmd: 'formatBlock', val: 'H3', label: 'H3', title: 'Antraštė' },
            { cmd: 'createLink', label: '🔗', title: 'Nuoroda' },
            { cmd: 'justifyLeft', label: 'L', title: 'Kairėje' },
            { cmd: 'justifyCenter', label: 'C', title: 'Centre' },
            { cmd: 'foreColor', val: '#ef4444', label: '🔴', title: 'Raudona' },
            { cmd: 'removeFormat', label: '🧹', title: 'Valyti' }
        ];
        let html = '';
        tools.forEach(t => {
            if (t.val) html += `<button type="button" class="editor-btn" onclick="execEdit('${t.cmd}', '${t.val}')" title="${t.title}">${t.label}</button>`;
            else if (t.cmd === 'createLink') html += `<button type="button" class="editor-btn" onclick="execLink()" title="${t.title}">${t.label}</button>`;
            else html += `<button type="button" class="editor-btn" onclick="execEdit('${t.cmd}')" title="${t.title}">${t.label}</button>`;
        });
        container.innerHTML = html;
    }
    function execEdit(cmd, val = null) { document.execCommand(cmd, false, val); }
    function execLink() { const url = prompt('Įveskite nuorodą:'); if (url) document.execCommand('createLink', false, url); }
    createToolbar('mainDescToolbar');

    // --- ATTRIBUTES & VARIATIONS ---
    // Funkcijos perkeltos į window scope, kad būtų pasiekiamos
    window.addRichAttrRow = function(label = '', valueHtml = '') {
        const container = document.getElementById('attributesContainer');
        const uniqueId = 'attr_editor_' + Date.now() + Math.floor(Math.random() * 1000);
        const div = document.createElement('div');
        div.className = 'attr-row';
        div.innerHTML = `
            <div><input type="text" name="attr_label[]" class="form-control" placeholder="Savybė" value="${label.replace(/"/g, '&quot;')}"></div>
            <div class="rich-editor-wrapper mini-editor">
                <div class="editor-toolbar" id="toolbar_${uniqueId}"></div>
                <div class="editor-content" id="${uniqueId}" contenteditable="true" placeholder="Reikšmė...">${valueHtml}</div>
                <textarea name="attr_value[]" hidden></textarea>
            </div>
            <button type="button" onclick="this.parentElement.remove()" style="color:red; border:none; background:none; cursor:pointer; font-size:20px;">&times;</button>
        `;
        container.appendChild(div);
        createToolbar('toolbar_' + uniqueId);
    };

    window.addVarRow = function(name = '', price = '') {
        const container = document.getElementById('variationsContainer');
        const div = document.createElement('div');
        div.style.cssText = "display:grid; grid-template-columns: 1fr 100px 40px; gap:10px; margin-bottom:8px;";
        div.innerHTML = `
            <input name="variation_name[]" class="form-control" placeholder="Pavadinimas (pvz. Raudona)" value="${name.replace(/"/g, '&quot;')}">
            <input name="variation_price[]" type="number" step="0.01" class="form-control" placeholder="+/- €" value="${price}">
            <button type="button" onclick="this.parentElement.remove()" style="color:red; border:none; background:none; cursor:pointer; font-size:20px;">&times;</button>
        `;
        container.appendChild(div);
    };

    // --- MODAL ---
    const modal = document.getElementById('productModal');
    window.openProductModal = function(mode, data = null) {
        document.getElementById('formAction').value = 'save_product';
        document.getElementById('productId').value = '';
        document.querySelector('form.modal-window').reset();
        
        // Reset dynamic areas
        document.getElementById('mainDescEditor').innerHTML = '';
        document.getElementById('attributesContainer').innerHTML = '';
        document.getElementById('variationsContainer').innerHTML = '';
        document.getElementById('modalImgPreview').innerHTML = '';
        document.getElementById('existingImgContainer').innerHTML = '';
        
        // Uncheck all categories
        document.querySelectorAll('.cat-check').forEach(cb => cb.checked = false);
        
        window.switchTab('basic');

        if (mode === 'create') {
            document.getElementById('modalTitle').innerText = 'Nauja prekė';
            addRichAttrRow();
        } else if (mode === 'edit' && data) {
            document.getElementById('modalTitle').innerText = 'Redaguoti prekę: ' + data.title;
            document.getElementById('productId').value = data.id;
            
            document.getElementById('p_title').value = data.title;
            document.getElementById('p_subtitle').value = data.subtitle || '';
            document.getElementById('p_ribbon').value = data.ribbon_text || '';
            document.getElementById('mainDescEditor').innerHTML = data.description;
            document.getElementById('p_price').value = data.price;
            document.getElementById('p_sale_price').value = data.sale_price || '';
            document.getElementById('p_quantity').value = data.quantity;
            document.getElementById('p_meta_tags').value = data.meta_tags || '';
            
            // Categories (checkboxes)
            if (data.category_ids) {
                // data.category_ids gali būti masyvas skaičių
                data.category_ids.forEach(cid => {
                    const cb = document.querySelector(`.cat-check[value="${cid}"]`);
                    if(cb) cb.checked = true;
                });
            } else if (data.category_id) {
                // Fallback jei nėra category_ids
                const cb = document.querySelector(`.cat-check[value="${data.category_id}"]`);
                if(cb) cb.checked = true;
            }

            // Related Products
            if (data.related_ids) {
                const select = document.getElementById('p_related');
                Array.from(select.options).forEach(opt => {
                    opt.selected = data.related_ids.includes(parseInt(opt.value));
                });
            }

            // Attributes
            if (data.attributes && data.attributes.length > 0) {
                data.attributes.forEach(attr => addRichAttrRow(attr.label, attr.value));
            } else {
                addRichAttrRow();
            }

            // Variations
            if (data.variations && data.variations.length > 0) {
                data.variations.forEach(v => addVarRow(v.name, v.price_delta));
            }
            
            // Existing Images
            if (data.primary_image) {
                document.getElementById('existingImgContainer').innerHTML = 
                    `<img src="${data.primary_image}" style="width:60px; height:60px; object-fit:cover; border:1px solid #ddd; border-radius:4px;">`;
            }
        }
        
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('open'), 10);
    };

    window.closeProductModal = function() {
        modal.classList.remove('open');
        setTimeout(() => modal.style.display = 'none', 200);
    };

    // --- SUBMIT ---
    window.syncEditors = function() {
        document.getElementById('p_description').value = document.getElementById('mainDescEditor').innerHTML;
        document.querySelectorAll('#attributesContainer .attr-row').forEach(row => {
            const editor = row.querySelector('.editor-content');
            const textarea = row.querySelector('textarea[name="attr_value[]"]');
            if(editor && textarea) textarea.value = editor.innerHTML;
        });
        return true;
    };

    // --- TABS & HELPERS ---
    window.switchTab = function(id) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-' + id).classList.add('active');
        const btns = document.querySelectorAll('.tab-btn');
        // Simple mapping
        if(id==='basic') btns[0].classList.add('active');
        if(id==='specs') btns[1].classList.add('active');
        if(id==='prices') btns[2].classList.add('active');
        if(id==='media') btns[3].classList.add('active');
        if(id==='seo') btns[4].classList.add('active');
    };

    window.previewModalImages = function(input) {
        const container = document.getElementById('modalImgPreview');
        container.innerHTML = '';
        if (input.files) {
            Array.from(input.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.cssText = "width:60px; height:60px; object-fit:cover; border-radius:4px; border:1px solid #ddd;";
                    container.appendChild(img);
                }
                reader.readAsDataURL(file);
            });
        }
    };

    // --- BULK ---
    window.toggleAll = function(source) {
        document.querySelectorAll('.prod-check').forEach(c => c.checked = source.checked);
        updateBulkUI();
    };
    window.updateBulkUI = function() {
        const checked = document.querySelectorAll('.prod-check:checked').length;
        const panel = document.getElementById('bulkActionsPanel');
        document.getElementById('selectedCount').innerText = checked;
        if (checked > 0) panel.classList.add('visible'); else panel.classList.remove('visible');
    };
    window.submitBulkDelete = function() {
        if (confirm('Ar tikrai norite ištrinti pasirinktas prekes?')) {
            document.getElementById('productsListForm').submit();
        }
    };
    window.filterTable = function() {
        const filter = document.getElementById('tableSearch').value.toUpperCase();
        document.querySelectorAll('#productsTable tbody tr').forEach(tr => {
            tr.style.display = tr.innerText.toUpperCase().indexOf(filter) > -1 ? '' : 'none';
        });
    };
</script>
