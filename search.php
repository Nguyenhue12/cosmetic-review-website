<?php
require_once 'config/database.php';
require_once 'includes/service_post_functions.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
$normalizedTag = service_posts_normalize_tag($q);
if (function_exists('mb_strlen')) {
    $length = mb_strlen($q, 'UTF-8');
} else {
    $length = strlen($q);
}

if ($length < 2) {
    echo json_encode(['ok' => true, 'query' => $q, 'results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$like = '%' . $q . '%';
$tagLike = '%' . $normalizedTag . '%';
$results = [];

$stmt_services = $pdo->prepare("
    SELECT sp.id, sp.title, sp.content, sp.image_url, sp.created_at,
           u.name as user_name,
           c.name as category_name,
           (
               SELECT GROUP_CONCAT(t.tag ORDER BY t.tag SEPARATOR ', ')
               FROM service_post_tags t
               WHERE t.post_id = sp.id
           ) as tags
    FROM service_posts sp
    JOIN users u ON u.id = sp.user_id
    LEFT JOIN categories c ON c.id = sp.category_id
    WHERE sp.status = 'active'
      AND (
          sp.title LIKE ?
          OR sp.content LIKE ?
          OR sp.location LIKE ?
          OR u.name LIKE ?
          OR EXISTS (
              SELECT 1 FROM service_post_tags t
              WHERE t.post_id = sp.id AND t.tag LIKE ?
          )
      )
    ORDER BY sp.created_at DESC
    LIMIT 8
");
$stmt_services->execute([$like, $like, $like, $like, $tagLike]);
foreach ($stmt_services->fetchAll() as $row) {
    $snippet = trim(strip_tags($row['content'] ?? ''));
    if (function_exists('mb_substr')) {
        $snippet = mb_substr($snippet, 0, 130, 'UTF-8');
    } else {
        $snippet = substr($snippet, 0, 130);
    }
    $results[] = [
        'type' => 'Dịch vụ',
        'icon' => 'spa',
        'title' => $row['title'],
        'subtitle' => trim(($row['category_name'] ?: 'Dịch vụ') . ' • ' . $row['user_name']),
        'snippet' => $snippet,
        'url' => 'service_detail.php?id=' . $row['id'],
        'image' => $row['image_url'] ?: '',
        'tags' => $row['tags'] ?: ''
    ];
}

$stmt_people = $pdo->prepare("
    SELECT id, name, email, avatar, role
    FROM users
    WHERE name LIKE ? OR email LIKE ?
    ORDER BY role = 'admin' DESC, reputation DESC, name ASC
    LIMIT 6
");
$stmt_people->execute([$like, $like]);
foreach ($stmt_people->fetchAll() as $row) {
    $results[] = [
        'type' => 'Người dùng',
        'icon' => 'person',
        'title' => $row['name'],
        'subtitle' => $row['role'],
        'snippet' => $row['email'],
        'url' => 'profile.php',
        'image' => $row['avatar'] ?: '',
        'tags' => ''
    ];
}

$stmt_community = $pdo->prepare("
    SELECT d.id, d.title, d.content, d.created_at,
           u.name as user_name,
           c.name as category_name
    FROM discussions d
    JOIN users u ON u.id = d.user_id
    LEFT JOIN categories c ON c.id = d.category_id
    WHERE d.title LIKE ? OR d.content LIKE ? OR u.name LIKE ? OR c.name LIKE ?
    ORDER BY d.created_at DESC
    LIMIT 8
");
$stmt_community->execute([$like, $like, $like, $like]);
foreach ($stmt_community->fetchAll() as $row) {
    $snippet = trim(strip_tags($row['content'] ?? ''));
    if (function_exists('mb_substr')) {
        $snippet = mb_substr($snippet, 0, 130, 'UTF-8');
    } else {
        $snippet = substr($snippet, 0, 130);
    }
    $results[] = [
        'type' => 'Cộng đồng',
        'icon' => 'forum',
        'title' => $row['title'],
        'subtitle' => trim(($row['category_name'] ?: 'Cộng đồng') . ' • ' . $row['user_name']),
        'snippet' => $snippet,
        'url' => 'community.php#post-' . $row['id'],
        'image' => '',
        'tags' => ''
    ];
}

echo json_encode(['ok' => true, 'query' => $q, 'results' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
