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
ensureNewsTable($pdo); // Tai sukurs ir ryšių lentelę per mūsų atnaujintą logiką
ensureAdminAccount($pdo);

// Gauname visas kategorijas
$categories = $pdo->query("SELECT * FROM news_categories ORDER BY name ASC")->fetchAll();

$errors = [];
$title = '';
$summary = '';
$author = '';
$selectedCatIds = []; // Masyvas pasirinktoms kategorijoms
$body = '';
$visibility = 'public';
$isFeatured = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    
    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $author = trim($_POST['author'] ?? '');
    // Gauname pasirinktas kategorijas (masyvas)
    $selectedCatIds = $_POST['categories'] ?? []; 
    $body = trim($_POST['body'] ?? '');
    $visibility = $_POST['visibility'] === 'members' ? 'members' : 'public';
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

    if ($title === '' || $body === '' || $summary === '') {
        $errors[] = 'Užpildykite pavadinimą, santrauką ir tekstą.';
    }

    $imagePath = '';
    $uploaded = uploadImageWithValidation($_FILES['image'] ?? [], 'news_', $errors, 'Įkelkite naujienos nuotrauką.');
    if ($uploaded) {
        $imagePath = $uploaded;
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // 1. Įrašome pačią naujieną
            $stmt = $pdo->prepare('INSERT INTO news (title, summary, author, image_url, body, visibility, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $summary, $author, $imagePath, $body, $visibility, $isFeatured]);
            $newsId = $pdo->lastInsertId();

            // 2. Įrašome kategorijų ryšius
            if (!empty($selectedCatIds)) {
                $relStmt = $pdo->prepare('INSERT INTO news_category_relations (news_id, category_id) VALUES (?, ?)');
                foreach ($selectedCatIds as $catId) {
                    $relStmt->execute([$newsId, (int)$catId]);
                }
            }

            $pdo->commit();
            
            header('Location: /news.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            logError('News creation failed', $e);
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
  <title>Kurti naujieną | Cukrinukas</title>
  <?php echo headerStyles(); ?>
  <style>
    :root { --color-bg: #f7f7fb; --color-primary: #0b0b0b; }
    * { box-sizing: border-box; }
    .wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .card { background: #fff; padding: 28px; border-radius: 16px; box-shadow: 0 14px 32px rgba(0,0,0,0.08); width: min(720px, 100%); }
    .card h1 { margin: 0 0 8px; font-size: 26px; }
    label { display: block; margin: 14px 0 6px; font-weight: 600; }
    input[type=text], input[type=file], textarea, select { width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #d7d7e2; background: #f9f9ff; font-size: 15px; }
    textarea { min-height: 160px; resize: vertical; }
    button { padding: 12px 18px; border-radius: 12px; border: none; background: #0b0b0b; color: #fff; font-weight: 600; cursor:pointer; margin-top: 14px; }
    .notice.error { background: #fff1f1; border: 1px solid #f3b7b7; color: #991b1b; padding:12px; border-radius:12px; margin-bottom:12px; }
    
    .rich-editor { min-height:220px; padding:12px; border:1px solid #d7d7e2; border-radius:12px; background:#f9f9ff; font-family: sans-serif; }
    .toolbar { display:flex; gap:5px; flex-wrap:wrap; margin-bottom:8px; }
    .toolbar button { border-radius:8px; padding:6px 10px; border:1px solid #ccc; background:#fff; cursor:pointer; font-weight:600; }

    /* Checkbox stilius kategorijoms */
    .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; border: 1px solid #d7d7e2; padding: 12px; border-radius: 12px; background: #f9f9ff; max-height: 200px; overflow-y: auto; }
    .cat-item { display:flex; align-items:center; gap:8px; cursor:pointer; padding:4px; border-radius:6px; transition:background 0.1s; }
    .cat-item:hover { background:#eef2ff; }
    .cat-item input { width:18px; height:18px; accent-color:#0b0b0b; cursor:pointer; }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'news'); ?>
  <div class="wrapper">
    <div class="card">
      <h1>Nauja naujiena</h1>
      <p style="margin:0 0 14px; color:#444;">Paskelbkite naują įrašą.</p>

      <?php if ($errors): ?>
        <div class="notice error">
          <?php foreach ($errors as $error): echo htmlspecialchars($error) . '<br>'; endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" onsubmit="return syncBody();">
        <?php echo csrfField(); ?>
        
        <label for="title">Pavadinimas</label>
        <input id="title" name="title" type="text" required value="<?php echo htmlspecialchars($title); ?>">

        <label for="author">Autorius</label>
        <input id="author" name="author" type="text" value="<?php echo htmlspecialchars($author); ?>">

        <label>Kategorijos (galima žymėti kelias)</label>
        <div class="cat-grid">
            <?php foreach ($categories as $cat): ?>
                <label class="cat-item">
                    <input type="checkbox" name="categories[]" value="<?php echo $cat['id']; ?>" 
                        <?php echo in_array($cat['id'], $selectedCatIds) ? 'checked' : ''; ?>>
                    <span><?php echo htmlspecialchars($cat['name']); ?></span>
                </label>
            <?php endforeach; ?>
            <?php if(empty($categories)): ?>
                <div style="color:#666; font-size:14px; padding:4px;">Kategorijų nėra. Sukurkite jas per admin pultą.</div>
            <?php endif; ?>
        </div>

        <label for="summary">Santrauka</label>
        <textarea id="summary" name="summary" required style="min-height:80px;"><?php echo htmlspecialchars($summary); ?></textarea>

        <label for="image">Nuotrauka</label>
        <input id="image" name="image" type="file" accept="image/*" required>

        <label>Turinys</label>
        <div class="toolbar">
            <button type="button" onmousedown="event.preventDefault(); document.execCommand('bold', false, null);"><b>B</b></button>
            <button type="button" onmousedown="event.preventDefault(); document.execCommand('italic', false, null);"><i>I</i></button>
            <button type="button" onmousedown="event.preventDefault(); document.execCommand('underline', false, null);"><u>U</u></button>
            <button type="button" onmousedown="event.preventDefault(); document.execCommand('insertUnorderedList', false, null);">• Sąrašas</button>
        </div>
        <div id="body-editor" class="rich-editor" contenteditable="true"><?php echo sanitizeHtml($body); ?></div>
        <textarea id="body" name="body" hidden><?php echo htmlspecialchars($body); ?></textarea>

        <div style="margin-top:12px; display:flex; gap:20px; flex-wrap:wrap;">
            <label style="display:flex; align-items:center; gap:8px; margin:0; cursor:pointer;">
                <input type="checkbox" name="is_featured" value="1" <?php echo $isFeatured ? 'checked' : ''; ?> style="width:20px; height:20px;"> 
                Rodyti kaip išskirtinę
            </label>
        </div>

        <label for="visibility">Matomumas</label>
        <select id="visibility" name="visibility">
          <option value="public" <?php echo $visibility === 'public' ? 'selected' : ''; ?>>Visiems matoma</option>
          <option value="members" <?php echo $visibility === 'members' ? 'selected' : ''; ?>>Tik nariams</option>
        </select>

        <button type="submit">Sukurti naujieną</button>
      </form>
      
      <div style="margin-top:15px;">
        <a href="/news.php" style="text-decoration:underline;">Atšaukti</a>
      </div>
    </div>
  </div>
  <script>
    function syncBody() {
        document.getElementById('body').value = document.getElementById('body-editor').innerHTML;
        return true;
    }
  </script>
</body>
</html>
