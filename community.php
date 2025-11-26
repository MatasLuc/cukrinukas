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
.hero {
  max-width: 1100px;
  margin: 40px auto 0;
  padding: 0 18px;
}
.hero-card {
  background: radial-gradient(circle at 20% 20%, rgba(130,158,214,0.16), transparent 45%),
              radial-gradient(circle at 80% 0%, rgba(255,173,96,0.18), transparent 38%),
              linear-gradient(135deg, #fff, #f6f7ff);
  border: 1px solid #e6e6ef;
  border-radius: 24px;
  padding: 32px;
  box-shadow: 0 22px 48px rgba(0,0,0,0.08);
  overflow: hidden;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
}
.card {
  background: #fff;
  border: 1px solid #e6e6ef;
  border-radius: 18px;
  padding: 18px;
  box-shadow: 0 16px 34px rgba(0,0,0,0.06);
}
.badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 999px;
  background: #0b0b0b;
  color: #fff;
  font-weight: 700;
  letter-spacing: 0.2px;
}
.rule-list { margin: 0; padding-left: 18px; color: #2c2c35; line-height: 1.6; }
.links {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 18px;
}
.link-card {
  background: radial-gradient(circle at 20% 20%, rgba(130,158,214,0.16), transparent 45%),
              radial-gradient(circle at 80% 0%, rgba(255,173,96,0.18), transparent 38%),
              linear-gradient(135deg, #fff, #f6f7ff);
  color: #0b0b0b;
  border-radius: 18px;
  padding: 22px;
  border: 1px solid #e6e6ef;
  box-shadow: 0 22px 48px rgba(0,0,0,0.08);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.link-card.secondary {
  background: radial-gradient(circle at 20% 20%, rgba(130,158,214,0.16), transparent 45%),
              radial-gradient(circle at 80% 0%, rgba(255,173,96,0.18), transparent 38%),
              linear-gradient(135deg, #fff, #f6f7ff);
}
.link-card a {
  color: #0b0b0b;
  text-decoration: none;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(11,11,11,0.04);
  border: 1px solid rgba(11,11,11,0.06);
}
.pill { display:inline-flex; align-items:center; gap:8px; padding:6px 10px; background:#f0f4ff; border-radius:999px; border:1px solid #dce4ff; }
</style>
<main class="hero">
  <div class="hero-card">
    <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;justify-content:space-between;">
      <div style="max-width:640px;display:flex;flex-direction:column;gap:10px;">
        <span class="badge">Bendruomenė</span>
        <h1 style="margin:0;">Pasikalbėkime, dalinkimės ir kurkime kartu</h1>
        <p style="margin:0; font-size:16px; color:#2c2c35; line-height:1.6;">
          Čia susitinka žmonės, kurie nori diskutuoti, padėti vieni kitiems ir sąžiningai prekiauti tarpusavyje.
          Įsikvėpk, pasidalink patirtimi ir atrask naujų kontaktų.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:12px; margin-top:6px;">
          <span class="pill">Diskusijos be reklaminio triukšmo</span>
          <span class="pill">Sąžiningi mainai</span>
          <span class="pill">Draugiška moderacija</span>
        </div>
      </div>
      <div class="card" style="min-width:260px; max-width:320px;">
        <h3 style="margin-top:0;">Kaip prisijungti?</h3>
        <p class="muted" style="margin:0;">Prisijunkite prie paskyros ir pasirinkite jus dominantčią erdvę. Jei esate naujokas, <a href="/register.php">susikurkite paskyrą</a>.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
          <?php if ($user['id']): ?>
            <a class="btn" href="/community_discussions.php" style="background:#0b0b0b;color:#fff;border-color:#0b0b0b;">Eiti į diskusijas</a>
          <?php else: ?>
            <a class="btn" href="/login.php" style="background:#0b0b0b;color:#fff;border-color:#0b0b0b;">Prisijunkite</a>
          <?php endif; ?>
          <a class="btn btn-secondary" href="/community_market.php" style="border-color:#d0d6e9;">Peržiūrėti turgų</a>
        </div>
      </div>
    </div>
  </div>

  <?php foreach ($messages as $msg): ?>
    <div style="margin-top:18px;background:#edf9f0;border:1px solid #b8e2c4;padding:12px;border-radius:12px;">
      &check; <?php echo htmlspecialchars($msg); ?>
    </div>
  <?php endforeach; ?>
  <?php foreach ($errors as $err): ?>
    <div style="margin-top:18px;background:#fff1f1;border:1px solid #f3b7b7;padding:12px;border-radius:12px;">
      &times; <?php echo htmlspecialchars($err); ?>
    </div>
  <?php endforeach; ?>

  <section style="margin-top:24px;">
    <div class="links">
      <div class="link-card">
        <div style="display:flex;align-items:center;gap:10px;">
          <div style="background:rgba(255,255,255,0.16);width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-weight:800;">💬</div>
          <div>
            <div style="font-weight:800;">Diskusijos</div>
            <div style="opacity:0.8;">Klausimai, patarimai ir bendruomenės pulsas.</div>
          </div>
        </div>
        <a href="/community_discussions.php">Eiti į diskusijas →</a>
      </div>
      <div class="link-card secondary">
        <div style="display:flex;align-items:center;gap:10px;">
          <div style="background:rgba(0,0,0,0.14);width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-weight:800;">🛍️</div>
          <div>
            <div style="font-weight:800;">Bendruomenės turgus</div>
            <div style="opacity:0.9;">Pasiūlymai ir užklausos tarp narių.</div>
          </div>
        </div>
        <a href="/community_market.php">Peržiūrėti turgų →</a>
      </div>
    </div>
  </section>

  <section style="margin-top:24px;">
    <div class="grid">
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
      <div class="card" style="border:1px solid #d8e4ff;background:linear-gradient(135deg,#f6f8ff,#ffffff);">
        <h3 style="margin-top:0;">Kultūra ir saugumas</h3>
        <p style="margin:0;color:#2c2c35; line-height:1.6;">Moderatoriai gali pašalinti netinkamą turinį ir apriboti prieigą pažeidėjams. Jei pastebite pažeidimų, informuokite administraciją.</p>
        <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
          <span class="pill" style="background:#fff6e8;border-color:#ffdbab;">Draugiškas tonas</span>
          <span class="pill" style="background:#e8fff6;border-color:#b7f1d5;">Pagalba naujokams</span>
        </div>
      </div>
    </div>
  </section>
</main>

<?php renderFooter($pdo); ?>
