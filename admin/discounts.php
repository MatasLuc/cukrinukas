<?php
// admin/discounts.php

// 1. Gauname kategorijas
$allCategories = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();

// 2. Gauname aktyvias nuolaidas
// Pastaba: Jei jūsų sistema saugo nuolaidas tiesiog 'categories' lentelėje stulpeliuose,
// čia atrenkame tik tas, kurios turi nuolaidą.
// Jei naudojate atskirą lentelę 'category_discounts', užklausa būtų kitokia.
// Darau prielaidą pagal jūsų seną kodą, kad tai 'categories' lentelės laukai arba helperis.
// Čia naudosime tiesioginę užklausą į categories lentelę, kur discount_type nėra NULL.

$activeDiscounts = $pdo->query("
    SELECT * FROM categories 
    WHERE discount_type IS NOT NULL AND discount_type != '' 
    ORDER BY discount_value DESC
")->fetchAll();
?>

<style>
    /* Kortelės ir lentelės */
    .discount-card {
        background: #fff; border: 1px solid #e1e3ef; border-radius: 12px; padding: 20px;
        display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;
        transition: 0.2s;
    }
    .discount-card:hover { border-color: #4f46e5; box-shadow: 0 4px 12px rgba(79,70,229,0.05); }
    
    .disc-icon {
        width: 48px; height: 48px; background: #fdf2f8; color: #db2777;
        border-radius: 10px; display: flex; align-items: center; justify-content: center;
        font-size: 20px; margin-right: 16px;
    }
    .disc-info h4 { margin: 0 0 4px 0; font-size: 16px; }
    .disc-info p { margin: 0; font-size: 13px; color: #6b6b7a; }
    
    .badge-percent { background: #ecfdf5; color: #047857; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 12px; border:1px solid #d1fae5; }
    .badge-amount { background: #eff6ff; color: #1d4ed8; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 12px; border:1px solid #dbeafe; }

    /* Modal */
    .modal-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); z-index: 1000;
        display: none; align-items: center; justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-window {
        background: #fff; width: 100%; max-width: 450px;
        border-radius: 16px; padding: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
    <div>
        <h2>Nuolaidų valdymas</h2>
        <p class="muted" style="margin:0;">Tvarkykite kategorijų nuolaidas ir akcijas.</p>
    </div>
    <button class="btn" onclick="openDiscModal()">+ Pridėti nuolaidą</button>
</div>

<div class="card">
    <h3 style="margin-bottom:16px;">Aktyvios kategorijų akcijos</h3>
    
    <?php if (empty($activeDiscounts)): ?>
        <div style="text-align:center; padding:30px; color:#94a3b8; border:1px dashed #e2e8f0; border-radius:12px;">
            Šiuo metu aktyvių nuolaidų nėra.
        </div>
    <?php else: ?>
        <?php foreach ($activeDiscounts as $disc): ?>
            <div class="discount-card">
                <div style="display:flex; align-items:center;">
                    <div class="disc-icon">🏷️</div>
                    <div class="disc-info">
                        <h4><?php echo htmlspecialchars($disc['name']); ?></h4>
                        <?php 
                            if ($disc['discount_type'] === 'percent') {
                                echo '<span class="badge-percent">-' . (float)$disc['discount_value'] . '% Nuolaida</span>';
                            } else {
                                echo '<span class="badge-amount">-' . number_format($disc['discount_value'], 2) . ' € Nuolaida</span>';
                            }
                        ?>
                    </div>
                </div>
                
                <div style="display:flex; gap:8px;">
                    <button class="btn secondary" style="padding:8px 14px;" 
                            onclick='openDiscModal(<?php echo json_encode($disc); ?>)'>Redaguoti</button>
                    
                    <form method="post" onsubmit="return confirm('Panaikinti nuolaidą šiai kategorijai?');" style="margin:0;">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="remove_category_discount">
                        <input type="hidden" name="category_id" value="<?php echo $disc['id']; ?>">
                        <button class="btn" style="padding:8px 14px; background:#fff1f1; color:#b91c1c; border-color:#fecaca;">&times;</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="discModal" class="modal-overlay">
    <div class="modal-window">
        <div style="display:flex; justify-content:space-between; margin-bottom:16px;">
            <h3 style="margin:0;" id="modalTitle">Kategorijos nuolaida</h3>
            <button onclick="closeDiscModal()" style="border:none; background:none; font-size:24px; cursor:pointer;">&times;</button>
        </div>
        
        <form method="post">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="save_category_discount">
            
            <div style="margin-bottom:16px;">
                <label style="font-weight:700; font-size:12px; color:#6b6b7a; text-transform:uppercase;">Kategorija</label>
                <select name="category_id" id="d_category" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-top:6px;">
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="muted" style="font-size:11px; margin-top:4px;">Pasirinkite kategoriją, kuriai taikysite nuolaidą.</p>
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <label style="font-weight:700; font-size:12px; color:#6b6b7a; text-transform:uppercase;">Tipas</label>
                    <select name="discount_type" id="d_type" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-top:6px;">
                        <option value="percent">Procentai (%)</option>
                        <option value="amount">Fiksuota suma (€)</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight:700; font-size:12px; color:#6b6b7a; text-transform:uppercase;">Reikšmė</label>
                    <input type="number" step="0.01" name="discount_value" id="d_value" required placeholder="pvz. 20" 
                           style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-top:6px;">
                </div>
            </div>

            <div style="text-align:right; margin-top:24px;">
                <button type="button" class="btn secondary" onclick="closeDiscModal()">Atšaukti</button>
                <button type="submit" class="btn">Išsaugoti nuolaidą</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('discModal');
    const catSelect = document.getElementById('d_category');
    
    function openDiscModal(data = null) {
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('open'), 10);
        
        if (data) {
            document.getElementById('modalTitle').innerText = 'Redaguoti nuolaidą';
            catSelect.value = data.id;
            // Jei redaguojame, neleidžiame keisti kategorijos, kad nesupainiotume (optional)
            // catSelect.style.pointerEvents = 'none'; 
            // catSelect.style.background = '#f9f9f9';
            
            document.getElementById('d_type').value = data.discount_type;
            document.getElementById('d_value').value = data.discount_value;
        } else {
            document.getElementById('modalTitle').innerText = 'Nauja nuolaida';
            catSelect.style.pointerEvents = 'auto';
            catSelect.style.background = '#fff';
            document.getElementById('d_value').value = '';
        }
    }

    function closeDiscModal() {
        modal.classList.remove('open');
        setTimeout(() => modal.style.display = 'none', 200);
    }
    
    modal.addEventListener('click', e => {
        if(e.target === modal) closeDiscModal();
    });
</script>
