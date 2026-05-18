<?php
// Shared helpers for the referral system.
// Used by api/referrals.php, api/auth.php (signup), api/verify_email.php (signup bonus),
// api/yoomoney_callback.php and api/bot/cryptobot_webhook.php (5% lifetime bonus).

if (!function_exists('generate_referral_code')) {
    function generate_referral_code(): string {
        // 8 chars, uppercase letters + digits, no ambiguous (0/O, 1/I/L)
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $code;
    }
}

if (!function_exists('ensure_referral_code')) {
    /** Returns the user's referral code, generating one if missing. */
    function ensure_referral_code(PDO $pdo, int $userId): string {
        $stmt = $pdo->prepare('SELECT referral_code FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $existing = $stmt->fetchColumn();
        if ($existing) return $existing;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = generate_referral_code();
            try {
                $upd = $pdo->prepare('UPDATE users SET referral_code = ? WHERE id = ? AND referral_code IS NULL');
                $upd->execute([$code, $userId]);
                if ($upd->rowCount() > 0) return $code;
                // Already had one (race) — fetch and return
                $stmt->execute([$userId]);
                $existing = $stmt->fetchColumn();
                if ($existing) return $existing;
            } catch (Exception $e) {
                // Unique collision — retry
            }
        }
        throw new RuntimeException('Failed to generate referral code');
    }
}

if (!function_exists('lookup_referrer_by_code')) {
    /** Returns referrer user_id by code, or null if not found / self-ref. */
    function lookup_referrer_by_code(PDO $pdo, string $code, ?int $signupUserId = null): ?int {
        $code = strtoupper(trim($code));
        if ($code === '') return null;
        $stmt = $pdo->prepare('SELECT id FROM users WHERE referral_code = ? LIMIT 1');
        $stmt->execute([$code]);
        $rid = $stmt->fetchColumn();
        if (!$rid) return null;
        if ($signupUserId !== null && (int)$rid === $signupUserId) return null; // can't refer yourself
        return (int)$rid;
    }
}

if (!function_exists('attach_referrer')) {
    /** Sets referrer_id on a newly created user (one-time). */
    function attach_referrer(PDO $pdo, int $userId, int $referrerId): bool {
        $stmt = $pdo->prepare('UPDATE users SET referrer_id = ? WHERE id = ? AND referrer_id IS NULL');
        $stmt->execute([$referrerId, $userId]);
        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('credit_referral_signup_bonus')) {
    /**
     * Credits 50 ₽ to BOTH referrer and referred user, ONCE per user.
     * Triggered after email verification (or first successful signup if no email step).
     * Returns true if bonus was paid this call, false if already paid or no referrer.
     */
    function credit_referral_signup_bonus(PDO $pdo, int $userId): bool {
        $stmt = $pdo->prepare('SELECT referrer_id, referral_signup_bonus_paid FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || empty($row['referrer_id'])) return false;
        if ((int)$row['referral_signup_bonus_paid'] === 1) return false;

        $referrerId = (int)$row['referrer_id'];
        $BONUS = 50.00;

        $pdo->beginTransaction();
        try {
            // Lock and re-check
            $chk = $pdo->prepare('SELECT referral_signup_bonus_paid FROM users WHERE id = ? FOR UPDATE');
            $chk->execute([$userId]);
            if ((int)$chk->fetchColumn() === 1) { $pdo->rollBack(); return false; }

            // Credit referred (the new user)
            $pdo->prepare('UPDATE users SET balance = balance + ?, referral_signup_bonus_paid = 1 WHERE id = ?')
                ->execute([$BONUS, $userId]);
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'referral_bonus', ?, ?)")
                ->execute([$userId, $BONUS, 'Бонус за регистрацию по реферальной ссылке']);

            // Credit referrer
            $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')
                ->execute([$BONUS, $referrerId]);
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'referral_bonus', ?, ?)")
                ->execute([$referrerId, $BONUS, 'Бонус за приглашённого пользователя']);

            // Log in referral_bonuses for both sides
            $pdo->prepare("INSERT INTO referral_bonuses (referrer_id, referred_id, bonus_type, amount, description) VALUES (?, ?, 'signup', ?, ?)")
                ->execute([$referrerId, $userId, $BONUS, 'Регистрация друга']);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }
}

if (!function_exists('credit_referral_topup_bonus')) {
    /**
     * Credits 5% of a top-up amount to the referrer of `userId`.
     * Called from yoomoney_callback.php and cryptobot_webhook.php after a successful top-up.
     * Idempotency: caller is responsible for not calling twice for same source_topup_id (we also
     * deduplicate via UNIQUE-ish check).
     */
    function credit_referral_topup_bonus(PDO $pdo, int $referredUserId, float $topupAmountRub, ?int $sourceTopupId = null): bool {
        $stmt = $pdo->prepare('SELECT referrer_id FROM users WHERE id = ?');
        $stmt->execute([$referredUserId]);
        $referrerId = (int)$stmt->fetchColumn();
        if (!$referrerId) return false;
        if ($topupAmountRub < 0.01) return false;

        // Dedup by source_topup_id
        if ($sourceTopupId !== null) {
            $dup = $pdo->prepare('SELECT id FROM referral_bonuses WHERE source_topup_id = ? AND bonus_type = ? LIMIT 1');
            $dup->execute([$sourceTopupId, 'lifetime']);
            if ($dup->fetchColumn()) return false;
        }

        $bonus = round($topupAmountRub * 0.05, 2);
        if ($bonus < 0.01) return false;

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')
                ->execute([$bonus, $referrerId]);
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'referral_bonus', ?, ?)")
                ->execute([$referrerId, $bonus, '5% с пополнения друга (' . number_format($topupAmountRub, 2, '.', '') . ' ₽)']);
            $pdo->prepare("INSERT INTO referral_bonuses (referrer_id, referred_id, bonus_type, amount, source_topup_id, description) VALUES (?, ?, 'lifetime', ?, ?, ?)")
                ->execute([$referrerId, $referredUserId, $bonus, $sourceTopupId, 'Пополнение друга: ' . number_format($topupAmountRub, 2, '.', '') . ' ₽']);
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }
}
