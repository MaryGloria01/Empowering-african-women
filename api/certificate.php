<?php
require_once __DIR__ . '/db.php';
cors();

$user = current_user();
if (!$user) json_out(['error' => 'Not authenticated'], 401);

$method = $_SERVER['REQUEST_METHOD'];

// GET — return all certificates for current user
if ($method === 'GET') {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT course_slug, title, score, issued_at, cert_code FROM certificates WHERE user_id = ? ORDER BY issued_at ASC'
    );
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();

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
    $data  = get_input();
    $slug  = trim($data['course'] ?? '');
    $title = trim($data['title'] ?? '');
    $score = trim($data['score'] ?? '');

    if (!$slug) json_out(['error' => 'Course slug required.'], 400);

    // Validate slug is alphanumeric with hyphens only
    if (!preg_match('/^[a-z0-9\-]{1,80}$/', $slug)) {
        json_out(['error' => 'Invalid course slug.'], 400);
    }

    $pdo = getDB();

    // Already issued? Return existing cert_code
    $stmt = $pdo->prepare('SELECT cert_code, issued_at FROM certificates WHERE user_id = ? AND course_slug = ?');
    $stmt->execute([$user['id'], $slug]);
    $existing = $stmt->fetch();
    if ($existing) {
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

    json_out(['success' => true, 'cert_code' => $certCode, 'already' => false]);
}

json_out(['error' => 'Method not allowed'], 405);
