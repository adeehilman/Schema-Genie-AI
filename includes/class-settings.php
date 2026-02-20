<?php
/**
 * Settings page: API key storage (encrypted), Azure endpoint, model settings.
 */
defined('ABSPATH') || exit;

class Schema_Genie_AI_Settings {

    const PAGE_SLUG = 'schema-genie-ai-settings';
    const OPTION_GROUP = 'schema_genie_ai_options';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add settings page under Settings menu.
     */
    public function add_menu_page() {
        add_options_page(
            __('Schema Genie AI', 'schema-genie-ai'),
            __('Schema Genie AI', 'schema-genie-ai'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Register all settings fields.
     */
    public function register_settings() {
        // Section: Azure OpenAI
        add_settings_section(
            'schema_genie_ai_azure',
            __('Azure OpenAI Configuration', 'schema-genie-ai'),
            function () {
                echo '<p>' . esc_html__('Configure your Azure OpenAI credentials. The API key is encrypted before storage.', 'schema-genie-ai') . '</p>';
            },
            self::PAGE_SLUG
        );

        // API Key (encrypted)
        register_setting(self::OPTION_GROUP, 'schema_genie_ai_api_key', [
            'sanitize_callback' => [$this, 'encrypt_api_key'],
        ]);
        add_settings_field(
            'schema_genie_ai_api_key',
            __('API Key', 'schema-genie-ai'),
            [$this, 'render_api_key_field'],
            self::PAGE_SLUG,
            'schema_genie_ai_azure'
        );

        // Azure Endpoint
        register_setting(self::OPTION_GROUP, 'schema_genie_ai_azure_endpoint', [
            'sanitize_callback' => 'esc_url_raw',
        ]);
        add_settings_field(
            'schema_genie_ai_azure_endpoint',
            __('Azure Endpoint', 'schema-genie-ai'),
            function () {
                $val = get_option('schema_genie_ai_azure_endpoint', '');
                echo '<input type="url" name="schema_genie_ai_azure_endpoint" value="' . esc_attr($val) . '" class="regular-text" />';
            },
            self::PAGE_SLUG,
            'schema_genie_ai_azure'
        );

        // API Version
        register_setting(self::OPTION_GROUP, 'schema_genie_ai_azure_api_version', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        add_settings_field(
            'schema_genie_ai_azure_api_version',
            __('API Version', 'schema-genie-ai'),
            function () {
                $val = get_option('schema_genie_ai_azure_api_version', '2025-01-01-preview');
                echo '<input type="text" name="schema_genie_ai_azure_api_version" value="' . esc_attr($val) . '" class="regular-text" />';
            },
            self::PAGE_SLUG,
            'schema_genie_ai_azure'
        );

        // Model / Deployment Name
        register_setting(self::OPTION_GROUP, 'schema_genie_ai_model', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        add_settings_field(
            'schema_genie_ai_model',
            __('Model / Deployment Name', 'schema-genie-ai'),
            function () {
                $val = get_option('schema_genie_ai_model', 'gpt-4o');
                echo '<input type="text" name="schema_genie_ai_model" value="' . esc_attr($val) . '" class="regular-text" />';
                echo '<p class="description">' . esc_html__('Azure deployment name, e.g., gpt-4o or gpt-4o-mini', 'schema-genie-ai') . '</p>';
            },
            self::PAGE_SLUG,
            'schema_genie_ai_azure'
        );

        // Section: Generation Settings
        add_settings_section(
            'schema_genie_ai_generation',
            __('Generation Settings', 'schema-genie-ai'),
            null,
            self::PAGE_SLUG
        );

        // Temperature
        register_setting(self::OPTION_GROUP, 'schema_genie_ai_temperature', [
            'sanitize_callback' => function ($val) { return max(0, min(2, floatval($val))); },
        ]);
        add_settings_field(
            'schema_genie_ai_temperature',
            __('Temperature', 'schema-genie-ai'),
            function () {
                $val = get_option('schema_genie_ai_temperature', '0.1');
                echo '<input type="number" name="schema_genie_ai_temperature" value="' . esc_attr($val) . '" min="0" max="2" step="0.1" class="small-text" />';
                echo '<p class="description">' . esc_html__('Lower = more deterministic. Recommended: 0.1 for schemas.', 'schema-genie-ai') . '</p>';
            },
            self::PAGE_SLUG,
            'schema_genie_ai_generation'
        );

        // Max Tokens
        register_setting(self::OPTION_GROUP, 'schema_genie_ai_max_tokens', [
            'sanitize_callback' => function ($val) { return max(500, min(8000, intval($val))); },
        ]);
        add_settings_field(
            'schema_genie_ai_max_tokens',
            __('Max Tokens', 'schema-genie-ai'),
            function () {
                $val = get_option('schema_genie_ai_max_tokens', '2000');
                echo '<input type="number" name="schema_genie_ai_max_tokens" value="' . esc_attr($val) . '" min="500" max="8000" step="100" class="small-text" />';
                echo '<p class="description">' . esc_html__('Max response tokens. 2000 is usually enough for schemas.', 'schema-genie-ai') . '</p>';
            },
            self::PAGE_SLUG,
            'schema_genie_ai_generation'
        );

        // Timeout
        register_setting(self::OPTION_GROUP, 'schema_genie_ai_timeout', [
            'sanitize_callback' => function ($val) { return max(10, min(120, intval($val))); },
        ]);
        add_settings_field(
            'schema_genie_ai_timeout',
            __('Request Timeout (seconds)', 'schema-genie-ai'),
            function () {
                $val = get_option('schema_genie_ai_timeout', '45');
                echo '<input type="number" name="schema_genie_ai_timeout" value="' . esc_attr($val) . '" min="10" max="120" step="5" class="small-text" />';
            },
            self::PAGE_SLUG,
            'schema_genie_ai_generation'
        );

        // Content limit
        register_setting(self::OPTION_GROUP, 'schema_genie_ai_content_limit', [
            'sanitize_callback' => function ($val) { return max(1000, min(10000, intval($val))); },
        ]);
        add_settings_field(
            'schema_genie_ai_content_limit',
            __('Content Character Limit', 'schema-genie-ai'),
            function () {
                $val = get_option('schema_genie_ai_content_limit', '4000');
                echo '<input type="number" name="schema_genie_ai_content_limit" value="' . esc_attr($val) . '" min="1000" max="10000" step="500" class="small-text" />';
                echo '<p class="description">' . esc_html__('How many characters of article content to send to AI. Higher = more accurate but costs more tokens.', 'schema-genie-ai') . '</p>';
            },
            self::PAGE_SLUG,
            'schema_genie_ai_generation'
        );

        // Auto generate
        register_setting(self::OPTION_GROUP, 'schema_genie_ai_auto_generate', [
            'sanitize_callback' => function ($val) { return $val ? '1' : '0'; },
        ]);
        add_settings_field(
            'schema_genie_ai_auto_generate',
            __('Auto-generate on Publish', 'schema-genie-ai'),
            function () {
                $val = get_option('schema_genie_ai_auto_generate', '0');
                echo '<label><input type="checkbox" name="schema_genie_ai_auto_generate" value="1" ' . checked($val, '1', false) . ' /> ';
                echo esc_html__('Automatically generate schema when a new post is published (first time only, async).', 'schema-genie-ai');
                echo '</label>';
            },
            self::PAGE_SLUG,
            'schema_genie_ai_generation'
        );
    }

    /**
     * Render the API key field (masked display).
     */
    public function render_api_key_field() {
        $encrypted = get_option('schema_genie_ai_api_key', '');
        $has_key = !empty($encrypted);

        if ($has_key) {
            echo '<input type="password" name="schema_genie_ai_api_key" value="" class="regular-text" placeholder="' . esc_attr__('••••••• (key saved, enter new to replace)', 'schema-genie-ai') . '" />';
            echo '<p class="description" style="color: green;">✓ ' . esc_html__('API key is stored (encrypted).', 'schema-genie-ai') . '</p>';
        } else {
            echo '<input type="password" name="schema_genie_ai_api_key" value="" class="regular-text" placeholder="' . esc_attr__('Enter your Azure OpenAI API key', 'schema-genie-ai') . '" />';
            echo '<p class="description" style="color: red;">✗ ' . esc_html__('No API key configured.', 'schema-genie-ai') . '</p>';
        }
        echo '<br><button type="button" id="sgai-test-connection" class="button button-small" style="margin-top: 5px;">'
            . esc_html__('Test Connection', 'schema-genie-ai') . '</button>';
        echo '<span id="sgai-test-result" style="margin-left: 10px;"></span>';
    }

    /**
     * Encrypt the API key before storing to wp_options.
     */
    public function encrypt_api_key($new_key) {
        if (empty($new_key)) {
            return get_option('schema_genie_ai_api_key', '');
        }
        return self::encrypt($new_key);
    }

    /**
     * Encrypt a string using AES-256-CBC with WordPress salts.
     */
    public static function encrypt(string $plaintext): string {
        $key = hash('sha256', wp_salt('auth'), true);
        $iv  = substr(hash('sha256', wp_salt('secure_auth'), true), 0, 16);
        $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($encrypted);
    }

    /**
     * Decrypt the API key from wp_options.
     */
    public static function decrypt(string $encrypted): string {
        if (empty($encrypted)) return '';
        $key = hash('sha256', wp_salt('auth'), true);
        $iv  = substr(hash('sha256', wp_salt('secure_auth'), true), 0, 16);
        $decoded = base64_decode($encrypted);
        $decrypted = openssl_decrypt($decoded, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) return '';
        return $decrypted;
    }

    /**
     * Get the decrypted API key.
     */
    public static function get_api_key(): string {
        $encrypted = get_option('schema_genie_ai_api_key', '');
        return self::decrypt($encrypted);
    }

    /**
     * Render the settings page.
     */
    public function render_page() {
        if (!current_user_can('manage_options')) return;

        global $wpdb;
        $total_schemas = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_schema_genie_ai_status' AND meta_value = 'success'"
        );
        $total_errors = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_schema_genie_ai_status' AND meta_value = 'error'"
        );
        $total_posts = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'"
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Schema Genie AI Settings', 'schema-genie-ai'); ?></h1>

            <!-- Stats -->
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px 20px; margin-bottom: 20px; display: flex; gap: 30px;">
                <div>
                    <strong style="font-size: 24px; color: #2271b1;"><?php echo esc_html($total_schemas); ?></strong>
                    <br><span style="color: #666;"><?php esc_html_e('Schemas Generated', 'schema-genie-ai'); ?></span>
                </div>
                <div>
                    <strong style="font-size: 24px; color: #d63638;"><?php echo esc_html($total_errors); ?></strong>
                    <br><span style="color: #666;"><?php esc_html_e('Errors', 'schema-genie-ai'); ?></span>
                </div>
                <div>
                    <strong style="font-size: 24px; color: #666;"><?php echo esc_html($total_posts - $total_schemas); ?></strong>
                    <br><span style="color: #666;"><?php esc_html_e('Posts Without Schema', 'schema-genie-ai'); ?></span>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button(__('Save Settings', 'schema-genie-ai'));
                ?>
            </form>

            <!-- Bulk Generation Tool -->
            <hr>
            <h2><?php esc_html_e('Bulk Schema Generation', 'schema-genie-ai'); ?></h2>
            <p><?php esc_html_e('Generate schemas for all published posts that do not have one yet.', 'schema-genie-ai'); ?></p>
            <button type="button" id="sgai-bulk-generate" class="button button-secondary">
                <?php esc_html_e('Generate All Missing Schemas', 'schema-genie-ai'); ?>
            </button>
            <div id="sgai-bulk-progress" style="margin-top: 10px; display: none;">
                <div style="background: #eee; height: 24px; border-radius: 4px; overflow: hidden;">
                    <div id="sgai-bulk-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                </div>
                <p id="sgai-bulk-status"></p>
            </div>

            <script>
            jQuery(function($) {
                $('#sgai-bulk-generate').on('click', function() {
                    if (!confirm('<?php echo esc_js(__('This will generate schemas for all posts without one. Each call uses API tokens. Continue?', 'schema-genie-ai')); ?>')) return;

                    var $btn = $(this).prop('disabled', true);
                    var $progress = $('#sgai-bulk-progress').show();
                    var $bar = $('#sgai-bulk-bar');
                    var $status = $('#sgai-bulk-status');

                    $.post(ajaxurl, {
                        action: 'sgai_bulk_get_posts',
                        nonce: '<?php echo wp_create_nonce('schema_genie_ai_nonce'); ?>'
                    }, function(response) {
                        if (!response.success || !response.data.post_ids.length) {
                            $status.text('No posts need schema generation.');
                            $btn.prop('disabled', false);
                            return;
                        }

                        var ids = response.data.post_ids;
                        var total = ids.length;
                        var done = 0;
                        var errors = 0;

                        function processNext() {
                            if (ids.length === 0) {
                                $status.text('Done! Generated: ' + (done - errors) + ', Errors: ' + errors);
                                $btn.prop('disabled', false);
                                return;
                            }
                            var id = ids.shift();
                            $status.text('Processing post ' + (done + 1) + ' of ' + total + '...');
                            $.post(ajaxurl, {
                                action: 'sgai_generate_schema',
                                post_id: id,
                                nonce: '<?php echo wp_create_nonce('schema_genie_ai_nonce'); ?>'
                            }, function(r) {
                                done++;
                                if (!r.success) errors++;
                                $bar.css('width', ((done / total) * 100) + '%');
                                setTimeout(processNext, 3000);
                            }).fail(function() {
                                done++;
                                errors++;
                                $bar.css('width', ((done / total) * 100) + '%');
                                setTimeout(processNext, 3000);
                            });
                        }
                        processNext();
                    });
                });
            });
            </script>

            <!-- Test Connection Script -->
            <script>
            jQuery(function($) {
                var $saveBtn = $('input[type="submit"]');
                var $apiKeyInput = $('input[name="schema_genie_ai_api_key"]');
                var hasStoredKey = <?php echo json_encode(!empty(get_option('schema_genie_ai_api_key', ''))); ?>;

                if (!hasStoredKey && $apiKeyInput.val().trim() === '') {
                    $saveBtn.prop('disabled', true).css('opacity', '0.5');
                }

                $apiKeyInput.on('input', function() {
                    if ($(this).val().trim() !== '' || hasStoredKey) {
                        $saveBtn.prop('disabled', false).css('opacity', '1');
                    } else {
                        $saveBtn.prop('disabled', true).css('opacity', '0.5');
                    }
                });

                $('#sgai-test-connection').on('click', function() {
                    var $btn = $(this).prop('disabled', true);
                    var $result = $('#sgai-test-result').text('Testing...').css('color', '#666');
                    var rawKey = $apiKeyInput.val().trim();

                    if (!rawKey) {
                        $result.text('\u2717 Please enter an API key first.').css('color', '#d63638');
                        $btn.prop('disabled', false);
                        return;
                    }

                    $.post(ajaxurl, {
                        action: 'sgai_test_connection',
                        nonce: '<?php echo wp_create_nonce('schema_genie_ai_nonce'); ?>',
                        raw_key: rawKey
                    }, function(response) {
                        $btn.prop('disabled', false);
                        if (response.success) {
                            $result.text('\u2713 ' + response.data.message).css('color', '#00a32a');
                            $saveBtn.prop('disabled', false).css('opacity', '1');
                        } else {
                            var msg = response.data.message;
                            if (response.data.url) msg += ' | URL: ' + response.data.url;
                            $result.text('\u2717 ' + msg).css('color', '#d63638');
                        }
                    }).fail(function() {
                        $btn.prop('disabled', false);
                        $result.text('\u2717 Request failed').css('color', '#d63638');
                    });
                });
            });
            </script>
        </div>
        <?php
    }
}

// AJAX handler for bulk: get posts without schema
add_action('wp_ajax_sgai_bulk_get_posts', function () {
    check_ajax_referer('schema_genie_ai_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();

    global $wpdb;
    $post_ids = $wpdb->get_col("
        SELECT p.ID FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_schema_genie_ai_status'
        WHERE p.post_type = 'post'
        AND p.post_status = 'publish'
        AND (pm.meta_value IS NULL OR pm.meta_value != 'success')
        ORDER BY p.ID ASC
    ");

    wp_send_json_success(['post_ids' => array_map('intval', $post_ids)]);
});

// AJAX handler: Test API connection
add_action('wp_ajax_sgai_test_connection', function () {
    check_ajax_referer('schema_genie_ai_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Not authorized']);

    $api_key = isset($_POST['raw_key']) ? sanitize_text_field(wp_unslash($_POST['raw_key'])) : '';
    if (empty($api_key)) wp_send_json_error(['message' => 'No API key provided.']);

    $endpoint    = get_option('schema_genie_ai_azure_endpoint', '');
    $api_version = get_option('schema_genie_ai_azure_api_version', '2025-01-01-preview');
    $model       = get_option('schema_genie_ai_model', 'gpt-4o');

    if (empty($endpoint)) wp_send_json_error(['message' => 'Azure endpoint is not configured. Save your endpoint first.']);

    $url = rtrim($endpoint, '/') .
           '/openai/deployments/' . urlencode($model) .
           '/chat/completions?api-version=' . urlencode($api_version);

    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json', 'api-key' => $api_key],
        'body' => wp_json_encode([
            'messages' => [['role' => 'user', 'content' => 'Say "OK" in one word.']],
            'max_tokens' => 5,
        ]),
        'timeout' => 15,
        'sslverify' => true,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Network error: ' . $response->get_error_message()]);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($code === 200) {
        wp_send_json_success(['message' => 'Connection successful! You can now save settings.', 'url' => $url]);
    } else {
        $err = json_decode($body, true);
        $msg = isset($err['error']['message']) ? $err['error']['message'] : $body;
        wp_send_json_error(['message' => "HTTP $code: $msg", 'url' => $url]);
    }
});
