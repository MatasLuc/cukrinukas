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
    
    /* Layout struktūra: Pilnas plotis be šoninės juostos */
    .page { max-width: 1200px; margin:0 auto; padding:32px 20px 72px; display:flex; flex-direction:column; gap:40px; }

    /* Hero Section */
    .hero { 
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border:1px solid #dbeafe; 
        border-radius:24px; 
        padding:40px; 
        display:flex; 
        align-items:center; 
        justify-content:space-between; 
        gap:32px; 
        flex-wrap:wrap; 
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .hero-content { max-width: 600px; }
    .hero h1 { margin:0 0 12px; font-size:32px; color:#1e3a8a; letter-spacing:-0.5px; }
    .hero p { margin:0; color:#1e40af; line-height:1.6; font-size:16px; }
    
    .pill { 
        display:inline-flex; align-items:center; gap:8px; 
        padding:6px 12px; border-radius:999px; 
        background:#fff; border:1px solid #bfdbfe; 
        font-weight:600; font-size:13px; color:#1e40af; 
        margin-bottom: 16px;
    }

    .stat-card { 
        background:#fff; border:1px solid rgba(255,255,255,0.6); 
        padding:20px 24px; border-radius:16px; 
        min-width:180px; text-align:right;
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1);
    }
    .stat-card strong { display:block; font-size:24px; color:#1e3a8a; margin-bottom: 4px; }
    .stat-card span { color: #64748b; font-size:14px; font-weight: 500; }

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
    
    .card-body { padding: 32px; flex-grow: 1; display: flex; flex-direction: column; gap: 16px; }

    /* Navigacijos tinklelis (2 didelės kortelės) */
    .nav-grid { 
        display:grid; 
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); 
        gap:24px; 
    }
    
    .nav-card-icon {
        width: 56px; height: 56px; 
        background: #eff6ff; border-radius: 14px; 
        display: flex; align-items: center; justify-content: center; 
        font-size: 28px; margin-bottom: 8px;
    }

    /* Veiksmų sekcija (Login/Register) */
    .action-bar {
        background: #f8fafc;
        border: 1px dashed var(--border);
        border-radius: 20px;
        padding: 32px;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
    }
    .action-buttons { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; }

    /* Informacinis tinklelis (3 stulpeliai: Rules +, Rules -, Culture) */
    .info-grid { 
        display:grid; 
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
        gap:24px; 
    }

    .rule-list { margin: 0; padding-left: 20px; color: var(--text-muted); line-height: 1.6; font-size: 15px; }
    .rule-list li { margin-bottom: 8px; }

    /* Buttons */
    .btn, .btn-outline { 
        padding:12px 24px; border-radius:12px; 
        font-weight:600; font-size:15px;
        cursor:pointer; text-decoration:none; 
        display:inline-flex; align-items:center; justify-content:center;
        transition: all .2s;
    }
    .btn { border:none; background: #0f172a; color:#fff; }
    .btn:hover { background: #1e293b; color:#fff; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
    
    .btn-outline { background: #fff; color: var(--text-main); border: 1px solid var(--border); }
    .btn-outline:hover { border-color: var(--accent); color: var(--accent); background: #f8fafc; }

    /* Messages */
    .notice { padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:14px; display:flex; gap:10px; align-items:center; }
    .success { background: #ecfdf5; border: 1px solid #d1fae5; color: #065f46; }
    .error { background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; }

    .help-footer { text-align: center; margin-top: 10px; font-size: 14px; color: var(--text-muted); }
    .help-footer a { color: var(--accent); font-weight: 600; text-decoration: underline; }

    @media (max-width: 768px) {
        .hero { padding: 24px; flex-direction: column; align-items: flex-start; }
        .stat-card { width: 100%; text-align: left; }
        .nav-grid, .info-grid { grid-template-columns: 1fr; }
    }
</style>

<?php renderHeader($pdo, 'community'); ?>

<div class="page">
  <section class="hero">
    <div class="hero-content">
      <div class="pill">👥 Bendruomenė</div>
      <h1>Pasikalbėkime ir dalinkimės</h1>
      <p>Čia susitinka žmonės diskutuoti, padėti vieni kitiems ir sąžiningai prekiauti tarpusavyje. Prisijunk prie mūsų augančios bendruomenės.</p>
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
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y8="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <?php echo htmlspecialchars($err); ?>
            </div>
        <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="nav-grid">
      <a href="/community_discussions.php" class="card" style="text-decoration:none;">
          <div class="card-body">
              <div class="nav-card-icon" style="color: var(--accent);">💬</div>
              <h2 style="margin:0 0 8px; color:var(--text-main); font-size:22px;">Diskusijos</h2>
              <p style="margin:0; color:var(--text-muted); font-size:16px; line-height:1.5;">
                  Klausimai, patarimai ir bendruomenės pulsas. Prisijunk prie pokalbių, kurk naujas temas ir bendrauk.
              </p>
              <div style="margin-top:auto; padding-top:20px; color:var(--accent); font-weight:600; font-size:15px;">
                  Eiti į diskusijas →
              </div>
          </div>
      </a>
      
      <a href="/community_market.php" class="card" style="text-decoration:none;">
          <div class="card-body">
              <div class="nav-card-icon" style="color: #16a34a; background: #f0fdf4;">🛍️</div>
              <h2 style="margin:0 0 8px; color:var(--text-main); font-size:22px;">Bendruomenės turgus</h2>
              <p style="margin:0; color:var(--text-muted); font-size:16px; line-height:1.5;">
                  Pasiūlymai ir užklausos tarp narių. Rask, ko ieškai, arba parduok tai, kas nebereikalinga.
              </p>
              <div style="margin-top:auto; padding-top:20px; color:var(--accent); font-weight:600; font-size:15px;">
                  Peržiūrėti turgų →
              </div>
          </div>
      </a>
  </div>

  <div class="action-bar">
      <?php if ($user['id']): ?>
          <h3 style="margin:0; font-size:20px;">Sveiki sugrįžę, <?php echo htmlspecialchars($user['username']); ?>! 👋</h3>
          <p style="margin:0; color:var(--text-muted);">Jūs jau esate bendruomenės narys. Galite laisvai kurti temas ir komentuoti.</p>
          <div class="action-buttons">
              <a class="btn" href="/community_thread_new.php">Kurti naują temą</a>
              <a class="btn-outline" href="/account.php">Mano profilis</a>
          </div>
      <?php else: ?>
          <h3 style="margin:0; font-size:20px;">Norite prisijungti prie diskusijų?</h3>
          <p style="margin:0; color:var(--text-muted);">Prisijunkite prie paskyros, kad galėtumėte kurti temas, rašyti komentarus ir naudotis turgumi.</p>
          <div class="action-buttons">
              <a class="btn" href="/login.php">Prisijunkite</a>
              <a class="btn-outline" href="/register.php">Registruotis</a>
          </div>
      <?php endif; ?>
  </div>

  <div class="info-grid">
      <div class="card">
          <div class="card-body">
              <h3 style="margin-top:0; font-size:18px;">✅ Ką gali?</h3>
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
              <h3 style="margin-top:0; font-size:18px;">🚫 Ko negalima?</h3>
              <ul class="rule-list">
                  <li>Reklamuoti nesusijusių paslaugų.</li>
                  <li>Naudoti neapykantos kalbos ar įžeidinėti.</li>
                  <li>Apgaudinėti dėl kainos, būklės ar nuosavybės.</li>
                  <li>Dalintis asmeniniais duomenimis be leidimo.</li>
              </ul>
          </div>
      </div>

      <div class="card" style="background: linear-gradient(135deg, #f8fafc, #fff);">
          <div class="card-body">
              <h3 style="margin-top:0; font-size:18px;">🌟 Kultūra ir saugumas</h3>
              <p style="margin:0 0 16px; font-size:14px; color:var(--text-muted); line-height:1.6;">
                  Mes kuriame draugišką erdvę. Moderatoriai gali pašalinti netinkamą turinį, kad visi jaustųsi saugūs.
              </p>
              <div style="margin-top:auto; display:flex; gap:8px; flex-wrap:wrap;">
                  <span style="font-size:12px; background:#fff7ed; color:#9a3412; padding:6px 10px; border-radius:8px; border:1px solid #fed7aa; font-weight:600;">🧡 Draugiškumas</span>
                  <span style="font-size:12px; background:#f0fdf4; color:#166534; padding:6px 10px; border-radius:8px; border:1px solid #bbf7d0; font-weight:600;">🌱 Pagalba</span>
              </div>
          </div>
      </div>
  </div>

  <div class="help-footer">
      Kilo klausimų dėl bendruomenės taisyklių? <a href="/contact.php">Susisiekite su mumis</a>
  </div>

</div>

<?php renderFooter($pdo); ?>
