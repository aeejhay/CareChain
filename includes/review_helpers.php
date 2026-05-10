<?php
/**
 * CareChain review helpers: star display and denormalized rating totals on profiles.
 */

if (!function_exists('carechain_stars_unicode')) {
    /**
     * Build a simple ★/☆ string from an average (0–5).
     */
    function carechain_stars_unicode($avg, $max = 5)
    {
        $avg = (float) $avg;
        if ($avg <= 0) {
            return str_repeat('☆', $max);
        }
        $filled = (int) max(0, min($max, (int) round($avg)));
        return str_repeat('★', $filled) . str_repeat('☆', $max - $filled);
    }

    /**
     * Recompute AVG/COUNT from reviews and store on worker_profiles or facility_profiles.
     */
    function carechain_refresh_ratings(PDO $pdo, $revieweeUserId)
    {
        $revieweeUserId = (int) $revieweeUserId;
        if ($revieweeUserId < 1) {
            return;
        }
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$revieweeUserId]);
        $role = $stmt->fetchColumn();
        if (!$role || $role === 'admin') {
            return;
        }
        $stmt = $pdo->prepare('SELECT AVG(rating) AS avg_r, COUNT(*) AS cnt FROM reviews WHERE reviewee_id = ?');
        $stmt->execute([$revieweeUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $avg = $row['avg_r'] !== null ? round((float) $row['avg_r'], 2) : 0.00;
        $cnt = (int) ($row['cnt'] ?? 0);
        if ($role === 'worker') {
            $u = $pdo->prepare('UPDATE worker_profiles SET rating = ?, total_reviews = ? WHERE user_id = ?');
            $u->execute([$avg, $cnt, $revieweeUserId]);
        } elseif ($role === 'facility') {
            $u = $pdo->prepare('UPDATE facility_profiles SET rating = ?, total_reviews = ? WHERE user_id = ?');
            $u->execute([$avg, $cnt, $revieweeUserId]);
        }
    }
}
