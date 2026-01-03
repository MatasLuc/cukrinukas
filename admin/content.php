<?php
// admin/content.php

$newsList = $pdo->query('SELECT id, title, created_at FROM news ORDER BY created_at DESC')->fetchAll();
$recipeList = $pdo->query('SELECT id, title, created_at FROM recipes ORDER BY created_at DESC')->fetchAll();
?>

<div class="card">
  <h3>Naujienos</h3>
  <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom:10px;">
    <p style="margin:0; color:#6b6b7a;">Redaguokite diabeto naujienų įrašus.</p>
    <a class="btn" href="/news_create.php">+ Nauja naujiena</a>
  </div>
  <table>
    <thead><tr><th>Pavadinimas</th><th>Data</th><th>Veiksmai</th></tr></thead>
    <tbody>
      <?php foreach ($newsList as $n): ?>
        <tr>
          <td><?php echo htmlspecialchars($n['title']); ?></td>
          <td><?php echo date('Y-m-d', strtotime($n['created_at'])); ?></td>
          <td><a class="btn" href="/news_edit.php?id=<?php echo (int)$n['id']; ?>">Redaguoti</a></td>
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
          <td><a class="btn" href="/recipe_edit.php?id=<?php echo (int)$r['id']; ?>">Redaguoti</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
