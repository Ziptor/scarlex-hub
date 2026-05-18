<?php
// Helper: one-time link tokens for cross-channel auth (site <-> Telegram bot).
// Usage:
//   require_once __DIR__ . '/_link_tokens.php';
//   $token = issue_link_token($pdo, $userId, 'site_login');     // 10 min TTL
//   $userId = consume_link_token($pdo, $token, 'site_login');   // null on invalid/expired/used

if (!function_exists('issue_link_token')) {
    function issue_link_token(\PDO $pdo, int $userId, string $kind, int $ttlSeconds = 600): string {
        $token = bin2hex(random_bytes(24)); // 48 hex chars
        $pdo->prepare(
            "INSERT INTO link_tokens (token, user_id, kind, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))"
        )->execute([$token, $userId, $kind, $ttlSeconds]);
        // Best-effort cleanup of stale rows (older than 1 day)
        try { $pdo->exec("DELETE FROM link_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)"); } catch (\Throwable $e) {}
        return $token;
    }
}

if (!function_exists('consume_link_token')) {
    function consume_link_token(\PDO $pdo, string $token, string $kind): ?int {
        $token = trim($token);
        if ($token === '') return null;
        $stmt = $pdo->prepare(
            "SELECT user_id, expires_at, used_at FROM link_tokens
             WHERE token = ? AND kind = ? LIMIT 1"
        );
        $stmt->execute([$token, $kind]);
        $row = $stmt->fetch();
        if (!$row) return null;
        if (!empty($row['used_at'])) return null;
        if (strtotime((string)$row['expires_at']) < time()) return null;
        $pdo->prepare("UPDATE link_tokens SET used_at = NOW() WHERE token = ?")->execute([$token]);
        return (int)$row['user_id'];
    }
}
