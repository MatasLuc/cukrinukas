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

// Visos kategorijos
$categories = $pdo->query("SELECT * FROM news_categories ORDER BY name ASC")->fetchAll();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM news WHERE id = ?');
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    die('Įrašas nerastas');
}

// GAUNAME JAU PRISKIRTAS KATEGORIJAS
$stmtCats = $pdo->prepare("SELECT category_id FROM news_category_relations WHERE news_id = ?");
$stmtCats->execute([$id]);
$currentCatIds = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

$errors = [];
$message = '';
// Numatytosios reikšmės iš DB
$title = $item['title'];
$summary = $item['summary'];
$author = $item['author'];
$body = $item['body'];
$visibility = $item['visibility'];
$isFeatured = $item['is_featured'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $author = trim($_POST['author'] ?? '');
    // Naujas pasirinkimas iš formos
    $selectedCatIds = $_POST['categories'] ?? [];
    $body = trim($_POST['body'] ?? '');
    $visibility = $_POST['visibility'] === 'members' ? 'members' : 'public';
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

    if ($title === '' || $body === '' || $summary === '') {
        $errors[] = 'Užpildykite visus laukus.';
    }

    $imagePath = $item['image_url'];
    $newImage = uploadImageWithValidation($_FILES['image'] ?? [], 'news_', $errors, null);
    if ($newImage) {
        $imagePath = $newImage;
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // 1. Atnaujiname naujienos info
            $stmt = $pdo->prepare('UPDATE news SET title = ?, summary = ?, author = ?, image_url = ?, body = ?, visibility = ?, is_featured = ? WHERE id = ?');
            $stmt->execute([$title, $summary, $author, $imagePath, $body, $visibility, $isFeatured, $id]);

            // 2. Atnaujiname kategorijas: Ištriname senas -> Įrašome naujas
            $pdo->prepare("DELETE FROM news_category_relations WHERE news_id = ?")->execute([$id]);
            
            if (!empty($selectedCatIds)) {
                $relStmt = $pdo->prepare('INSERT INTO news_category_relations (news_id, category_id) VALUES (?, ?)');
                foreach ($selectedCatIds as $catId) {
                    $relStmt->execute([$id, (int)$catId]);
                }
            }
            
            $pdo->commit();
            
            $message = 'Naujiena sėkmingai atnaujinta';
            // Atnaujiname atvaizdavimui skirtą kintamąjį
            $currentCatIds = $selectedCatIds;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            logError('News update failed', $e);
            $errors[] = friendlyErrorMessage();
        }
    }
}

$safeBody = sanitizeHtml($body);
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Redaguoti naujieną</title>
  <?php echo headerStyles(); ?>
  <style>
    :root { --color-bg: #f7f7fb; }
    .wrapper { padding: 24px; display:flex; justify-content:center; }
    .card { background: #fff; padding: 28px; border-radius: 16px; width: min(720px, 100%); box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    label { display:block; margin:12px 0 5px; font-weight:600; }
    input[type=text], select, textarea { width: 100%; padding: 10px; border:1px solid #ccc; border-radius:8px; background:#fbfbff; }
    .rich-editor { min-height:200px; border:1px solid #ccc; padding:10px; border-radius:8px; margin-bottom:12px; background:#fbfbff; }
    .notice.success { background:#e6fffa; color:#047481; padding:10px; border-radius:8px; margin-bottom:10px; border:1px solid #b2f5ea; }
    .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; border: 1px solid #ddd; padding: 12px; border-radius: 8px; background: #fbfbff; max-height: 200px; overflow-y: auto; }
    .cat-item { display:flex; align-items:center; gap:8px; cursor:pointer; padding:4px; transition:background 0.1s; border-radius:4px; }
    .cat-item:hover { background:#eee; }
    .toolbar button { margin-right:5px; padding:5px 8px; cursor:pointer; }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'news'); ?>
  <div class="wrapper">
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center;">
          <h1>Redaguoti naujieną</h1>
          <a href="/news.php" class="btn secondary" style="font-size:14px; padding:6px 12px;">Grįžti</a>
      </div>

      <?php if ($message): ?><div class="notice success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <?php if ($errors): ?><div class="notice error" style="background:#fff5f5; color:red; padding:10px; border:1px solid red; border-radius:8px;"><?= implode('<br>', $errors) ?></div><?php endif; ?>
      
      <form method="post" enctype="multipart/form-data" onsubmit="document.getElementById('body').value = document.getElementById('body-editor').innerHTML;">
        <?php echo csrfField(); ?>
        
        <label>Pavadinimas</label>
        <input name="title" type="text" value="<?= htmlspecialchars($title) ?>" required>

        <label>Autorius</label>
        <input name="author" type="text" value="<?= htmlspecialchars($author) ?>">

        <label>Kategorijos</label>
        <div class="cat-grid">
            <?php foreach ($categories as $cat): ?>
                <label class="cat-item">
                    <input type="checkbox" name="categories[]" value="<?= $cat['id'] ?>" 
                        <?= in_array($cat['id'], $currentCatIds) ? 'checked' : '' ?> 
                        style="width:auto; margin:0;">
                    <?= htmlspecialchars($cat['name']) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <label>Santrauka</label>
        <textarea name="summary" required style="min-height:80px;"><?= htmlspecialchars($summary) ?></textarea>

        <label>Nuotrauka</label>
        <div style="display:flex; align-items:center; gap:15px; margin-bottom:5px;">
            <?php if($item['image_url']): ?>
                <img src="<?= htmlspecialchars($item['image_url']) ?>" style="width:60px; height:60px; object-fit:cover; border-radius:6px;">
            <?php endif; ?>
            <input name="image" type="file" accept="image/*">
        </div>
        
        <label>Turinys</label>
        <div class="toolbar">
            <button type="button" onmousedown="event.preventDefault(); document.execCommand('bold')"><b>B</b></button>
            <button type="button" onmousedown="event.preventDefault(); document.execCommand('italic')"><i>I</i></button>
            <button type="button" onmousedown="event.preventDefault(); document.execCommand('underline')"><u>U</u></button>
        </div>
        <div id="body-editor" class="rich-editor" contenteditable="true"><?= $safeBody ?></div>
        <textarea id="body" name="body" hidden><?= htmlspecialchars($body) ?></textarea>

        <label style="display:flex; align-items:center; gap:8px; margin-top:10px;">
            <input type="checkbox" name="is_featured" value="1" <?= $isFeatured ? 'checked' : '' ?> style="width:auto;"> 
            Išskirtinė naujiena (rodyti viršuje)
        </label>

        <label>Matomumas</label>
        <select name="visibility">
            <option value="public" <?= $visibility == 'public' ? 'selected' : '' ?>>Visiems</option>
            <option value="members" <?= $visibility == 'members' ? 'selected' : '' ?>>Tik registruotiems</option>
        </select>

        <button type="submit" style="background:#0b0b0b; color:#fff; padding:12px 24px; border-radius:10px; border:none; margin-top:20px; font-weight:bold; cursor:pointer; width:100%;">Išsaugoti pakeitimus</button>
      </form>
    </div>
  </div>
</body>
</html>
