<?php
// admin/shipping.php

$shipping = getShippingSettings($pdo);
$lockerNetworks = getLockerNetworks($pdo);
$products = $pdo->query('SELECT id, title FROM products ORDER BY title')->fetchAll(); 
$freeShippingProductIds = getFreeShippingProductIds($pdo);
?>

<style>
    .section-title { font-size: 16px; font-weight: 700; margin: 0 0 4px 0; color: var(--text-main); display:flex; align-items:center; gap:8px; }
    .section-subtitle { font-size: 12px; color: var(--text-muted); margin: 0 0 16px 0; }
    
    .form-group { margin-bottom: 12px; }
    .form-label { display: block; font-size: 12px; font-weight: 600; color: var(--text-main); margin-bottom: 4px; }
    .form-control { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; transition: border-color 0.15s; }
    .form-control:focus { border-color: #4f46e5; outline: none; }
    
    .locker-item { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 8px; transition: box-shadow 0.2s; }
    .locker-item:hover { box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    
    .badge-omniva { background: #ffedd5; color: #c2410c; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .badge-lpexpress { background: #eff6ff; color: #1d4ed8; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
</style>

<div class="grid grid-2">
    
    <div class="card">
        <h3 class="section-title">🚚 Pristatymo įkainiai</h3>
        <p class="section-subtitle">Nustatykite bazines kainas pirkėjams.</p>
        
        <form method="post">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="shipping_save">
            
            <div class="form-group">
                <label class="form-label">Kurjerio kaina (€)</label>
                <input name="shipping_courier" type="number" step="0.01" min="0" class="form-control" 
                       value="<?php echo htmlspecialchars($shipping['courier_price'] ?? $shipping['base_price'] ?? 3.99); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Paštomato kaina (€)</label>
                <input name="shipping_locker" type="number" step="0.01" min="0" class="form-control" 
                       value="<?php echo htmlspecialchars($shipping['locker_price'] ?? 2.49); ?>">
            </div>
            
            <div class="form-group" style="padding-top:10px; border-top:1px dashed #eee;">
                <label class="form-label" style="color:#059669;">Nemokamas pristatymas nuo (€)</label>
                <input name="shipping_free_over" type="number" step="0.01" min="0" class="form-control" 
                       value="<?php echo htmlspecialchars($shipping['free_over'] ?? ''); ?>" placeholder="Palikti tuščią, jei netaikoma">
                <small style="color:#9ca3af; font-size:11px;">Jei krepšelio suma viršys šią ribą, pristatymas bus nemokamas.</small>
            </div>
            
            <div style="text-align:right; margin-top:16px;">
                <button class="btn" type="submit">Išsaugoti kainas</button>
            </div>
        </form>
    </div>

    <div class="card" style="background:#f0fdf4; border-color:#bbf7d0;">
        <h3 class="section-title" style="color:#166534;">🎁 Specialūs pasiūlymai</h3>
        <p class="section-subtitle" style="color:#15803d;">Pirkdami šias prekes klientai gaus nemokamą pristatymą.</p>
        
        <form method="post">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="shipping_free_products">
            
            <div style="display:grid; gap:10px;">
                <?php for ($i = 0; $i < 4; $i++): $current = $freeShippingProductIds[$i] ?? ''; ?>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:12px; font-weight:700; color:#166534; width:20px;">#<?php echo $i + 1; ?></span>
                    <select name="promo_products[]" class="form-control" style="border-color:#bbf7d0;">
                        <option value="">— Nepasirinkta —</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>" <?php echo (int)$current === (int)$p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endfor; ?>
            </div>
            
            <div style="text-align:right; margin-top:16px;">
                <button class="btn" type="submit" style="background:#166534; border-color:#166534; color:#fff;">Atnaujinti pasiūlymus</button>
            </div>
        </form>
    </div>
</div>

<div class="grid grid-2" style="margin-top:24px;">
    <div class="card">
        <h3 class="section-title">➕ Pridėti paštomatą</h3>
        <p class="section-subtitle">Rankinis naujo terminalo įvedimas.</p>
        
        <form method="post">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="locker_new">
            
            <div class="form-group">
                <label class="form-label">Tinklas</label>
                <select name="locker_provider" class="form-control" required>
                    <option value="">Pasirinkite</option>
                    <option value="omniva">Omniva</option>
                    <option value="lpexpress">LP Express</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Pavadinimas</label>
                <input name="locker_title" class="form-control" placeholder="Pvz. Vilnius Akropolis" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Adresas</label>
                <input name="locker_address" class="form-control" placeholder="Pvz. Ozo g. 25, Vilnius" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Pastabos (Nebūtina)</label>
                <input name="locker_note" class="form-control" placeholder="Papildoma info">
            </div>
            
            <button class="btn secondary" type="submit" style="width:100%;">Pridėti</button>
        </form>
    </div>

    <div class="card">
        <h3 class="section-title">📥 Importuoti iš failo</h3>
        <p class="section-subtitle">Masinis įkėlimas iš .xlsx failo.</p>
        
        <form method="post" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="locker_import">
            
            <div class="form-group">
                <label class="form-label">Tinklas</label>
                <select name="locker_provider" class="form-control" required>
                    <option value="">Pasirinkite</option>
                    <option value="omniva">Omniva</option>
                    <option value="lpexpress">LP Express</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Failas (.xlsx)</label>
                <input type="file" name="locker_file" accept=".xlsx" class="form-control" required style="padding:6px;">
            </div>
            
            <div style="background:#f9fafb; padding:10px; border-radius:6px; font-size:11px; color:#6b7280; margin-bottom:12px; line-height:1.4;">
                <strong>Reikalingi stulpeliai:</strong><br>
                Omniva: <em>Pašto kodas, Pavadinimas, Miestas, Gatvė...</em><br>
                LP Express: <em>Miestas, ID, Pavadinimas, Adresas...</em>
            </div>
            
            <button class="btn secondary" type="submit" style="width:100%;">Importuoti duomenis</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top:24px;">
    <h3 class="section-title">📍 Esami paštomatai</h3>
    
    <?php if (!$lockerNetworks): ?>
        <div style="padding:20px; text-align:center; color:#9ca3af; border:1px dashed #e5e7eb; border-radius:8px;">
            Paštomatų sąrašas tuščias. Importuokite arba pridėkite rankiniu būdu.
        </div>
    <?php else: ?>
        <div class="grid grid-2" style="align-items:start;">
            <?php foreach ($lockerNetworks as $providerKey => $list): ?>
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; padding-bottom:5px; border-bottom:2px solid #f3f4f6;">
                        <h4 style="margin:0; text-transform:uppercase; font-size:13px; color:#4b5563;">
                            <?php echo htmlspecialchars($providerKey === 'omniva' ? 'Omniva' : 'LP Express'); ?>
                        </h4>
                        <span class="badge-<?php echo $providerKey; ?>" style="background:#f3f4f6; color:#6b7280;">Viso: <?php echo count($list); ?></span>
                    </div>
                    
                    <div style="max-height:400px; overflow-y:auto; padding-right:5px;">
                        <?php foreach ($list as $loc): ?>
                            <div class="locker-item">
                                <form method="post">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="locker_update">
                                    <input type="hidden" name="locker_id" value="<?php echo (int)$loc['id']; ?>">
                                    <input type="hidden" name="locker_provider" value="<?php echo htmlspecialchars($loc['provider']); ?>">
                                    
                                    <div style="margin-bottom:6px;">
                                        <input name="locker_title" value="<?php echo htmlspecialchars($loc['title']); ?>" 
                                               class="form-control" style="font-weight:600; border:none; padding:0; background:transparent; font-size:13px;" title="Pavadinimas">
                                    </div>
                                    
                                    <div style="display:flex; gap:6px; margin-bottom:6px;">
                                        <input name="locker_address" value="<?php echo htmlspecialchars($loc['address']); ?>" 
                                               class="form-control" style="font-size:12px; padding:4px 8px;">
                                    </div>
                                    
                                    <div style="display:flex; gap:6px;">
                                        <input name="locker_note" value="<?php echo htmlspecialchars($loc['note'] ?? ''); ?>" 
                                               class="form-control" placeholder="Pastaba" style="font-size:12px; padding:4px 8px; color:#6b7280;">
                                        <button type="submit" class="btn secondary" style="padding:4px 8px; font-size:11px;">Išsaugoti</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
