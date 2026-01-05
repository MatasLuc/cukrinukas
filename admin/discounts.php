<?php
// admin/discounts.php

// 1. Gauname duomenis
$discountCodes = getAllDiscountCodes($pdo);
$globalDiscount = getGlobalDiscount($pdo);

// 2. Pataisyta užklausa: jungiame categories su category_discounts
$activeCatDiscounts = $pdo->query("
    SELECT c.id, c.name, d.type as discount_type, d.value as discount_value, d.free_shipping
    FROM categories c
    JOIN category_discounts d ON c.id = d.category_id
    WHERE d.active = 1 
    AND (d.type IN ('percent', 'amount') OR d.free_shipping = 1)
    ORDER BY c.name ASC
")->fetchAll();

$allCategories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
?>

<style>
    /* Tab navigacija */
    .tab-nav { display:flex; gap:0; border-bottom:1px solid #e1e3ef; margin-bottom:24px; }
    .tab-btn {
        padding: 12px 24px; background:transparent; border:none; border-bottom:2px solid transparent;
        font-weight:600; color:#6b6b7a; cursor:pointer; font-size:14px; transition:0.2s;
    }
    .tab-btn:hover { color:#4f46e5; background:#f9f9ff; }
    .tab-btn.active { color:#4f46e5; border-bottom-color:#4f46e5; }

    .tab-content { display:none; animation: fadeIn 0.3s ease; }
    .tab-content.active { display:block; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:translateY(0); } }

    /* Kortelės */
    .disc-card {
        background: #fff; border: 1px solid #e1e3ef; border-radius: 12px; padding: 16px;
        margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;
        transition: 0.2s;
    }
    .disc-card:hover { border-color: #4f46e5; box-shadow: 0 4px 12px rgba(79,70,229,0.05); }
    
    .badge { padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 11px; border:1px solid transparent; text-transform:uppercase; }
    .badge-blue { background: #eff6ff; color: #1d4ed8; border-color: #dbeafe; }
    .badge-green { background: #ecfdf5; color: #047857; border-color: #d1fae5; }
    .badge-gray { background: #f3f4f6; color: #374151; border-color: #e5e7eb; }

    /* Modal */
    .modal-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); z-index: 1000;
        display: none; align-items: center; justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-window {
        background: #fff; width: 100%; max-width: 500px;
        border-radius: 16px; padding: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 12px; font-weight: 700; color: #6b6b7a; margin-bottom: 6px; text-transform: uppercase; }
    .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
    <h2>Nuolaidų valdymas</h2>
</div>

<div class="tab-nav">
    <button class="tab-btn active" onclick="switchTab('codes')">🎟️ Nuolaidų kodai</button>
    <button class="tab-btn" onclick="switchTab('categories')">🏷️ Kategorijų akcijos</button>
    <button class="tab-btn" onclick="switchTab('global')">⚙️ Bendra nuolaida</button>
</div>

<div id="tab-codes" class="tab-content active">
    <div style="text-align:right; margin-bottom:16px;">
        <button class="btn" onclick="openCodeModal()">+ Naujas kodas</button>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>Kodas</th><th>Tipas</th><th>Reikšmė</th><th>Limitai</th><th>Panaudota</th><th>Statusas</th><th>Veiksmai</th></tr></thead>
            <tbody>
                <?php foreach ($discountCodes as $code): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($code['code']); ?></strong></td>
                    <td>
                        <?php if($code['type'] == 'percent') echo 'Procentai'; ?>
                        <?php if($code['type'] == 'amount') echo 'Suma'; ?>
                        <?php if($code['type'] == 'free_shipping') echo 'Nemokamas siuntimas'; ?>
                    </td>
                    <td>
                        <?php if($code['type'] == 'percent') echo '-' . (float)$code['value'] . '%'; ?>
                        <?php if($code['type'] == 'amount') echo '-' . number_format($code['value'], 2) . ' €'; ?>
                        <?php if($code['type'] == 'free_shipping') echo '0.00 €'; ?>
                    </td>
                    <td><?php echo $code['usage_limit'] > 0 ? $code['usage_limit'] : '∞'; ?></td>
                    <td><?php echo (int)$code['used_count']; ?></td>
                    <td>
                        <?php if($code['active']): ?>
                            <span class="badge badge-green">Aktyvus</span>
                        <?php else: ?>
                            <span class="badge badge-gray">Išjungtas</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn secondary" style="padding:4px 8px; font-size:12px;" onclick='openCodeModal(<?php echo json_encode($code); ?>)'>Redaguoti</button>
                        <form method="post" onsubmit="return confirm('Trinti kodą?');" style="display:inline-block;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="delete_discount_code">
                            <input type="hidden" name="id" value="<?php echo $code['id']; ?>">
                            <button class="btn" style="padding:4px 8px; font-size:12px; background:#fff1f1; color:#b91c1c; border-color:#fecaca;">&times;</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($discountCodes)): ?>
                    <tr><td colspan="7" class="muted" style="text-align:center;">Nuolaidų kodų nėra.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="tab-categories" class="tab-content">
    <div style="text-align:right; margin-bottom:16px;">
        <button class="btn" onclick="openCatModal()">+ Pridėti akciją</button>
    </div>

    <div class="card">
        <?php if(empty($activeCatDiscounts)): ?>
            <div style="text-align:center; padding:20px; color:#999;">Aktyvių kategorijų nuolaidų nėra.</div>
        <?php else: ?>
            <?php foreach ($activeCatDiscounts as $catDisc): ?>
            <div class="disc-card">
                <div>
                    <div style="font-weight:700; font-size:15px;"><?php echo htmlspecialchars($catDisc['name']); ?></div>
                    <div class="muted" style="font-size:12px;">ID: <?php echo $catDisc['id']; ?></div>
                </div>
                <div>
                    <?php if($catDisc['free_shipping']): ?>
                        <span class="badge badge-green">Nemokamas siuntimas</span>
                    <?php endif; ?>
                    
                    <?php if($catDisc['discount_type'] == 'percent' && $catDisc['discount_value'] > 0): ?>
                        <span class="badge badge-blue">-<?php echo (float)$catDisc['discount_value']; ?>%</span>
                    <?php elseif($catDisc['discount_type'] == 'amount' && $catDisc['discount_value'] > 0): ?>
                        <span class="badge badge-blue">-<?php echo number_format($catDisc['discount_value'], 2); ?> €</span>
                    <?php endif; ?>
                </div>
                <div style="display:flex; gap:8px;">
                    <button class="btn secondary" style="padding:6px 12px; font-size:12px;" onclick='openCatModal(<?php echo json_encode($catDisc); ?>)'>Redaguoti</button>
                    <form method="post" onsubmit="return confirm('Panaikinti nuolaidą kategorijai?');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="remove_category_discount">
                        <input type="hidden" name="category_id" value="<?php echo $catDisc['id']; ?>">
                        <button class="btn" style="padding:6px 12px; font-size:12px; background:#fff1f1; color:#b91c1c; border-color:#fecaca;">&times;</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="tab-global" class="tab-content">
    <div class="card" style="max-width:600px; margin:0 auto;">
        <h3>Bendri krepšelio nustatymai</h3>
        <p class="muted" style="font-size:13px; margin-bottom:20px;">Ši nuolaida taikoma visam krepšeliui automatiškai.</p>
        
        <form method="post">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="save_global_discount">
            
            <div class="form-group">
                <label>Nuolaidos tipas</label>
                <select name="type" class="form-control" onchange="toggleGlobalVal(this.value)">
                    <option value="none" <?php echo $globalDiscount['type'] === 'none' ? 'selected' : ''; ?>>Išjungta</option>
                    <option value="percent" <?php echo $globalDiscount['type'] === 'percent' ? 'selected' : ''; ?>>Procentai (%)</option>
                    <option value="amount" <?php echo $globalDiscount['type'] === 'amount' ? 'selected' : ''; ?>>Fiksuota suma (€)</option>
                    <option value="free_shipping" <?php echo $globalDiscount['type'] === 'free_shipping' ? 'selected' : ''; ?>>Nemokamas pristatymas</option>
                </select>
            </div>
            
            <div class="form-group" id="globalValGroup">
                <label>Reikšmė</label>
                <input type="number" step="0.01" name="value" class="form-control" value="<?php echo htmlspecialchars($globalDiscount['value']); ?>">
            </div>
            
            <button type="submit" class="btn" style="width:100%;">Išsaugoti pakeitimus</button>
        </form>
    </div>
</div>

<div id="codeModal" class="modal-overlay">
    <div class="modal-window">
        <div style="display:flex; justify-content:space-between; margin-bottom:16px;">
            <h3 style="margin:0;" id="codeModalTitle">Nuolaidos kodas</h3>
            <button onclick="closeModal('codeModal')" style="border:none; background:none; font-size:24px; cursor:pointer;">&times;</button>
        </div>
        <form method="post">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="save_discount_code">
            <input type="hidden" name="id" id="c_id" value="">
            
            <div class="form-group">
                <label>Kodas</label>
                <input type="text" name="code" id="c_code" required class="form-control" style="font-weight:700; text-transform:uppercase;">
            </div>
            <div style="display:flex; gap:16px;">
                <div class="form-group" style="flex:1;">
                    <label>Tipas</label>
                    <select name="type" id="c_type" class="form-control" onchange="toggleCodeVal(this.value)">
                        <option value="percent">Procentai %</option>
                        <option value="amount">Suma €</option>
                        <option value="free_shipping">Nemokamas siuntimas</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1;" id="c_val_group">
                    <label>Reikšmė</label>
                    <input type="number" step="0.01" name="value" id="c_value" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Panaudojimų limitas (0 - neribota)</label>
                <input type="number" name="usage_limit" id="c_limit" class="form-control" value="0">
            </div>
            <div class="form-group">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="active" id="c_active" value="1" checked> Aktyvus
                </label>
            </div>
            <button type="submit" class="btn" style="width:100%;">Išsaugoti</button>
        </form>
    </div>
</div>

<div id="catModal" class="modal-overlay">
    <div class="modal-window">
        <div style="display:flex; justify-content:space-between; margin-bottom:16px;">
            <h3 style="margin:0;">Kategorijos akcija</h3>
            <button onclick="closeModal('catModal')" style="border:none; background:none; font-size:24px; cursor:pointer;">&times;</button>
        </div>
        <form method="post">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="save_category_discount">
            
            <div class="form-group">
                <label>Kategorija</label>
                <select name="category_id" id="cat_id" class="form-control">
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; gap:16px;">
                <div class="form-group" style="flex:1;">
                    <label>Tipas</label>
                    <select name="discount_type" id="cat_type" class="form-control" onchange="toggleCatVal(this.value)">
                        <option value="percent">Procentai %</option>
                        <option value="amount">Suma €</option>
                        <option value="free_shipping">Nemokamas siuntimas</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1;" id="cat_val_group">
                    <label>Reikšmė</label>
                    <input type="number" step="0.01" name="discount_value" id="cat_value" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn" style="width:100%;">Išsaugoti</button>
        </form>
    </div>
</div>

<script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById('tab-' + tabId).classList.add('active');
        const btns = document.querySelectorAll('.tab-btn');
        if(tabId === 'codes') btns[0].classList.add('active');
        if(tabId === 'categories') btns[1].classList.add('active');
        if(tabId === 'global') btns[2].classList.add('active');
        localStorage.setItem('admin_discounts_tab', tabId);
    }
    const savedTab = localStorage.getItem('admin_discounts_tab');
    if(savedTab) switchTab(savedTab);

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
        setTimeout(() => document.getElementById(id).style.display = 'none', 200);
    }

    function openCodeModal(data = null) {
        const modal = document.getElementById('codeModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('open'), 10);
        if (data) {
            document.getElementById('codeModalTitle').innerText = 'Redaguoti kodą';
            document.getElementById('c_id').value = data.id;
            document.getElementById('c_code').value = data.code;
            document.getElementById('c_type').value = data.type;
            document.getElementById('c_value').value = data.value;
            document.getElementById('c_limit').value = data.usage_limit;
            document.getElementById('c_active').checked = data.active == 1;
        } else {
            document.getElementById('codeModalTitle').innerText = 'Naujas kodas';
            document.getElementById('c_id').value = '';
            document.getElementById('c_code').value = '';
            document.getElementById('c_type').value = 'percent';
            document.getElementById('c_value').value = '';
            document.getElementById('c_limit').value = '0';
            document.getElementById('c_active').checked = true;
        }
        toggleCodeVal(document.getElementById('c_type').value);
    }

    function openCatModal(data = null) {
        const modal = document.getElementById('catModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('open'), 10);
        if (data) {
            document.getElementById('cat_id').value = data.id;
            document.getElementById('cat_type').value = data.discount_type || 'percent';
            document.getElementById('cat_value').value = data.discount_value;
            if(data.free_shipping == 1) document.getElementById('cat_type').value = 'free_shipping';
        } else {
            document.getElementById('cat_type').value = 'percent';
            document.getElementById('cat_value').value = '';
        }
        toggleCatVal(document.getElementById('cat_type').value);
    }

    function toggleCodeVal(val) {
        const g = document.getElementById('c_val_group');
        const i = document.getElementById('c_value');
        if (val === 'free_shipping') { i.disabled = true; i.value = '0'; g.style.opacity = '0.5'; } else { i.disabled = false; g.style.opacity = '1'; }
    }
    function toggleCatVal(val) {
        const g = document.getElementById('cat_val_group');
        const i = document.getElementById('cat_value');
        if (val === 'free_shipping') { i.disabled = true; i.value = '0'; g.style.opacity = '0.5'; } else { i.disabled = false; g.style.opacity = '1'; }
    }
    function toggleGlobalVal(val) {
        const g = document.getElementById('globalValGroup');
        const i = g.querySelector('input');
        if (val === 'free_shipping' || val === 'none') { i.disabled = true; i.value = '0'; g.style.opacity = '0.5'; } else { i.disabled = false; g.style.opacity = '1'; }
    }
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) { if (e.target === this) closeModal(this.id); });
    });
</script>
