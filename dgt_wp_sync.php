<?php
// Remote WordPress Database Sync Engine for Server 2 (u534600013@194.164.64.89)
// Invoked by DGT Central System to publish and update blog posts directly into WordPress DB

$rawInput = file_get_contents('php://stdin');
if (empty($rawInput)) {
    echo json_encode(['success' => false, 'error' => 'Empty payload']);
    exit(1);
}

$data = json_decode($rawInput, true);
if (!$data || empty($data['domain']) || empty($data['slug'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid domain or slug']);
    exit(1);
}

$domain = strtolower(trim($data['domain']));
$domain = preg_replace('/^www\./i', '', $domain);
$wpConfigPath = "/home/u534600013/domains/{$domain}/public_html/wp-config.php";

if (!file_exists($wpConfigPath)) {
    echo json_encode(['success' => false, 'error' => "wp-config.php not found for {$domain}"]);
    exit(1);
}

try {
    $cfg = file_get_contents($wpConfigPath);
    preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $cfg, $mDb);
    preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $cfg, $mUser);
    preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $cfg, $mPass);
    preg_match("/\\\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]/", $cfg, $mPrefix);

    $dbName = $mDb[1] ?? null;
    $dbUser = $mUser[1] ?? null;
    $dbPass = $mPass[1] ?? null;
    $prefix = $mPrefix[1] ?? 'wp_';

    if (!$dbName || !$dbUser || !$dbPass) {
        echo json_encode(['success' => false, 'error' => 'Could not extract DB credentials']);
        exit(1);
    }

    $pdo = new PDO("mysql:host=localhost;dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $slug = $data['slug'];
    $title = $data['title'] ?? 'Untitled';
    $content = $data['content'] ?? '';
    $excerpt = $data['excerpt'] ?? '';
    $status = $data['status'] ?? 'publish';
    $postDate = $data['post_date'] ?? date('Y-m-d H:i:s', time() - 60);
    $postDateGmt = $data['post_date_gmt'] ?? gmdate('Y-m-d H:i:s', time() - 60);
    $siteUrl = rtrim($data['site_url'] ?? "https://{$domain}", '/');
    $guid = "{$siteUrl}/{$slug}/";

    // Check existing post
    $chk = $pdo->prepare("SELECT ID FROM {$prefix}posts WHERE post_name = ? AND post_type = 'post' LIMIT 1");
    $chk->execute([$slug]);
    $existingId = $chk->fetchColumn();

    if ($existingId) {
        $postId = (int) $existingId;
        $upd = $pdo->prepare("UPDATE {$prefix}posts SET 
            post_title = ?, 
            post_content = ?, 
            post_status = ?, 
            post_date = ?, 
            post_date_gmt = ?, 
            post_modified = ?, 
            post_modified_gmt = ?, 
            post_excerpt = ?,
            guid = ?
            WHERE ID = ?");
        $upd->execute([
            $title,
            $content,
            $status,
            $postDate,
            $postDateGmt,
            $postDate,
            $postDateGmt,
            $excerpt,
            $guid,
            $postId
        ]);
    } else {
        $ins = $pdo->prepare("INSERT INTO {$prefix}posts 
            (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_name, post_modified, post_modified_gmt, post_type, to_ping, pinged, post_content_filtered, guid) 
            VALUES (1, ?, ?, ?, ?, ?, ?, 'open', 'open', ?, ?, ?, 'post', '', '', '', ?)");
        $ins->execute([
            $postDate,
            $postDateGmt,
            $content,
            $title,
            $excerpt,
            $status,
            $slug,
            $postDate,
            $postDateGmt,
            $guid
        ]);
        $postId = (int) $pdo->lastInsertId();
    }

    // Set SEO Meta & Featured Image (FIFU + DGT meta)
    $upsertMeta = function (int $pid, string $key, string $val) use ($pdo, $prefix): void {
        $chkM = $pdo->prepare("SELECT meta_id FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = ? LIMIT 1");
        $chkM->execute([$pid, $key]);
        $mid = $chkM->fetchColumn();
        if ($mid) {
            $u = $pdo->prepare("UPDATE {$prefix}postmeta SET meta_value = ? WHERE meta_id = ?");
            $u->execute([$val, $mid]);
        } else {
            $i = $pdo->prepare("INSERT INTO {$prefix}postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)");
            $i->execute([$pid, $key, $val]);
        }
    };

    if (!empty($data['meta_title'])) {
        foreach (['_yoast_wpseo_title', 'rank_math_title', '_dgt_meta_title'] as $mk) {
            $upsertMeta($postId, $mk, (string) $data['meta_title']);
        }
    }
    if (!empty($data['meta_description'])) {
        foreach (['_yoast_wpseo_metadesc', 'rank_math_description', '_dgt_meta_description'] as $mk) {
            $upsertMeta($postId, $mk, (string) $data['meta_description']);
        }
    }
    if (!empty($data['featured_image_url'])) {
        foreach (['_dgt_featured_image_url', 'fifu_image_url', '_fifu_image_url'] as $mk) {
            $upsertMeta($postId, $mk, trim((string) $data['featured_image_url']));
        }
    }

    // Assign primary blog categories so post appears immediately on /blog or /blogs
    $taxIds = [];
    $reqCat = trim((string) ($data['category'] ?? ''));
    if ($reqCat !== '' && !preg_match('/^(\d+(st|nd|rd|th)\s*class|class\s*\d+|uncategorized)$/i', $reqCat)) {
        $catSlug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $reqCat), '-'));
        $cStmt = $pdo->prepare("SELECT tt.term_taxonomy_id FROM {$prefix}terms t JOIN {$prefix}term_taxonomy tt ON t.term_id = tt.term_id WHERE (t.name = ? OR t.slug = ?) AND tt.taxonomy = 'category' LIMIT 1");
        $cStmt->execute([$reqCat, $catSlug]);
        $foundTax = (int) $cStmt->fetchColumn();
        if (!$foundTax && $catSlug !== '') {
            $insT = $pdo->prepare("INSERT INTO {$prefix}terms (name, slug, term_group) VALUES (?, ?, 0)");
            $insT->execute([$reqCat, $catSlug]);
            $newTermId = (int) $pdo->lastInsertId();
            if ($newTermId > 0) {
                $insTt = $pdo->prepare("INSERT INTO {$prefix}term_taxonomy (term_id, taxonomy, description, parent, count) VALUES (?, 'category', '', 0, 1)");
                $insTt->execute([$newTermId]);
                $foundTax = (int) $pdo->lastInsertId();
            }
        }
        if ($foundTax > 0) {
            $taxIds[] = $foundTax;
        }
    }

    $bStmt = $pdo->query("SELECT tt.term_taxonomy_id FROM {$prefix}terms t JOIN {$prefix}term_taxonomy tt ON t.term_id = tt.term_id WHERE t.slug IN ('blog', 'blogs', 'all-blogs', 'post', 'posts', 'articles', 'news', 'machinery-guide', 'electric-forklift-guides', 'skid-steer-buying-guides') AND tt.taxonomy = 'category'");
    foreach ($bStmt->fetchAll(PDO::FETCH_COLUMN) as $btid) {
        $taxIds[] = (int) $btid;
    }

    $defStmt = $pdo->prepare("SELECT option_value FROM {$prefix}options WHERE option_name = 'default_category' LIMIT 1");
    $defStmt->execute();
    $defCatTermId = (int) $defStmt->fetchColumn();
    if ($defCatTermId > 0) {
        $dtStmt = $pdo->prepare("SELECT tt.term_taxonomy_id, t.slug FROM {$prefix}terms t JOIN {$prefix}term_taxonomy tt ON t.term_id = tt.term_id WHERE t.term_id = ? AND tt.taxonomy = 'category' LIMIT 1");
        $dtStmt->execute([$defCatTermId]);
        $dtRow = $dtStmt->fetch(PDO::FETCH_ASSOC);
        if ($dtRow && (empty($taxIds) || ($dtRow['slug'] ?? '') !== 'uncategorized')) {
            $taxIds[] = (int) $dtRow['term_taxonomy_id'];
        }
    }

    foreach (array_unique(array_filter($taxIds)) as $ttId) {
        $relStmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}term_relationships (object_id, term_taxonomy_id, term_order) VALUES (?, ?, 0)");
        $relStmt->execute([$postId, $ttId]);
        $cntStmt = $pdo->prepare("UPDATE {$prefix}term_taxonomy SET count = (SELECT COUNT(*) FROM {$prefix}term_relationships WHERE term_taxonomy_id = ?) WHERE term_taxonomy_id = ?");
        $cntStmt->execute([$ttId, $ttId]);
    }

    // Clear Elementor widget cache & touch LiteSpeed purge marker
    $pdo->exec("DELETE FROM {$prefix}postmeta WHERE meta_key = '_elementor_element_cache'");
    $wpDir = dirname($wpConfigPath);
    if (is_dir($wpDir . '/wp-content/cache')) {
        @touch($wpDir . '/wp-content/cache/.purge');
    }

    // Set canonical / live URL
    $liveUrl = "{$siteUrl}/{$slug}/";

    echo json_encode([
        'success' => true,
        'id'      => $postId,
        'url'     => $liveUrl,
        'domain'  => $domain,
    ]);
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit(1);
}

