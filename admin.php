<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login.php');
    exit;
}

$pdo = getPdo();
// Užtikriname, kad visos lentelės egzistuoja
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
tryAutoLogin($pdo);

$messages = [];
$errors = [];
$view = $_GET['view'] ?? 'dashboard';

// Įtraukiame pagalbines funkcijas ir veiksmų logiką
require __DIR__ . '/admin/functions.php';
require __DIR__ . '/admin/actions.php';

// Pastaba: hero_stats.php paliekame, jei jis naudojamas kitur,
// bet Dashboard'e naudosime atskirus skaičiavimus detalesnei statistikai.
require __DIR__ . '/admin/hero_stats.php'; 
?>
<!doctype html>
<html lang="lt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administravimas | Cukrinukas.lt</title>
  <?php echo headerStyles(); ?>
  <?php require __DIR__ . '/admin/header.php'; ?>
  
  <style>
      :root {
          --primary: #4f46e5;
          --bg-body: #f3f4f6;
          --bg-card: #ffffff;
          --text-main: #111827;
          --text-muted: #6b7280;
          --border: #e5e7eb;
          --success: #10b981;
          --danger: #ef4444;
          --warning: #f59e0b;
      }
      
      body {
          background-color: var(--bg-body);
          color: var(--text-main);
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      }
      
      .admin-header {
          background: var(--bg-card);
          border-bottom: 1px solid var(--border);
          padding: 1rem 0;
          margin-bottom: 2rem;
      }

      .page {
          max-width: 1200px;
          margin: 0 auto;
          padding: 0 20px 40px 20px;
      }

      /* Modern Navigation Tabs */
      .nav-scroll-wrapper {
          overflow-x: auto;
          padding-bottom: 5px;
          margin-bottom: 24px;
          border-bottom: 1px solid var(--border);
      }
      
      .nav-tabs {
          display: flex;
          gap: 4px;
          min-width: max-content;
      }
      
      .nav-link {
          padding: 10px 16px;
          text-decoration: none;
          color: var(--text-muted);
          font-weight: 500;
          font-size: 14px;
          border-radius: 6px;
          transition: all 0.2s;
          display: inline-flex;
          align-items: center;
          gap: 6px;
      }
      
      .nav-link:hover {
          background: rgba(79, 70, 229, 0.05);
          color: var(--primary);
      }
      
      .nav-link.active {
          background: #eef2ff;
          color: var(--primary);
          font-weight: 600;
      }

      /* Alerts */
      .alert {
          padding: 12px 16px;
          border-radius: 8px;
          margin-bottom: 16px;
          font-size: 14px;
          display: flex;
          align-items: center;
          gap: 8px;
      }
      .alert.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
      .alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

      /* Common Card Styles (jei nenaudojamas layout.php stilius) */
      .card {
          background: var(--bg-card);
          border: 1px solid var(--border);
          border-radius: 12px;
          padding: 24px;
          box-shadow: 0 1px 3px rgba(0,0,0,0.05);
          margin-bottom: 24px;
      }
      
      h3 { margin-top: 0; font-size: 16px; font-weight: 700; color: var(--text-main); margin-bottom: 16px; }
      
      /* Grid System overrides */
      .grid { display: grid; gap: 24px; }
      @media (min-width: 768px) {
          .grid-2 { grid-template-columns: 1fr 1fr; }
          .grid-3 { grid-template-columns: 1fr 1fr 1fr; }
          .grid-4 { grid-template-columns: repeat(4, 1fr); }
      }
      
      /* Tables */
      table { width: 100%; border-collapse: collapse; font-size: 14px; }
      th { text-align: left; padding: 12px; border-bottom: 2px solid var(--border); color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
      td { padding: 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
      tr:last-child td { border-bottom: none; }
      
      .btn {
          display: inline-flex; align-items: center; justify-content: center;
          padding: 8px 16px; border-radius: 6px; font-weight: 500; font-size: 14px;
          cursor: pointer; transition: 0.2s; border: 1px solid transparent;
      }
  </style>
</head>
<body>
  <?php renderHeader($pdo, 'admin'); ?>
  
  <div class="page">
    
    <div style="margin-top: 24px; margin-bottom: 24px;">
        <h1 style="margin:0; font-size:24px;">Sveiki sugrįžę, Administratoriau! 👋</h1>
        <p style="margin:4px 0 0 0; color:var(--text-muted); font-size:14px;">Štai kas vyksta jūsų parduotuvėje šiandien.</p>
    </div>

    <?php foreach ($messages as $msg): ?>
      <div class="alert success">&check; <?php echo htmlspecialchars($msg); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $err): ?>
      <div class="alert error">&times; <?php echo htmlspecialchars($err); ?></div>
    <?php endforeach; ?>

    <div class="nav-scroll-wrapper">
        <div class="nav-tabs">
          <a class="nav-link <?php echo $view === 'dashboard' ? 'active' : ''; ?>" href="?view=dashboard">📊 Skydelis</a>
          <a class="nav-link <?php echo $view === 'orders' ? 'active' : ''; ?>" href="?view=orders">📦 Užsakymai</a>
          <a class="nav-link <?php echo $view === 'products' ? 'active' : ''; ?>" href="?view=products">🏷️ Prekės</a>
          <a class="nav-link <?php echo $view === 'categories' ? 'active' : ''; ?>" href="?view=categories">📂 Kategorijos</a>
          <a class="nav-link <?php echo $view === 'users' ? 'active' : ''; ?>" href="?view=users">👥 Vartotojai</a>
          <a class="nav-link <?php echo $view === 'community' ? 'active' : ''; ?>" href="?view=community">💬 Bendruomenė</a>
          <a class="nav-link <?php echo $view === 'content' ? 'active' : ''; ?>" href="?view=content">📝 Turinys</a>
          <a class="nav-link <?php echo $view === 'discounts' ? 'active' : ''; ?>" href="?view=discounts">🏷️ Nuolaidos</a>
          <a class="nav-link <?php echo $view === 'design' ? 'active' : ''; ?>" href="?view=design">🎨 Dizainas</a>
          <a class="nav-link <?php echo $view === 'menus' ? 'active' : ''; ?>" href="?view=menus">🔗 Meniu</a>
          <a class="nav-link <?php echo $view === 'shipping' ? 'active' : ''; ?>" href="?view=shipping">🚚 Pristatymas</a>
        </div>
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
    // Automatiškai paslepiame pranešimus po 5s
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => el.style.display = 'none');
    }, 5000);

    // Egzistuojančios funkcijos
    function addAttrRow(targetId){
      const wrap = document.getElementById(targetId);
      if(!wrap) return;
      const div = document.createElement('div');
      div.style.display = 'flex'; div.style.gap = '8px'; div.style.marginBottom = '8px';
      
      const name = document.createElement('input');
      name.name = 'attr_label[]';
      name.className = 'chip-input';
      name.placeholder = 'Pavadinimas';
      name.style.flex = '1';

      const val = document.createElement('input');
      val.name = 'attr_value[]';
      val.className = 'chip-input';
      val.placeholder = 'Reikšmė';
      val.style.flex = '1';

      div.appendChild(name);
      div.appendChild(val);
      wrap.appendChild(div);
    }
    
    function addVarRow(targetId){
        // ... (tavo esama funkcija)
        const wrap = document.getElementById(targetId);
        if(!wrap) return;
        const div = document.createElement('div');
        div.style.display = 'flex'; div.style.gap = '8px'; div.style.marginBottom = '8px';

        const name = document.createElement('input');
        name.name = 'variation_name[]';
        name.className = 'chip-input';
        name.placeholder = 'Variacijos pavadinimas';
        name.style.flex = '2';

        const price = document.createElement('input');
        price.name = 'variation_price[]';
        price.type = 'number';
        price.step = '0.01';
        price.className = 'chip-input';
        price.placeholder = '+Kaina';
        price.style.flex = '1';

        div.appendChild(name);
        div.appendChild(price);
        wrap.appendChild(div);
    }
  </script>
  <?php renderFooter($pdo); ?>
</body>
</html>
