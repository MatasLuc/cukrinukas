<?php
require_once __DIR__ . '/security.php';
enforcePostRequestCsrf();

function currentUser(): array {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'is_admin' => !empty($_SESSION['is_admin']),
    ];
}

function headerStyles(?int $overrideShadow = null): string {
    // Shadow kept configurable for future needs, but default styling uses none.
    $shadowSource = $overrideShadow ?? ($GLOBALS['headerShadowIntensity'] ?? getenv('HEADER_SHADOW_INTENSITY'));
    $shadowLevel = is_numeric($shadowSource) ? max(0, min(100, (int)$shadowSource)) : 0;
    $shadowOpacity = round(0.28 * ($shadowLevel / 100), 3);
    $shadowBlur = round(26 * ($shadowLevel / 100), 2);

    return <<<CSS
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</noscript><style>
:root {
  --font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --font-weight-regular: 400;
  --font-weight-medium: 500;
  --font-weight-semibold: 600;
  --font-weight-bold: 700;
  --text-color: #0b0b0b;
  --surface: #f7f7fb;
  --accent: #829ed6;
  --header-shadow-blur: {$shadowBlur}px;
  --header-shadow-opacity: {$shadowOpacity};
  --header-surface: #ffffff;
  --header-border: #e6e6ef;
}

body { margin: 0; background: var(--surface); color: var(--text-color); font-family: var(--font-family); font-weight: var(--font-weight-regular); }
* { font-family: var(--font-family); }

h1, h2, h3, h4, h5, h6 { font-weight: var(--font-weight-semibold); letter-spacing: -0.01em; color: var(--text-color); }
strong, b { font-weight: 700 !important; color: var(--text-color); }

a { color: var(--text-color); }
.muted { color: #4a4a55; font-weight: var(--font-weight-medium); }

/* Headerio Z-INDEX pataisymas: užtikrina, kad meniu visada viršuje */
.header {
  background: rgba(255,255,255,0.98);
  backdrop-filter: saturate(1.2) blur(14px);
  position: sticky;
  top: 0;
  z-index: 1000; 
  box-shadow: none;
  border-bottom: 1px solid var(--header-border);
}

.announcement-bar {
  background: var(--accent);
  color: #0b0b0b;
  text-align: center;
  padding: 10px 16px;
  font-weight: var(--font-weight-semibold);
  position: sticky;
  top: 0;
  z-index: 1001;
  display: flex;
  justify-content: center;
  gap: 10px;
  align-items: center;
}
.announcement-bar a { color: inherit; text-decoration: none; font-weight: var(--font-weight-semibold); }
.announcement-bar__label { padding:6px 10px; background:rgba(255,255,255,0.6); border-radius:999px; font-size:12px; }

.navbar {
  max-width: 1200px;
  margin: 0 auto;
  padding: 14px 22px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  color: #0b0b0b;
  position: relative;
}
.nav-toggle {
  display: none;
  background: transparent;
  border: none;
  color: #0b0b0b;
  padding: 8px;
  font-size: 24px;
  cursor: pointer;
  line-height: 1;
}
.brand {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-weight: var(--font-weight-bold);
  letter-spacing: 0.3px;
  font-size: 19px;
  color: #0b0b0b;
  padding: 8px 12px;
  border-radius: 12px;
  transition: background .2s ease, color .2s ease;
  background: rgba(130,158,214,0.15);
  text-decoration: none;
}
.brand:hover { background: rgba(130,158,214,0.28); }

.nav-links {
  display: flex;
  gap: 14px;
  align-items: center;
  flex: 1;
  justify-content: flex-end;
}
.nav-item { position: relative; }
.nav-submenu {
  position:absolute;
  top:100%;
  left:0;
  background:#fff;
  border:1px solid #e6e6ef;
  border-radius:12px;
  padding:12px;
  box-shadow:0 18px 38px rgba(0,0,0,0.16);
  display:none;
  min-width:180px;
  z-index: 1002;
  flex-direction:column;
  gap:4px;
}
.nav-submenu .nav-item { display:block; }
.nav-item:hover .nav-submenu, .nav-item:focus-within .nav-submenu { display:flex; }
.nav-submenu a { color:#0b0b0b; font-weight:600; padding:8px 10px; display:block; border-radius:10px; }
.nav-submenu a:hover { background:#f3f3f8; }

.nav-links a {
  color: #0b0b0b;
  text-decoration: none;
  font-weight: var(--font-weight-medium);
  padding: 10px 12px;
  border-radius: 12px;
  transition: background .15s ease, color .15s ease;
}
.nav-links a:hover { color: #0b0b0b; background: rgba(130,158,214,0.18); }

.nav-links .user-area { position: relative; padding-bottom: 6px; }
.nav-links .user-button {
  display:flex;
  align-items:center;
  gap:8px;
  padding:10px 14px;
  border-radius:14px;
  background:#f6f7ff;
  color:#0b0b0b;
  border:1px solid #dcdce7;
  font-weight: var(--font-weight-medium);
  box-shadow:0 10px 20px rgba(0,0,0,0.08);
  position: relative;
  cursor: pointer;
}
.user-button__badge {
  position:absolute;
  top:-6px;
  right:-6px;
  background: var(--accent);
  color:#0b0b0b;
  font-weight: var(--font-weight-bold);
  font-size:11px;
  line-height:1;
  padding:4px 6px;
  border-radius:999px;
  box-shadow:0 6px 14px rgba(0,0,0,0.14);
}
.nav-links .user-menu {
  position:absolute;
  top:100%;
  right:0;
  background:#fff;
  color:var(--text-color);
  border-radius:12px;
  box-shadow:0 22px 48px rgba(0,0,0,0.18);
  padding:12px;
  min-width:240px;
  display:flex;
  flex-direction:column;
  gap:6px;
  border:1px solid #e6e6ef;
  opacity:0;
  pointer-events:none;
  transition:opacity .15s ease;
  z-index: 1003;
}
.checkbox-row {
  display:flex;
  align-items:center;
  gap:8px;
  margin:0;
  font-weight: var(--font-weight-medium);
}
input[type=checkbox] {
  width: 16px;
  height: 16px;
  margin: 0;
  accent-color: #829ed6;
}
.user-menu a,
.user-menu button {
  color:#0b0b0b;
  font-weight: var(--font-weight-semibold);
  font-size:15px;
  padding:8px 10px;
  border-radius:10px;
  background:none;
  border:none;
  text-align:left;
  cursor:pointer;
}
.user-menu a:hover,
.user-menu button:hover { background:#f3f3f8; }
.user-area:hover .user-menu,
.user-area:focus-within .user-menu,
.user-menu:hover,
.user-menu.open { opacity:1; pointer-events:auto; }

.cart-link { position: relative; }
.cart-icon {
  display:inline-flex;
  align-items:center;
  gap:6px;
  color:#0b0b0b;
  font-weight:600;
}
.cart-icon svg { width:22px; height:22px; }
.cart-count {
  position: absolute;
  top: -6px;
  right: -10px;
  background: #829ed6;
  color: #fff;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  font-size: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: var(--font-weight-semibold);
  box-shadow:0 4px 10px rgba(0,0,0,0.16);
}
.cart-dropdown {
  position: absolute;
  right: 0;
  top: calc(100% + 10px);
  min-width: 460px;
  background: #fff;
  border: 1px solid #e6e6ef;
  border-radius: 12px;
  box-shadow: 0 16px 40px rgba(0,0,0,0.12);
  padding: 12px;
  display: none;
  flex-direction: column;
  gap: 10px;
  color: var(--text-color);
  transform: translateX(0);
  overflow-y: auto;
  max-height: 70vh;
  z-index: 1005; /* Krepšelis turi būti aukščiau meniu */
}
.cart-link:hover .cart-dropdown,
.cart-link:focus-within .cart-dropdown,
.cart-dropdown.open { display: flex; }
.cart-preview-item { display:flex; gap:10px; align-items:center; }
.cart-preview-item img { width:56px; height:56px; object-fit:cover; border-radius:10px; }
.cart-preview-meta { display:flex; justify-content: space-between; width:100%; }
.cart-preview-footer { display:flex; justify-content: space-between; align-items:center; margin-top:6px; }
.btn {
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:10px 14px;
  border-radius:12px;
  border:1px solid var(--accent);
  background: var(--accent);
  color:#0b0b0b;
  font-weight: var(--font-weight-semibold);
  text-decoration: none;
  box-shadow:0 10px 22px rgba(130,158,214,0.28);
}
.btn.secondary {
  background: transparent;
  color: var(--text-color);
}
.btn.ghost {
  background: transparent;
  color: var(--text-color);
  box-shadow:none;
  border-color: var(--accent);
}
.pill-count {
  background: var(--accent);
  color: #0b0b0b;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: var(--font-weight-semibold);
}

/* MOBILE MENU STYLES */
@media (max-width: 900px) {
  .navbar {
    justify-content: flex-start;
  }
  .brand {
    margin-right: auto;
  }
  .nav-toggle { 
    display: inline-flex; 
    margin-right: 8px;
  }
  .cart-link {
    order: 2;
  }
  
  .nav-links {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    background: #ffffff;
    border-bottom: 1px solid #e6e6ef;
    padding: 20px;
    box-sizing: border-box;
    flex-direction: column;
    align-items: stretch;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    z-index: 1000;
  }
  .nav-links.open { display: flex; }
  
  .nav-item {
    width: 100%;
  }
  .nav-links a {
    display: block;
    padding: 12px 15px;
    font-size: 16px;
    background: #fcfcfd;
    border: 1px solid transparent;
    margin-bottom: 4px;
  }
  .nav-links a:active,
  .nav-links a:hover {
    background: #f3f3f8;
    border-color: #e6e6ef;
  }
  
  .nav-submenu {
    position: static;
    box-shadow: none;
    border: none;
    padding: 0 0 0 15px;
    display: none;
    width: 100%;
    min-width: auto;
    z-index: auto;
  }
  .nav-item:hover .nav-submenu { display: flex; }

  .user-area {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 2px solid #f0f0f5;
    width: 100%;
  }
  .user-button {
    width: 100%;
    justify-content: center;
  }
  .user-menu {
    position: static;
    opacity: 1;
    pointer-events: auto;
    box-shadow: none;
    border: 1px solid #f0f0f5;
    margin-top: 10px;
    width: 100%;
    box-sizing: border-box;
    z-index: auto;
  }

  .cart-dropdown {
    position: fixed;
    top: 60px;
    left: 2%;
    right: 2%;
    width: 96%;
    min-width: auto;
    max-height: 80vh;
    z-index: 2000;
  }
}
</style>
CSS;
}

function renderHeader(PDO $pdo, string $active = '', array $meta = []): void {
    // Meta duomenų paruošimas su numatytosiomis reikšmėmis
    $metaTitle = $meta['title'] ?? 'Cukrinukas.lt – diabeto priemonės ir žinios';
    $metaDescRaw = strip_tags($meta['description'] ?? 'Gliukometrai, sensoriai, juostelės, mažo GI užkandžiai ir patarimai gyvenimui su diabetu.');
    $metaImage = $meta['image'] ?? 'https://cukrinukas.lt/uploads/default_social.jpg';
    $metaUrl = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    // SEO: Sutvarkytas aprašymo kirpimas
    $metaDesc = $metaDescRaw;
    if (mb_strlen($metaDescRaw) > 160) {
        $metaDesc = mb_substr($metaDescRaw, 0, 160);
        $lastSpace = mb_strrpos($metaDesc, ' ');
        if ($lastSpace !== false) {
            $metaDesc = mb_substr($metaDesc, 0, $lastSpace) . '...';
        } else {
            $metaDesc .= '...';
        }
    }

    $cart = getCartData($pdo, $_SESSION['cart'] ?? [], $_SESSION['cart_variations'] ?? []);
    $user = currentUser();
    try {
        ensureDirectMessages($pdo);
    } catch (Throwable $e) {
        // fail silently
    }
    $navItems = getNavigationTree($pdo);
    $siteContent = getSiteContent($pdo);
    $unreadMessages = $user['id'] ? getUnreadDirectMessagesCount($pdo, (int)$user['id']) : 0;
    $bannerEnabled = !empty($siteContent['banner_enabled']) && $siteContent['banner_enabled'] !== '0';
    $bannerText = trim($siteContent['banner_text'] ?? '');
    $bannerLink = trim($siteContent['banner_link'] ?? '');
    $bannerBg = $siteContent['banner_background'] ?? '#829ed6';
    $renderNav = function(array $items, bool $isChild = false) use (&$renderNav) {
        if (!$items) return '';
        $html = $isChild ? '<div class="nav-submenu">' : '';
        foreach ($items as $item) {
            $html .= '<div class="nav-item">';
            $html .= '<a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['label']) . '</a>';
            if (!empty($item['children'])) {
                $html .= $renderNav($item['children'], true);
            }
            $html .= '</div>';
        }
        if ($isChild) {
            $html .= '</div>';
        }
        return $html;
    };
    ?>
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link rel="manifest" href="/manifest.json">
      <meta name="theme-color" content="#829ed6">
      <link rel="apple-touch-icon" href="/uploads/icon-192.png">

      <title><?php echo htmlspecialchars($metaTitle); ?></title>
      <meta name="description" content="<?php echo htmlspecialchars($metaDesc); ?>">
      <link rel="canonical" href="<?php echo htmlspecialchars($metaUrl); ?>">
      <meta property="og:type" content="website">
      <meta property="og:url" content="<?php echo htmlspecialchars($metaUrl); ?>">
      <meta property="og:title" content="<?php echo htmlspecialchars($metaTitle); ?>">
      <meta property="og:description" content="<?php echo htmlspecialchars($metaDesc); ?>">
      <meta property="og:image" content="<?php echo htmlspecialchars($metaImage); ?>">
      <meta property="twitter:card" content="summary_large_image">
      <meta property="twitter:url" content="<?php echo htmlspecialchars($metaUrl); ?>">
      <meta property="twitter:title" content="<?php echo htmlspecialchars($metaTitle); ?>">
      <meta property="twitter:description" content="<?php echo htmlspecialchars($metaDesc); ?>">
      <meta property="twitter:image" content="<?php echo htmlspecialchars($metaImage); ?>">

      <script>
        if ('serviceWorker' in navigator) {
          window.addEventListener('load', () => {
            navigator.serviceWorker.register('/service-worker.js')
              .then(reg => console.log('SW registered:', reg.scope))
              .catch(err => console.log('SW registration failed:', err));
          });
        }
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', 'JUSU_PIXEL_ID'); 
        fbq('track', 'PageView');
      </script>
      <noscript><img height="1" width="1" style="display:none"
      src="https://www.facebook.com/tr?id=JUSU_PIXEL_ID&ev=PageView&noscript=1"
      /></noscript>

      <?php echo headerStyles(); ?>
    </head>

    <?php if ($bannerEnabled && $bannerText): ?>
      <div class="announcement-bar" style="background: <?php echo htmlspecialchars($bannerBg); ?>;">
        <span class="announcement-bar__label">Aktualu</span>
        <?php if ($bannerLink): ?>
          <a href="<?php echo htmlspecialchars($bannerLink); ?>"><?php echo htmlspecialchars($bannerText); ?></a>
        <?php else: ?>
          <span><?php echo htmlspecialchars($bannerText); ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <header class="header" style="top: <?php echo ($bannerEnabled && $bannerText) ? '44px' : '0'; ?>;">
    <nav class="navbar">
      <button class="nav-toggle" type="button" aria-label="Meniu" aria-expanded="false">
         <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
      </button>
      <a class="brand" href="/">Cukrinukas.lt</a>
      
      <div class="nav-links">
        <?php echo $renderNav($navItems); ?>
          
          <?php if ($user['id']): ?>
              <div class="user-area">
                <button class="user-button" type="button">Labas, <?php echo htmlspecialchars($user['name']); ?><?php if ($unreadMessages): ?><span class="user-button__badge"><?php echo $unreadMessages; ?></span><?php endif; ?></button>
                <div class="user-menu">
                  <a class="user-menu__action" href="/orders.php">Užsakymai</a>
                  <a class="user-menu__action" href="/account.php">Paskyros redagavimas</a>
                  <a class="user-menu__action" href="/messages.php">Žinutės<?php if ($unreadMessages): ?> <span class="pill-count"><?php echo $unreadMessages; ?></span><?php endif; ?></a>
                  <a class="user-menu__action" href="/saved.php">Mano išsaugoti</a>
                  <?php if ($user['is_admin']): ?>
                    <a class="user-menu__action" href="/admin.php">Administravimas</a>
                  <?php endif; ?>
                  <form method="post" action="/login.php" style="margin:0;">
                    <?php echo csrfField(); ?>
<input type="hidden" name="logout" value="1">
                    <button class="user-menu__action" type="submit">Atsijungti</button>
                  </form>
                </div>
              </div>
          <?php else: ?>
            <a href="/login.php">Prisijungti</a>
          <?php endif; ?>
      </div>

      <div class="cart-link">
            <a class="cart-icon" href="/cart.php" aria-label="Krepšelis">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M5 5h2l1 11h8l1-8H7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="10" cy="20" r="1.2" fill="currentColor"/>
                <circle cx="17" cy="20" r="1.2" fill="currentColor"/>
              </svg>
              <?php if ($cart['count']): ?><span class="cart-count"><?php echo $cart['count']; ?></span><?php endif; ?>
            </a>
            <div class="cart-dropdown" aria-label="Krepšelio peržiūra">
              <?php if (!$cart['items']): ?>
                <div class="cart-preview-item" style="color:#6b6b7a;">Krepšelis tuščias</div>
              <?php else: ?>
                <?php foreach ($cart['items'] as $item): ?>
                  <?php
                    $baseLine = isset($item['line_base']) ? (float)$item['line_base'] : (float)$item['line_total'];
                    $lineTotal = (float)$item['line_total'];
                    $finalUnit = $item['quantity'] > 0 ? $lineTotal / (int)$item['quantity'] : $item['price'];
                    $discounted = $lineTotal < $baseLine;
                  ?>
                  <div class="cart-preview-item">
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                    <div style="width:100%;">
                      <div class="cart-preview-meta">
                        <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                        <span>
                          <?php if ($discounted): ?><span style="text-decoration:line-through; color:#6b6b7a; font-size:13px; margin-right:6px;"><?php echo number_format($item['line_total'], 2); ?> €</span><?php endif; ?>
                          <?php echo number_format($lineTotal, 2); ?> €
                        </span>
                      </div>
                      <div style="color:#6b6b7a; font-size:14px;">Kiekis: <?php echo $item['quantity']; ?> × <?php echo number_format($finalUnit, 2); ?> €</div>
                    </div>
                  </div>
                <?php endforeach; ?>
                <div class="cart-preview-footer">
                  <span><strong>Iš viso:</strong> <?php echo number_format($cart['total'], 2); ?> €</span>
                  <div style="display:flex; gap:8px;">
                    <a class="btn secondary" href="/cart.php">Peržiūrėti</a>
                    <a class="btn ghost" href="/checkout.php">Apmokėti</a>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
    </nav>
    </header>
    <script>
      document.querySelectorAll('.user-area').forEach(function(area){
        let timer;
        const menu = area.querySelector('.user-menu');
        function openMenu(){
          clearTimeout(timer);
          if (menu) menu.classList.add('open');
        }
        function closeMenu(){
          timer = setTimeout(()=> menu && menu.classList.remove('open'), 80);
        }
        area.addEventListener('mouseenter', openMenu);
        area.addEventListener('mouseleave', closeMenu);
        if (menu){
          menu.addEventListener('mouseenter', openMenu);
          menu.addEventListener('mouseleave', closeMenu);
        }
      });

      document.querySelectorAll('.cart-link').forEach(function(link){
        let timer;
        const dropdown = link.querySelector('.cart-dropdown');
        function open(){
          clearTimeout(timer);
          dropdown && dropdown.classList.add('open');
        }
        function close(){
          timer = setTimeout(() => dropdown && dropdown.classList.remove('open'), 150);
        }
        ['mouseenter','focusin'].forEach(evt => link.addEventListener(evt, open));
        ['mouseleave','focusout'].forEach(evt => link.addEventListener(evt, close));
        if (dropdown){
          dropdown.addEventListener('mouseenter', open);
          dropdown.addEventListener('mouseleave', close);
          dropdown.addEventListener('wheel', function(e){ open(); }, { passive: true });
          dropdown.addEventListener('scroll', open, { passive: true });
        }
        window.addEventListener('scroll', () => {
          if (link.matches(':hover') || dropdown.matches(':hover')) { open(); }
        }, { passive: true });
      });

      const navToggle = document.querySelector('.nav-toggle');
      const navLinks = document.querySelector('.nav-links');
      if (navToggle && navLinks) {
        navToggle.addEventListener('click', function(){
          navLinks.classList.toggle('open');
          navToggle.setAttribute('aria-expanded', navLinks.classList.contains('open') ? 'true' : 'false');
        });
        navLinks.querySelectorAll('a').forEach(function(anchor){
          anchor.addEventListener('click', function(){
            navLinks.classList.remove('open');
            navToggle.setAttribute('aria-expanded', 'false');
          });
        });
      }
    </script>
    <?php
}

function resolvePdo(?PDO $pdo = null): PDO {
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }
    return getPdo();
}

function renderFooter(?PDO $pdo = null): void {
    static $footerCssPrinted = false;
    if (!$footerCssPrinted) {
        $footerCssPrinted = true;
        echo <<<CSS
<style>
.footer { background: #121212; color: #fff; padding: 40px 22px; margin-top: 60px; font-size: 14px; }
.footer-inner { max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 30px; }
.footer-top { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 30px; }
.footer-nav { display: flex; gap: 24px; }
.footer-nav a { color: #ccc; text-decoration: none; font-weight: 500; transition: color 0.2s; }
.footer-nav a:hover { color: #fff; }
.footer-social { display: flex; align-items: center; gap: 14px; }
.footer-social span { color: #888; margin-right: 6px; font-weight: 500; }
.footer-social a { color: #fff; opacity: 0.8; transition: opacity 0.2s; display: inline-flex; }
.footer-social a:hover { opacity: 1; }
.footer-social svg { width: 22px; height: 22px; fill: currentColor; }
.footer-bottom { color: #666; font-size: 13px; text-align: left; }

@media (max-width: 800px) {
  .footer-top { flex-direction: column; gap: 20px; text-align: center; }
  .footer-nav { flex-wrap: wrap; justify-content: center; gap: 16px; }
  .footer-bottom { text-align: center; }
}
</style>
CSS;
    }
    ?>
    <footer class="footer">
      <div class="footer-inner">
        <div class="footer-top">
           <div class="footer-nav">
              <a href="products.php">Pavadinimas 1</a>
              <a href="products.php">Pavadinimas 2</a>
              <a href="products.php">Pavadinimas 3</a>
              <a href="products.php">Pavadinimas 4</a>
              <a href="products.php">Pavadinimas 5</a>
           </div>
           <div class="footer-social">
              <span>Mus galite rasti ir čia</span>
              <a href="#" aria-label="Facebook" target="_blank" rel="noopener">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2.04c-5.5 0-10 4.49-10 10.02 0 5 3.66 9.15 8.44 9.9v-7H7.9v-2.9h2.54V9.85c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.23.19 2.23.19v2.47h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.9h-2.33v7a10 10 0 0 0 8.44-9.9c0-5.53-4.5-10.02-10-10.02Z"/></svg>
              </a>
              <a href="#" aria-label="Instagram" target="_blank" rel="noopener">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2.16c3.2 0 3.58.01 4.85.07 3.25.15 4.77 1.69 4.92 4.92.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.15 3.23-1.66 4.77-4.92 4.92-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85C2.38 3.92 3.9 2.38 7.15 2.23c1.27-.06 1.65-.07 4.85-.07m0-2.16c-3.26 0-3.67.01-4.95.07C3.76.23 1.05 1.77.47 5.05.41 6.33.4 6.74.4 10s.01 3.67.07 4.95c.58 3.28 3.29 4.82 6.58 4.88 1.28.06 1.69.07 4.95.07s3.67-.01 4.95-.07c3.29-.06 4.83-1.6 4.88-4.88.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.56-3.28-3.28-4.82-6.58-4.88C15.67.01 15.26 0 12 0Z"/><path d="M12 5.84a6.16 6.16 0 1 0 0 12.32 6.16 6.16 0 0 0 0-12.32Zm0 10.16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Z"/><path d="M20.63 4.8a1.44 1.44 0 1 1-2.88 0 1.44 1.44 0 0 1 2.88 0Z"/></svg>
              </a>
           </div>
        </div>
        <div class="footer-bottom">
           © 2026 cukrinukas.lt. Visos teisės saugomos
        </div>
      </div>
    </footer>
    <?php
}
