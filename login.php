<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$pdo = getPdo();
ensureUsersTable($pdo);
ensureProductsTable($pdo);
ensureAdminAccount($pdo);

$errors = [];
$message = '';

if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Įveskite el. pašto adresą ir slaptažodį.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('SELECT id, name, email, password_hash, is_admin FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'Neteisingi prisijungimo duomenys.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['is_admin'] = (int) $user['is_admin'];
                header('Location: /');
                exit;
            }
        } catch (Throwable $e) {
            logError('Login failed', $e);
            $errors[] = friendlyErrorMessage();
        }
    }
}
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Prisijungimas | E-kolekcija</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --surface: #ffffff;
      --ink: #0f172a;
      --muted: #5b5f6a;
      --accent: #6f4ef2;
      --accent-2: #2f9aff;
      --border: #e4e6f0;
    }
    * { box-sizing: border-box; }
    body { background: var(--bg); color: var(--ink); }
    .wrapper { min-height: 100vh; display: grid; grid-template-columns: 1.1fr 1fr; align-items: center; gap: 24px; padding: 32px 24px; max-width: 1100px; margin: 0 auto; }
    .hero { background: linear-gradient(135deg, rgba(47,154,255,0.18), rgba(111,78,242,0.28)); border-radius: 28px; padding: 32px; border: 1px solid rgba(255,255,255,0.5); box-shadow: 0 18px 48px rgba(15,23,42,0.12); backdrop-filter: blur(5px); position: relative; overflow: hidden; }
    .hero::after { content: ""; position: absolute; right: -60px; top: -40px; width: 200px; height: 200px; background: radial-gradient(circle at center, rgba(255,255,255,0.6), transparent 60%); filter: blur(20px); }
    .hero h1 { margin: 0 0 10px; font-size: 32px; letter-spacing: -0.4px; }
    .hero p { margin: 0 0 16px; color: #161e33; max-width: 520px; }
    .hero-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 18px; }
    .hero-card { background: rgba(255,255,255,0.9); border-radius: 16px; padding: 14px; border: 1px solid rgba(255,255,255,0.6); box-shadow: 0 14px 30px rgba(17,24,39,0.12); }
    .hero-card strong { display: block; font-size: 22px; margin-bottom: 4px; }
    .hero-card span { color: var(--muted); font-size: 14px; }
    .card { background: var(--surface); padding: 30px; border-radius: 20px; box-shadow: 0 16px 38px rgba(15,23,42,0.08); border: 1px solid var(--border); width: min(460px, 100%); margin-left: auto; }
    .card h2 { margin: 0 0 8px; font-size: 26px; letter-spacing: -0.2px; }
    .card p { margin: 0 0 18px; color: var(--muted); }
    label { display: block; margin-bottom: 6px; font-weight: 700; color: #121826; }
    input { width: 100%; padding: 14px; border-radius: 14px; border: 1px solid var(--border); background: #fbfbff; font-size: 15px; transition: all .2s ease; }
    input:focus { outline: 2px solid rgba(111,78,242,0.3); box-shadow: 0 8px 20px rgba(111,78,242,0.12); }
    button { width: 100%; padding: 14px; border-radius: 14px; border: none; background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #fff; font-weight: 700; cursor: pointer; margin-top: 12px; box-shadow: 0 16px 40px rgba(47,154,255,0.25); }
    .link-row { display: flex; justify-content: space-between; margin-top: 14px; font-size: 14px; }
    .notice { padding: 14px; border-radius: 14px; margin-bottom: 12px; border: 1px solid; }
    .notice.error { background: #fff1f1; border: 1px solid #f3b7b7; color: #991b1b; box-shadow: 0 10px 24px rgba(244, 63, 94, 0.12); }
    .notice.success { background: #edf9f0; border: 1px solid #b8e2c4; color: #0f5132; box-shadow: 0 10px 24px rgba(16, 185, 129, 0.12); }
    .brand { display: inline-flex; align-items: center; gap: 10px; font-weight: 800; color: var(--ink); text-decoration: none; margin-bottom: 16px; font-size: 21px; letter-spacing: -0.2px; }
    .eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 999px; background: rgba(255,255,255,0.8); border: 1px solid rgba(255,255,255,0.6); font-weight: 700; font-size: 13px; color: #1e293b; }
    .pill-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
    .pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(0,0,0,0.04); color: #111827; font-weight: 600; border: 1px solid rgba(0,0,0,0.06); }
    @media (max-width: 900px) { .wrapper { grid-template-columns: 1fr; } .card { margin: 0 auto; } }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'login'); ?>
  <div class="wrapper">
    <div class="hero">
      <div class="eyebrow">Sveiki sugrįžę · Premium patirtis</div>
      <h1>Prisijunkite ir tęskite savo atradimus</h1>
      <p>Valdykite užsakymus, išsaugotus produktus ir pranešimus vienoje vietoje. Modernus dizainas, greita sąsaja ir saugus prisijungimas.</p>
      <div class="pill-row">
        <span class="pill">🔒 Dviejų lygių apsauga</span>
        <span class="pill">⚡ Greitas užsakymų peržiūrėjimas</span>
        <span class="pill">💌 Personalizuotos naujienos</span>
      </div>
      <div class="hero-grid">
        <div class="hero-card"><strong>150k+</strong><span>Sėkmingų atsiskaitymų</span></div>
        <div class="hero-card"><strong>24/7</strong><span>Klientų aptarnavimas</span></div>
        <div class="hero-card"><strong>99.9%</strong><span>Veikimo laikas</span></div>
      </div>
    </div>

    <div class="card">
      <a class="brand" href="/">Cukrinukas.lt</a>
      <h2>Prisijungti</h2>
      <p>Įveskite savo el. pašto adresą ir slaptažodį.</p>

      <?php if ($errors): ?>
        <div class="notice error">
          <?php foreach ($errors as $error): ?>
            <div><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($message): ?>
        <div class="notice success"><?php echo $message; ?></div>
      <?php endif; ?>

      <form method="post">
        <?php echo csrfField(); ?>
        <label for="email">El. paštas</label>
        <input id="email" name="email" type="email" required autocomplete="email">

        <label for="password">Slaptažodis</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">

        <button type="submit">Prisijungti</button>
      </form>

      <div class="link-row">
        <a href="/forgot_password.php">Pamiršau slaptažodį</a>
        <a href="/register.php">Neturite paskyros? Registruokitės</a>
        <a href="/">↩ Pagrindinis</a>
      </div>
    </div>
  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
