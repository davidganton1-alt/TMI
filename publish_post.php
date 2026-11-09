<?php
/**
 * The Miner Island - Blog Auto-Publisher
 * Inserts a new "Factory Update" blog post into oc_factory_update.
 * SECURITY: protected by a secret key. Only calls with ?key=SECRET_KEY insert.
 * Usage (from the daily agent):
 *   POST publish_post.php?key=SECRET_KEY
 *   body (JSON or form): title, slug, excerpt, body_html, meta_title, meta_description, category
 *
 * Deploy to: https://theminerisland.com/publish_post.php
 * Set SECRET_KEY to a random strong string (see D:\software-house\secrets\).
 */

// ---- Hardcode the key (set to a strong random value) ----
$SECRET_KEY = 'TMI_7Qx2kL9mN4vR8sT3zB6wY1_Pub';

// ---- Load OpenCart DB credentials from config.php ----
$cfg = dirname(__FILE__) . '/config.php';
if (file_exists($cfg)) {
    require_once $cfg;
} else {
    // fallback: define explicitly if config.php is elsewhere
    // define('DB_HOSTNAME','localhost'); define('DB_USERNAME','...'); define('DB_PASSWORD','...'); define('DB_DATABASE','...');
}

// ---- Auth ----
if (!isset($_GET['key']) || $_GET['key'] !== $SECRET_KEY) {
    http_response_code(403);
    die(json_encode(['ok' => false, 'error' => 'forbidden']));
}

// ---- Read input (JSON or POST) ----
$in = json_decode(file_get_contents('php://input'), true);
if (!$in || !is_array($in)) { $in = $_POST; }
$title  = isset($in['title'])  ? trim($in['title'])  : '';
$slug   = isset($in['slug'])   ? trim($in['slug'])   : '';
$excerpt= isset($in['excerpt'])? trim($in['excerpt']): '';
$body   = isset($in['body_html'])? $in['body_html'] : '';
$mt     = isset($in['meta_title'])? trim($in['meta_title']) : '';
$md     = isset($in['meta_description'])? trim($in['meta_description']) : '';
$cat    = isset($in['category'])? trim($in['category']) : '';
$img    = isset($in['hero_image_url'])? trim($in['hero_image_url']) : '';
$seoFor = isset($in['seo_for_post_id'])? (int)$in['seo_for_post_id'] : 0;
$updHero = isset($in['update_hero_for_post_id'])? (int)$in['update_hero_for_post_id'] : 0;
$updBody = isset($in['update_body_for_post_id'])? (int)$in['update_body_for_post_id'] : 0;

if ($updBody > 0) {
    // Replace the body_html (without any embedded hero img) for an existing post
    $conn = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    if ($conn->connect_error) { die(json_encode(['ok'=>false,'error'=>$conn->connect_error])); }
    $stmt = $conn->prepare("UPDATE oc_factory_update SET body_html=? WHERE factory_post_id=?");
    $stmt->bind_param('si', $body, $updBody);
    if ($stmt->execute()) {
        echo json_encode(['ok'=>true,'body_updated'=>true,'factory_post_id'=>$updBody,'body_chars'=>strlen($body)]);
    } else { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$stmt->error]); }
    $stmt->close(); $conn->close(); exit;
}

if ($updHero > 0 && $img !== '') {
    // Update hero_image_url (local path relative to image/) for an existing post
    $conn = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    if ($conn->connect_error) { die(json_encode(['ok'=>false,'error'=>$conn->connect_error])); }
    $stmt = $conn->prepare("UPDATE oc_factory_update SET hero_image_url=? WHERE factory_post_id=?");
    $stmt->bind_param('si', $img, $updHero);
    if ($stmt->execute()) {
        echo json_encode(['ok'=>true,'hero_updated'=>true,'factory_post_id'=>$updHero,'hero_image_url'=>$img]);
    } else { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$stmt->error]); }
    $stmt->close(); $conn->close(); exit;
}

if ($seoFor > 0) {
    // Just (re)register the SEO url for an existing post
    $conn = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    if ($conn->connect_error) { die(json_encode(['ok'=>false,'error'=>$conn->connect_error])); }
    $conn->query("DELETE FROM oc_seo_url WHERE query='factory_post_id=$seoFor'");
    $stmt = $conn->prepare("INSERT INTO oc_seo_url (store_id, language_id, query, keyword) VALUES (0,1,?,?)");
    $q = 'factory_post_id='.$seoFor; $stmt->bind_param('ss', $q, $slug);
    if ($stmt->execute()) {
        echo json_encode(['ok'=>true,'seo_url_registered'=>true,'slug'=>$slug,'url'=>'https://theminerisland.com/'.$slug]);
    } else { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$stmt->error]); }
    $stmt->close(); $conn->close(); exit;
}
// if a hero image is a full URL (not a local path), prepend a cover <img> to the body.
// Local paths (relative to image/) are rendered by the template's hero block instead.
if ($img !== '' && strpos($img, 'http') === 0 && strpos($body, $img) === false) {
    $body = '<img src="'.htmlspecialchars($img, ENT_QUOTES).'" alt="'.htmlspecialchars($title, ENT_QUOTES).'" style="max-width:100%;height:auto;border-radius:8px;margin-bottom:16px;" /><br />'.$body;
}
if ($slug === '') {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
    $slug = trim($slug, '-');
}

// ---- Insert ----
$conn = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
if ($conn->connect_error) { die(json_encode(['ok'=>false,'error'=>$conn->connect_error])); }

$id = (int)$conn->query("SELECT COALESCE(MAX(factory_post_id),0)+1 AS n FROM oc_factory_update")->fetch_assoc()['n'];

$stmt = $conn->prepare(
  "INSERT INTO oc_factory_update
     (factory_post_id, slug, title, excerpt, body_html, hero_image_url, video_embed,
      published_at, status, sort_order, meta_title, meta_description, author_name, category)
   VALUES (?,?,?,?,?,?, '', NOW(), 1, 0, ?,?, 'The Miner Island team', ?)"
);
$stmt->bind_param('issssssss', $id, $slug, $title, $excerpt, $body, $img, $mt, $md, $cat);
if ($stmt->execute()) {
    // register the SEO url so the slug routes to the post
    $conn->query("DELETE FROM oc_seo_url WHERE query='factory_post_id=$id'");
    $s2 = $conn->prepare("INSERT INTO oc_seo_url (store_id, language_id, query, keyword) VALUES (0,1,?,?)");
    $q = 'factory_post_id='.$id; $s2->bind_param('ss', $q, $slug); $s2->execute(); $s2->close();
    echo json_encode(['ok' => true, 'factory_post_id' => $id, 'slug' => $slug,
                      'url' => 'https://theminerisland.com/' . $slug]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $stmt->error]);
}
$stmt->close(); $conn->close();
