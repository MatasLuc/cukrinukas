<?php
// Įjungiame klaidų rodymą laikinai, kad matytumėte, jei kas nors dar negerai
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureCommunityTables($pdo); // Užtikrina, kad lentelės egzistuoja
tryAutoLogin($pdo);
$user = currentUser();

// --- LOGIKA ---
$types = ['Siūlau', 'Ieškau', 'Dovanoju'];
$typeFilter = $_GET['type'] ?? null;
if ($typeFilter && !in_array($typeFilter, $types)) $typeFilter = null;

$where = "WHERE m.status = 'active'";
$params = [];

if ($typeFilter) {
    // Filtruojame pagal kategorijos pavadinimą iš prijungtos lentelės
    $where .= " AND c.name = ?";
    $params[] = $typeFilter;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Skaičiuojame kiekį
$countSql = "SELECT COUNT(*) 
             FROM community_listings m 
             LEFT JOIN community_listing_categories c ON m.category_id = c.id 
             $where";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Pagrindinė užklausa
// SVARBU: Naudojame u.name, nes u.username lentelėje users neegzistuoja
$sql = "
    SELECT m.*, u.name as username, c.name as type_name
    FROM community_listings m
    LEFT JOIN users u ON m.user_id = u.id
    LEFT JOIN community_listing_categories c ON m.category_id = c.id
    $where
    ORDER BY m.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

echo headerStyles();
?>
<style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text-main: #0f172a;
      --text-muted: #475467;
      --accent: #16a34a;
      --accent-hover: #15803d;
    }
    * { box-sizing: border-box; }
    body { margin:0; background: var(--bg); color: var(--text-main); font-family: 'Inter', sans-serif; }
    a { color:inherit; text-decoration:none; transition: color .2s; }
    
    .page { max-width: 1200px; margin:0 auto; padding:32px 20px 72px; display:flex; flex-direction:column; gap:32px; }

    /* Hero */
    .hero { 
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border:1px solid #dcfce7; 
        border-radius:24px; 
        padding:40px; 
        display:flex; 
        align-items:center; 
        justify-content:space-between; 
        gap:32px; 
        flex-wrap:wrap; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .hero-content { max-width: 600px; flex: 1; }
    .hero h1 { margin:0 0 12px; font-size:32px; color:#14532d; letter-spacing:-0.5px; }
    .hero p { margin:0; color:#15803d; line-height:1.6; font-size:16px; }
    
    .pill { 
        display:inline-flex; align-items:center; gap:8px; 
        padding:6px 12px; border-radius:999px; 
        background:#fff; border:1px solid #bbf7d0; 
        font-weight:600; font-size:13px; color:#15803d; 
        margin-bottom: 16px;
    }

    .hero-card {
        background: #fff;
        border: 1px solid rgba(255,255,255,0.8);
        padding: 24px;
        border-radius: 20px;
        width: 100%;
        max-width: 300px;
        box-shadow: 0 10px 25px -5px rgba(22, 163, 74, 0.15);
        text-align: center;
        flex-shrink: 0;
    }
    .hero-card h3 { margin: 0 0 8px; font-size: 18px; color: var(--text-main); }
    .hero-card p { margin: 0 0 16px; font-size: 13px; color: var(--text-muted); line-height: 1.4; }

    /* Filters */
    .filter-bar {
        display: flex; gap: 10px; overflow-x: auto; padding-bottom: 4px; align-items: center;
    }
    .filter-chip {
        padding: 8px 16px; border-radius: 99px; background: #fff; border: 1px solid var(--border);
        color: var(--text-muted); font-size: 14px; font-weight: 500; white-space: nowrap;
    }
    .filter-chip:hover { border-color: var(--accent); color: var(--accent); }
    .filter-chip.active { background: var(--accent); color: #fff; border-color: var(--accent); }

    /* Grid */
    .market-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 24px;
    }
    .item-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 20px;
        overflow: hidden;
        display: flex; flex-direction: column;
        transition: transform .2s, box-shadow .2s;
        height: 100%;
    }
    .item-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1);
        border-color: #cbd5e1;
    }
    .item-image {
        height: 200px; width: 100%;
        background: #f1f5f9;
        display: flex; align-items: center; justify-content: center;
        font-size: 48px; color: #cbd5e1;
        overflow: hidden;
        border-bottom: 1px solid var(--border);
    }
    .item-image img { width: 100%; height: 100%; object-fit: cover; }
    .item-body { padding: 20px; flex: 1; display: flex; flex-direction: column; gap: 8px; }
    
    .item-badge {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        padding: 4px 8px; border-radius: 6px; align-self: flex-start;
        letter-spacing: 0.5px; margin-bottom: 4px;
    }
    .badge-siulau { background: #dcfce7; color: #166534; }
    .badge-ieskau { background: #dbeafe; color: #1e40af; }
    .badge-dovanoju { background: #fef9c3; color: #854d0e; }
    .badge-kita { background: #f1f5f9; color: #475467; }

    .item-title { font-size: 18px; font-weight: 700; margin: 0; color: var(--text-main); line-height: 1.3; }
    .item-price { font-size: 18px; font-weight: 700; color: var(--accent); margin-top: auto; padding-top: 12px; }
    .item-meta { font-size: 13px; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }

    .btn { 
        padding:10px 20px; border-radius:10px; border:none;
        background: #16a34a; color:#fff; font-weight:600; font-size:14px;
        cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; justify-content:center;
        transition: all .2s; width: 100%;
    }
    .btn:hover { background: #15803d; transform: translateY(-1px); }
    .btn-outline { 
        padding:8px 12px; border-radius:8px; border: 1px solid var(--border);
        background: #fff; color: var(--text-main); text-decoration: none; display: inline-block;
    }
    .empty-state {
        grid-column: 1 / -1;
        text-align: center; padding: 64px 20px;
        background: #fff; border-radius: 20px; border: 1px dashed var(--border);
    }

    @media (max-width: 700px) {
        .hero { padding: 24px; flex-direction: column; align-items: stretch; }
        .hero-card { max-width: 100%; }
    }
</style>

<?php renderHeader($pdo, 'community'); ?>

<div class="page">
    <section class="hero">
        <div class="hero-content">
            <div class="pill">🛍️ Turgelis</div>
            <h1>Bendruomenės turgus</h1>
            <p>Mainykitės, parduokite nereikalingus daiktus ar ieškokite pagalbos. Tvarus būdas dalintis.</p>
        </div>
        <div class="hero-card">
            <?php if ($user['id']): ?>
                <h3>Turite ką pasiūlyti?</h3>
                <p>Įdėkite skelbimą nemokamai ir pasiekite bendruomenės narius.</p>
                <a href="/community_listing_new.php" class="btn">Įdėti skelbimą</a>
                <div style="margin-top:12px;">
                    <a href="/account.php" style="font-size:13px; color:var(--text-muted); text-decoration:underline;">Mano skelbimai</a>
                </div>
            <?php else: ?>
                <h3>Prisijunkite</h3>
                <p>Norėdami dėti skelbimus ar matyti kontaktus, turite prisijungti.</p>
                <a href="/login.php" class="btn">Prisijunkite</a>
            <?php endif; ?>
        </div>
    </section>

    <div>
        <div class="filter-bar">
            <a href="/community_market.php" class="filter-chip <?php echo !$typeFilter ? 'active' : ''; ?>">Visi skelbimai</a>
            <?php foreach ($types as $type): ?>
                <a href="?type=<?php echo urlencode($type); ?>" class="filter-chip <?php echo $typeFilter === $type ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($type); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="empty-state">
            <div style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">🏷️</div>
            <h3 style="margin: 0 0 8px; font-size: 18px;">Skelbimų nerasta</h3>
            <p style="color: var(--text-muted); margin: 0 0 24px; font-size: 15px;">Šiuo metu aktyvių skelbimų nėra.</p>
            <?php if ($user['id']): ?>
                <a class="btn" href="/community_listing_new.php" style="width:auto;">Įdėti pirmą skelbimą</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="market-grid">
            <?php foreach ($items as $item): 
                // Apsauga, jei type_name būtų NULL
                $typeName = !empty($item['type_name']) ? $item['type_name'] : 'Kita';
                $safeType = strtolower(str_replace(
                    ['ą','č','ę','ė','į','š','ų','ū','ž'], 
                    ['a','c','e','e','i','s','u','u','z'], 
                    $typeName
                ));
                $badgeClass = 'badge-' . $safeType;
                // Jei klasės nėra CSS'e, naudos default (per fallback, arba tiesiog neturės stiliaus)
                
                $itemUrl = '/community_listing.php?id=' . $item['id'];
            ?>
            <article class="item-card">
                <a href="<?php echo $itemUrl; ?>" class="item-image">
                    <?php if (!empty($item['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                    <?php else: ?>
                        <span>📷</span>
                    <?php endif; ?>
                </a>
                <div class="item-body">
                    <span class="item-badge <?php echo htmlspecialchars($badgeClass); ?>">
                        <?php echo htmlspecialchars($typeName); ?>
                    </span>
                    <a href="<?php echo $itemUrl; ?>" class="item-title">
                        <?php echo htmlspecialchars($item['title']); ?>
                    </a>
                    
                    <div class="item-price">
                        <?php echo ($item['price'] > 0) ? number_format($item['price'], 2) . ' €' : 'Nemokamai / Sutartinė'; ?>
                    </div>
                    
                    <div class="item-meta">
                        <span>👤 <?php echo htmlspecialchars($item['username'] ?: 'Narys'); ?></span>
                        <span><?php echo date('m-d', strtotime($item['created_at'])); ?></span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="display:flex; gap:8px; justify-content:center; margin-top:32px;">
                <?php for ($i=1; $i<=$totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo $typeFilter ? '&type='.urlencode($typeFilter) : ''; ?>" 
                       class="btn-outline" 
                       style="<?php echo $i===$page ? 'background:var(--accent); color:#fff; border-color:var(--accent);' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php renderFooter($pdo); ?>
