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
ensureAdminAccount($pdo);

$id = (int)($_GET['id'] ?? 0);
// NAUJA: Įtrauktas 'author' į SELECT užklausą
$stmt = $pdo->prepare('SELECT id, title, summary, author, image_url, body, visibility, is_featured FROM news WHERE id = ?');
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    echo 'Įrašas nerastas';
    exit;
}

$errors = [];
$message = '';
$summary = $item['summary'] ?? '';
$author = $item['author'] ?? ''; // NAUJA: Autoriaus kintamasis
$visibility = $item['visibility'] ?? 'public';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $author = trim($_POST['author'] ?? ''); // NAUJA: Gauname autorių iš formos
    $body = trim($_POST['body'] ?? '');
    $visibility = $_POST['visibility'] === 'members' ? 'members' : 'public';
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

    if ($title === '' || $body === '' || $summary === '') {
        $errors[] = 'Užpildykite pavadinimą, santrauką ir tekstą.';
    }

    $imagePath = $item['image_url'];
    $newImage = uploadImageWithValidation($_FILES['image'] ?? [], 'news_', $errors, null);
    if ($newImage !== null) {
        $imagePath = $newImage;
    }

    if (!$errors) {
        try {
            // NAUJA: Atnaujinta UPDATE užklausa su author stulpeliu
            $stmt = $pdo->prepare('UPDATE news SET title = ?, summary = ?, author = ?, image_url = ?, body = ?, visibility = ?, is_featured = ? WHERE id = ?');
            $stmt->execute([$title, $summary, $author, $imagePath, $body, $visibility, $isFeatured, $item['id']]);
            $message = 'Naujiena atnaujinta';
            $item['title'] = $title;
            $item['summary'] = $summary;
            $item['author'] = $author;
            $item['body'] = $body;
            $item['visibility'] = $visibility;
            $item['image_url'] = $imagePath;
            $item['is_featured'] = $isFeatured;
        } catch (Throwable $e) {
            logError('News update failed', $e);
            $errors[] = friendlyErrorMessage();
        }
    }
}

$currentBody = isset($body) ? $body : ($item['body'] ?? '');
$safeBody = sanitizeHtml($currentBody);
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Redaguoti naujieną | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    :root { --color-bg: #f7f7fb; --color-primary: #0b0b0b; }
    * { box-sizing: border-box; }
    a { color: inherit; text-decoration: none; }
    .wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .card { background: #fff; padding: 28px; border-radius: 16px; box-shadow: 0 14px 32px rgba(0,0,0,0.08); width: min(720px, 100%); }
    .card h1 { margin: 0 0 8px; font-size: 26px; }
    label { display: block; margin: 14px 0 6px; font-weight: 600; }
    input, textarea { width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #d7d7e2; background: #f9f9ff; font-size: 15px; }
    textarea { min-height: 160px; resize: vertical; }
    input:focus, textarea:focus { outline: 2px solid #0b0b0b; }
    button { padding: 12px 18px; border-radius: 12px; border: none; background: #0b0b0b; color: #fff; font-weight: 600; cursor:pointer; margin-top: 14px; }
    .notice { padding: 12px; border-radius: 12px; margin-top: 12px; }
    .notice.error { background: #fff1f1; border: 1px solid #f3b7b7; color: #991b1b; }
    .notice.success { background: #edf9f0; border: 1px solid #b8e2c4; color: #0f5132; }
    /* Pataisytas toolbar stilius, kad mygtukai neatrodytų paspausti */
    .toolbar button, .toolbar input, .toolbar select { border-radius:10px; padding:8px 10px; border:1px solid #d7d7e2; background:#fff; cursor:pointer; color:#0b0b0b; font-weight:600; user-select: none; }
    .toolbar input[type=color] { padding:0; width:40px; height:36px; }
    .rich-editor { min-height:220px; padding:12px; border:1px solid #d7d7e2; border-radius:12px; background:#f9f9ff; }
    .rich-editor img { max-width:100%; height:auto; display:block; margin:12px 0; border-radius:12px; }
  </style>
  
</head>
<body>
  <?php renderHeader($pdo, 'news'); ?>
  <div class="wrapper">
    <div class="card">
      <h1>Naujienos redagavimas</h1>
      <p style="margin:0 0 14px; color:#444;">Atnaujinkite diabeto naujienų įrašą.</p>

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
        <input id="title" name="title" type="text" required value="<?php echo htmlspecialchars($item['title']); ?>">

        <label for="author">Autorius</label>
        <input id="author" name="author" type="text" value="<?php echo htmlspecialchars($author); ?>" placeholder="Įveskite autoriaus vardą (pvz. Redakcija)">

        <label for="summary">Santrauka</label>
        <textarea id="summary" name="summary" required style="min-height:90px; "><?php echo htmlspecialchars($summary); ?></textarea>

        <label for="image">Nuotrauka</label>
        <input id="image" name="image" type="file" accept="image/*">
        <?php if ($item['image_url']): ?><p style="margin:6px 0 0; font-size:14px;">Dabartinė: <a href="<?php echo htmlspecialchars($item['image_url']); ?>" target="_blank">peržiūrėti</a></p><?php endif; ?>

        <label for="body-editor">Turinys</label>
        <div class="toolbar" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
          <button type="button" onmousedown="event.preventDefault()" onclick="format('bold')">B</button>
          <button type="button" onmousedown="event.preventDefault()" onclick="format('italic')"><em>I</em></button>
          <button type="button" onmousedown="event.preventDefault()" onclick="format('underline')"><u>U</u></button>
          <button type="button" onmousedown="event.preventDefault()" onclick="format('strikeThrough')"><s>S</s></button>
          <button type="button" onmousedown="event.preventDefault()" onclick="format('insertUnorderedList')">• Sąrašas</button>
          <button type="button" onmousedown="event.preventDefault()" onclick="format('insertOrderedList')">1. Sąrašas</button>
          <button type="button" onmousedown="event.preventDefault()" onclick="format('formatBlock','blockquote')">Citata</button>
          <button type="button" onmousedown="event.preventDefault()" onclick="format('justifyLeft')">↤</button>
          <button type="button" onmousedown="event.preventDefault()" onclick="format('justifyCenter')">↔</button>
          <button type="button" onmousedown="event.preventDefault()" onclick="format('justifyRight')">↦</button>
          <button type="button" onmousedown="event.preventDefault()" onclick="createLink()">Nuoroda</button>
          <button type="button" onmousedown="event.preventDefault()" onclick="triggerInlineImage()">Įkelti nuotrauką</button>
          <input type="color" onchange="formatColor(this.value)" aria-label="Teksto spalva">
          <select onchange="format('fontSize', this.value)">
            <option value="3">Šrifto dydis</option>
            <option value="2">Mažas</option>
            <option value="3">Vidutinis</option>
            <option value="4">Didelis</option>
            <option value="5">Labai didelis</option>
          </select>
          <button type="button" onmousedown="event.preventDefault()" onclick="format('removeFormat')">Išvalyti formatavimą</button>
        </div>
        <div id="body-editor" class="rich-editor" contenteditable="true">
          <?php echo $safeBody; ?>
        </div>
        <input type="file" id="inline-image-input" accept="image/*" style="display:none;">
        <textarea id="body" name="body" hidden required><?php echo htmlspecialchars($safeBody); ?></textarea>

        <label style="display:flex; align-items:center; gap:8px; margin-top:12px;">
          <input type="checkbox" name="is_featured" value="1" <?php echo $item['is_featured'] ? 'checked' : ''; ?>> Rodyti kaip išskirtinę
        </label>

        <label for="visibility">Matomumas</label>
        <select id="visibility" name="visibility" style="margin-top:6px;">
          <option value="public" <?php echo $visibility === 'public' ? 'selected' : ''; ?>>Visiems matoma</option>
          <option value="members" <?php echo $visibility === 'members' ? 'selected' : ''; ?>>Tik prisijungusiems</option>
        </select>

        <button type="submit">Išsaugoti pakeitimus</button>
      </form>

      <div style="margin-top: 16px; display:flex; justify-content: space-between;">
        <a href="/news.php">← Grįžti</a>
        <a href="/">↩ Pagrindinis</a>
      </div>
    </div>
  </div>
  <script>
    const editor = document.getElementById('body-editor');
    const hiddenBody = document.getElementById('body');
    const inlineImageInput = document.getElementById('inline-image-input');

    function format(cmd, value = null) {
      document.execCommand(cmd, false, value);
      editor.focus();
    }
    function formatColor(color) { format('foreColor', color); }
    function createLink() {
      const url = prompt('Įveskite nuorodą');
      if (url) { format('createLink', url); }
    }
    function decorateImages() {
      editor.querySelectorAll('img').forEach(img => {
        img.style.maxWidth = '100%';
        img.style.height = 'auto';
        img.style.display = 'block';
        img.style.margin = '12px 0';
        img.style.borderRadius = '12px';
      });
    }
    async function triggerInlineImage() {
      inlineImageInput.click();
    }
    inlineImageInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (!file) return;
      const formData = new FormData();
      formData.append('image', file);
      try {
        const res = await fetch('/editor_upload.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success && data.url) {
          format('insertImage', data.url);
          decorateImages();
        } else {
          alert(data.error || 'Nepavyko įkelti nuotraukos');
        }
      } catch (err) {
        alert('Klaida įkeliant nuotrauką');
      }
      inlineImageInput.value = '';
    });
    function syncBody() {
      decorateImages();
      hiddenBody.value = editor.innerHTML.trim();
    }
    decorateImages();
  </script>
  <?php renderFooter($pdo); ?>
</body>
</html>
