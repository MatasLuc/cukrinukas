<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login.php');
    exit;
}

$pdo = getPdo();
ensureUsersTable($pdo);
ensureCategoriesTable($pdo);
ensureProductsTable($pdo);
ensureOrdersTables($pdo);
ensureCartTables($pdo);
ensureAdminAccount($pdo);
ensureFeaturedProductsTable($pdo);
ensureNavigationTable($pdo);
ensureFooterLinksTable($pdo);
ensureNewsTable($pdo);
ensureRecipesTable($pdo);
ensureDiscountTables($pdo);
ensureCategoryDiscounts($pdo);
ensureShippingSettings($pdo);
ensureLockerTables($pdo);
ensureCommunityTables($pdo);

$messages = [];
$errors = [];
$view = $_GET['view'] ?? 'dashboard';

// Įtraukiame pagalbines funkcijas ir veiksmų logiką
require __DIR__ . '/admin/functions.php';
require __DIR__ . '/admin/actions.php';
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administravimas | Cukrinukas.lt</title>
  <?php echo headerStyles(); ?>
  <?php require __DIR__ . '/admin/header.php'; ?>
</head>
<body>
  <?php renderHeader($pdo, 'admin'); ?>
  <div class="page">
    
    <?php require __DIR__ . '/admin/hero_stats.php'; ?>

    <?php foreach ($messages as $msg): ?>
      <div class="alert success" style="margin-bottom:10px;">&check; <?php echo htmlspecialchars($msg); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $err): ?>
      <div class="alert error" style="margin-bottom:10px;">&times; <?php echo htmlspecialchars($err); ?></div>
    <?php endforeach; ?>

    <div class="nav">
      <a class="<?php echo $view === 'dashboard' ? 'active' : ''; ?>" href="?view=dashboard">Skydelis</a>
      <a class="<?php echo $view === 'products' ? 'active' : ''; ?>" href="?view=products">Prekės</a>
      <a class="<?php echo $view === 'categories' ? 'active' : ''; ?>" href="?view=categories">Kategorijos</a>
      <a class="<?php echo $view === 'content' ? 'active' : ''; ?>" href="?view=content">Turinys</a>
      <a class="<?php echo $view === 'design' ? 'active' : ''; ?>" href="?view=design">Dizainas</a>
      <a class="<?php echo $view === 'shipping' ? 'active' : ''; ?>" href="?view=shipping">Pristatymas</a>
      <a class="<?php echo $view === 'discounts' ? 'active' : ''; ?>" href="?view=discounts">Nuolaidos</a>
      <a class="<?php echo $view === 'community' ? 'active' : ''; ?>" href="?view=community">Bendruomenė</a>
      <a class="<?php echo $view === 'menus' ? 'active' : ''; ?>" href="?view=menus">Meniu</a>
      <a class="<?php echo $view === 'users' ? 'active' : ''; ?>" href="?view=users">Vartotojai</a>
      <a class="<?php echo $view === 'orders' ? 'active' : ''; ?>" href="?view=orders">Užsakymai</a>
    </div>

    <?php
    $allowedViews = [
        'dashboard', 'products', 'categories', 'content', 'design', 
        'shipping', 'discounts', 'community', 'menus', 'users', 'orders'
    ];

    if (in_array($view, $allowedViews)) {
        require __DIR__ . "/admin/{$view}.php";
    } else {
        echo '<div class="alert error">Puslapis nerastas.</div>';
    }
    ?>
  </div>
  
  <script>
    function addAttrRow(targetId){
      const wrap = document.getElementById(targetId);
      if(!wrap) return;
      const name = document.createElement('input');
      name.name = 'attr_label[]';
      name.className = 'chip-input';
      name.placeholder = 'Laukelio pavadinimas';
      const val = document.createElement('input');
      val.name = 'attr_value[]';
      val.className = 'chip-input';
      val.placeholder = 'Aprašymas';
      wrap.appendChild(name);
      wrap.appendChild(val);
    }
    function addVarRow(targetId){
      const wrap = document.getElementById(targetId);
      if(!wrap) return;
      const name = document.createElement('input');
      name.name = 'variation_name[]';
      name.className = 'chip-input';
      name.placeholder = 'Variacijos pavadinimas';
      const price = document.createElement('input');
      price.name = 'variation_price[]';
      price.type = 'number';
      price.step = '0.01';
      price.className = 'chip-input';
      price.placeholder = 'Kainos pokytis';
      wrap.appendChild(name);
      wrap.appendChild(price);
    }
    document.querySelectorAll('[data-toggle-select]').forEach(function(input){
      const selectName = input.getAttribute('data-toggle-select');
      let select = input.closest('form')?.querySelector('select[name="' + selectName + '"]');
      if (!select && input.getAttribute('form')) {
        const f = document.getElementById(input.getAttribute('form'));
        if (f && f.elements[selectName]) {
          select = f.elements[selectName];
        }
      }
      if (!select) {
        select = document.querySelector('select[name="' + selectName + '"]');
      }
      const toggle = function(){
        if (!select) return;
        const v = select.value;
        const disable = (v === 'free_shipping' || v === 'none');
        input.disabled = disable;
        if (disable) { input.value = '0'; }
      };
      if (select) {
        select.addEventListener('change', toggle);
        toggle();
      }
    });
  </script>
  <?php renderFooter($pdo); ?>
</body>
</html>
