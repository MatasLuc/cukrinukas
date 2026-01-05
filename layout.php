<?php

/**
 * Generuoja <head> stilius ir šriftus.
 */
function headerStyles($shadowIntensity = 0) {
    $shadowIntensity = max(0, min(100, (int)$shadowIntensity));
    $shadowOpacity = round(0.08 * ($shadowIntensity / 100), 3);
    
    return <<<HTML
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-main: 'Inter', system-ui, -apple-system, sans-serif;
            --c-brand: #2563eb;
            --c-brand-hover: #1d4ed8;
            --c-text-main: #0f172a;
            --c-text-muted: #64748b;
            --c-bg-light: #f8fafc;
            --c-border: #e2e8f0;
            --header-h: 70px;
        }

        body {
            font-family: var(--font-main);
            margin: 0;
            padding-top: var(--header-h);
            background-color: #f7f7fb;
            color: var(--c-text-main);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* --- HEADER --- */
        .site-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-h);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--c-border);
            z-index: 1000;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, {$shadowOpacity});
        }

        .header-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-weight: 800;
            font-size: 22px;
            color: var(--c-text-main);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .logo span { color: var(--c-brand); }

        /* Desktop Nav */
        .desktop-nav {
            display: flex;
            gap: 24px;
        }
        .nav-link {
            text-decoration: none;
            color: var(--c-text-muted);
            font-weight: 600;
            font-size: 14px;
            transition: color 0.2s;
        }
        .nav-link:hover, .nav-link.active {
            color: var(--c-brand);
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .icon-btn {
            color: var(--c-text-main);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            transition: background 0.2s;
            position: relative;
        }
        .icon-btn:hover { background: var(--c-bg-light); color: var(--c-brand); }
        .cart-count {
            position: absolute;
            top: 2px;
            right: 0;
            background: var(--c-brand);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
        }

        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--c-text-main);
            padding: 0;
            margin-left: 10px;
        }

        /* --- MOBILE MENU --- */
        .mobile-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1001;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        .mobile-overlay.open { opacity: 1; visibility: visible; }

        .mobile-drawer {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: 280px;
            background: #fff;
            z-index: 1002;
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
            padding: 20px;
            display: flex;
            flex-direction: column;
            box-shadow: -5px 0 20px rgba(0,0,0,0.1);
        }
        .mobile-drawer.open { transform: translateX(0); }

        .drawer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--c-border);
            padding-bottom: 15px;
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--c-text-muted);
        }
        
        .mobile-nav {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .mobile-link {
            font-size: 16px;
            font-weight: 600;
            color: var(--c-text-main);
            text-decoration: none;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .mobile-link:hover { color: var(--c-brand); }

        /* --- FOOTER --- */
        .site-footer {
            background: #fff;
            border-top: 1px solid var(--c-border);
            padding: 60px 0 30px;
            margin-top: auto;
        }
        .footer-grid {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 40px;
        }
        .footer-col h4 {
            margin: 0 0 16px;
            font-size: 16px;
            font-weight: 700;
            color: var(--c-text-main);
        }
        .footer-col p {
            font-size: 14px;
            color: var(--c-text-muted);
            line-height: 1.6;
            margin: 0;
        }
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .footer-links a {
            text-decoration: none;
            color: var(--c-text-muted);
            font-size: 14px;
            transition: color 0.2s;
        }
        .footer-links a:hover { color: var(--c-brand); }

        .footer-bottom {
            max-width: 1200px;
            margin: 40px auto 0;
            padding: 20px 20px 0;
            border-top: 1px solid var(--c-border);
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
        }

        /* Responsive */
        @media (max-width: 850px) {
            .desktop-nav { display: none; }
            .mobile-toggle { display: block; }
            .footer-grid { grid-template-columns: 1fr; gap: 30px; text-align: center; }
            .footer-col { display: flex; flex-direction: column; align-items: center; }
        }
    </style>
HTML;
}

/**
 * Atvaizduoja Header su navigacija ir mobile meniu.
 */
function renderHeader($pdo, $activePage = '') {
    $cartCount = 0;
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $qty) $cartCount += $qty;
    }
    
    $navItems = [
        'products' => ['url' => '/products.php', 'label' => 'Parduotuvė'],
        'market' => ['url' => '/community_market.php', 'label' => 'Turgelis'],
        'recipes' => ['url' => '/recipes.php', 'label' => 'Receptai'],
        'news' => ['url' => '/news.php', 'label' => 'Naujienos'],
        'community' => ['url' => '/community.php', 'label' => 'Bendruomenė'],
        'contact' => ['url' => '/contact.php', 'label' => 'Kontaktai'],
    ];

    $navHtml = '';
    foreach ($navItems as $key => $item) {
        $activeClass = ($activePage === $key) ? 'active' : '';
        $navHtml .= "<a href=\"{$item['url']}\" class=\"nav-link {$activeClass}\">{$item['label']}</a>";
    }

    echo <<<HTML
    <header class="site-header">
        <div class="header-inner">
            <a href="/" class="logo">
                <span>✦</span> Cukrinukas
            </a>

            <nav class="desktop-nav">
                {$navHtml}
            </nav>

            <div class="header-actions">
                <a href="/cart.php" class="icon-btn" aria-label="Krepšelis">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    <span class="cart-count" id="headerCartCount">{$cartCount}</span>
                </a>
                
                <a href="/account.php" class="icon-btn" aria-label="Profilis">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </a>

                <button class="mobile-toggle" onclick="toggleMobileMenu()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
            </div>
        </div>
    </header>

    <div class="mobile-overlay" onclick="toggleMobileMenu()" id="mobileOverlay"></div>
    <div class="mobile-drawer" id="mobileDrawer">
        <div class="drawer-header">
            <span class="logo" style="font-size:18px;">Cukrinukas</span>
            <button class="close-btn" onclick="toggleMobileMenu()">×</button>
        </div>
        <nav class="mobile-nav">
            {$navHtml}
            <div style="border-top:1px solid #eee; margin-top:10px; padding-top:10px;">
                <a href="/account.php" class="mobile-link">Mano paskyra</a>
                <a href="/saved.php" class="mobile-link">Norų sąrašas</a>
            </div>
        </nav>
    </div>

    <script>
        function toggleMobileMenu() {
            var overlay = document.getElementById('mobileOverlay');
            var drawer = document.getElementById('mobileDrawer');
            if(overlay && drawer) {
                overlay.classList.toggle('open');
                drawer.classList.toggle('open');
            }
        }
    </script>
HTML;
}

/**
 * Atvaizduoja Footer.
 */
function renderFooter($pdo) {
    echo <<<HTML
    <footer class="site-footer">
        <div class="footer-grid">
            <div class="footer-col">
                <a href="/" class="logo" style="margin-bottom:12px; display:inline-block;"><span>✦</span> Cukrinukas</a>
                <p>Jūsų patikimas partneris diabeto valdyme. Kokybiškos priemonės, bendruomenės palaikymas ir skanūs receptai.</p>
            </div>

            <div class="footer-col">
                <h4>Navigacija</h4>
                <ul class="footer-links">
                    <li><a href="/products.php">Parduotuvė</a></li>
                    <li><a href="/community.php">Bendruomenė</a></li>
                    <li><a href="/recipes.php">Receptai</a></li>
                    <li><a href="/about.php">Apie mus</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Susisiekite</h4>
                <ul class="footer-links">
                    <li><a href="mailto:info@cukrinukas.lt">info@cukrinukas.lt</a></li>
                    <li><a href="/contact.php">Kontaktų forma</a></li>
                    <li><a href="/faq.php">D.U.K.</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            &copy; 2025 Cukrinukas. Visos teisės saugomos.
        </div>
    </footer>
HTML;
}

// ---------------------------------------------------------
// PAGALBINĖS FUNKCIJOS, KURIŲ REIKIA INDEX.PHP (KAD NEMESTŲ 500 KLAIDOS)
// ---------------------------------------------------------

function renderErrors($errors) {
    if (empty($errors)) return;
    echo '<div style="max-width:1200px; margin:20px auto; padding:0 20px;">';
    foreach ($errors as $error) {
        echo '<div style="background:#fee2e2; border:1px solid #fecaca; color:#991b1b; padding:12px; border-radius:10px; margin-bottom:10px;">' . htmlspecialchars($error) . '</div>';
    }
    echo '</div>';
}

function renderSuccess($messages) {
    if (empty($messages)) return;
    echo '<div style="max-width:1200px; margin:20px auto; padding:0 20px;">';
    foreach ($messages as $msg) {
        echo '<div style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:12px; border-radius:10px; margin-bottom:10px;">' . htmlspecialchars($msg) . '</div>';
    }
    echo '</div>';
}

function csrfField() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

function validateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('CSRF validation failed');
    }
}
?>
