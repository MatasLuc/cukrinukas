<?php
require __DIR__ . '/db.php';

header("Content-Type: application/xml; charset=utf-8");

$pdo = getPdo();
$baseUrl = 'https://cukrinukas.lt'; // Pasitikrinkite savo domeną!

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

// 1. Statiniai puslapiai
$staticPages = [
    '/',
    '/products.php',
    '/news.php',
    '/recipes.php',
    '/community.php',
    '/contact.php',
    '/faq.php',
    '/about.php'
];

foreach ($staticPages as $page) {
    echo '<url>' . PHP_EOL;
    echo '  <loc>' . $baseUrl . $page . '</loc>' . PHP_EOL;
    echo '  <changefreq>weekly</changefreq>' . PHP_EOL;
    echo '  <priority>0.8</priority>' . PHP_EOL;
    echo '</url>' . PHP_EOL;
}

// 2. Produktai
$stmt = $pdo->query("SELECT id, created_at FROM products WHERE quantity > 0 ORDER BY created_at DESC");
while ($row = $stmt->fetch()) {
    // Jei naudojate .htaccess perrašymą:
    $url = $baseUrl . '/product.php?id=' . $row['id'];
    $date = date('c', strtotime($row['created_at']));
    
    echo '<url>' . PHP_EOL;
    echo '  <loc>' . $url . '</loc>' . PHP_EOL;
    echo '  <lastmod>' . $date . '</lastmod>' . PHP_EOL;
    echo '  <changefreq>daily</changefreq>' . PHP_EOL;
    echo '  <priority>1.0</priority>' . PHP_EOL;
    echo '</url>' . PHP_EOL;
}

// 3. Receptai
$stmt = $pdo->query("SELECT id, created_at FROM recipes ORDER BY created_at DESC");
while ($row = $stmt->fetch()) {
    $url = $baseUrl . '/recipe_view.php?id=' . $row['id'];
    $date = date('c', strtotime($row['created_at']));
    echo '<url>' . PHP_EOL;
    echo '  <loc>' . $url . '</loc>' . PHP_EOL;
    echo '  <lastmod>' . $date . '</lastmod>' . PHP_EOL;
    echo '  <priority>0.7</priority>' . PHP_EOL;
    echo '</url>' . PHP_EOL;
}

// 4. Naujienos
$stmt = $pdo->query("SELECT id, created_at FROM news WHERE visibility = 'public' ORDER BY created_at DESC");
while ($row = $stmt->fetch()) {
    $url = $baseUrl . '/news_view.php?id=' . $row['id'];
    $date = date('c', strtotime($row['created_at']));
    echo '<url>' . PHP_EOL;
    echo '  <loc>' . $url . '</loc>' . PHP_EOL;
    echo '  <lastmod>' . $date . '</lastmod>' . PHP_EOL;
    echo '  <priority>0.6</priority>' . PHP_EOL;
    echo '</url>' . PHP_EOL;
}

echo '</urlset>';
