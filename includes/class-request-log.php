<?php
/**
 * AI Request Log — tracks every AI API call for transparency.
 *
 * Creates a custom DB table and provides CRUD methods for logging
 * schema generation requests.
 */
defined('ABSPATH') || exit;

class Schema_Genie_AI_Request_Log {

    /**
     * Get the table name (with WP prefix).
     */
    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'sgai_request_log';
    }

    /**
     * Create the log table. Called on plugin activation.
     */
    public static function create_table(): void {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            post_title VARCHAR(255) NOT NULL DEFAULT '',
            post_type VARCHAR(50) NOT NULL DEFAULT '',
            trigger_type VARCHAR(20) NOT NULL DEFAULT 'manual',
            triggered_by VARCHAR(100) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'started',
            tokens_prompt INT UNSIGNED NOT NULL DEFAULT 0,
            tokens_completion INT UNSIGNED NOT NULL DEFAULT 0,
            tokens_total INT UNSIGNED NOT NULL DEFAULT 0,
            error_message TEXT NULL,
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_status (status),
            KEY idx_trigger_type (trigger_type),
            KEY idx_created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Insert a new log entry (status = 'started').
     * Returns the log row ID so it can be updated later.
     *
     * @param int    $post_id
     * @param string $trigger_type  manual | bulk | auto_save | cron
     * @return int   The inserted row ID.
     */
    public static function log_start(int $post_id, string $trigger_type = 'manual'): int {
        global $wpdb;

        $post  = get_post($post_id);
        $user  = wp_get_current_user();

        $wpdb->insert(self::table_name(), [
            'post_id'      => $post_id,
            'post_title'   => $post ? get_the_title($post_id) : '(deleted)',
            'post_type'    => $post ? $post->post_type : '',
            'trigger_type' => sanitize_key($trigger_type),
            'triggered_by' => $user && $user->ID ? $user->user_login : 'system',
            'status'       => 'started',
            'created_at'   => current_time('mysql'),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    /**
     * Update a log entry to 'success' with token usage and duration.
     *
     * @param int   $log_id
     * @param array $usage      ['prompt_tokens' => int, 'completion_tokens' => int, 'total_tokens' => int]
     * @param int   $duration_ms
     */
    public static function log_success(int $log_id, array $usage = [], int $duration_ms = 0): void {
        global $wpdb;

        $wpdb->update(self::table_name(), [
            'status'            => 'success',
            'tokens_prompt'     => isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : 0,
            'tokens_completion' => isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : 0,
            'tokens_total'      => isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : 0,
            'duration_ms'       => $duration_ms,
        ], ['id' => $log_id], ['%s', '%d', '%d', '%d', '%d'], ['%d']);
    }

    /**
     * Update a log entry to 'error' with error message.
     *
     * @param int    $log_id
     * @param string $error_message
     * @param int    $duration_ms
     */
    public static function log_error(int $log_id, string $error_message, int $duration_ms = 0): void {
        global $wpdb;

        $wpdb->update(self::table_name(), [
            'status'        => 'error',
            'error_message' => sanitize_textarea_field($error_message),
            'duration_ms'   => $duration_ms,
        ], ['id' => $log_id], ['%s', '%s', '%d'], ['%d']);
    }

    /**
     * Get paginated log entries.
     *
     * @param int    $page
     * @param int    $per_page
     * @param string $status_filter  '' for all, or 'success'/'error'
     * @param string $trigger_filter '' for all, or 'manual'/'bulk'/'cron'/'auto_save'
     * @return array
     */
    public static function get_logs(int $page = 1, int $per_page = 20, string $status_filter = '', string $trigger_filter = ''): array {
        global $wpdb;
        $table  = self::table_name();
        $offset = ($page - 1) * $per_page;

        $where = '1=1';
        $params = [];

        if ($status_filter) {
            $where .= ' AND status = %s';
            $params[] = $status_filter;
        }
        if ($trigger_filter) {
            $where .= ' AND trigger_type = %s';
            $params[] = $trigger_filter;
        }

        $params[] = $per_page;
        $params[] = $offset;

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";

        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }

    /**
     * Get total count for pagination.
     *
     * @param string $status_filter
     * @param string $trigger_filter
     * @return int
     */
    public static function get_total_count(string $status_filter = '', string $trigger_filter = ''): int {
        global $wpdb;
        $table = self::table_name();

        $where = '1=1';
        $params = [];

        if ($status_filter) {
            $where .= ' AND status = %s';
            $params[] = $status_filter;
        }
        if ($trigger_filter) {
            $where .= ' AND trigger_type = %s';
            $params[] = $trigger_filter;
        }

        if (empty($params)) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$where}",
            $params
        ));
    }

    /**
     * Get summary statistics.
     *
     * @return array ['total' => int, 'success' => int, 'error' => int, 'total_tokens' => int]
     */
    public static function get_stats(): array {
        global $wpdb;
        $table = self::table_name();

        $total   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $success = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'success'");
        $errors  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'error'");
        $tokens  = (int) $wpdb->get_var("SELECT COALESCE(SUM(tokens_total), 0) FROM {$table}");

        return [
            'total'        => $total,
            'success'      => $success,
            'error'        => $errors,
            'total_tokens' => $tokens,
        ];
    }

    /**
     * Cleanup logs older than N days.
     *
     * @param int $days  Default 90 days.
     * @return int       Number of rows deleted.
     */
    public static function cleanup(int $days = 90): int {
        global $wpdb;
        $table = self::table_name();

        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}
