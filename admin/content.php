<?php
// admin/content.php

$newsList = $pdo->query('SELECT id, title, created_at FROM news ORDER BY created_at DESC')->fetchAll();
$recipeList = $pdo->query('SELECT id, title, created_at FROM recipes ORDER BY created_at DESC')->fetchAll();
?>

<div class="card">
  <h3>Naujienos</h3>
  <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom:10px;">
    <p style="margin:0; color:#6b6b7a;">Redaguokite diabeto naujienų įrašus.</p>
    <a class="btn" style="background:#fff; color:#0b0b0b; border:1px solid #ccc;" href="/admin/news_categories.php">Valdyti kategorijas</a>
    <a class="btn" href="/news_create.php">+ Nauja naujiena</a>
  </div>
  <table>
    <thead><tr><th>Pavadinimas</th><th>Data</th><th>Veiksmai</th></tr></thead>
    <tbody>
      <?php foreach ($newsList as $n): ?>
        <tr>
          <td><?php echo htmlspecialchars($n['title']); ?></td>
          <td><?php echo date('Y-m-d', strtotime($n['created_at'])); ?></td>
          <td style="display:flex; gap: 8px;">
            <a class="btn" href="/news_edit.php?id=<?php echo (int)$n['id']; ?>">Redaguoti</a>
            <form method="POST" onsubmit="return confirm('Ar tikrai norite ištrinti šią naujieną?');" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="action" value="delete_news">
                <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                <button type="submit" class="btn" style="background-color: #d32f2f; color: white; border: none; cursor: pointer;">Ištrinti</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card" style="margin-top:18px;">
  <h3>Receptai</h3>
  <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom:10px;">
    <p style="margin:0; color:#6b6b7a;">Prižiūrėkite receptus ir jų turinį.</p>
    <a class="btn" href="/recipe_create.php">+ Naujas receptas</a>
  </div>
  <table>
    <thead><tr><th>Pavadinimas</th><th>Data</th><th>Veiksmai</th></tr></thead>
    <tbody>
      <?php foreach ($recipeList as $r): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['title']); ?></td>
          <td><?php echo date('Y-m-d', strtotime($r['created_at'])); ?></td>
          <td style="display:flex; gap: 8px;">
            <a class="btn" href="/recipe_edit.php?id=<?php echo (int)$r['id']; ?>">Redaguoti</a>
            <form method="POST" onsubmit="return confirm('Ar tikrai norite ištrinti šį receptą?');" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="action" value="delete_recipe">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <button type="submit" class="btn" style="background-color: #d32f2f; color: white; border: none; cursor: pointer;">Ištrinti</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
