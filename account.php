<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$pdo = getPdo();
ensureUsersTable($pdo);
ensureAdminAccount($pdo);

$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT id, name, email, profile_photo, birthdate, gender, city, country FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $birthdate = $_POST['birthdate'] !== '' ? $_POST['birthdate'] : null;
    $gender = trim($_POST['gender'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $profilePhoto = $user['profile_photo'] ?? null;

    if (!empty($_FILES['profile_photo']['name'])) {
        $allowedMimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        $uploaded = saveUploadedFile($_FILES['profile_photo'], $allowedMimeMap, 'profile_');
        if ($uploaded) {
            $profilePhoto = $uploaded;
        }
    }

    if ($name === '' || $email === '') {
        $errors[] = 'Įveskite vardą ir el. paštą.';
    }

    if (!$errors) {
        $pdo->prepare('UPDATE users SET name = ?, email = ?, birthdate = ?, gender = ?, city = ?, country = ?, profile_photo = ? WHERE id = ?')
            ->execute([$name, $email, $birthdate, $gender ?: null, $city ?: null, $country ?: null, $profilePhoto, $userId]);
        $_SESSION['user_name'] = $name;
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
        }
        $success = 'Paskyra atnaujinta';
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }
}
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Paskyra | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    :root {
      --bg: #f7f7fb;
      --card: #ffffff;
      --border: #e4e7ec;
      --text: #0f172a;
      --muted: #52606d;
      --accent: #7c3aed;
    }
    * { box-sizing:border-box; }
    body { margin:0; background: var(--bg); color: var(--text); font-family:'Inter', system-ui, -apple-system, sans-serif; }
    a { color:inherit; text-decoration:none; }
    .page { max-width: 900px; margin:0 auto; padding:32px 20px 60px; display:grid; gap:18px; }

    .hero { background: linear-gradient(135deg, #eef2ff, #e0f2fe); border:1px solid #e5e7eb; border-radius:28px; padding:24px 22px; box-shadow:0 24px 60px rgba(0,0,0,0.08); display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .hero h1 { margin:0; font-size:clamp(26px, 5vw, 34px); letter-spacing:-0.02em; color:#0b1224; }
    .hero p { margin:6px 0 0; color: var(--muted); line-height:1.6; max-width:520px; }
    .pill { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; background:#fff; border:1px solid #e4e7ec; font-weight:700; color:#0b1224; box-shadow:0 12px 26px rgba(0,0,0,0.08); }
    .stat { background:#fff; border:1px solid #e4e7ec; padding:14px 16px; border-radius:16px; min-width:140px; box-shadow:0 10px 24px rgba(0,0,0,0.06); text-align:right; }
    .stat strong { display:block; font-size:22px; color:#0b1224; }
    .stat span { color: var(--muted); font-size:13px; }

    .layout { display:grid; grid-template-columns: 1fr 0.9fr; gap:18px; align-items:start; }
    @media(max-width: 820px){ .layout { grid-template-columns:1fr; } }

    .card { background:var(--card); border:1px solid var(--border); border-radius:18px; padding:18px 18px 22px; box-shadow:0 14px 32px rgba(0,0,0,0.06); }
    .card h2 { margin:0 0 8px; font-size:20px; }
    label { display:block; margin:12px 0 6px; font-weight:600; color:#111827; }
    input, select, textarea { width:100%; padding:12px; border-radius:12px; border:1px solid var(--border); background:#f9fafb; font-family:inherit; }
    button { padding:12px 16px; border-radius:12px; border:1px solid transparent; background: linear-gradient(135deg, #4338ca, #7c3aed); color:#fff; font-weight:700; cursor:pointer; width:100%; box-shadow:0 16px 40px rgba(124,58,237,0.25); transition: transform .18s ease, box-shadow .18s ease; }
    button:hover { transform: translateY(-1px); box-shadow:0 18px 52px rgba(67,56,202,0.35); }

    .notice { padding:12px; border-radius:12px; margin-bottom:10px; }
    .error { background:#fff1f1; border:1px solid #f3b7b7; color:#991b1b; }
    .success { background:#edf9f0; border:1px solid #b8e2c4; color:#0f5132; }
    .muted { color: var(--muted); font-size:14px; }
    .profile { display:flex; align-items:center; gap:12px; }
    .avatar { width:72px; height:72px; border-radius:18px; background:#eef2ff; border:1px solid #e4e7ec; display:flex; align-items:center; justify-content:center; font-weight:800; color:#4338ca; overflow:hidden; }
    .avatar img { width:100%; height:100%; object-fit:cover; }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'account'); ?>
  <div class="page">
    <section class="hero">
      <div>
        <div class="pill">👤 Paskyros nustatymai</div>
        <h1>Paskyros redagavimas</h1>
        <p>Atnaujinkite savo profilį, kontaktinę informaciją ir prisijungimo duomenis, kad patirtis būtų sklandi.</p>
      </div>
      <div class="stat">
        <strong><?php echo htmlspecialchars($user['name'] ?? ''); ?></strong>
        <span>Vartotojo vardas</span>
      </div>
    </section>

    <div class="layout">
      <div class="card">
        <h2>Profilio duomenys</h2>
        <div class="muted" style="margin-bottom:10px;">Pasidalinkite šiek tiek informacijos apie save ir atnaujinkite kontaktus.</div>
        <?php foreach ($errors as $err): ?><div class="notice error"><?php echo htmlspecialchars($err); ?></div><?php endforeach; ?>
        <?php if ($success): ?><div class="notice success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" style="display:grid; gap:10px;">
          <?php echo csrfField(); ?>
          <div class="profile">
            <div class="avatar">
              <?php if (!empty($user['profile_photo'])): ?>
                <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profilio nuotrauka">
              <?php else: ?>
                <?php echo strtoupper(substr($user['name'] ?? 'Vartotojas', 0, 1)); ?>
              <?php endif; ?>
            </div>
            <div>
              <label for="profile_photo" style="margin:0 0 6px;">Profilio nuotrauka</label>
              <input id="profile_photo" name="profile_photo" type="file" accept="image/*">
              <div class="muted">PNG, JPG ar WEBP formatai iki 5MB.</div>
            </div>
          </div>

          <label for="name">Vardas</label>
          <input id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>

          <label for="email">El. paštas</label>
          <input id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" type="email" required>

          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:12px;">
            <div>
              <label for="birthdate">Gimimo data</label>
              <input id="birthdate" name="birthdate" type="date" value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>">
            </div>
            <div>
              <label for="gender">Lytis</label>
              <select id="gender" name="gender">
                <option value="">Nepasirinkta</option>
                <?php foreach (['moteris' => 'Moteris','vyras' => 'Vyras','kita' => 'Kita'] as $val => $label): ?>
                  <option value="<?php echo $val; ?>" <?php echo ($user['gender'] ?? '') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:12px;">
            <div>
              <label for="city">Miestas</label>
              <input id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" placeholder="Miestas">
            </div>
            <div>
              <label for="country">Šalis</label>
              <input id="country" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>" placeholder="Šalis">
            </div>
          </div>

          <label for="password">Naujas slaptažodis (pasirinktinai)</label>
          <input id="password" name="password" type="password" placeholder="Palikite tuščią, jei nekeičiate">

          <button type="submit">Išsaugoti pakeitimus</button>
        </form>
      </div>

      <div class="card">
        <h2>Saugumo rekomendacijos</h2>
        <ul style="margin:6px 0 0; padding-left:18px; color: var(--muted); line-height:1.6;">
          <li>Naudokite ilgą ir unikalų slaptažodį su raidėmis bei simboliais.</li>
          <li>Atnaujinkite kontaktinę informaciją, kad nepraleistumėte svarbių pranešimų.</li>
          <li>Įkelkite aiškią profilio nuotrauką, kad bendruomenė jus atpažintų.</li>
        </ul>
      </div>
    </div>
  </div>

  <?php renderFooter($pdo); ?>
</body>
</html>
