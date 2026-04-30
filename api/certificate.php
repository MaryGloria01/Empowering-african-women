<?php
require_once __DIR__ . '/db.php';
cors();

$user = current_user();
if (!$user) {
    debug_log("certificate.php: not authenticated | method=" . ($_SERVER['REQUEST_METHOD'] ?? '?') . " | session_id=" . session_id());
    json_out(['error' => 'Not authenticated'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
debug_log("REQUEST certificate.php | method=$method | user_id={$user['id']}");

// GET — return all certificates for current user
if ($method === 'GET') {
    debug_log("certificate.php GET: fetching certificates | user_id={$user['id']}");
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT course_slug, title, score, issued_at, cert_code FROM certificates WHERE user_id = ? ORDER BY issued_at ASC'
    );
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();
    debug_log("certificate.php GET: returned | user_id={$user['id']} | count=" . count($rows) . " | courses=" . implode(',', array_column($rows, 'course_slug')));

    $certs = array_map(function($r) {
        return [
            'id'       => $r['course_slug'],
            'title'    => $r['title'] ?: $r['course_slug'],
            'date'     => date('j F Y', strtotime($r['issued_at'])),
            'score'    => $r['score'] ?: '',
            'issuedAt' => $r['issued_at'],
            'certCode' => $r['cert_code'],
        ];
    }, $rows);

    json_out(['success' => true, 'certificates' => $certs]);
}

// POST — issue a certificate (idempotent — returns existing cert_code if already issued)
if ($method === 'POST') {
    verify_csrf();
    $data  = get_input();
    $slug  = trim($data['course'] ?? '');
    $title = trim($data['title'] ?? '');
    $score = trim($data['score'] ?? '');

    if (!$slug) {
        debug_log("certificate.php POST: missing slug | user_id={$user['id']}");
        json_out(['error' => 'Course slug required.'], 400);
    }

    // Validate slug against whitelist (most restrictive check)
    if (!in_array($slug, VALID_COURSE_SLUGS)) {
        debug_log("certificate.php POST: invalid slug | user_id={$user['id']} | slug=$slug");
        json_out(['error' => 'Invalid course slug.'], 400);
    }

    debug_log("certificate.php POST: issuing cert | user_id={$user['id']} | course=$slug | title=$title | score=$score");
    $pdo = getDB();

    // Already issued? Return existing cert_code (backfill score if it was previously empty)
    $stmt = $pdo->prepare('SELECT cert_code, issued_at, score FROM certificates WHERE user_id = ? AND course_slug = ?');
    $stmt->execute([$user['id'], $slug]);
    $existing = $stmt->fetch();
    if ($existing) {
        if ($score && (!$existing['score'] || $existing['score'] === '')) {
            $pdo->prepare('UPDATE certificates SET score = ? WHERE user_id = ? AND course_slug = ?')
                ->execute([$score, $user['id'], $slug]);
            debug_log("certificate.php POST: score backfilled | user_id={$user['id']} | course=$slug | score=$score");
        }
        debug_log("certificate.php POST: cert already exists | user_id={$user['id']} | course=$slug | cert_code={$existing['cert_code']}");
        json_out([
            'success'   => true,
            'cert_code' => $existing['cert_code'],
            'issued_at' => $existing['issued_at'],
            'already'   => true,
        ]);
    }

    $certCode = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare(
        'INSERT INTO certificates (user_id, course_slug, title, score, issued_at, cert_code)
         VALUES (?, ?, ?, ?, NOW(), ?)'
    );
    $stmt->execute([
        $user['id'],
        $slug,
        $title ?: $slug,
        $score ?: null,
        $certCode,
    ]);
    debug_log("certificate.php POST: cert issued | user_id={$user['id']} | course=$slug | cert_code=$certCode");

    json_out(['success' => true, 'cert_code' => $certCode, 'already' => false]);
}

debug_log("certificate.php: method not allowed | method=$method | user_id={$user['id']}");
json_out(['error' => 'Method not allowed'], 405);
