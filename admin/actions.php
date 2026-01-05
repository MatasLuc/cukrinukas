<?php
// admin/actions.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken();
    $action = $_POST['action'] ?? '';
    
// --- NUOLAIDOS IR AKCIJOS ---
    // 1. Bendra nuolaida
    if ($action === 'save_global_discount') {
        // HTML formoje naudojame name="type" ir name="value", todėl imame $_POST['type']
        $type = $_POST['type'] ?? 'none';
        $value = (float)($_POST['value'] ?? 0);
        
        // Funkcija saveGlobalDiscount(PDO, type, value, isFreeShipping)
        saveGlobalDiscount($pdo, $type, $value, $type === 'free_shipping');
        
        $_SESSION['flash_success'] = 'Bendra nuolaida išsaugota';
        header('Location: ?view=discounts'); exit;
    }

    // 2. Nuolaidų kodai
    if ($action === 'save_discount_code') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
        $code = trim($_POST['code'] ?? '');
        $type = $_POST['type'] ?? 'percent';
        $value = (float)($_POST['value'] ?? 0);
        $usageLimit = (int)($_POST['usage_limit'] ?? 0);
        $active = isset($_POST['active']);

        if ($code === '') {
             $_SESSION['flash_error'] = 'Įveskite nuolaidos kodą.';
        } else {
            $freeShipping = ($type === 'free_shipping');
            saveDiscountCodeEntry($pdo, $id, strtoupper($code), $type, $value, $usageLimit, $active, $freeShipping);
            $_SESSION['flash_success'] = $id ? 'Kodas atnaujintas' : 'Kodas sukurtas';
        }
        header('Location: ?view=discounts'); exit;
    }

    if ($action === 'delete_discount_code') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            deleteDiscountCode($pdo, $id);
            $_SESSION['flash_success'] = 'Kodas pašalintas';
        }
        header('Location: ?view=discounts'); exit;
    }

    // 3. Kategorijų nuolaidos
    if ($action === 'save_category_discount') {
        $catId = (int)$_POST['category_id'];
        $type = $_POST['discount_type']; // Imame 'discount_type', nes taip pavadinta HTML formoje
        $value = (float)$_POST['discount_value'];
        $freeShipping = ($type === 'free_shipping');
        
        if ($catId) {
            // Funkcija: saveCategoryDiscount($pdo, $categoryId, $type, $value, $freeShipping, $active)
            saveCategoryDiscount($pdo, $catId, $type, $value, $freeShipping, true);
            $_SESSION['flash_success'] = 'Kategorijos akcija išsaugota.';
        } else {
            $_SESSION['flash_error'] = 'Pasirinkite kategoriją.';
        }
        header('Location: ?view=discounts'); exit;
    }

    if ($action === 'remove_category_discount') {
        $catId = (int)$_POST['category_id'];
        if ($catId) {
            deleteCategoryDiscount($pdo, $catId);
            $_SESSION['flash_success'] = 'Nuolaida pašalinta.';
        }
        header('Location: ?view=discounts'); exit;
    }

    // --- KATEGORIJOS ---
    if ($action === 'new_category') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if ($name && $slug) {
            $stmt = $pdo->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)');
            $stmt->execute([$name, $slug]);
            $messages[] = 'Kategorija pridėta';
        } else {
            $errors[] = 'Įveskite kategorijos pavadinimą ir nuorodą.';
        }
        $view = 'categories';
    }

    if ($action === 'edit_category') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if ($id && $name && $slug) {
            $stmt = $pdo->prepare('UPDATE categories SET name = ?, slug = ? WHERE id = ?');
            $stmt->execute([$name, $slug, $id]);
            $messages[] = 'Kategorija atnaujinta';
        }
        $view = 'categories';
    }

    if ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            $messages[] = 'Kategorija ištrinta';
        }
        $view = 'categories';
    }

    // --- BENDRUOMENĖS KATEGORIJOS ---
    if ($action === 'new_thread_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $stmt = $pdo->prepare('INSERT INTO community_thread_categories (name) VALUES (?)');
            $stmt->execute([$name]);
            $messages[] = 'Diskusijų kategorija pridėta';
        } else {
            $errors[] = 'Įveskite kategorijos pavadinimą.';
        }
        $view = 'community';
    }

    if ($action === 'delete_thread_category') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM community_thread_categories WHERE id = ?')->execute([$id]);
            $messages[] = 'Diskusijų kategorija ištrinta';
        }
        $view = 'community';
    }

    if ($action === 'new_listing_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $stmt = $pdo->prepare('INSERT INTO community_listing_categories (name) VALUES (?)');
            $stmt->execute([$name]);
            $messages[] = 'Turgus kategorija pridėta';
        } else {
            $errors[] = 'Įveskite kategorijos pavadinimą.';
        }
        $view = 'community';
    }

    if ($action === 'delete_listing_category') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM community_listing_categories WHERE id = ?')->execute([$id]);
            $messages[] = 'Turgus kategorija ištrinta';
        }
        $view = 'community';
    }

    // --- PREKĖS IR FEATURED ---

    // 1. Featured prekių valdymas
    if ($action === 'featured_add') {
        $title = trim($_POST['featured_title'] ?? '');
        if ($title) {
            // Randame prekės ID pagal pavadinimą
            $stmt = $pdo->prepare("SELECT id FROM products WHERE title = ? LIMIT 1");
            $stmt->execute([$title]);
            $pid = $stmt->fetchColumn();
            
            if ($pid) {
                // Tikriname limitą
                $count = $pdo->query("SELECT COUNT(*) FROM featured_products")->fetchColumn();
                if ($count < 3) {
                    $pdo->prepare("INSERT INTO featured_products (product_id, sort_order) VALUES (?, 0)")->execute([$pid]);
                    $_SESSION['flash_success'] = 'Prekė pridėta į pagrindinį puslapį.';
                } else {
                    $_SESSION['flash_error'] = 'Maksimalus rodomų prekių skaičius (3) pasiektas.';
                }
            } else {
                $_SESSION['flash_error'] = 'Prekė nerasta.';
            }
        }
        header('Location: ?view=products'); exit;
    }

    if ($action === 'featured_remove') {
        $pid = (int)$_POST['product_id'];
        $pdo->prepare("DELETE FROM featured_products WHERE product_id = ?")->execute([$pid]);
        $_SESSION['flash_success'] = 'Prekė pašalinta iš pagrindinio puslapio.';
        header('Location: ?view=products'); exit;
    }

    // 2. Prekės kūrimas / redagavimas (Vieninga funkcija)
    if ($action === 'save_product') {
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
        
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $ribbon = trim($_POST['ribbon_text'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $salePrice = isset($_POST['sale_price']) && $_POST['sale_price'] !== '' ? (float)$_POST['sale_price'] : null;
        $qty = (int)($_POST['quantity'] ?? 0);
        $metaTags = trim($_POST['meta_tags'] ?? '');
        
        // Kategorijos (masyvas iš checkboxų)
        $cats = $_POST['categories'] ?? [];
        // Pirma kategorija laikoma "pagrindine" suderinamumui
        $primaryCatId = !empty($cats) ? (int)$cats[0] : null;

        if ($title) {
            if ($id) {
                // Atnaujinimas
                $stmt = $pdo->prepare('UPDATE products SET category_id=?, title=?, subtitle=?, description=?, ribbon_text=?, price=?, sale_price=?, quantity=?, meta_tags=? WHERE id=?');
                $stmt->execute([$primaryCatId, $title, $subtitle ?: null, $description, $ribbon ?: null, $price, $salePrice, $qty, $metaTags ?: null, $id]);
                $productId = $id;
                $_SESSION['flash_success'] = 'Prekė atnaujinta';
            } else {
                // Kūrimas
                $stmt = $pdo->prepare('INSERT INTO products (category_id, title, subtitle, description, ribbon_text, image_url, price, sale_price, quantity, meta_tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $primaryCatId, $title, $subtitle ?: null, $description, $ribbon ?: null,
                    'https://placehold.co/600x400?text=Preke', // Default img
                    $price, $salePrice, $qty, $metaTags ?: null
                ]);
                $productId = (int)$pdo->lastInsertId();
                $_SESSION['flash_success'] = 'Prekė sukurta';
            }

            // KATEGORIJŲ RYŠIAI (Multi-category)
            $pdo->prepare("DELETE FROM product_category_relations WHERE product_id = ?")->execute([$productId]);
            if (!empty($cats)) {
                $insCat = $pdo->prepare("INSERT INTO product_category_relations (product_id, category_id) VALUES (?, ?)");
                foreach ($cats as $cid) {
                    $insCat->execute([$productId, (int)$cid]);
                }
            }

            // NUOTRAUKOS
            if (!empty($_FILES['images']['name'][0])) {
                storeUploads($pdo, $productId, $_FILES['images']);
            }

            // SUSIJUSIOS PREKĖS
            $pdo->prepare('DELETE FROM product_related WHERE product_id = ?')->execute([$productId]);
            $related = array_filter(array_map('intval', $_POST['related_products'] ?? []));
            if ($related) {
                $insertRel = $pdo->prepare('INSERT IGNORE INTO product_related (product_id, related_product_id) VALUES (?, ?)');
                foreach ($related as $rel) {
                    if ($rel !== $productId) $insertRel->execute([$productId, $rel]);
                }
            }

            // ATRIBUTAI (išvalome ir įrašome iš naujo)
            $pdo->prepare('DELETE FROM product_attributes WHERE product_id = ?')->execute([$productId]);
            $attrNames = $_POST['attr_label'] ?? [];
            $attrValues = $_POST['attr_value'] ?? [];
            if ($attrNames) {
                $insertAttr = $pdo->prepare('INSERT INTO product_attributes (product_id, label, value) VALUES (?, ?, ?)');
                foreach ($attrNames as $idx => $label) {
                    $label = trim($label);
                    $val = trim($attrValues[$idx] ?? ''); 
                    if ($label && $val) {
                        $insertAttr->execute([$productId, $label, $val]);
                    }
                }
            }

            // VARIACIJOS
            $pdo->prepare('DELETE FROM product_variations WHERE product_id = ?')->execute([$productId]);
            $varNames = $_POST['variation_name'] ?? [];
            $varPrices = $_POST['variation_price'] ?? [];
            if ($varNames) {
                $insertVar = $pdo->prepare('INSERT INTO product_variations (product_id, name, price_delta) VALUES (?, ?, ?)');
                foreach ($varNames as $idx => $vName) {
                    $vName = trim($vName);
                    $delta = isset($varPrices[$idx]) ? (float)$varPrices[$idx] : 0;
                    if ($vName !== '') {
                        $insertVar->execute([$productId, $vName, $delta]);
                    }
                }
            }

        } else {
            $_SESSION['flash_error'] = 'Trūksta prekės pavadinimo.';
        }
        header('Location: ?view=products'); exit;
    }

    // Masinis trynimas
    if ($action === 'bulk_delete_products') {
        $ids = $_POST['selected_ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            // Ištriname viską, kas susiję su prekėmis
            $pdo->prepare("DELETE FROM product_attributes WHERE product_id IN ($placeholders)")->execute($ids);
            $pdo->prepare("DELETE FROM product_variations WHERE product_id IN ($placeholders)")->execute($ids);
            $pdo->prepare("DELETE FROM product_category_relations WHERE product_id IN ($placeholders)")->execute($ids);
            $pdo->prepare("DELETE FROM featured_products WHERE product_id IN ($placeholders)")->execute($ids);
            $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)")->execute($ids);
            
            $_SESSION['flash_success'] = 'Ištrinta prekių: ' . count($ids);
        } else {
            $_SESSION['flash_error'] = 'Nepasirinkote prekių.';
        }
        header('Location: ?view=products'); exit;
    }
    
    // --- VARTOTOJAI IR UŽSAKYMAI ---
    if ($action === 'toggle_admin') {
        $userId = (int)$_POST['user_id'];
        $pdo->prepare('UPDATE users SET is_admin = IF(is_admin=1,0,1) WHERE id = ?')->execute([$userId]);
        $messages[] = 'Vartotojo teisės atnaujintos';
        $view = 'users';
    }

    if ($action === 'edit_user') {
        $userId = (int)$_POST['user_id'];
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($userId && $name && $email) {
            $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')->execute([$name, $email, $userId]);
            $messages[] = 'Vartotojas atnaujintas';
        }
        $view = 'users';
    }

    if ($action === 'order_status') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $allowed = ["laukiama","apdorojama","išsiųsta","įvykdyta","atšaukta"];
        if ($orderId && in_array($status, $allowed, true)) {
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $orderId]);
            $messages[] = 'Užsakymo būsena atnaujinta';
        }
        $view = 'orders';
    }

    // --- MENIU (NAVIGACIJA) ---
    if ($action === 'nav_new') {
        $label = trim($_POST['label'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $parentId = $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
        $sort = (int)($pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM navigation_items')->fetchColumn());
        if ($label && $url) {
            $stmt = $pdo->prepare('INSERT INTO navigation_items (label, url, parent_id, sort_order) VALUES (?, ?, ?, ?)');
            $stmt->execute([$label, $url, $parentId, $sort]);
            $messages[] = 'Meniu punktas sukurtas';
        } else {
            $errors[] = 'Įveskite pavadinimą ir nuorodą.';
        }
        $view = 'menus';
    }

    if ($action === 'nav_update') {
        $id = (int)($_POST['id'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $parentId = $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
        if ($id && $label && $url) {
            $currentSort = $pdo->prepare('SELECT sort_order FROM navigation_items WHERE id = ?');
            $currentSort->execute([$id]);
            $sort = (int)($currentSort->fetchColumn() ?: 0);
            $stmt = $pdo->prepare('UPDATE navigation_items SET label = ?, url = ?, parent_id = ?, sort_order = ? WHERE id = ?');
            $stmt->execute([$label, $url, $parentId, $sort, $id]);
            $messages[] = 'Meniu atnaujintas';
        }
        $view = 'menus';
    }

    if ($action === 'nav_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM navigation_items WHERE id = ?')->execute([$id]);
            $messages[] = 'Meniu punktas pašalintas';
        }
        $view = 'menus';
    }

    if ($action === 'nav_reorder') {
        if (!empty($_POST['order']) && is_array($_POST['order'])) {
            $stmt = $pdo->prepare('UPDATE navigation_items SET sort_order = ? WHERE id = ?');
            foreach ($_POST['order'] as $id => $sort) {
                $stmt->execute([(int)$sort, (int)$id]);
            }
            $messages[] = 'Rikiavimas atnaujintas';
        }
        $view = 'menus';
    }

    // --- DIZAINAS IR TURINYS ---
    if ($action === 'footer_content') {
        $footerData = [
            'footer_brand_title' => trim($_POST['footer_brand_title'] ?? ''),
            'footer_brand_body' => trim($_POST['footer_brand_body'] ?? ''),
            'footer_brand_pill' => trim($_POST['footer_brand_pill'] ?? ''),
            'footer_quick_title' => trim($_POST['footer_quick_title'] ?? ''),
            'footer_help_title' => trim($_POST['footer_help_title'] ?? ''),
            'footer_contact_title' => trim($_POST['footer_contact_title'] ?? ''),
            'footer_contact_email' => trim($_POST['footer_contact_email'] ?? ''),
            'footer_contact_phone' => trim($_POST['footer_contact_phone'] ?? ''),
            'footer_contact_hours' => trim($_POST['footer_contact_hours'] ?? ''),
        ];
        saveSiteContent($pdo, $footerData);
        $messages[] = 'Poraštės tekstas atnaujintas';
        $view = 'design';
    }

    if ($action === 'footer_link_save') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $label = trim($_POST['label'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $section = $_POST['section'] ?? 'quick';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        if ($label && $url) {
            saveFooterLink($pdo, $id ?: null, $label, $url, $section, $sortOrder);
            $messages[] = $id ? 'Nuoroda atnaujinta' : 'Nuoroda pridėta';
        } else {
            $errors[] = 'Įveskite pavadinimą ir nuorodą.';
        }
        $view = 'design';
    }

    if ($action === 'footer_link_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            deleteFooterLink($pdo, $id);
            $messages[] = 'Nuoroda pašalinta';
        }
        $view = 'design';
    }

    if ($action === 'hero_copy') {
        $hero = [
            'hero_title' => trim($_POST['hero_title'] ?? ''),
            'hero_body' => trim($_POST['hero_body'] ?? ''),
            'hero_cta_label' => trim($_POST['hero_cta_label'] ?? ''),
            'hero_cta_url' => trim($_POST['hero_cta_url'] ?? ''),
        ];
        saveSiteContent($pdo, $hero);
        $messages[] = 'Hero tekstas atnaujintas';
        $view = 'design';
    }

    if ($action === 'page_hero_update') {
        $fields = [
            'news_hero_pill', 'news_hero_title', 'news_hero_body', 'news_hero_cta_label', 'news_hero_cta_url', 'news_hero_card_meta', 'news_hero_card_title', 'news_hero_card_body',
            'recipes_hero_pill', 'recipes_hero_title', 'recipes_hero_body', 'recipes_hero_cta_label', 'recipes_hero_cta_url', 'recipes_hero_card_meta', 'recipes_hero_card_title', 'recipes_hero_card_body',
            'faq_hero_pill', 'faq_hero_title', 'faq_hero_body',
            'contact_hero_pill', 'contact_hero_title', 'contact_hero_body', 'contact_cta_primary_label', 'contact_cta_primary_url', 'contact_cta_secondary_label', 'contact_cta_secondary_url', 'contact_card_pill', 'contact_card_title', 'contact_card_body',
        ];
        $payload = [];
        foreach ($fields as $field) {
            $payload[$field] = trim($_POST[$field] ?? '');
        }
        saveSiteContent($pdo, $payload);
        $messages[] = 'Hero sekcijos atnaujintos';
        $view = 'design';
    }

    if ($action === 'hero_media_update') {
        $type = $_POST['hero_media_type'] ?? 'image';
        $color = trim($_POST['hero_media_color'] ?? '#829ed6');
        $shadow = max(0, min(100, (int)($_POST['hero_shadow_intensity'] ?? 70)));
        $imagePath = trim($_POST['hero_media_image_existing'] ?? '');
        $videoPath = trim($_POST['hero_media_video_existing'] ?? '');
        $posterPath = trim($_POST['hero_media_poster_existing'] ?? '');
        $alt = trim($_POST['hero_media_alt'] ?? '');

        $imageMimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $videoMimeMap = [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
        ];

        if (!empty($_FILES['hero_media_image']['name'])) {
            $uploaded = saveUploadedFile($_FILES['hero_media_image'], $imageMimeMap, 'hero_img_');
            if ($uploaded) {
                $imagePath = $uploaded;
            }
        }

        if (!empty($_FILES['hero_media_video']['name'])) {
            $uploaded = saveUploadedFile($_FILES['hero_media_video'], $videoMimeMap, 'hero_vid_');
            if ($uploaded) {
                $videoPath = $uploaded;
            }
        }

        if (!empty($_FILES['hero_media_poster']['name'])) {
            $uploaded = saveUploadedFile($_FILES['hero_media_poster'], $imageMimeMap, 'hero_poster_');
            if ($uploaded) {
                $posterPath = $uploaded;
            }
        }

        $payload = [
            'hero_media_type' => $type,
            'hero_media_color' => $color,
            'hero_media_image' => $imagePath,
            'hero_media_video' => $videoPath,
            'hero_media_poster' => $posterPath,
            'hero_media_alt' => $alt,
            'hero_shadow_intensity' => (string)$shadow,
        ];

        saveSiteContent($pdo, $payload);
        $messages[] = 'Hero fonas atnaujintas';
        $view = 'design';
    }

    if ($action === 'promo_update') {
        $payload = [];
        for ($i = 1; $i <= 3; $i++) {
            $payload['promo_' . $i . '_icon'] = trim($_POST['promo_' . $i . '_icon'] ?? '');
            $payload['promo_' . $i . '_title'] = trim($_POST['promo_' . $i . '_title'] ?? '');
            $payload['promo_' . $i . '_body'] = trim($_POST['promo_' . $i . '_body'] ?? '');
        }
        saveSiteContent($pdo, $payload);
        $messages[] = 'Promo kortelės atnaujintos';
        $view = 'design';
    }

    if ($action === 'storyband_update') {
        $payload = [
            'storyband_badge' => trim($_POST['storyband_badge'] ?? ''),
            'storyband_title' => trim($_POST['storyband_title'] ?? ''),
            'storyband_body' => trim($_POST['storyband_body'] ?? ''),
            'storyband_cta_label' => trim($_POST['storyband_cta_label'] ?? ''),
            'storyband_cta_url' => trim($_POST['storyband_cta_url'] ?? ''),
            'storyband_card_eyebrow' => trim($_POST['storyband_card_eyebrow'] ?? ''),
            'storyband_card_title' => trim($_POST['storyband_card_title'] ?? ''),
            'storyband_card_body' => trim($_POST['storyband_card_body'] ?? ''),
        ];

        for ($i = 1; $i <= 3; $i++) {
            $payload['storyband_metric_' . $i . '_value'] = trim($_POST['storyband_metric_' . $i . '_value'] ?? '');
            $payload['storyband_metric_' . $i . '_label'] = trim($_POST['storyband_metric_' . $i . '_label'] ?? '');
        }

        saveSiteContent($pdo, $payload);
        $messages[] = 'Storyband turinys atnaujintas';
        $view = 'design';
    }

    if ($action === 'storyrow_update') {
        $payload = [
            'storyrow_eyebrow' => trim($_POST['storyrow_eyebrow'] ?? ''),
            'storyrow_title' => trim($_POST['storyrow_title'] ?? ''),
            'storyrow_body' => trim($_POST['storyrow_body'] ?? ''),
            'storyrow_bubble_meta' => trim($_POST['storyrow_bubble_meta'] ?? ''),
            'storyrow_bubble_title' => trim($_POST['storyrow_bubble_title'] ?? ''),
            'storyrow_bubble_body' => trim($_POST['storyrow_bubble_body'] ?? ''),
            'storyrow_floating_meta' => trim($_POST['storyrow_floating_meta'] ?? ''),
            'storyrow_floating_title' => trim($_POST['storyrow_floating_title'] ?? ''),
            'storyrow_floating_body' => trim($_POST['storyrow_floating_body'] ?? ''),
        ];

        for ($i = 1; $i <= 3; $i++) {
            $payload['storyrow_pill_' . $i] = trim($_POST['storyrow_pill_' . $i] ?? '');
        }

        saveSiteContent($pdo, $payload);
        $messages[] = 'Story-row turinys atnaujintas';
        $view = 'design';
    }

    if ($action === 'support_update') {
        $payload = [
            'support_meta' => trim($_POST['support_meta'] ?? ''),
            'support_title' => trim($_POST['support_title'] ?? ''),
            'support_body' => trim($_POST['support_body'] ?? ''),
            'support_card_meta' => trim($_POST['support_card_meta'] ?? ''),
            'support_card_title' => trim($_POST['support_card_title'] ?? ''),
            'support_card_body' => trim($_POST['support_card_body'] ?? ''),
            'support_card_cta_label' => trim($_POST['support_card_cta_label'] ?? ''),
            'support_card_cta_url' => trim($_POST['support_card_cta_url'] ?? ''),
        ];

        for ($i = 1; $i <= 3; $i++) {
            $payload['support_chip_' . $i] = trim($_POST['support_chip_' . $i] ?? '');
        }

        saveSiteContent($pdo, $payload);
        $messages[] = 'Support band turinys atnaujintas';
        $view = 'design';
    }

    if ($action === 'banner_update') {
        $banner = [
            'banner_enabled' => isset($_POST['banner_enabled']) ? '1' : '0',
            'banner_text' => trim($_POST['banner_text'] ?? ''),
            'banner_link' => trim($_POST['banner_link'] ?? ''),
            'banner_background' => trim($_POST['banner_background'] ?? '#829ed6'),
        ];
        saveSiteContent($pdo, $banner);
        $messages[] = 'Reklamjuostė atnaujinta';
        $view = 'design';
    }

    if ($action === 'testimonial_update') {
        $payload = [];
        for ($i = 1; $i <= 3; $i++) {
            $payload['testimonial_' . $i . '_name'] = trim($_POST['testimonial_' . $i . '_name'] ?? '');
            $payload['testimonial_' . $i . '_role'] = trim($_POST['testimonial_' . $i . '_role'] ?? '');
            $payload['testimonial_' . $i . '_text'] = trim($_POST['testimonial_' . $i . '_text'] ?? '');
        }
        saveSiteContent($pdo, $payload);
        $messages[] = 'Atsiliepimai atnaujinti';
        $view = 'design';
    }

    // --- PRISTATYMAS ---
    if ($action === 'shipping_save') {
        $courier = (float)($_POST['shipping_courier'] ?? 3.99);
        $locker = (float)($_POST['shipping_locker'] ?? 2.49);
        $free = $_POST['shipping_free_over'] !== '' ? (float)$_POST['shipping_free_over'] : null;
        saveShippingSettings($pdo, $courier, $courier, $locker, $free);
        $messages[] = 'Pristatymo nustatymai išsaugoti';
        $view = 'shipping';
    }

    if ($action === 'locker_new') {
        $provider = $_POST['locker_provider'] ?? '';
        $title = trim($_POST['locker_title'] ?? '');
        $address = trim($_POST['locker_address'] ?? '');
        $note = trim($_POST['locker_note'] ?? '');

        if (!in_array($provider, ['omniva', 'lpexpress'], true)) {
            $errors[] = 'Pasirinkite tinkamą paštomatų tinklą.';
        }
        if ($title === '' || $address === '') {
            $errors[] = 'Įveskite paštomato pavadinimą ir adresą.';
        }

        if (!$errors) {
            saveParcelLocker($pdo, $provider, $title, $address, $note ?: null);
            $messages[] = 'Paštomatas išsaugotas';
        }
        $view = 'shipping';
    }

    if ($action === 'locker_update') {
        $lockerId = (int)($_POST['locker_id'] ?? 0);
        $provider = $_POST['locker_provider'] ?? '';
        $title = trim($_POST['locker_title'] ?? '');
        $address = trim($_POST['locker_address'] ?? '');
        $note = trim($_POST['locker_note'] ?? '');

        if (!in_array($provider, ['omniva', 'lpexpress'], true)) {
            $errors[] = 'Pasirinkite tinkamą paštomatų tinklą.';
        }
        if ($title === '' || $address === '') {
            $errors[] = 'Įveskite paštomato pavadinimą ir adresą.';
        }
        if ($lockerId <= 0) {
            $errors[] = 'Pasirinkite paštomatą redagavimui.';
        }

        if (!$errors) {
            updateParcelLocker($pdo, $lockerId, $provider, $title, $address, $note ?: null);
            $messages[] = 'Paštomatas atnaujintas';
        }
        $view = 'shipping';
    }

    if ($action === 'locker_import') {
        $provider = $_POST['locker_provider'] ?? '';
        $view = 'shipping';

        if (!in_array($provider, ['omniva', 'lpexpress'], true)) {
            $errors[] = 'Pasirinkite tinkamą paštomatų tinklą importui.';
        }

        if (empty($_FILES['locker_file']) || ($_FILES['locker_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Įkelkite .xlsx failą su paštomatais.';
        }

        $allowedMimeMap = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ];

        $uploadedLockerPath = null;
        if (($_FILES['locker_file']['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
            $uploadedLockerPath = saveUploadedFile($_FILES['locker_file'], $allowedMimeMap, 'lockers_');
        }

        if (!$uploadedLockerPath) {
            $errors[] = 'Leidžiami tik .xlsx failai.';
        }

        if (!$errors && $uploadedLockerPath) {
            $parsed = parseLockerFile($provider, __DIR__ . '/../' . ltrim($uploadedLockerPath, '/'));
            if (!$parsed) {
                $errors[] = 'Nepavyko nuskaityti paštomatų iš failo.';
            } else {
                bulkSaveParcelLockers($pdo, $provider, $parsed);
                $messages[] = 'Importuota paštomatų: ' . count($parsed);
            }
        }
    }

    if ($action === 'shipping_free_products') {
        $selected = $_POST['promo_products'] ?? [];
        saveFreeShippingProducts($pdo, is_array($selected) ? $selected : []);
        $messages[] = 'Nemokamo pristatymo pasiūlymai atnaujinti';
        $view = 'shipping';
    }

    // --- BENDRUOMENĖS MODERAVIMAS ---
    if ($action === 'community_block') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $until = trim($_POST['banned_until'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if ($userId) {
            $pdo->prepare('REPLACE INTO community_blocks (user_id, banned_until, reason) VALUES (?, ?, ?)')->execute([$userId, $until ?: null, $reason ?: null]);
            $messages[] = 'Vartotojas apribotas';
        }
        $view = 'community';
    }

    if ($action === 'community_unblock') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid) {
            $pdo->prepare('DELETE FROM community_blocks WHERE user_id = ?')->execute([$uid]);
            $messages[] = 'Apribojimas pašalintas';
        }
        $view = 'community';
    }

    if ($action === 'community_order_status') {
        $id = (int)($_POST['order_id'] ?? 0);
        $status = $_POST['status'] ?? 'laukiama';
        if ($id) {
            $pdo->prepare('UPDATE community_orders SET status = ? WHERE id = ?')->execute([$status, $id]);
            $messages[] = 'Užklausos statusas atnaujintas';
        }
        $view = 'community';
    }

    if ($action === 'community_listing_status') {
        $id = (int)($_POST['listing_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        if ($id) {
            $pdo->prepare('UPDATE community_listings SET status = ? WHERE id = ?')->execute([$status, $id]);
            $messages[] = 'Skelbimo statusas atnaujintas';
        }
        $view = 'community';
    }
// --- BENDRUOMENĖS VEIKSMAI ---

    // Diskusijų kategorijos
    if ($action === 'save_community_category') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            if ($id) {
                $pdo->prepare('UPDATE community_thread_categories SET name = ? WHERE id = ?')->execute([$name, $id]);
                $_SESSION['flash_success'] = 'Kategorija atnaujinta.';
            } else {
                $pdo->prepare('INSERT INTO community_thread_categories (name) VALUES (?)')->execute([$name]);
                $_SESSION['flash_success'] = 'Kategorija sukurta.';
            }
        }
        header('Location: ?view=community'); exit;
    }
    if ($action === 'delete_community_category') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM community_thread_categories WHERE id = ?')->execute([$id]);
        $_SESSION['flash_success'] = 'Kategorija ištrinta.';
        header('Location: ?view=community'); exit;
    }
    if ($action === 'delete_community_thread') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM community_threads WHERE id = ?')->execute([$id]);
        $_SESSION['flash_success'] = 'Tema pašalinta.';
        header('Location: ?view=community'); exit;
    }
    
    // Turgelio kategorijos
    if ($action === 'save_listing_category') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            if ($id) {
                $pdo->prepare('UPDATE community_listing_categories SET name = ? WHERE id = ?')->execute([$name, $id]);
                $_SESSION['flash_success'] = 'Skelbimų kategorija atnaujinta.';
            } else {
                $pdo->prepare('INSERT INTO community_listing_categories (name) VALUES (?)')->execute([$name]);
                $_SESSION['flash_success'] = 'Skelbimų kategorija sukurta.';
            }
        }
        header('Location: ?view=community'); exit;
    }
    if ($action === 'delete_listing_category') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM community_listing_categories WHERE id = ?')->execute([$id]);
        $_SESSION['flash_success'] = 'Skelbimų kategorija ištrinta.';
        header('Location: ?view=community'); exit;
    }
    
    // Skelbimų valdymas
    if ($action === 'delete_listing') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM community_listings WHERE id = ?')->execute([$id]);
        $_SESSION['flash_success'] = 'Skelbimas pašalintas.';
        header('Location: ?view=community'); exit;
    }
    if ($action === 'update_listing_status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        if (in_array($status, ['active', 'sold'])) {
            $pdo->prepare('UPDATE community_listings SET status = ? WHERE id = ?')->execute([$status, $id]);
            $_SESSION['flash_success'] = 'Statusas atnaujintas.';
        }
        header('Location: ?view=community'); exit;
    }
    
    // Vartotojų blokavimas
    if ($action === 'block_user') {
        $userId = (int)$_POST['user_id'];
        $duration = $_POST['duration']; // 24h, 7d, 30d, permanent
        $reason = trim($_POST['reason']);
        
        if ($userId && $reason) {
            $until = null;
            if ($duration === '24h') $until = date('Y-m-d H:i:s', strtotime('+24 hours'));
            elseif ($duration === '7d') $until = date('Y-m-d H:i:s', strtotime('+7 days'));
            elseif ($duration === '30d') $until = date('Y-m-d H:i:s', strtotime('+30 days'));
            // Jei permanent, paliekam NULL arba labai tolimą datą. Čia paliekam NULL kaip "amžinai", bet reikia patikrinti logiką db.php.
            // Pagal db.php: banned_until DATETIME NULL. Jei NULL, gali reikšti "niekada" arba "visam laikui".
            // Dažniausiai NULL reiškia "nėra ban", todėl permanent turėtų būti tolima data.
            if ($duration === 'permanent') $until = '2099-12-31 00:00:00';
    
            // Įrašome į community_blocks
            $stmt = $pdo->prepare('INSERT INTO community_blocks (user_id, banned_until, reason) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $until, $reason]);
            $_SESSION['flash_success'] = "Vartotojas (ID: $userId) užblokuotas.";
        }
        header('Location: ?view=community'); exit;
    }
    if ($action === 'unblock_user') {
        $id = (int)$_POST['id'];
        // Galime arba trinti įrašą, arba nustatyti datą į praeitį. Trinimas švariau.
        $pdo->prepare('DELETE FROM community_blocks WHERE id = ?')->execute([$id]);
        $_SESSION['flash_success'] = 'Vartotojas atblokuotas.';
        header('Location: ?view=community'); exit;
    }
    // --- TURINIO TRYNIMAS ---
    if ($action === 'delete_news') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM news WHERE id = ?')->execute([$id]);
            $messages[] = 'Naujiena ištrinta';
        }
        $view = 'content';
    }

    if ($action === 'delete_recipe') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $pdo->prepare('DELETE FROM recipes WHERE id = ?')->execute([$id]);
            $messages[] = 'Receptas ištrintas';
        }
        $view = 'content';
    }
    // --- MENIU VALDYMAS ---
    if ($action === 'save_menu_item') {
        $id = $_POST['id'] ?? '';
        $label = trim($_POST['label']);
        $url = trim($_POST['url']);
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        
        // Apsauga: negalima tapti savo paties vaiku
        if ($id && $parentId == $id) {
            $parentId = null; 
        }
    
        if ($label && $url) {
            if ($id) {
                // Atnaujiname
                $stmt = $pdo->prepare('UPDATE navigation_items SET label = ?, url = ?, parent_id = ? WHERE id = ?');
                $stmt->execute([$label, $url, $parentId, $id]);
                $_SESSION['flash_success'] = 'Meniu punktas atnaujintas.';
            } else {
                // Kuriame naują. Randame didžiausią sort_order tam parent_id
                $maxSort = 0;
                if ($parentId) {
                    $maxSort = (int)$pdo->prepare('SELECT MAX(sort_order) FROM navigation_items WHERE parent_id = ?')->execute([$parentId]);
                } else {
                    $maxSort = (int)$pdo->query('SELECT MAX(sort_order) FROM navigation_items WHERE parent_id IS NULL')->fetchColumn();
                }
                
                $stmt = $pdo->prepare('INSERT INTO navigation_items (label, url, parent_id, sort_order) VALUES (?, ?, ?, ?)');
                $stmt->execute([$label, $url, $parentId, $maxSort + 1]);
                $_SESSION['flash_success'] = 'Meniu punktas sukurtas.';
            }
        }
        header('Location: ?view=menus'); exit;
    }
    
    if ($action === 'delete_menu_item') {
        $id = (int)$_POST['id'];
        // Ištriname (DB turėtų turėti ON DELETE CASCADE vaikams, bet dėl viso pikto)
        $pdo->prepare('DELETE FROM navigation_items WHERE id = ? OR parent_id = ?')->execute([$id, $id]);
        $_SESSION['flash_success'] = 'Meniu punktas ištrintas.';
        header('Location: ?view=menus'); exit;
    }
    
    if ($action === 'move_menu_item') {
        $id = (int)$_POST['id'];
        $direction = $_POST['direction']; // 'up' or 'down'
        
        // Gauname esamą elementą
        $stmt = $pdo->prepare('SELECT id, parent_id, sort_order FROM navigation_items WHERE id = ?');
        $stmt->execute([$id]);
        $current = $stmt->fetch();
        
        if ($current) {
            $parentId = $current['parent_id'];
            $currentSort = $current['sort_order'];
            
            // Randame kaimyną
            $neighbor = null;
            if ($direction === 'up') {
                // Ieškome artimiausio mažesnio sort_order su tuo pačiu parent_id
                $q = 'SELECT id, sort_order FROM navigation_items WHERE ';
                $q .= ($parentId ? 'parent_id = ?' : 'parent_id IS NULL');
                $q .= ' AND sort_order < ? ORDER BY sort_order DESC LIMIT 1';
                
                $stmt = $pdo->prepare($q);
                $args = $parentId ? [$parentId, $currentSort] : [$currentSort];
                $stmt->execute($args);
                $neighbor = $stmt->fetch();
            } elseif ($direction === 'down') {
                // Ieškome artimiausio didesnio sort_order
                $q = 'SELECT id, sort_order FROM navigation_items WHERE ';
                $q .= ($parentId ? 'parent_id = ?' : 'parent_id IS NULL');
                $q .= ' AND sort_order > ? ORDER BY sort_order ASC LIMIT 1';
                
                $stmt = $pdo->prepare($q);
                $args = $parentId ? [$parentId, $currentSort] : [$currentSort];
                $stmt->execute($args);
                $neighbor = $stmt->fetch();
            }
            
            // Sukeičiame vietomis
            if ($neighbor) {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE navigation_items SET sort_order = ? WHERE id = ?')->execute([$neighbor['sort_order'], $current['id']]);
                $pdo->prepare('UPDATE navigation_items SET sort_order = ? WHERE id = ?')->execute([$current['sort_order'], $neighbor['id']]);
                $pdo->commit();
                $_SESSION['flash_success'] = 'Pozicija pakeista.';
            }
        }
        header('Location: ?view=menus'); exit;
    }
}
