<?php
/**
 * Admin Meta Box — adds a "Generate Schema" UI to the post editor.
 */
defined('ABSPATH') || exit;

class Schema_Genie_AI_Meta_Box {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Get the post types where the schema generator should appear.
     */
    public static function get_supported_post_types(): array {
        // Automatically include all public post types (post, page, news, etc.)
        $public_types = get_post_types(['public' => true], 'names');

        // Exclude attachment (media) — no schema needed for images/files
        unset($public_types['attachment']);

        return apply_filters('schema_genie_ai_post_types', array_values($public_types));
    }

    /**
     * Register the meta box for posts.
     */
    public function add_meta_box() {
        $post_types = self::get_supported_post_types();
        foreach ($post_types as $pt) {
            add_meta_box(
                'sgai_schema_meta_box',
                __('Schema Genie AI', 'schema-genie-ai'),
                [$this, 'render'],
                $pt,
                'side',
                'default'
            );
        }
    }

    /**
     * Enqueue the meta box JavaScript (only on post edit screens).
     */
    public function enqueue_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, self::get_supported_post_types(), true)) {
            return;
        }

        // Inline script — no external file needed (keeps the plugin self-contained)
        wp_enqueue_script('jquery');
    }

    /**
     * Render the meta box contents.
     */
    public function render(WP_Post $post) {
        $status    = get_post_meta($post->ID, '_schema_genie_ai_status', true);
        $generated = get_post_meta($post->ID, '_schema_genie_ai_generated', true);
        $error     = get_post_meta($post->ID, '_schema_genie_ai_error', true);
        $tokens    = get_post_meta($post->ID, '_schema_genie_ai_tokens', true);
        $has_data  = !empty(get_post_meta($post->ID, '_schema_genie_ai_data', true));

        // Nonce for AJAX
        $nonce = wp_create_nonce('schema_genie_ai_nonce');

        // Status display
        $status_text  = '';
        $status_color = '#666';
        switch ($status) {
            case 'success':
                $status_text  = sprintf(__('Generated: %s', 'schema-genie-ai'), $generated);
                $status_color = '#00a32a';
                break;
            case 'error':
                $status_text  = sprintf(__('Error: %s', 'schema-genie-ai'), $error);
                $status_color = '#d63638';
                break;
            case 'generating':
                $status_text  = __('Generating...', 'schema-genie-ai');
                $status_color = '#dba617';
                break;
            default:
                $status_text  = __('Not generated yet', 'schema-genie-ai');
                $status_color = '#999';
        }

        // Token usage
        $token_info = '';
        if ($tokens) {
            $usage = json_decode($tokens, true);
            if (isset($usage['total_tokens'])) {
                $token_info = sprintf(__('Tokens used: %d', 'schema-genie-ai'), $usage['total_tokens']);
            }
        }

        ?>
        <div id="sgai-schema-box">
            <!-- Status -->
            <p id="sgai-schema-status" style="color: <?php echo esc_attr($status_color); ?>; font-weight: 600; margin-bottom: 8px;">
                <?php echo esc_html($status_text); ?>
            </p>

            <?php if ($token_info): ?>
                <p style="color: #666; font-size: 11px; margin: 0 0 5px;"><?php echo esc_html($token_info); ?></p>
            <?php endif; ?>

            <?php if ($has_data && Schema_Genie_AI_Injector::is_synced_to_rank_math($post->ID)): ?>
                <p style="color: #2271b1; font-size: 11px; margin: 0 0 10px; font-weight: 500;">✓ <?php esc_html_e('Synced to Rank Math', 'schema-genie-ai'); ?></p>
            <?php elseif ($has_data && (class_exists('RankMath') || defined('RANK_MATH_VERSION'))): ?>
                <p style="color: #dba617; font-size: 11px; margin: 0 0 10px;">⚠ <?php esc_html_e('Not synced — click Regenerate to sync', 'schema-genie-ai'); ?></p>
            <?php endif; ?>

            <!-- Buttons -->
            <div style="display: flex; gap: 6px; margin-bottom: 10px;">
                <button type="button" id="sgai-generate-btn" class="button button-primary button-small">
                    <?php echo $has_data
                        ? esc_html__('Regenerate Schema', 'schema-genie-ai')
                        : esc_html__('Generate Schema', 'schema-genie-ai');
                    ?>
                </button>

                <?php if ($has_data): ?>
                    <button type="button" id="sgai-clear-btn" class="button button-small" style="color: #b32d2e;">
                        <?php esc_html_e('Clear', 'schema-genie-ai'); ?>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Preview toggle -->
            <?php if ($has_data): ?>
                <a href="#" id="sgai-preview-toggle" style="font-size: 12px;">
                    <?php esc_html_e('▶ Show JSON-LD Preview', 'schema-genie-ai'); ?>
                </a>
                <textarea id="sgai-preview-area" readonly
                    style="display: none; width: 100%; height: 300px; font-family: monospace; font-size: 11px; margin-top: 8px; background: #f6f7f7; resize: vertical;"
                ></textarea>
            <?php endif; ?>

            <!-- Loading spinner -->
            <div id="sgai-loading" style="display: none; text-align: center; padding: 15px;">
                <span class="spinner is-active" style="float: none;"></span>
                <p style="color: #666; font-size: 12px;"><?php esc_html_e('Calling Azure OpenAI... This may take 10-30 seconds.', 'schema-genie-ai'); ?></p>
            </div>
        </div>

        <script>
        jQuery(function($) {
            var postId = <?php echo (int) $post->ID; ?>;
            var nonce  = '<?php echo esc_js($nonce); ?>';
            var $box   = $('#sgai-schema-box');

            // Generate button
            $box.on('click', '#sgai-generate-btn', function() {
                var $btn = $(this).prop('disabled', true);
                var $loading = $('#sgai-loading').show();
                var $status = $('#sgai-schema-status');

                $status.css('color', '#dba617').text('<?php echo esc_js(__('Generating...', 'schema-genie-ai')); ?>');

                $.post(ajaxurl, {
                    action: 'sgai_generate_schema',
                    post_id: postId,
                    nonce: nonce
                }, function(response) {
                    $loading.hide();
                    $btn.prop('disabled', false);

                    if (response.success) {
                        $status.css('color', '#00a32a').text('✓ ' + response.data.message);
                        // Reload the meta box to show updated content
                        location.reload();
                    } else {
                        $status.css('color', '#d63638').text('✗ ' + response.data.message);
                    }
                }).fail(function(xhr) {
                    $loading.hide();
                    $btn.prop('disabled', false);
                    var msg = xhr.responseJSON && xhr.responseJSON.data
                        ? xhr.responseJSON.data.message
                        : '<?php echo esc_js(__('Request failed. Check browser console.', 'schema-genie-ai')); ?>';
                    $status.css('color', '#d63638').text('✗ ' + msg);
                });
            });

            // Clear button
            $box.on('click', '#sgai-clear-btn', function() {
                if (!confirm('<?php echo esc_js(__('Remove the generated schema for this post?', 'schema-genie-ai')); ?>')) {
                    return;
                }

                $.post(ajaxurl, {
                    action: 'sgai_clear_schema',
                    post_id: postId,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });

            // Preview toggle
            $box.on('click', '#sgai-preview-toggle', function(e) {
                e.preventDefault();
                var $area = $('#sgai-preview-area');
                var $toggle = $(this);

                if ($area.is(':visible')) {
                    $area.slideUp(200);
                    $toggle.text('<?php echo esc_js(__('▶ Show JSON-LD Preview', 'schema-genie-ai')); ?>');
                } else {
                    // Fetch preview
                    if (!$area.val()) {
                        $.post(ajaxurl, {
                            action: 'sgai_preview_schema',
                            post_id: postId,
                            nonce: nonce
                        }, function(response) {
                            if (response.success) {
                                $area.val(response.data.preview);
                            }
                        });
                    }
                    $area.slideDown(200);
                    $toggle.text('<?php echo esc_js(__('▼ Hide JSON-LD Preview', 'schema-genie-ai')); ?>');
                }
            });
        });
        </script>
        <?php
    }
}
