<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureCommunityTables($pdo);
ensureNavigationTable($pdo);
tryAutoLogin($pdo);
$user = currentUser();

$messages = [];
$errors = [];
if (!empty($_SESSION['flash_success'])) {
    $messages[] = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $errors[] = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

echo headerStyles();
?>
<style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text-main: #0f172a;
      --text-muted: #475467;
      --accent: #2563eb;
      --accent-hover: #1d4ed8;
      --focus-ring: rgba(37, 99, 235, 0.2);
    }
    * { box-sizing: border-box; }
    body { margin:0; background: var(--bg); color: var(--text-main); font-family: 'Inter', sans-serif; }
    a { color:inherit; text-decoration:none; transition: color .2s; }
    
    /* Layout struktūra */
    .page { max-width: 1200px; margin:0 auto; padding:32px 20px 72px; display:flex; flex-direction:column; gap:28px; }

    /* Hero Section */
    .hero { 
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border:1px solid #dbeafe; 
        border-radius:24px; 
        padding:32px; 
        display:flex; 
        align-items:center; 
        justify-content:space-between; 
        gap:24px; 
        flex-wrap:wrap; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .hero h1 { margin:0 0 8px; font-size:28px; color:#1e3a8a; letter-spacing:-0.5px; }
    .hero p { margin:0; color:#1e40af; line-height:1.5; max-width:520px; font-size:15px; }
    
    .pill { 
        display:inline-flex; align-items:center; gap:8px; 
        padding:6px 12px; border-radius:999px; 
        background:#fff; border:1px solid #bfdbfe; 
        font-weight:600; font-size:13px; color:#1e40af; 
        margin-bottom: 12px;
    }

    .stat-card { 
        background:#fff; border:1px solid rgba(255,255,255,0.6); 
        padding:16px 20px; border-radius:16px; 
        min-width:160px; text-align:right;
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1);
    }
    .stat-card strong { display:block; font-size:20px; color:#1e3a8a; margin-bottom: 4px; }
    .stat-card span { color: #64748b; font-size:13px; font-weight: 500; }

    /* Main Grid Layout */
    .layout { display:grid; grid-template-columns: 1fr 320px; gap:24px; align-items:start; }
    @media(max-width: 900px){ .layout { grid-template-columns:1fr; } }

    /* Cards */
    .card { 
        background:var(--card); 
        border:1px solid var(--border); 
        border-radius:20px; 
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transition: transform .2s, box-shadow .2s;
        height: 100%;
        display: flex; flex-direction: column;
    }
    .card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-color: #cbd5e1; }
    
    .card-body { padding: 24px; flex-grow: 1; display: flex; flex-direction: column; gap: 12px; }
    
    /* Navigation Link Cards */
    .nav-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:20px; margin-bottom: 24px; }
    
    .nav-card-icon {
        width: 48px; height: 48px; 
        background: #eff6ff; border-radius: 12px; 
        display: flex; align-items: center; justify-content: center; 
        font-size: 24px; margin-bottom: 16px;
    }

    .rule-list { margin: 0; padding-left: 20px; color: var(--text-muted); line-height: 1.6; font-size: 14px; }
    .rule-list li { margin-bottom: 8px; }

    /* Sidebar */
    .sidebar-card { padding: 24px; margin-bottom: 24px; }
    .sidebar-card h3 { margin:0 0 16px; font-size:16px; color: var(--text-main); font-weight: 700; }

    /* Buttons */
    .btn, .btn-outline { 
        padding:10px 20px; border-radius:10px; 
        font-weight:600; font-size:14px;
        cursor:pointer; text-decoration:none; 
        display:inline-flex; align-items:center; justify-content:center;
        transition: all .2s; width: 100%;
    }
    .btn { border:none; background: #0f172a; color:#fff; }
    .btn:hover { background: #1e293b; color:#fff; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    
    .btn-outline { background: #fff; color: var(--text-main); border: 1px solid var(--border); }
    .btn-outline:hover { border-color: var(--accent); color: var(--accent); background: #f8fafc; }

    /* Messages */
    .notice { padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; display:flex; gap:10px; align-items:center; }
    .success { background: #ecfdf5; border: 1px solid #d1fae5; color: #065f46; }
    .error { background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; }

    @media (max-width: 600px) {
        .hero { padding: 24px; }
        .layout { grid-template-columns: 1fr; }
        .nav-grid { grid-template-columns: 1fr; }
    }
</style>

<?php renderHeader($pdo, 'community'); ?>

<div class="page">
  <section class="hero">
    <div>
      <div class="pill">👥 Bendruomenė</div>
      <h1>Pasikalbėkime ir dalinkimės</h1>
      <p>Čia susitinka žmonės diskutuoti, padėti vieni kitiems ir sąžiningai prekiauti tarpusavyje.</p>
    </div>
    <div class="stat-card">
      <strong>2</strong>
      <span>Pagrindinės erdvės</span>
    </div>
  </section>

  <?php if ($messages || $errors): ?>
    <div>
        <?php foreach ($messages as $msg): ?>
            <div class="notice success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endforeach; ?>
        <?php foreach ($errors as $err): ?>
            <div class="notice error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <?php echo htmlspecialchars($err); ?>
            </div>
        <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="layout">
    <div>
        <div class="nav-grid">
            <a href="/community_discussions.php" class="card" style="text-decoration:none;">
                <div class="card-body">
                    <div class="nav-card-icon" style="color: var(--accent);">💬</div>
                    <h3 style="margin:0 0 8px; color:var(--text-main); font-size:18px;">Diskusijos</h3>
                    <p style="margin:0; color:var(--text-muted); font-size:14px; line-height:1.5;">
                        Klausimai, patarimai ir bendruomenės pulsas. Prisijunk prie pokalbių.
                    </p>
                    <div style="margin-top:auto; padding-top:16px; color:var(--accent); font-weight:600; font-size:14px;">
                        Eiti į diskusijas →
                    </div>
                </div>
            </a>
            
            <a href="/community_market.php" class="card" style="text-decoration:none;">
                <div class="card-body">
                    <div class="nav-card-icon" style="color: #16a34a; background: #f0fdf4;">🛍️</div>
                    <h3 style="margin:0 0 8px; color:var(--text-main); font-size:18px;">Bendruomenės turgus</h3>
                    <p style="margin:0; color:var(--text-muted); font-size:14px; line-height:1.5;">
                        Pasiūlymai ir užklausos tarp narių. Rask arba parduok.
                    </p>
                    <div style="margin-top:auto; padding-top:16px; color:var(--accent); font-weight:600; font-size:14px;">
                        Peržiūrėti turgų →
                    </div>
                </div>
            </a>
        </div>

        <div class="nav-grid">
            <div class="card">
                <div class="card-body">
                    <h3 style="margin-top:0; font-size:16px;">Ką gali?</h3>
                    <ul class="rule-list">
                        <li>Kurti temas ir dalintis patarimais ar klausimais.</li>
                        <li>Prisijungti prie diskusijų, balsuoti „patinka“.</li>
                        <li>Siūlyti ar ieškoti prekių Bendruomenės turguje.</li>
                        <li>Siųsti užklausas kitiems nariams.</li>
                    </ul>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h3 style="margin-top:0; font-size:16px;">Ko negalima?</h3>
                    <ul class="rule-list">
                        <li>Reklamuoti nesusijusių paslaugų.</li>
                        <li>Naudoti neapykantos kalbos ar įžeidinėti.</li>
                        <li>Apgaudinėti dėl kainos, būklės ar nuosavybės.</li>
                        <li>Dalintis asmeniniais duomenimis be leidimo.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <aside>
        <div class="card sidebar-card">
            <h3>Prisijunk prie mūsų</h3>
            <p style="margin:0 0 16px; font-size:13px; color:var(--text-muted); line-height:1.5;">
                Prisijunkite prie paskyros ir pasirinkite jus dominantčią erdvę.
            </p>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php if ($user['id']): ?>
                    <a class="btn" href="/community_discussions.php">Eiti į diskusijas</a>
                    <a class="btn-outline" href="/account.php">Mano profilis</a>
                <?php else: ?>
                    <a class="btn" href="/login.php">Prisijunkite</a>
                    <a class="btn-outline" href="/register.php">Registruotis</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card sidebar-card" style="background: linear-gradient(135deg, #f8fafc, #fff);">
            <h3>Kultūra ir saugumas</h3>
            <p style="margin:0 0 16px; font-size:13px; color:var(--text-muted); line-height:1.6;">
                Moderatoriai gali pašalinti netinkamą turinį. Laikykimės draugiško tono.
            </p>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <span style="font-size:12px; background:#fff7ed; color:#9a3412; padding:4px 8px; border-radius:6px; border:1px solid #fed7aa;">🧡 Draugiškumas</span>
                <span style="font-size:12px; background:#f0fdf4; color:#166534; padding:4px 8px; border-radius:6px; border:1px solid #bbf7d0;">🌱 Pagalba</span>
            </div>
        </div>

        <div class="card sidebar-card" style="background: #f8fafc; border: 1px solid var(--border);">
            <h3>Reikia pagalbos?</h3>
            <p style="font-size:13px; color:var(--text-muted); line-height:1.5; margin-bottom:12px;">
                Kilo klausimų dėl bendruomenės taisyklių?
            </p>
            <a href="/contact.php" style="font-size:13px; font-weight:600; color:var(--accent);">Susisiekti su mumis →</a>
        </div>
    </aside>
  </div>
</div>

<?php renderFooter($pdo); ?>
