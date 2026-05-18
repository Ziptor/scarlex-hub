<?php
// Generate unique 5-digit public_id
function generate_public_id($pdo) {
    for ($i = 0; $i < 20; $i++) {
        $pid = (string)random_int(10000, 99999);
        $stmt = $pdo->prepare('SELECT id FROM users WHERE public_id = ?');
        $stmt->execute([$pid]);
        if (!$stmt->fetch()) return $pid;
    }
    // Fallback: 6 digits if many collisions
    return (string)random_int(100000, 999999);
}

/**
 * Validate a single IPv4 address or IPv4 CIDR (e.g. "1.2.3.4" or "10.0.0.0/8").
 * Rejects IPv6 and out-of-range prefixes.
 */
function validate_ipv4_cidr(string $s): bool {
    $s = trim($s);
    if ($s === '') return false;
    if (strpos($s, '/') === false) {
        return filter_var($s, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }
    [$ip, $prefix] = explode('/', $s, 2);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return false;
    return ctype_digit($prefix) && (int)$prefix >= 0 && (int)$prefix <= 32;
}

/**
 * Lightweight user-agent → human label. Returns e.g. "Chrome on Windows".
 * Best-effort only; full parsing belongs in a library.
 */
function parse_user_agent(?string $ua): string {
    if (!$ua) return 'Неизвестное устройство';
    $browser = 'Браузер';
    if (stripos($ua, 'Edg/') !== false)              $browser = 'Edge';
    elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) $browser = 'Opera';
    elseif (stripos($ua, 'Firefox') !== false)        $browser = 'Firefox';
    elseif (stripos($ua, 'Chrome') !== false)         $browser = 'Chrome';
    elseif (stripos($ua, 'Safari') !== false)         $browser = 'Safari';

    $os = 'устройство';
    if (stripos($ua, 'Windows NT 11') !== false)       $os = 'Windows 11';
    elseif (stripos($ua, 'Windows NT 10') !== false)   $os = 'Windows 10';
    elseif (stripos($ua, 'Windows') !== false)         $os = 'Windows';
    elseif (stripos($ua, 'iPhone') !== false)          $os = 'iPhone';
    elseif (stripos($ua, 'iPad') !== false)            $os = 'iPad';
    elseif (stripos($ua, 'Android') !== false)         $os = 'Android';
    elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) $os = 'macOS';
    elseif (stripos($ua, 'Linux') !== false)           $os = 'Linux';

    return $browser . ' на ' . $os;
}

/**
 * Extract real client IP, honoring X-Forwarded-For when present.
 */
function client_ip(): string {
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff !== '') {
        $first = trim(explode(',', $xff)[0]);
        if ($first !== '') return $first;
    }
    return $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
}
