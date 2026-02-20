<?php
/**
 * Plugin Name: Schema Genie AI
 * Plugin URI:  https://wordpress.org/plugins/schema-genie-ai/
 * Description: AI-powered JSON-LD structured data generator. Automatically creates Article, LegalService, FAQPage, and HowTo schema markup based on your content using Azure OpenAI.
 * Version:     1.1.0
 * Author:      Schema Genie
 * Author URI:  https://wordpress.org/plugins/schema-genie-ai/
 * License:     GPL v2 or later
 * Text Domain: schema-genie-ai
 */

defined('ABSPATH') || exit;

// Plugin constants
define('SCHEMA_GENIE_AI_VERSION', '1.1.0');
define('SCHEMA_GENIE_AI_PATH', plugin_dir_path(__FILE__));
define('SCHEMA_GENIE_AI_URL', plugin_dir_url(__FILE__));

// Load classes
require_once SCHEMA_GENIE_AI_PATH . 'includes/class-settings.php';
require_once SCHEMA_GENIE_AI_PATH . 'includes/class-ai-client.php';
require_once SCHEMA_GENIE_AI_PATH . 'includes/class-schema-template.php';
require_once SCHEMA_GENIE_AI_PATH . 'includes/class-schema-generator.php';
require_once SCHEMA_GENIE_AI_PATH . 'includes/class-meta-box.php';
require_once SCHEMA_GENIE_AI_PATH . 'includes/class-schema-injector.php';

/**
 * Main plugin class — orchestrates all components.
 */
class Schema_Genie_AI_Plugin {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Admin hooks
        if (is_admin()) {
            new Schema_Genie_AI_Settings();
            new Schema_Genie_AI_Meta_Box();

            // AJAX handlers
            add_action('wp_ajax_sgai_generate_schema', [$this, 'ajax_generate_schema']);
            add_action('wp_ajax_sgai_clear_schema', [$this, 'ajax_clear_schema']);
            add_action('wp_ajax_sgai_preview_schema', [$this, 'ajax_preview_schema']);
        }

        // Frontend injection
        new Schema_Genie_AI_Injector();

        // WP-Cron hook for async generation
        add_action('schema_genie_ai_async_generate', [$this, 'async_generate_schema']);

        // Optional: auto-generate on post save
        add_action('save_post', [$this, 'maybe_auto_generate'], 20, 3);
    }

    /**
     * AJAX: Generate schema for a post.
     */
    public function ajax_generate_schema() {
        check_ajax_referer('schema_genie_ai_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'schema-genie-ai')]);
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'schema-genie-ai')]);
        }

        try {
            $generator = new Schema_Genie_AI_Generator();
            $result = $generator->generate($post_id);

            wp_send_json_success([
                'message'   => __('Schema generated successfully!', 'schema-genie-ai'),
                'generated' => get_post_meta($post_id, '_schema_genie_ai_generated', true),
                'preview'   => $this->get_pretty_preview($post_id),
                'status'    => 'success',
            ]);
        } catch (Exception $e) {
            update_post_meta($post_id, '_schema_genie_ai_status', 'error');
            update_post_meta($post_id, '_schema_genie_ai_error', $e->getMessage());

            wp_send_json_error([
                'message' => $e->getMessage(),
                'status'  => 'error',
            ]);
        }
    }

    /**
     * AJAX: Clear schema for a post.
     */
    public function ajax_clear_schema() {
        check_ajax_referer('schema_genie_ai_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'schema-genie-ai')]);
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'schema-genie-ai')]);
        }

        delete_post_meta($post_id, '_schema_genie_ai_data');
        delete_post_meta($post_id, '_schema_genie_ai_generated');
        delete_post_meta($post_id, '_schema_genie_ai_status');
        delete_post_meta($post_id, '_schema_genie_ai_error');
        delete_post_meta($post_id, '_schema_genie_ai_raw');
        delete_post_meta($post_id, '_schema_genie_ai_tokens');
        delete_post_meta($post_id, '_schema_genie_ai_rm_synced');

        // Also clear Rank Math schema if it was synced by us
        if (class_exists('RankMath') || defined('RANK_MATH_VERSION')) {
            delete_post_meta($post_id, 'rank_math_schema');
        }

        wp_send_json_success([
            'message' => __('Schema cleared.', 'schema-genie-ai'),
        ]);
    }

    /**
     * AJAX: Preview final JSON-LD for a post.
     */
    public function ajax_preview_schema() {
        check_ajax_referer('schema_genie_ai_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'schema-genie-ai')]);
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        wp_send_json_success([
            'preview' => $this->get_pretty_preview($post_id),
        ]);
    }

    /**
     * Get pretty-printed JSON preview.
     */
    private function get_pretty_preview(int $post_id): string {
        $data = get_post_meta($post_id, '_schema_genie_ai_data', true);
        if (empty($data)) {
            return '';
        }

        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            return $data;
        }

        $full = [
            '@context' => 'https://schema.org',
            '@graph'   => $decoded,
        ];

        return wp_json_encode($full, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Auto-generate on post save (if enabled in settings).
     */
    public function maybe_auto_generate(int $post_id, WP_Post $post, bool $update) {
        if (wp_is_post_revision($post_id)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        $supported = Schema_Genie_AI_Meta_Box::get_supported_post_types();
        if (!in_array($post->post_type, $supported, true)) return;
        if ($post->post_status !== 'publish') return;

        $auto = get_option('schema_genie_ai_auto_generate', '0');
        if ($auto !== '1') return;

        // Don't re-generate if already exists (use button for re-generation)
        $existing = get_post_meta($post_id, '_schema_genie_ai_data', true);
        if (!empty($existing)) return;

        // Schedule async to avoid blocking the editor
        if (!wp_next_scheduled('schema_genie_ai_async_generate', [$post_id])) {
            wp_schedule_single_event(time() + 10, 'schema_genie_ai_async_generate', [$post_id]);
        }
    }

    /**
     * Async generation via WP-Cron.
     */
    public function async_generate_schema(int $post_id) {
        try {
            $generator = new Schema_Genie_AI_Generator();
            $generator->generate($post_id);
        } catch (Exception $e) {
            update_post_meta($post_id, '_schema_genie_ai_status', 'error');
            update_post_meta($post_id, '_schema_genie_ai_error', $e->getMessage());

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Schema Genie AI] Async generation failed for post ' . $post_id . ': ' . $e->getMessage());
            }
        }
    }
}

// Activation hook
register_activation_hook(__FILE__, function () {
    // Set default options
    $defaults = [
        'schema_genie_ai_provider'          => 'azure',
        'schema_genie_ai_azure_endpoint'    => '',
        'schema_genie_ai_azure_api_version' => '2025-01-01-preview',
        'schema_genie_ai_model'             => 'gpt-4o',
        'schema_genie_ai_max_tokens'        => '2000',
        'schema_genie_ai_temperature'       => '0.1',
        'schema_genie_ai_timeout'           => '45',
        'schema_genie_ai_auto_generate'     => '0',
        'schema_genie_ai_content_limit'     => '4000',
    ];

    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }
});

// Initialize
add_action('plugins_loaded', function () {
    Schema_Genie_AI_Plugin::instance();
});
