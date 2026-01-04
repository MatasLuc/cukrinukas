<?php
require_once __DIR__ . '/env.php';
loadEnvFile();
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/security.php';

// Shared PDO connection helper for MySQL-backed auth pages and store features.
// Set environment variables DB_HOST, DB_NAME, DB_USER, DB_PASS before use.

function getPdo(): PDO {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = requireEnv('DB_HOST');
    $db   = requireEnv('DB_NAME');
    $user = requireEnv('DB_USER');
    $pass = requireEnv('DB_PASS');
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}

function ensureUsersTable(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            is_admin TINYINT(1) NOT NULL DEFAULT 0,
            profile_photo VARCHAR(255) DEFAULT NULL,
            birthdate DATE DEFAULT NULL,
            gender VARCHAR(20) DEFAULT NULL,
            city VARCHAR(120) DEFAULT NULL,
            country VARCHAR(120) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Add missing admin column for existing deployments.
    $column = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_admin'")->fetch();
    if (!$column) {
        $pdo->exec('ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash');
    }

    $columns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    $addIfMissing = [
        'profile_photo' => "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL AFTER is_admin",
        'birthdate' => "ALTER TABLE users ADD COLUMN birthdate DATE DEFAULT NULL AFTER profile_photo",
        'gender' => "ALTER TABLE users ADD COLUMN gender VARCHAR(20) DEFAULT NULL AFTER birthdate",
        'city' => "ALTER TABLE users ADD COLUMN city VARCHAR(120) DEFAULT NULL AFTER gender",
        'country' => "ALTER TABLE users ADD COLUMN country VARCHAR(120) DEFAULT NULL AFTER city",
    ];
    foreach ($addIfMissing as $field => $sql) {
        if (!in_array($field, $columns, true)) {
            $pdo->exec($sql);
        }
    }
}

function ensureUploadsDir(): string {
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
    $htaccess = $uploadDir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "<Files *.php>\n    Require all denied\n</Files>\n\nOptions -ExecCGI\nSetHandler none\n");
    }
    return $uploadDir;
}

function detectMimeType(array $file): string {
    if (empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
        return '';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = finfo_file($finfo, $file['tmp_name']) ?: '';
        finfo_close($finfo);
        return is_string($mime) ? $mime : '';
    }

    $mime = mime_content_type($file['tmp_name']);
    return is_string($mime) ? $mime : '';
}

function saveUploadedFile(array $file, array $allowedMimeMap, string $prefix = 'upload_'): ?string {
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $mime = detectMimeType($file);
    if ($mime === '' || !isset($allowedMimeMap[$mime])) {
        return null;
    }

    $extension = $allowedMimeMap[$mime];
    $uploadDir = ensureUploadsDir();
    $targetName = uniqid($prefix, true) . '.' . $extension;
    $destination = $uploadDir . '/' . $targetName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return null;
    }

    return '/uploads/' . $targetName;
}

function ensureAdminAccount(PDO $pdo): void {
    ensureUsersTable($pdo);
    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
    if ($adminCount === 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, 1)');
        $stmt->execute(['Administratorius', 'admin@e-kolekcija.lt', $hash]);
    }
}

function ensureSystemUser(PDO $pdo): int {
    ensureUsersTable($pdo);

    $email = 'noreply@cukrinukas.lt';
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }

    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, 0)');
    $insert->execute(['Cukrinukas.lt', $email, $hash]);

    return (int)$pdo->lastInsertId();
}

function ensureNewsTable(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS news (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            summary TEXT NULL,
            image_url VARCHAR(500) NOT NULL,
            body TEXT NOT NULL,
            visibility ENUM("public","members") NOT NULL DEFAULT "public",
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $columns = $pdo->query("SHOW COLUMNS FROM news")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('summary', $columns, true)) {
        $pdo->exec('ALTER TABLE news ADD COLUMN summary TEXT NULL AFTER title');
    }
    if (!in_array('visibility', $columns, true)) {
        $pdo->exec('ALTER TABLE news ADD COLUMN visibility ENUM("public","members") NOT NULL DEFAULT "public" AFTER body');
    }
    // NAUJA: Pridedame autoriaus stulpelį, jei jo nėra
    if (!in_array('author', $columns, true)) {
        $pdo->exec('ALTER TABLE news ADD COLUMN author VARCHAR(100) DEFAULT NULL AFTER summary');
    }
}

function ensureRecipesTable(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS recipes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            author VARCHAR(255) NULL,
            summary TEXT NULL,
            image_url VARCHAR(500) NOT NULL,
            body TEXT NOT NULL,
            visibility ENUM("public","members") NOT NULL DEFAULT "public",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $columns = $pdo->query("SHOW COLUMNS FROM recipes")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('summary', $columns, true)) {
        $pdo->exec('ALTER TABLE recipes ADD COLUMN summary TEXT NULL AFTER title');
    }
    if (!in_array('author', $columns, true)) {
        $pdo->exec('ALTER TABLE recipes ADD COLUMN author VARCHAR(255) NULL AFTER title');
    }
    if (!in_array('visibility', $columns, true)) {
        $pdo->exec('ALTER TABLE recipes ADD COLUMN visibility ENUM("public","members") NOT NULL DEFAULT "public" AFTER body');
    }
}

function ensureCommunityTables(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS community_thread_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS community_listing_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS community_threads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            category_id INT NULL,
            title VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES community_thread_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS community_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            thread_id INT NOT NULL,
            user_id INT NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (thread_id) REFERENCES community_threads(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS community_blocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            banned_until DATETIME NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS community_listings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            category_id INT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            status ENUM("active","sold") NOT NULL DEFAULT "active",
            seller_email VARCHAR(190) DEFAULT NULL,
            seller_phone VARCHAR(60) DEFAULT NULL,
            image_url VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES community_listing_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $columns = $pdo->query('SHOW COLUMNS FROM community_listings')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('seller_email', $columns, true)) {
        $pdo->exec('ALTER TABLE community_listings ADD COLUMN seller_email VARCHAR(190) DEFAULT NULL AFTER status');
    }
    if (!in_array('seller_phone', $columns, true)) {
        $pdo->exec('ALTER TABLE community_listings ADD COLUMN seller_phone VARCHAR(60) DEFAULT NULL AFTER seller_email');
    }
    if (!in_array('category_id', $columns, true)) {
        $pdo->exec('ALTER TABLE community_listings ADD COLUMN category_id INT NULL AFTER user_id');
        $pdo->exec('ALTER TABLE community_listings ADD CONSTRAINT fk_listing_category FOREIGN KEY (category_id) REFERENCES community_listing_categories(id) ON DELETE SET NULL');
    }

    $threadColumns = $pdo->query('SHOW COLUMNS FROM community_threads')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('category_id', $threadColumns, true)) {
        $pdo->exec('ALTER TABLE community_threads ADD COLUMN category_id INT NULL AFTER user_id');
        $pdo->exec('ALTER TABLE community_threads ADD CONSTRAINT fk_thread_category FOREIGN KEY (category_id) REFERENCES community_thread_categories(id) ON DELETE SET NULL');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS community_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            listing_id INT NOT NULL,
            buyer_id INT NOT NULL,
            status ENUM("laukiama","patvirtinta","atšaukta","įvykdyta") NOT NULL DEFAULT "laukiama",
            note TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (listing_id) REFERENCES community_listings(id) ON DELETE CASCADE,
            FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS community_order_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            user_id INT NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES community_orders(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ensureDirectMessages(PDO $pdo): void {
    // Ensure prerequisite users table exists before creating the messaging table
    ensureUsersTable($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS direct_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            recipient_id INT NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME NULL,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function getUnreadDirectMessagesCount(PDO $pdo, int $userId): int {
    ensureDirectMessages($pdo);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM direct_messages WHERE recipient_id = ? AND read_at IS NULL');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function markDirectMessagesRead(PDO $pdo, int $userId, int $partnerId): void {
    ensureDirectMessages($pdo);
    $stmt = $pdo->prepare('UPDATE direct_messages SET read_at = NOW() WHERE recipient_id = ? AND sender_id = ? AND read_at IS NULL');
    $stmt->execute([$userId, $partnerId]);
}

function isCommunityBlocked(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM community_blocks WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    if (empty($row['banned_until'])) {
        return $row;
    }
    $until = strtotime($row['banned_until']);
    return ($until && $until > time()) ? $row : null;
}

function seedRecipeExamples(PDO $pdo): void {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM recipes')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $recipes = [
        [
            'title' => 'Mažo GI avižų dubenėlis su uogomis',
            'image_url' => 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?auto=format&fit=crop&w=1200&q=80',
            'body' => '<p>Trumpas pusryčių receptas: avižos, graikiškas jogurtas, mėlynės ir šaukštelis linų sėmenų. Balansas tarp skaidulų ir baltymų.</p>',
        ],
        [
            'title' => 'Traškios daržovių lazdelės su humusu',
            'image_url' => 'https://images.unsplash.com/photo-1522184216315-dc2a82a2f3f8?auto=format&fit=crop&w=1200&q=80',
            'body' => '<p>Morkos, agurkai ir salierai patiekiami su baltymingu humusu – puikus užkandis tarp matavimų.</p>',
        ],
        [
            'title' => 'Kepta lašiša su cukinijų juostelėmis',
            'image_url' => 'https://images.unsplash.com/photo-1604908177075-0ac1c9bb6466?auto=format&fit=crop&w=1200&q=80',
            'body' => '<p>Lašišą kepkite orkaitėje su citrina ir žolelėmis, patiekite su lengvai troškintomis cukinijomis.</p>',
        ],
    ];

    $stmt = $pdo->prepare('INSERT INTO recipes (title, image_url, body) VALUES (?, ?, ?)');
    foreach ($recipes as $recipe) {
        $stmt->execute([$recipe['title'], $recipe['image_url'], $recipe['body']]);
    }
}

function ensureCategoriesTable(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            slug VARCHAR(180) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ensureProductImagesTable(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS product_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            path VARCHAR(255) NOT NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ensureProductsTable(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NULL,
            title VARCHAR(200) NOT NULL,
            subtitle VARCHAR(200) DEFAULT NULL,
            description TEXT NOT NULL,
            ribbon_text VARCHAR(120) DEFAULT NULL,
            image_url VARCHAR(500) NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            sale_price DECIMAL(10,2) DEFAULT NULL,
            quantity INT NOT NULL DEFAULT 0,
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            meta_tags TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    ensureProductImagesTable($pdo);
    ensureProductRelations($pdo);

    // Progressive column additions for existing deployments
    $columns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll();
    $names = array_column($columns, 'Field');
    if (!in_array('subtitle', $names, true)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN subtitle VARCHAR(200) DEFAULT NULL AFTER title");
    }
    if (!in_array('ribbon_text', $names, true)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN ribbon_text VARCHAR(120) DEFAULT NULL AFTER description");
    }
    if (!in_array('sale_price', $names, true)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN sale_price DECIMAL(10,2) DEFAULT NULL AFTER price");
    }
    if (!in_array('meta_tags', $names, true)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN meta_tags TEXT NULL AFTER is_featured");
    }
}

function getSiteContent(PDO $pdo): array {
    ensureSiteContentTable($pdo);
    $rows = $pdo->query('SELECT `key`, `value` FROM site_content')->fetchAll(PDO::FETCH_KEY_PAIR);
    return $rows;
}

function saveSiteContent(PDO $pdo, array $data): void {
    ensureSiteContentTable($pdo);
    $stmt = $pdo->prepare('REPLACE INTO site_content (`key`, `value`) VALUES (?, ?)');
    foreach ($data as $key => $value) {
        $stmt->execute([$key, $value]);
    }
}

function ensureFooterLinksTable(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS footer_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            label VARCHAR(180) NOT NULL,
            url VARCHAR(500) NOT NULL,
            section ENUM("quick","help") NOT NULL DEFAULT "quick",
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $count = (int)$pdo->query('SELECT COUNT(*) FROM footer_links')->fetchColumn();
    if ($count === 0) {
        $seeds = [
            ['Apie mus', '/about.php', 'quick', 1],
            ['Parduotuvė', '/products.php', 'quick', 2],
            ['Naujienos', '/news.php', 'quick', 3],
            ['DUK', '/faq.php', 'help', 1],
            ['Pristatymas', '/shipping.php', 'help', 2],
            ['Grąžinimas', '/returns.php', 'help', 3],
        ];
        $stmt = $pdo->prepare('INSERT INTO footer_links (label, url, section, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($seeds as $row) {
            $stmt->execute($row);
        }
    }
}

function ensureFooterLinks(PDO $pdo): void {
    // Backwards-compatible wrapper for callers expecting ensureFooterLinks.
    ensureFooterLinksTable($pdo);
}

function getFooterLinks(PDO $pdo): array {
    ensureFooterLinksTable($pdo);
    $rows = $pdo->query('SELECT id, label, url, section, sort_order FROM footer_links ORDER BY sort_order ASC, id ASC')->fetchAll();
    $grouped = ['quick' => [], 'help' => []];
    foreach ($rows as $row) {
        $section = $row['section'];
        if (!isset($grouped[$section])) {
            $grouped[$section] = [];
        }
        $grouped[$section][] = $row;
    }
    return $grouped;
}

function saveFooterLink(PDO $pdo, ?int $id, string $label, string $url, string $section, int $sortOrder): void {
    ensureFooterLinksTable($pdo);
    $section = $section === 'help' ? 'help' : 'quick';
    if ($id) {
        $stmt = $pdo->prepare('UPDATE footer_links SET label = ?, url = ?, section = ?, sort_order = ? WHERE id = ?');
        $stmt->execute([$label, $url, $section, $sortOrder, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO footer_links (label, url, section, sort_order) VALUES (?, ?, ?, ?)');
        $stmt->execute([$label, $url, $section, $sortOrder]);
    }
}

function deleteFooterLink(PDO $pdo, int $id): void {
    ensureFooterLinksTable($pdo);
    $stmt = $pdo->prepare('DELETE FROM footer_links WHERE id = ?');
    $stmt->execute([$id]);
}

function ensureSiteContentTable(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS site_content (
            `key` VARCHAR(120) PRIMARY KEY,
            `value` TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $defaults = [
        'hero_title' => 'Pagalba kasdienei diabeto priežiūrai',
        'hero_body' => 'Gliukometrai, sensoriai, maži GI užkandžiai ir bendruomenės patarimai – viskas vienoje vietoje, kad matavimai būtų ramūs.',
        'hero_cta_label' => 'Peržiūrėti pasiūlymus →',
        'hero_cta_url' => '/products.php',
        'hero_media_type' => 'image',
        'hero_media_color' => '#829ed6',
        'hero_media_image' => 'https://images.pexels.com/photos/6942003/pexels-photo-6942003.jpeg',
        'hero_media_video' => '',
        'hero_media_poster' => '',
        'hero_media_alt' => 'Cukrinukas fonas',
        'hero_shadow_intensity' => '70',
        'news_hero_pill' => '📰 Bendruomenės pulsas',
        'news_hero_title' => 'Šviežiausios naujienos ir patarimai',
        'news_hero_body' => 'Aktualijos apie diabetą, kasdienę priežiūrą ir mūsų parduotuvės atnaujinimus – viskas vienoje vietoje.',
        'news_hero_cta_label' => 'Peržiūrėti straipsnius',
        'news_hero_cta_url' => '#news',
        'news_hero_card_meta' => 'Temos žyma',
        'news_hero_card_title' => 'Inovatyvi priežiūra',
        'news_hero_card_body' => 'Atrinkti patarimai ir sėkmės istorijos',
        'recipes_hero_pill' => '🍽️ Subalansuotos idėjos kasdienai',
        'recipes_hero_title' => 'Šiuolaikiški receptai, kurie įkvepia',
        'recipes_hero_body' => 'Lengvai paruošiami patiekalai, praturtinti patarimais ir mitybos įkvėpimu kiekvienai dienai.',
        'recipes_hero_cta_label' => 'Naršyti receptus',
        'recipes_hero_cta_url' => '#recipes',
        'recipes_hero_card_meta' => 'Šio mėnesio skonis',
        'recipes_hero_card_title' => 'Mėtos ir pistacijos',
        'recipes_hero_card_body' => 'Gaivus duetas desertams ir užkandžiams',
        'faq_hero_pill' => '💡 Pagalba ir gairės',
        'faq_hero_title' => 'Dažniausiai užduodami klausimai',
        'faq_hero_body' => 'Trumpi atsakymai apie pristatymą, grąžinimus ir kaip išsirinkti tinkamus produktus diabetui prižiūrėti.',
        'contact_hero_pill' => '🤝 Esame šalia',
        'contact_hero_title' => 'Susisiekime ir aptarkime, kaip galime padėti',
        'contact_hero_body' => 'Greiti atsakymai, nuoširdūs patarimai ir pagalba parenkant reikiamus produktus – parašykite mums.',
        'contact_cta_primary_label' => 'Rašyti el. laišką',
        'contact_cta_primary_url' => 'mailto:e.kolekcija@gmail.com',
        'contact_cta_secondary_label' => 'Skambinti +37060093880',
        'contact_cta_secondary_url' => 'tel:+37060093880',
        'contact_card_pill' => 'Greita reakcija',
        'contact_card_title' => 'Iki 1 darbo dienos',
        'contact_card_body' => 'Į užklausas atsakome kuo greičiau, kad galėtumėte pasirūpinti savo poreikiais.',
        'banner_enabled' => '0',
        'banner_text' => '',
        'banner_link' => '',
        'banner_background' => '#829ed6',
        'promo_1_icon' => '1%',
        'promo_1_title' => 'Žemas GI prioritetas',
        'promo_1_body' => 'Visi užkandžiai atrinkti taip, kad padėtų išlaikyti stabilesnį gliukozės lygį.',
        'promo_2_icon' => '24/7',
        'promo_2_title' => 'Greita pagalba',
        'promo_2_body' => 'Klauskite apie sensorius ar pompų priedus – atsakome ir telefonu, ir el. paštu.',
        'promo_3_icon' => '★',
        'promo_3_title' => 'Bendruomenės patirtys',
        'promo_3_body' => 'Dalijamės realių vartotojų patarimais apie matavimus, sportą ir mitybą.',
        'storyband_badge' => 'Nuo gliukometro iki lėkštės',
        'storyband_title' => 'Kasdieniai sprendimai diabetui',
        'storyband_body' => 'Sudėjome priemones ir žinias, kurios palengvina cukrinio diabeto priežiūrą: nuo matavimų iki receptų ir užkandžių.',
        'storyband_cta_label' => 'Rinktis rinkinį',
        'storyband_cta_url' => '/products.php',
        'storyband_card_eyebrow' => 'Reklaminis akcentas',
        'storyband_card_title' => '„Cukrinukas“ rinkiniai',
        'storyband_card_body' => 'Starteriai su gliukometrais, užkandžiais ir atsargomis 30 dienų. Pradėkite be streso.',
        'storyband_metric_1_value' => '1200+',
        'storyband_metric_1_label' => 'užsakymų per metus',
        'storyband_metric_2_value' => '25',
        'storyband_metric_2_label' => 'receptai su subalansuotu GI',
        'storyband_metric_3_value' => '5 min',
        'storyband_metric_3_label' => 'vidutinis atsakymo laikas',
        'storyrow_eyebrow' => 'Dienos rutina',
        'storyrow_title' => 'Stebėjimas, užkandžiai ir ramybė',
        'storyrow_body' => 'Greitai pasiekiami sensorių pleistrai, cukraus kiekį subalansuojantys batonėliai ir starterių rinkiniai, kad kiekviena diena būtų šiek tiek lengvesnė.',
        'storyrow_pill_1' => 'Gliukozės matavimai',
        'storyrow_pill_2' => 'Subalansuotos užkandžių dėžutės',
        'storyrow_pill_3' => 'Kelionėms paruošti rinkiniai',
        'storyrow_bubble_meta' => 'Rekomendacija',
        'storyrow_bubble_title' => '„Cukrinukas“ specialistai',
        'storyrow_bubble_body' => 'Suderiname atsargas pagal jūsų dienos režimą: nuo ankstyvų matavimų iki vakaro koregavimų.',
        'storyrow_floating_meta' => 'Greitas pristatymas',
        'storyrow_floating_title' => '1-2 d.d.',
        'storyrow_floating_body' => 'Visoje Lietuvoje nuo 2.50 €',
        'support_meta' => 'Bendruomenė',
        'support_title' => 'Pagalba jums ir šeimai',
        'support_body' => 'Nuo pirmo sensoriaus iki subalansuotos vakarienės – čia rasite trumpus gidus, vaizdo pamokas ir dietologės patarimus.',
        'support_chip_1' => 'Vaizdo gidai',
        'support_chip_2' => 'Dietologės Q&A',
        'support_chip_3' => 'Tėvų kampelis',
        'support_card_meta' => 'Gyva konsultacija',
        'support_card_title' => '5 d. per savaitę',
        'support_card_body' => 'Trumpi pokalbiai su cukrinio diabeto slaugytoja per „Messenger“ – pasikalbam apie sensorius, vaikus ar receptų koregavimus.',
        'support_card_cta_label' => 'Rezervuoti laiką',
        'support_card_cta_url' => '/contact.php',
        'footer_brand_title' => 'Cukrinukas.lt',
        'footer_brand_body' => 'Diabeto priemonės, mažo GI užkandžiai ir kasdienių sprendimų gidai vienoje vietoje.',
        'footer_brand_pill' => 'Kasdienė priežiūra',
        'footer_quick_title' => 'Greitos nuorodos',
        'footer_help_title' => 'Pagalba',
        'footer_contact_title' => 'Kontaktai',
        'footer_contact_email' => 'info@cukrinukas.lt',
        'footer_contact_phone' => '+370 600 00000',
        'footer_contact_hours' => 'I–V 09:00–18:00',
        'testimonial_1_name' => 'Gintarė, 1 tipo diabetas',
        'testimonial_1_role' => 'Mėgsta aktyvią dieną',
        'testimonial_1_text' => 'Sensoriai, užkandžiai ir patarimai vienoje vietoje sutaupo daug laiko.',
        'testimonial_2_name' => 'Mantas, tėtis',
        'testimonial_2_role' => 'Prižiūri sūnaus matavimus',
        'testimonial_2_text' => 'Greitas pristatymas ir aiškūs gidai padeda jaustis užtikrintai.',
        'testimonial_3_name' => 'Rūta, dietologė',
        'testimonial_3_role' => 'Dalijasi mitybos idėjomis',
        'testimonial_3_text' => 'Receptų santraukos ir mažo GI produktai yra tai, ko reikia kasdienai.',
    ];

    $stmt = $pdo->prepare('INSERT IGNORE INTO site_content (`key`, `value`) VALUES (?, ?)');
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
}

function ensureShippingSettings(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS shipping_settings (
            id INT PRIMARY KEY,
            base_price DECIMAL(10,2) NOT NULL DEFAULT 3.99,
            courier_price DECIMAL(10,2) NOT NULL DEFAULT 3.99,
            locker_price DECIMAL(10,2) NOT NULL DEFAULT 2.49,
            free_over DECIMAL(10,2) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $columns = $pdo->query('SHOW COLUMNS FROM shipping_settings')->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('courier_price', $columns, true)) {
        $pdo->exec('ALTER TABLE shipping_settings ADD COLUMN courier_price DECIMAL(10,2) NOT NULL DEFAULT 3.99 AFTER base_price');
        $pdo->exec('UPDATE shipping_settings SET courier_price = base_price');
    }
    if (!in_array('locker_price', $columns, true)) {
        $pdo->exec('ALTER TABLE shipping_settings ADD COLUMN locker_price DECIMAL(10,2) NOT NULL DEFAULT 2.49 AFTER courier_price');
        $pdo->exec('UPDATE shipping_settings SET locker_price = base_price');
    }

    $exists = $pdo->query('SELECT COUNT(*) FROM shipping_settings WHERE id = 1')->fetchColumn();
    if (!$exists) {
        $pdo->prepare('INSERT INTO shipping_settings (id, base_price, courier_price, locker_price, free_over) VALUES (1, 3.99, 3.99, 2.49, NULL)')->execute();
    }
}

function ensureLockerTables(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS parcel_lockers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            address VARCHAR(255) NOT NULL,
            note TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY provider_title_address (provider, title, address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ensureFreeShippingProducts(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS shipping_free_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL UNIQUE,
            position TINYINT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function getShippingSettings(PDO $pdo): array {
    ensureShippingSettings($pdo);
    $row = $pdo->query('SELECT base_price, courier_price, locker_price, free_over FROM shipping_settings WHERE id = 1')->fetch();
    return $row ?: ['base_price' => 3.99, 'courier_price' => 3.99, 'locker_price' => 2.49, 'free_over' => null];
}

function saveShippingSettings(PDO $pdo, float $base, float $courier, float $locker, ?float $freeOver): void {
    ensureShippingSettings($pdo);
    $stmt = $pdo->prepare('REPLACE INTO shipping_settings (id, base_price, courier_price, locker_price, free_over) VALUES (1, ?, ?, ?, ?)');
    $stmt->execute([$base, $courier, $locker, $freeOver]);
}

function saveParcelLocker(PDO $pdo, string $provider, string $title, string $address, ?string $note = null): void {
    ensureLockerTables($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO parcel_lockers (provider, title, address, note) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE title = VALUES(title), address = VALUES(address), note = VALUES(note)'
    );
    $stmt->execute([$provider, $title, $address, $note]);
}

function updateParcelLocker(PDO $pdo, int $id, string $provider, string $title, string $address, ?string $note = null): void {
    ensureLockerTables($pdo);
    $stmt = $pdo->prepare('UPDATE parcel_lockers SET provider = ?, title = ?, address = ?, note = ? WHERE id = ?');
    $stmt->execute([$provider, $title, $address, $note, $id]);
}

function bulkSaveParcelLockers(PDO $pdo, string $provider, array $lockers): void {
    ensureLockerTables($pdo);
    if (!$lockers) {
        return;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO parcel_lockers (provider, title, address, note) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), address = VALUES(address), note = VALUES(note)'
        );
        foreach ($lockers as $locker) {
            $stmt->execute([
                $provider,
                $locker['title'],
                $locker['address'],
                $locker['note'] ?? null,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function getLockerNetworks(PDO $pdo): array {
    ensureLockerTables($pdo);
    $stmt = $pdo->query('SELECT id, provider, title, address, note FROM parcel_lockers ORDER BY provider, title');
    $rows = $stmt->fetchAll();
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[$row['provider']][] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'address' => $row['address'],
            'note' => $row['note'],
        ];
    }
    return $grouped;
}

function getLockerById(PDO $pdo, int $id): ?array {
    ensureLockerTables($pdo);
    $stmt = $pdo->prepare('SELECT id, provider, title, address, note FROM parcel_lockers WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? [
        'id' => (int)$row['id'],
        'provider' => $row['provider'],
        'title' => $row['title'],
        'address' => $row['address'],
        'note' => $row['note'],
    ] : null;
}

function getFreeShippingProductIds(PDO $pdo): array {
    ensureFreeShippingProducts($pdo);
    $rows = $pdo->query('SELECT product_id FROM shipping_free_products ORDER BY position ASC LIMIT 4')->fetchAll();
    return array_map('intval', array_column($rows, 'product_id'));
}

function getFreeShippingProducts(PDO $pdo): array {
    ensureFreeShippingProducts($pdo);
    $stmt = $pdo->query(
        'SELECT s.product_id, s.position, p.title, p.price, p.sale_price, p.image_url, p.category_id,
                (SELECT path FROM product_images WHERE product_id = p.id AND is_primary = 1 ORDER BY id DESC LIMIT 1) AS primary_image
         FROM shipping_free_products s
         JOIN products p ON p.id = s.product_id
         ORDER BY s.position ASC
         LIMIT 4'
    );
    return $stmt->fetchAll();
}

function saveFreeShippingProducts(PDO $pdo, array $productIds): void {
    ensureFreeShippingProducts($pdo);
    $clean = [];
    foreach ($productIds as $pid) {
        $id = (int)$pid;
        if ($id > 0 && !in_array($id, $clean, true)) {
            $clean[] = $id;
        }
        if (count($clean) >= 4) {
            break;
        }
    }

    $pdo->exec('DELETE FROM shipping_free_products');
    if (!$clean) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO shipping_free_products (product_id, position) VALUES (?, ?)');
    $pos = 1;
    foreach ($clean as $pid) {
        $stmt->execute([$pid, $pos]);
        $pos++;
    }
}

function ensureProductRelations(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS product_related (
            product_id INT NOT NULL,
            related_product_id INT NOT NULL,
            PRIMARY KEY (product_id, related_product_id),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (related_product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS product_attributes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            label VARCHAR(180) NOT NULL,
            value TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS product_variations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            name VARCHAR(180) NOT NULL,
            price_delta DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ensureFeaturedProductsTable(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS featured_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL UNIQUE,
            position TINYINT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $count = (int)$pdo->query('SELECT COUNT(*) FROM featured_products')->fetchColumn();
    if ($count === 0) {
        $seeds = $pdo->query('SELECT id FROM products ORDER BY is_featured DESC, created_at DESC LIMIT 3')->fetchAll();
        $stmt = $pdo->prepare('INSERT INTO featured_products (product_id, position) VALUES (?, ?)');
        $pos = 1;
        foreach ($seeds as $seed) {
            $stmt->execute([(int)$seed['id'], $pos]);
            $pos++;
        }
    }
}

function ensureWishlistTables(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS wishlist_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_product (user_id, product_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ensureSavedContentTables(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS saved_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            item_type ENUM("product","news","recipe") NOT NULL,
            item_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_saved (user_id, item_type, item_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function saveItemForUser(PDO $pdo, int $userId, string $type, int $itemId): void {
    ensureSavedContentTables($pdo);
    $stmt = $pdo->prepare('INSERT IGNORE INTO saved_items (user_id, item_type, item_id) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $type, $itemId]);
}

function removeSavedItem(PDO $pdo, int $userId, string $type, int $itemId): void {
    ensureSavedContentTables($pdo);
    $stmt = $pdo->prepare('DELETE FROM saved_items WHERE user_id = ? AND item_type = ? AND item_id = ?');
    $stmt->execute([$userId, $type, $itemId]);
}

function getSavedItems(PDO $pdo, int $userId): array {
    ensureSavedContentTables($pdo);
    $stmt = $pdo->prepare('SELECT * FROM saved_items WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function ensureNavigationTable(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS navigation_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            label VARCHAR(120) NOT NULL,
            url VARCHAR(255) NOT NULL,
            parent_id INT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES navigation_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $count = (int) $pdo->query('SELECT COUNT(*) FROM navigation_items')->fetchColumn();
    if ($count === 0) {
        $items = [
            ['Parduotuvė', '/products.php', null, 1],
            ['Naujienos', '/news.php', null, 2],
            ['Receptai', '/recipes.php', null, 3],
            ['Bendruomenė', '/community.php', null, 4],
            ['Kontaktai', '/contact.php', null, 5],
            ['DUK', '/faq.php', null, 6],
        ];
        $stmt = $pdo->prepare('INSERT INTO navigation_items (label, url, parent_id, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($items as $item) {
            $stmt->execute($item);
        }
    } else {
        $upserts = [
            ['Bendruomenė', '/community.php', 4],
            ['Kontaktai', '/contact.php', 5],
            ['DUK', '/faq.php', 6],
        ];
        foreach ($upserts as $row) {
            [$label, $url, $sort] = $row;
            $check = $pdo->prepare('SELECT id FROM navigation_items WHERE label = ? LIMIT 1');
            $check->execute([$label]);
            $existingId = $check->fetchColumn();
            if ($existingId) {
                $pdo->prepare('UPDATE navigation_items SET url = ?, sort_order = ? WHERE id = ?')->execute([$url, $sort, $existingId]);
            } else {
                $pdo->prepare('INSERT INTO navigation_items (label, url, parent_id, sort_order) VALUES (?, ?, NULL, ?)')->execute([$label, $url, $sort]);
            }
        }
    }
}

function getCartData(PDO $pdo, array $cartSession, array $variationSelections = []): array {
    $items = [];
    $baseTotal = 0;
    $finalTotal = 0;
    $globalAmount = 0;
    $categoryAmount = 0;
    $count = 0;
    $freeShippingIds = getFreeShippingProductIds($pdo);

    if (!$cartSession) {
        return [
            'items' => $items,
            'total' => $finalTotal,
            'count' => $count,
            'base_total' => $baseTotal,
            'global_amount' => $globalAmount,
            'category_amount' => $categoryAmount,
            'global_discount' => getGlobalDiscount($pdo),
            'category_discounts' => getCategoryDiscounts($pdo),
            'free_shipping_ids' => $freeShippingIds,
        ];
    }

    $ids = array_keys($cartSession);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, title, price, sale_price, image_url, category_id FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    $globalDiscount = getGlobalDiscount($pdo);
    $categoryDiscounts = getCategoryDiscounts($pdo);

    foreach ($rows as $row) {
        $qty = (int) ($cartSession[$row['id']] ?? 1);
        $varSelection = $variationSelections[$row['id']] ?? null;
        $delta = (float)($varSelection['delta'] ?? 0);

        $baseUnit = ($row['sale_price'] !== null ? (float)$row['sale_price'] : (float)$row['price']) + $delta;
        $baseOriginal = (float)$row['price'] + $delta;
        $afterGlobal = applyGlobalDiscount($baseUnit, $globalDiscount);
        $catDiscount = null;
        if (isset($row['category_id'])) {
            $catDiscount = $categoryDiscounts[(int)$row['category_id']] ?? null;
        }
        $finalUnit = applyCategoryDiscount($afterGlobal, $catDiscount);

        $baseLine = $qty * $baseOriginal;
        $finalLine = $qty * $finalUnit;

        $baseTotal += $baseLine;
        $finalTotal += $finalLine;
        $globalAmount += ($baseUnit - $afterGlobal) * $qty;
        $categoryAmount += ($afterGlobal - $finalUnit) * $qty;
        $count += $qty;
        $items[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'price' => $finalUnit,
            'original_unit' => $baseOriginal,
            'image_url' => $row['image_url'],
            'quantity' => $qty,
            'line_total' => $finalLine,
            'line_base' => $baseLine,
            'category_id' => $row['category_id'],
            'variation' => $varSelection,
            'free_shipping_gift' => in_array((int)$row['id'], $freeShippingIds, true),
        ];
    }

    return [
        'items' => $items,
        'total' => $finalTotal,
        'count' => $count,
        'base_total' => $baseTotal,
        'global_amount' => $globalAmount,
        'category_amount' => $categoryAmount,
        'global_discount' => $globalDiscount,
        'category_discounts' => $categoryDiscounts,
        'free_shipping_ids' => $freeShippingIds,
    ];
}

function seedStoreExamples(PDO $pdo): void {
    ensureCategoriesTable($pdo);
    ensureProductsTable($pdo);

    $categoryCount = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($categoryCount === 0) {
        $categories = [
            ['name' => 'Gliukometrai', 'slug' => 'gliukometrai'],
            ['name' => 'Juostelės ir lancetai', 'slug' => 'juosteles-lancetai'],
            ['name' => 'Sensoriai', 'slug' => 'sensoriai'],
            ['name' => 'Mitybos produktai', 'slug' => 'mitybos-produktai'],
        ];

        $stmt = $pdo->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)');
        foreach ($categories as $category) {
            $stmt->execute([$category['name'], $category['slug']]);
        }
    }

    $productCount = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($productCount === 0) {
        $glucometersId = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'gliukometrai'")->fetchColumn();
        $stripsId = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'juosteles-lancetai'")->fetchColumn();
        $sensorsId = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'sensoriai'")->fetchColumn();
        $foodId = (int) $pdo->query("SELECT id FROM categories WHERE slug = 'mitybos-produktai'")->fetchColumn();

        $products = [
            [
                'category_id' => $glucometersId,
                'title' => 'SmartSense gliukometras',
                'description' => 'Bluetooth gliukometras su mobilia programėle ir automatinėmis ataskaitomis.',
                'image_url' => 'https://images.unsplash.com/photo-1582719478190-9f0e2c09c6ee?auto=format&fit=crop&w=800&q=80',
                'price' => 79.99,
                'quantity' => 20,
                'is_featured' => 1,
            ],
            [
                'category_id' => $stripsId,
                'title' => 'Testo juostelės (50 vnt.)',
                'description' => 'Greitos ir tikslios juostelės gliukometro matavimams namuose.',
                'image_url' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=800&q=80',
                'price' => 24.50,
                'quantity' => 100,
                'is_featured' => 1,
            ],
            [
                'category_id' => $sensorsId,
                'title' => 'CGM sensorius (14 d.)',
                'description' => 'Nuolatinis gliukozės stebėjimo sensorius su programėlės pranešimais.',
                'image_url' => 'https://images.unsplash.com/photo-1582719478250-5c7ff88f2375?auto=format&fit=crop&w=800&q=80',
                'price' => 59.00,
                'quantity' => 40,
                'is_featured' => 1,
            ],
            [
                'category_id' => $foodId,
                'title' => 'Mažo GI baltymų batonėliai (12 vnt.)',
                'description' => 'Sotūs batonėliai su mažesniu cukraus kiekiu ir subalansuotu baltymų kiekiu.',
                'image_url' => 'https://images.unsplash.com/photo-1528715471579-d1bcf0ba5e83?auto=format&fit=crop&w=800&q=80',
                'price' => 18.99,
                'quantity' => 60,
                'is_featured' => 0,
            ],
        ];

        $stmt = $pdo->prepare('INSERT INTO products (category_id, title, description, image_url, price, quantity, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($products as $product) {
            $stmt->execute([
                $product['category_id'],
                $product['title'],
                $product['description'],
                $product['image_url'],
                $product['price'],
                $product['quantity'],
                $product['is_featured'],
            ]);
        }
    }
}

function getNavigationTree(PDO $pdo): array {
    ensureNavigationTable($pdo);
    $rows = $pdo->query('SELECT id, label, url, parent_id FROM navigation_items ORDER BY sort_order ASC, id ASC')->fetchAll();
    $children = [];
    foreach ($rows as $row) {
        $row['children'] = [];
        $parentKey = $row['parent_id'] ?? 0;
        if (!isset($children[$parentKey])) {
            $children[$parentKey] = [];
        }
        $children[$parentKey][] = $row;
    }

    $build = function($parentId) use (&$build, &$children): array {
        $branch = [];
        foreach ($children[$parentId] ?? [] as $item) {
            $item['children'] = $build($item['id']);
            $branch[] = $item;
        }
        return $branch;
    };

    return $build(0);
}

function ensureOrdersTables(PDO $pdo): void {
    // Ensure referenced tables exist before creating foreign keys.
    ensureUsersTable($pdo);
    ensureProductsTable($pdo);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            customer_name VARCHAR(200) NOT NULL,
            customer_email VARCHAR(200) NOT NULL,
            customer_phone VARCHAR(50) NOT NULL DEFAULT "",
            customer_address TEXT NOT NULL,
            discount_code VARCHAR(80) NULL,
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            shipping_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT "laukiama",
            delivery_method VARCHAR(50) NOT NULL DEFAULT "address",
            delivery_details TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $orderColumns = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('discount_code', $orderColumns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN discount_code VARCHAR(80) NULL AFTER customer_address');
    }
    if (!in_array('discount_amount', $orderColumns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER discount_code');
    }
    if (!in_array('shipping_amount', $orderColumns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN shipping_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER discount_amount');
    }
    if (!in_array('delivery_method', $orderColumns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN delivery_method VARCHAR(50) NOT NULL DEFAULT "address" AFTER status');
    }
    if (!in_array('delivery_details', $orderColumns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN delivery_details TEXT NULL AFTER delivery_method');
    }
    if (!in_array('customer_phone', $orderColumns, true)) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(50) NOT NULL DEFAULT "" AFTER customer_email');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ensureDiscountTables(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS discount_settings (
            id TINYINT PRIMARY KEY,
            type ENUM("none","percent","amount","free_shipping") NOT NULL DEFAULT "none",
            value DECIMAL(10,2) NOT NULL DEFAULT 0,
            free_shipping TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS discount_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(80) NOT NULL UNIQUE,
            type ENUM("percent","amount","free_shipping") NOT NULL DEFAULT "percent",
            value DECIMAL(10,2) NOT NULL DEFAULT 0,
            usage_limit INT NOT NULL DEFAULT 0,
            used_count INT NOT NULL DEFAULT 0,
            free_shipping TINYINT(1) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $existing = $pdo->query('SELECT COUNT(*) FROM discount_settings')->fetchColumn();
    if ((int)$existing === 0) {
        $pdo->exec("INSERT INTO discount_settings (id, type, value, free_shipping) VALUES (1, 'none', 0, 0)");
    }

    $columns = $pdo->query("SHOW COLUMNS FROM discount_settings")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('free_shipping', $columns, true)) {
        $pdo->exec('ALTER TABLE discount_settings ADD COLUMN free_shipping TINYINT(1) NOT NULL DEFAULT 0 AFTER value');
    }

    $codeColumns = $pdo->query("SHOW COLUMNS FROM discount_codes")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('free_shipping', $codeColumns, true)) {
        $pdo->exec('ALTER TABLE discount_codes ADD COLUMN free_shipping TINYINT(1) NOT NULL DEFAULT 0 AFTER used_count');
    }
    $settingsType = $pdo->query("SHOW COLUMNS FROM discount_settings LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
    if ($settingsType && strpos($settingsType['Type'], 'free_shipping') === false) {
        $pdo->exec('ALTER TABLE discount_settings MODIFY type ENUM("none","percent","amount","free_shipping") NOT NULL DEFAULT "none"');
    }
    $codeType = $pdo->query("SHOW COLUMNS FROM discount_codes LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
    if ($codeType && strpos($codeType['Type'], 'free_shipping') === false) {
        $pdo->exec('ALTER TABLE discount_codes MODIFY type ENUM("percent","amount","free_shipping") NOT NULL DEFAULT "percent"');
    }
}

function ensureCategoryDiscounts(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS category_discounts (
            category_id INT PRIMARY KEY,
            type ENUM("none","percent","amount","free_shipping") NOT NULL DEFAULT "none",
            value DECIMAL(10,2) NOT NULL DEFAULT 0,
            free_shipping TINYINT(1) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $typeCol = $pdo->query("SHOW COLUMNS FROM category_discounts LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
    if ($typeCol && strpos($typeCol['Type'], 'free_shipping') === false) {
        $pdo->exec('ALTER TABLE category_discounts MODIFY type ENUM("none","percent","amount","free_shipping") NOT NULL DEFAULT "none"');
    }
}

function getAllDiscountCodes(PDO $pdo): array {
    ensureDiscountTables($pdo);
    $stmt = $pdo->query('SELECT * FROM discount_codes ORDER BY created_at DESC');
    return $stmt->fetchAll();
}

function getCategoryDiscounts(PDO $pdo): array {
    ensureCategoryDiscounts($pdo);
    $stmt = $pdo->query('SELECT * FROM category_discounts WHERE active = 1');
    $rows = $stmt->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        if (($row['type'] ?? '') === 'free_shipping') {
            $row['free_shipping'] = 1;
            $row['value'] = 0;
        }
        $map[(int)$row['category_id']] = $row;
    }
    return $map;
}

function saveCategoryDiscount(PDO $pdo, int $categoryId, string $type, float $value, bool $freeShipping, bool $active): void {
    ensureCategoryDiscounts($pdo);
    $allowedType = in_array($type, ['none', 'percent', 'amount', 'free_shipping'], true) ? $type : 'none';
    $val = $allowedType === 'free_shipping' ? 0 : max(0, $value);
    $free = ($allowedType === 'free_shipping' || $freeShipping) ? 1 : 0;
    $activeFlag = $active ? 1 : 0;
    $stmt = $pdo->prepare('REPLACE INTO category_discounts (category_id, type, value, free_shipping, active) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$categoryId, $allowedType, $val, $free, $activeFlag]);
}

function deleteCategoryDiscount(PDO $pdo, int $categoryId): void {
    ensureCategoryDiscounts($pdo);
    $stmt = $pdo->prepare('DELETE FROM category_discounts WHERE category_id = ?');
    $stmt->execute([$categoryId]);
}

function saveDiscountCodeEntry(PDO $pdo, ?int $id, string $code, string $type, float $value, int $usageLimit, bool $active, bool $freeShipping = false): void {
    ensureDiscountTables($pdo);
    $allowedType = in_array($type, ['percent', 'amount', 'free_shipping'], true) ? $type : 'percent';
    $code = trim($code);
    $value = $allowedType === 'free_shipping' ? 0 : max(0, $value);
    $usageLimit = max(0, $usageLimit);
    $activeFlag = $active ? 1 : 0;
    $free = ($allowedType === 'free_shipping' || $freeShipping) ? 1 : 0;

    if ($id) {
        $stmt = $pdo->prepare('UPDATE discount_codes SET code = ?, type = ?, value = ?, usage_limit = ?, free_shipping = ?, active = ? WHERE id = ?');
        $stmt->execute([$code, $allowedType, $value, $usageLimit, $free, $activeFlag, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO discount_codes (code, type, value, usage_limit, free_shipping, active) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$code, $allowedType, $value, $usageLimit, $free, $activeFlag]);
    }
}

function deleteDiscountCode(PDO $pdo, int $id): void {
    ensureDiscountTables($pdo);
    $stmt = $pdo->prepare('DELETE FROM discount_codes WHERE id = ?');
    $stmt->execute([$id]);
}

function ensureCartTables(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS cart_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_product (user_id, product_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function getFeaturedProductIds(PDO $pdo): array {
    ensureFeaturedProductsTable($pdo);
    $rows = $pdo->query('SELECT product_id FROM featured_products ORDER BY position ASC LIMIT 3')->fetchAll();
    return array_map(fn($r) => (int)$r['product_id'], $rows);
}

function saveFeaturedProductIds(PDO $pdo, array $productIds): void {
    ensureFeaturedProductsTable($pdo);
    $pdo->exec('TRUNCATE TABLE featured_products');
    $stmt = $pdo->prepare('INSERT INTO featured_products (product_id, position) VALUES (?, ?)');
    $pos = 1;
    foreach ($productIds as $pid) {
        if ($pos > 3) { break; }
        $stmt->execute([(int)$pid, $pos]);
        $pos++;
    }
}

function saveCartItem(PDO $pdo, int $userId, int $productId, int $qty): void {
    ensureCartTables($pdo);
    $stmt = $pdo->prepare('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)');
    $stmt->execute([$userId, $productId, $qty]);
}

function deleteCartItem(PDO $pdo, int $userId, int $productId): void {
    ensureCartTables($pdo);
    $stmt = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
}

function clearUserCart(PDO $pdo, int $userId): void {
    ensureCartTables($pdo);
    $stmt = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?');
    $stmt->execute([$userId]);
}

function getGlobalDiscount(PDO $pdo): array {
    ensureDiscountTables($pdo);
    $row = $pdo->query('SELECT type, value, free_shipping FROM discount_settings WHERE id = 1')->fetch();
    if ($row && ($row['type'] ?? '') === 'free_shipping') {
        $row['free_shipping'] = 1;
        $row['value'] = 0;
    }
    return $row ?: ['type' => 'none', 'value' => 0, 'free_shipping' => 0];
}

function applyGlobalDiscount(float $amount, array $globalDiscount): float {
    $amount = max(0, $amount);
    $type = $globalDiscount['type'] ?? 'none';
    $value = (float)($globalDiscount['value'] ?? 0);

    if ($type === 'percent' && $value > 0) {
        return max(0, $amount - ($amount * ($value / 100)));
    }

    if ($type === 'amount' && $value > 0) {
        return max(0, $amount - $value);
    }

    return $amount;
}

function applyCategoryDiscount(float $amount, ?array $categoryDiscount): float {
    if (!$categoryDiscount) {
        return max(0, $amount);
    }
    $amount = max(0, $amount);
    $type = $categoryDiscount['type'] ?? 'none';
    $value = (float)($categoryDiscount['value'] ?? 0);

    if ($type === 'percent' && $value > 0) {
        return max(0, $amount - ($amount * ($value / 100)));
    }

    if ($type === 'amount' && $value > 0) {
        return max(0, $amount - $value);
    }

    return $amount;
}

function buildPriceDisplay(array $product, array $globalDiscount, array $categoryDiscounts = []): array {
    $baseOriginal = (float)($product['price'] ?? 0);
    $baseEffective = $product['sale_price'] !== null ? (float)$product['sale_price'] : $baseOriginal;
    $afterGlobal = applyGlobalDiscount($baseEffective, $globalDiscount);
    $catDiscount = null;
    if (isset($product['category_id']) && $product['category_id']) {
        $catDiscount = $categoryDiscounts[(int)$product['category_id']] ?? null;
    }
    $final = applyCategoryDiscount($afterGlobal, $catDiscount);

    $hasGlobal = ($globalDiscount['type'] ?? 'none') !== 'none' && ($globalDiscount['value'] ?? 0) > 0;
    $hasCategory = $catDiscount && (($catDiscount['type'] ?? 'none') !== 'none') && (($catDiscount['value'] ?? 0) > 0);
    $hasSale = $product['sale_price'] !== null;

    $hasDiscount = $hasSale || $hasGlobal || $hasCategory;
    $originalToShow = $hasDiscount ? $baseOriginal : $final;

    return [
        'current' => $final,
        'original' => $originalToShow,
        'has_discount' => $hasDiscount && $final < $originalToShow,
    ];
}

function saveGlobalDiscount(PDO $pdo, string $type, float $value, bool $freeShipping = false): void {
    ensureDiscountTables($pdo);
    $allowed = in_array($type, ['none', 'percent', 'amount', 'free_shipping'], true) ? $type : 'none';
    $val = $allowed === 'free_shipping' ? 0 : max(0, $value);
    $freeFlag = $allowed === 'free_shipping' ? 1 : ($freeShipping ? 1 : 0);
    $stmt = $pdo->prepare('REPLACE INTO discount_settings (id, type, value, free_shipping) VALUES (1, ?, ?, ?)');
    $stmt->execute([$allowed, $val, $freeFlag]);
}

function findDiscountCode(PDO $pdo, string $code): ?array {
    ensureDiscountTables($pdo);
    $stmt = $pdo->prepare('SELECT * FROM discount_codes WHERE code = ?');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    if (!$row || !(int)$row['active']) {
        return null;
    }
    if ((int)$row['usage_limit'] > 0 && (int)$row['used_count'] >= (int)$row['usage_limit']) {
        return null;
    }
    return $row;
}

function incrementDiscountUsage(PDO $pdo, string $code): void {
    $stmt = $pdo->prepare('UPDATE discount_codes SET used_count = used_count + 1 WHERE code = ?');
    $stmt->execute([$code]);
}

function getUserCartSnapshot(PDO $pdo, int $userId): array {
    ensureCartTables($pdo);
    $stmt = $pdo->prepare('SELECT c.product_id, c.quantity, p.title, p.price, p.image_url FROM cart_items c JOIN products p ON p.id = c.product_id WHERE c.user_id = ? ORDER BY c.updated_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function seedNewsExamples(PDO $pdo): void {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM news')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $samples = [
        [
            'title' => 'Nuotolinis cukraus stebėjimas kasdienybėje',
            'image_url' => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=1200&q=80',
            'body' => 'Dalijamės patarimais, kaip naudoti nuolatinio gliukozės stebėjimo sensorius ir gauti įspėjimus telefone laiku.',
            'is_featured' => 1,
        ],
        [
            'title' => 'Nauji mažo GI užkandžiai kelionei',
            'image_url' => 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?auto=format&fit=crop&w=1200&q=80',
            'body' => 'Į asortimentą įtraukėme baltyminius batonėlius ir riešutų mišinius, pritaikytus diabetui kontroliuoti.',
            'is_featured' => 1,
        ],
        [
            'title' => 'Kaip kalibruoti gliukometrą namuose',
            'image_url' => 'https://images.unsplash.com/photo-1582719478250-5c7ff88f2375?auto=format&fit=crop&w=1200&q=80',
            'body' => 'Trumpas žingsnis po žingsnio gidas, kaip pasiruošti matavimams, kad rezultatai būtų patikimi.',
            'is_featured' => 1,
        ],
        [
            'title' => 'Cukrinio diabeto klubas Marijampolėje',
            'image_url' => 'https://images.unsplash.com/photo-1478144592103-25e218a04891?auto=format&fit=crop&w=1200&q=80',
            'body' => 'Kviečiame į bendruomenės susitikimus pasidalinti receptais, fizinio aktyvumo patarimais ir pagalba naujokams.',
            'is_featured' => 0,
        ],
    ];

    $stmt = $pdo->prepare('INSERT INTO news (title, summary, image_url, body, visibility, is_featured) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($samples as $news) {
        $summary = mb_substr(strip_tags($news['body']), 0, 160) . '...';
        $stmt->execute([$news['title'], $summary, $news['image_url'], $news['body'], 'public', $news['is_featured']]);
    }
}
function ensurePasswordResetsTable(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}
?>
