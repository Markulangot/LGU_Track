<?php
/**
 * LexTrack Helper Functions
 */

/**
 * Sanitize output for HTML
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format date for display
 */
function format_date($date) {
    return date('M d, Y', strtotime($date));
}

/**
 * Get status badge classes based on status
 */
function get_status_badge_class($status) {
    switch (strtolower($status)) {
        case 'active':
        case 'enacted':
            return 'bg-primary text-on-primary';
        case 'pending':
            return 'bg-surface-container-high text-on-surface';
        case 'repealed':
            return 'bg-error text-on-error';
        case 'archived':
        case 'amended':
            return 'bg-surface-variant text-on-surface-variant';
        case 'draft':
            return 'bg-surface-variant text-on-surface-variant';
        default:
            return 'bg-surface-container text-on-surface-variant';
    }
}

/**
 * Map legacy and system statuses into the new reading-stage categories for display and filtering.
 */
function normalize_reading_status($status) {
    $s = strtolower(trim($status));
    $map = [
        // Treat legacy 'under_review' and 'draft' as the initial "For Reading" stage
        'under_review' => 'for_reading',
        'draft'        => 'for_reading',
        '1st_reading'  => '1st_reading',
        '2nd_reading'  => '2nd_reading',
        '3rd_reading'  => '3rd_reading',
        'active'       => 'approved',
        'approved'     => 'approved',
        'rejected'     => 'rejected',
    ];
    return $map[$s] ?? $s;
}

function reading_label($status) {
    $labels = [
        'for_reading' => 'For Reading',
        '1st_reading' => '1st Reading',
        '2nd_reading' => '2nd Reading',
        '3rd_reading' => '3rd Reading',
        'approved'    => 'Approved',
        'rejected'    => 'Rejected',
    ];
    $n = normalize_reading_status($status);
    return $labels[$n] ?? ucwords(str_replace('_', ' ', $n));
}

/**
 * Rate Limiter
 * Enforces a maximum number of requests per time window for a specific endpoint and IP address.
 */
function check_rate_limit($pdo, $endpoint, $max_attempts = 5, $time_window_minutes = 15) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $time_window_seconds = $time_window_minutes * 60;

    // Clean up old records for this endpoint and IP
    $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND endpoint = ? AND last_attempt < DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->execute([$ip_address, $endpoint, $time_window_seconds]);

    // Check current attempts
    $stmt = $pdo->prepare("SELECT id, attempts FROM rate_limits WHERE ip_address = ? AND endpoint = ?");
    $stmt->execute([$ip_address, $endpoint]);
    $record = $stmt->fetch();

    if ($record) {
        if ($record['attempts'] >= $max_attempts) {
            header('HTTP/1.1 429 Too Many Requests');
            throw new Exception("Rate limit exceeded. Max {$max_attempts} attempts per {$time_window_minutes} minutes.");
        }
        // Increment attempts
        $stmt = $pdo->prepare("UPDATE rate_limits SET attempts = attempts + 1, last_attempt = NOW() WHERE id = ?");
        $stmt->execute([$record['id']]);
    } else {
        // First attempt
        $stmt = $pdo->prepare("INSERT INTO rate_limits (ip_address, endpoint, attempts, first_attempt, last_attempt) VALUES (?, ?, 1, NOW(), NOW())");
        $stmt->execute([$ip_address, $endpoint]);
    }
}
?>
