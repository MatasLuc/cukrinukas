<?php
// admin/community.php

// Paimame kategorijas ir temas (jei reikia statistikų)
$categories = $pdo->query('SELECT * FROM community_categories ORDER BY created_at ASC')->fetchAll();

// Galime paimti naujausias temas bendrai apžvalgai
$recentThreads = $pdo->query('
    SELECT t.*, u.name as author, c.name as category_name 
    FROM community_threads t 
    LEFT JOIN users u ON t.user_id = u.id 
    LEFT JOIN community_categories c ON t.category_id = c.id
    ORDER BY t.created_at DESC LIMIT 10
')->fetchAll();
?>

<style>
    .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .cat-card { 
        background: #fff; border: 1px solid #e1e3ef; border-radius: 12px; padding: 16px; 
        transition: 0.2s; position: relative;
    }
    .cat-card:hover { border-color: #4f46e5; box-shadow: 0 4px 12px rgba(79,70,229,0.08); }
    .cat-title { font-weight: 700; font-size: 16px; margin-bottom: 4px; }
    .cat-desc { font-size: 13px; color: #6b6b7a; line-height: 1.4; margin-bottom: 12px; min-height: 36px;}
    
    /* Modal styles (kartojasi, kad veiktų atskirai) */
    .modal-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
        z-index: 1000; display: none; align-items: center; justify-content: center;
        padding: 20px; opacity: 0; transition: opacity 0.2s ease;
    }
    .modal-overlay.open { display: flex; opacity: 1; }
    .modal-window {
        background: #fff; width: 100%; max-width: 500px;
        border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); padding: 24px;
    }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
    <h2>Bendruomenės valdymas</h2>
    <button class="btn" onclick="openCatModal()">+ Nauja kategorija</button>
</div>

<h3 class="muted" style="font-size:12px; text-transform:uppercase; margin-bottom:12px;">Diskusijų kategorijos</h3>
<div class="cat-grid">
    <?php foreach ($categories as $cat): ?>
        <div class="cat-card">
            <div class="cat-title"><?php echo htmlspecialchars($cat['name']); ?></div>
            <div class="cat-desc"><?php echo htmlspecialchars($cat['description']); ?></div>
            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f0f0f5; padding-top:10px;">
                <span class="muted" style="font-size:11px;">ID: <?php echo $cat['id']; ?></span>
                <div style="display:flex; gap:6px;">
                    <button class="btn secondary" style="padding:4px 8px; font-size:12px;" 
                            onclick='openCatModal(<?php echo json_encode($cat); ?>)'>Redaguoti</button>
                    
                    <form method="post" onsubmit="return confirm('Trinti kategoriją?');" style="margin:0;">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="delete_community_category">
                        <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                        <button type="submit" class="btn" style="padding:4px 8px; font-size:12px; background:#fff1f1; color:#b91c1c; border-color:#fecaca;">&times;</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h3>Naujausios diskusijos</h3>
    <table>
        <thead>
            <tr>
                <th>Tema</th>
                <th>Kategorija</th>
                <th>Autorius</th>
                <th>Data</th>
                <th>Veiksmai</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentThreads as $thread): ?>
                <tr>
                    <td><a href="/community_thread.php?id=<?php echo $thread['id']; ?>" target="_blank" style="font-weight:600; text-decoration:underline;"><?php echo htmlspecialchars($thread['title']); ?></a></td>
                    <td><span class="muted" style="font-size:12px;"><?php echo htmlspecialchars($thread['category_name']); ?></span></td>
                    <td><?php echo htmlspecialchars($thread['author']); ?></td>
                    <td><?php echo date('Y-m-d', strtotime($thread['created_at'])); ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Trinti temą?');">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="delete_community_thread">
                            <input type="hidden" name="id" value="<?php echo $thread['id']; ?>">
                            <button class="btn" style="padding:4px 10px; font-size:12px; background:#f3f4f6; color:#111;">Ištrinti</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$recentThreads): ?>
                <tr><td colspan="5" class="muted">Diskusijų dar nėra.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="catModal" class="modal-overlay">
    <div class="modal-window">
        <div style="display:flex; justify-content:space-between; margin-bottom:16px;">
            <h3 style="margin:0;" id="modalTitle">Nauja kategorija</h3>
            <button onclick="closeCatModal()" style="border:none; background:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form method="post">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="save_community_category">
            <input type="hidden" name="id" id="c_id" value="">
            
            <label>Pavadinimas</label>
            <input type="text" name="name" id="c_name" required placeholder="pvz. Receptai">
            
            <label>Aprašymas</label>
            <textarea name="description" id="c_desc" rows="3" placeholder="Trumpas aprašymas..."></textarea>
            
            <div style="text-align:right; margin-top:16px;">
                <button type="button" class="btn secondary" onclick="closeCatModal()">Atšaukti</button>
                <button type="submit" class="btn">Išsaugoti</button>
            </div>
        </form>
    </div>
</div>

<script>
    const catModal = document.getElementById('catModal');
    const cId = document.getElementById('c_id');
    const cName = document.getElementById('c_name');
    const cDesc = document.getElementById('c_desc');
    const modalTitle = document.getElementById('modalTitle');

    function openCatModal(data = null) {
        if (data) {
            modalTitle.innerText = 'Redaguoti kategoriją';
            cId.value = data.id;
            cName.value = data.name;
            cDesc.value = data.description;
        } else {
            modalTitle.innerText = 'Nauja kategorija';
            cId.value = '';
            cName.value = '';
            cDesc.value = '';
        }
        catModal.style.display = 'flex';
        setTimeout(() => catModal.classList.add('open'), 10);
    }

    function closeCatModal() {
        catModal.classList.remove('open');
        setTimeout(() => catModal.style.display = 'none', 200);
    }
    
    catModal.addEventListener('click', e => {
        if(e.target === catModal) closeCatModal();
    });
</script>
