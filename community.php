<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureCommunityTables($pdo);
ensureNavigationTable($pdo);
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
renderHeader($pdo, 'community');
?>
<style>
/* Perkelti kintamieji ir stiliai iš products.php, kad viskas sutaptų */
:root { --bg: #f7f7fb; --card: #ffffff; --border: #e4e7ec; --text: #1f2937; --muted: #52606d; --accent: #2563eb; }
* { box-sizing: border-box; }
body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); }
a { color:inherit; text-decoration:none; }

/* Puslapio konteineris kaip products.php (max-width: 1200px) */
.page { 
    max-width: 1200px; 
    margin: 0 auto; 
    padding: 32px 20px 72px; 
    display: grid; 
    gap: 28px; 
}

/* Hero sekcija kaip products.php */
.hero {
  padding: 26px; 
  border-radius: 28px; 
  background: linear-gradient(135deg, #eff6ff, #dbeafe); /* Mėlynas gradientas */
  border: 1px solid #e5e7eb; 
  box-shadow: 0 18px 48px rgba(0,0,0,0.08);
  /* Vidinis išdėstymas lieka lankstus, kad tilptų turinys */
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
}

.card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 24px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  transition: transform .2s, border-color .2s;
}
.card:hover {
    transform: translateY(-4px);
    border-color: var(--accent);
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 999px;
  background: #1f2937;
  color: #fff;
  font-weight: 700;
  letter-spacing: 0.2px;
  font-size: 13px;
}

.rule-list { margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.6; }

.links {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 18px;
}

/* Nuorodų kortelės - stilius supaprastintas, be radial-gradient */
.link-card {
  background: #fff;
  color: var(--text);
  border-radius: 20px;
  padding: 22px;
  border: 1px solid var(--border);
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: transform .2s, border-color .2s;
}
.link-card:hover {
    transform: translateY(-4px);
    border-color: var(--accent);
}

.link-card a {
  color: var(--text);
  text-decoration: none;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border-radius: 12px;
  background: #f3f4f6;
  border: 1px solid transparent;
  transition: all .2s;
  width: fit-content;
}
.link-card a:hover {
    background: #e5e7eb;
}

.pill { 
    display:inline-flex; 
    align-items:center; 
    gap:8px; 
    padding:6px 12px; 
    background:#fff; 
    border-radius:999px; 
    border:1px solid var(--border);
    font-size: 13px;
    font-weight: 500;
    color: var(--muted);
}

/* Mygtukai */
.btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 18px; border-radius: 12px; background: #1f2937; color: #fff; border: 1px solid #1f2937; font-weight: 600; cursor: pointer; text-decoration: none; transition: opacity 0.2s; }
.btn:hover { opacity: 0.9; }
.btn-secondary { background: #fff; color: #1f2937; border-color: var(--border); }
.btn-secondary:hover { background: #f9fafb; }
</style>

<div class="page">
  <section class="hero">
    <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:center;justify-content:space-between;">
      <div style="max-width:600px;display:flex;flex-direction:column;gap:14px;">
        <div><span class="badge">Bendruomenė</span></div>
        <h1 style="margin:0; font-size: clamp(26px, 4vw, 36px); color:#0f172a;">Pasikalbėkime, dalinkimės ir kurkime kartu</h1>
        <p style="margin:0; font-size:16px; color:var(--muted); line-height:1.6;">
          Čia susitinka žmonės, kurie nori diskutuoti, padėti vieni kitiems ir sąžiningai prekiauti tarpusavyje.
          Įsikvėpk, pasidalink patirtimi ir atrask naujų kontaktų.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:8px; margin-top:4px;">
          <span class="pill">💬 Diskusijos</span>
          <span class="pill">🤝 Sąžiningi mainai</span>
          <span class="pill">🛡️ Draugiška moderacija</span>
        </div>
      </div>
      
      <div class="card" style="min-width:280px; max-width:320px; border-color: #dbeafe; background: rgba(255,255,255,0.8);">
        <h3 style="margin-top:0; font-size:18px;">Prisijunk prie mūsų</h3>
        <p style="margin:0 0 16px; font-size:14px; color:var(--muted); line-height:1.5;">Prisijunkite prie paskyros ir pasirinkite jus dominantčią erdvę.</p>
        <div style="display:flex; flex-direction:column; gap:10px;">
          <?php if ($user['id']): ?>
            <a class="btn" href="/community_discussions.php" style="width:100%;">Eiti į diskusijas</a>
          <?php else: ?>
            <a class="btn" href="/login.php" style="width:100%;">Prisijunkite</a>
            <div style="text-align:center; font-size:13px; color:var(--muted);">
                Neturite paskyros? <a href="/register.php" style="color:var(--accent); font-weight:600;">Registruotis</a>
            </div>
          <?php endif; ?>
          <a class="btn btn-secondary" href="/community_market.php" style="width:100%;">Peržiūrėti turgų</a>
        </div>
      </div>
    </div>
  </section>

  <?php foreach ($messages as $msg): ?>
    <div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:12px 16px; border-radius:12px;">
      &check; <?php echo htmlspecialchars($msg); ?>
    </div>
  <?php endforeach; ?>
  <?php foreach ($errors as $err): ?>
    <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:12px 16px; border-radius:12px;">
      &times; <?php echo htmlspecialchars($err); ?>
    </div>
  <?php endforeach; ?>

  <section class="links">
    <div class="link-card">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="background:#eff6ff; width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px;">💬</div>
        <div>
          <div style="font-weight:700; font-size:18px;">Diskusijos</div>
          <div style="color:var(--muted); font-size:14px;">Klausimai, patarimai ir bendruomenės pulsas.</div>
        </div>
      </div>
      <div style="margin-top:auto;">
        <a href="/community_discussions.php">Eiti į diskusijas →</a>
      </div>
    </div>
    <div class="link-card">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="background:#f0fdf4; width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px;">🛍️</div>
        <div>
          <div style="font-weight:700; font-size:18px;">Bendruomenės turgus</div>
          <div style="color:var(--muted); font-size:14px;">Pasiūlymai ir užklausos tarp narių.</div>
        </div>
      </div>
      <div style="margin-top:auto;">
        <a href="/community_market.php">Peržiūrėti turgų →</a>
      </div>
    </div>
  </section>

  <section class="grid">
    <div class="card">
      <h3 style="margin-top:0;">Ką gali?</h3>
      <ul class="rule-list">
        <li>Kurti temas ir dalintis patarimais ar klausimais.</li>
        <li>Prisijungti prie diskusijų, balsuoti „patinka“, skatinti vieni kitus.</li>
        <li>Siūlyti ar ieškoti prekių Bendruomenės turguje.</li>
        <li>Siųsti užklausas ir susisiekti su kitais nariais dėl skelbimų.</li>
      </ul>
    </div>
    <div class="card">
      <h3 style="margin-top:0;">Ko negalima?</h3>
      <ul class="rule-list">
        <li>Reklamuoti nesusijusių paslaugų ar skelbimų ne turgaus skiltyje.</li>
        <li>Naudoti neapykantos kalbos, įžeidinėti ar kelti turinio be sutikimo.</li>
        <li>Apgaudinėti dėl kainos, būklės ar nuosavybės.</li>
        <li>Dalintis asmeniniais duomenimis be aiškaus leidimo.</li>
      </ul>
    </div>
    <div class="card" style="background: linear-gradient(135deg, #f8fafc, #fff);">
      <h3 style="margin-top:0;">Kultūra ir saugumas</h3>
      <p style="margin:0;color:var(--muted); line-height:1.6;">Moderatoriai gali pašalinti netinkamą turinį ir apriboti prieigą pažeidėjams. Jei pastebite pažeidimų, informuokite administraciją.</p>
      <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
        <span class="pill" style="background:#fff7ed; border-color:#fed7aa; color:#9a3412;">🧡 Draugiškas tonas</span>
        <span class="pill" style="background:#f0fdf4; border-color:#bbf7d0; color:#166534;">🌱 Pagalba naujokams</span>
      </div>
    </div>
  </section>
</div>

<?php renderFooter($pdo); ?>
