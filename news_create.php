<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/layout.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login.php');
    exit;
}

$pdo = getPdo();
ensureNewsTable($pdo);
ensureProductsTable($pdo);
ensureAdminAccount($pdo);

$errors = [];
$message = '';
$summary = '';
$visibility = 'public';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $visibility = $_POST['visibility'] === 'members' ? 'members' : 'public';
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

    if ($title === '' || $body === '' || $summary === '') {
        $errors[] = 'Užpildykite pavadinimą, santrauką ir tekstą.';
    }

    $imagePath = uploadImageWithValidation($_FILES['image'] ?? [], 'news_', $errors, 'Įkelkite nuotrauką.');

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO news (title, summary, image_url, body, visibility, is_featured) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $summary, $imagePath, $body, $visibility, $isFeatured]);
            $message = 'Naujiena sėkmingai sukurta!';
            $_POST = [];
            $summary = '';
            $body = '';
        } catch (Throwable $e) {
            logError('News create failed', $e);
            $errors[] = friendlyErrorMessage();
        }
    }
}

$safeBody = isset($body) ? sanitizeHtml($body) : '';
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nauja naujiena | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    :root { --color-bg: #f7f7fb; --color-primary: #0b0b0b; }
    * { box-sizing: border-box; }
    a { color: inherit; text-decoration: none; }
    .wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .card { background: #fff; padding: 28px; border-radius: 16px; box-shadow: 0 14px 32px rgba(0,0,0,0.08); width: min(640px, 100%); }
    .card h1 { margin: 0 0 8px; font-size: 26px; }
    label { display: block; margin: 14px 0 6px; font-weight: 600; }
    input, textarea { width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #d7d7e2; background: #f9f9ff; font-size: 15px; }
    textarea { min-height: 160px; resize: vertical; }
    input:focus, textarea:focus { outline: 2px solid #0b0b0b; }
    button { padding: 12px 18px; border-radius: 12px; border: none; background: #0b0b0b; color: #fff; font-weight: 600; cursor: pointer; margin-top: 14px; }
    .notice { padding: 12px; border-radius: 12px; margin-top: 12px; }
    .notice.error { background: #fff1f1; border: 1px solid #f3b7b7; color: #991b1b; }
    .notice.success { background: #edf9f0; border: 1px solid #b8e2c4; color: #0f5132; }
    .row { display: flex; align-items: center; gap: 12px; margin-top: 8px; }
    .toolbar button { background:#f1f1f7; border:1px solid #d7d7e2; padding:8px 10px; border-radius:10px; cursor:pointer; font-weight:700; }
    .toolbar button:hover { background:#e8e8f3; }
    .toolbar input[type="color"] { width:42px; height:42px; border:1px solid #d7d7e2; border-radius:10px; padding:0; background:#fff; cursor:pointer; }
    .toolbar select { border-radius:10px; border:1px solid #d7d7e2; padding:8px; background:#fff; }

  </style>
</head>
<body>
  <?php renderHeader($pdo, 'news'); ?>
  <div class="wrapper">
    <div class="card">
      <h1>Nauja naujiena</h1>
      <p style="margin:0 0 14px; color:#444;">Sukurkite įrašą su pavadinimu, iliustracija ir tekstu. Pažymėkite, jei norite rodyti titulinėje.</p>

      <?php if ($errors): ?>
        <div class="notice error">
          <?php foreach ($errors as $error): ?>
            <div><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($message): ?>
        <div class="notice success"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" onsubmit="syncBody();">
        <?php echo csrfField(); ?>
<label for="title">Pavadinimas</label>
        <input id="title" name="title" type="text" required value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>">

        <label for="summary">Santrauka</label>
        <textarea id="summary" name="summary" required style="min-height:90px;"><?php echo htmlspecialchars($summary); ?></textarea>

        <label for="image">Nuotrauka</label>
        <input id="image" name="image" type="file" accept="image/*" required>

        <label for="body-editor">Tekstas</label>
        <div class="toolbar" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
          <button type="button" onclick="format('bold')">B</button>
          <button type="button" onclick="format('italic')"><em>I</em></button>
          <button type="button" onclick="format('underline')"><u>U</u></button>
          <input type="color" onchange="formatColor(this.value)" aria-label="Teksto spalva">
          <select onchange="format('fontSize', this.value)">
            <option value="3">Šrifto dydis</option>
            <option value="2">Mažas</option>
            <option value="3">Vidutinis</option>
            <option value="4">Didelis</option>
            <option value="5">Labai didelis</option>
          </select>
        </div>
        <div id="body-editor" contenteditable="true" style="min-height:200px; padding:12px; border:1px solid #d7d7e2; border-radius:12px; background:#f9f9ff;">
          <?php echo $safeBody; ?>
        </div>
        <textarea id="body" name="body" hidden required><?php echo htmlspecialchars($safeBody); ?></textarea>

        <div class="row">
          <input id="is_featured" name="is_featured" type="checkbox" <?php echo !empty($isFeatured) ? 'checked' : ''; ?>>
          <label for="is_featured" style="margin:0; font-weight:500;">Rodyti pagrindiniame puslapyje (iki 3 įrašų)</label>
        </div>

        <label for="visibility">Matomumas</label>
        <select id="visibility" name="visibility" style="margin-top:6px;">
          <option value="public" <?php echo $visibility === 'public' ? 'selected' : ''; ?>>Visiems matoma</option>
          <option value="members" <?php echo $visibility === 'members' ? 'selected' : ''; ?>>Tik prisijungusiems</option>
        </select>

        <button type="submit">Išsaugoti naujieną</button>
      </form>

      <div class="row" style="justify-content: space-between; margin-top: 16px;">
        <a href="/news.php">← Grįžti į naujienas</a>
        <a href="/">↩ Pagrindinis</a>
      </div>
    </div>
  </div>
  <script>
    function format(cmd, value = null) {
      document.execCommand(cmd, false, value);
    }
    function formatColor(color) {
      document.execCommand('foreColor', false, color);
    }
    function syncBody() {
      const editor = document.getElementById('body-editor');
      document.getElementById('body').value = editor.innerHTML.trim();
    }
  </script>

  <?php renderFooter($pdo); ?>
</body>
</html>
