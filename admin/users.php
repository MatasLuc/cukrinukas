<?php
// admin/users.php

$users = $pdo->query('SELECT id, name, email, is_admin, created_at FROM users ORDER BY created_at DESC')->fetchAll();
?>

<div class="card">
  <h3>Vartotojų valdymas</h3>
  <table class="table-form">
    <thead><tr><th>Vardas</th><th>El. paštas</th><th>Rolė</th><th>Užsakymai</th><th>Krepšelis</th><th>Veiksmai</th></tr></thead>
    <tbody>
      <?php foreach ($users as $user):
        $orderCountStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
        $orderCountStmt->execute([$user['id']]);
        $orderCount = (int)$orderCountStmt->fetchColumn();
        $ordersMini = $pdo->prepare('SELECT id, total, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 3');
        $ordersMini->execute([$user['id']]);
        $orderRows = $ordersMini->fetchAll();
        $cartSnapshot = getUserCartSnapshot($pdo, (int)$user['id']);
        $userFormId = 'userform' . (int)$user['id'];
      ?>
        <form id="<?php echo $userFormId; ?>" method="post"></form>
        <tr>
          <td>
            <input type="hidden" form="<?php echo $userFormId; ?>" name="action" value="edit_user">
            <input type="hidden" form="<?php echo $userFormId; ?>" name="user_id" value="<?php echo (int)$user['id']; ?>">
            <input form="<?php echo $userFormId; ?>" name="name" value="<?php echo htmlspecialchars($user['name']); ?>">
          </td>
          <td><input form="<?php echo $userFormId; ?>" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"></td>
          <td style="min-width:120px;"><?php echo $user['is_admin'] ? 'Admin' : 'Vartotojas'; ?></td>
          <td>
            <div><strong><?php echo $orderCount; ?></strong> vnt.</div>
            <?php if ($orderRows): ?>
              <ul style="margin:4px 0 0; padding-left:18px;">
                <?php foreach ($orderRows as $o): ?>
                  <li>#<?php echo (int)$o['id']; ?> — <?php echo number_format((float)$o['total'], 2); ?> € (<?php echo htmlspecialchars($o['status']); ?>)</li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($cartSnapshot): ?>
              <ul style="margin:0; padding-left:18px;">
                <?php foreach ($cartSnapshot as $c): ?>
                  <li><?php echo htmlspecialchars($c['title']); ?> (<?php echo $c['quantity']; ?> vnt)</li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <span class="muted">Tuščias</span>
            <?php endif; ?>
          </td>
          <td class="inline-actions">
            <button class="btn" form="<?php echo $userFormId; ?>" type="submit" style="padding:8px 12px;">Išsaugoti</button>
            <?php if ($user['id'] !== ($_SESSION['user_id'] ?? null)): ?>
              <form method="post" style="margin:0;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="toggle_admin">
                <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                <button class="btn" type="submit" style="background:#fff; color:#0b0b0b; padding:8px 12px;">Perjungti admin</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
