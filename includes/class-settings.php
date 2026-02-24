<?php
/**
 * Settings page: 3-tab admin UI — Settings, Missing Schemas, AI Request Log.
 */
defined('ABSPATH') || exit;

class Schema_Genie_AI_Settings {

    const PAGE_SLUG = 'schema-genie-ai-settings';
    const OPTION_GROUP = 'schema_genie_ai_options';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

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
     * Get enabled post types for schema detection.
     */
    public static function get_enabled_post_types(): array {
        $saved = get_option('schema_genie_ai_enabled_post_types', []);
        if (empty($saved)) {
            $public_types = get_post_types(['public' => true], 'names');
            unset($public_types['attachment']);
            return array_values($public_types);
        }
        return $saved;
    }

    public function register_settings() {
        // Section: Azure OpenAI
        add_settings_section('schema_genie_ai_azure', __('Azure OpenAI Configuration', 'schema-genie-ai'), function () {
            echo '<p>' . esc_html__('Configure your Azure OpenAI credentials. The API key is encrypted before storage.', 'schema-genie-ai') . '</p>';
        }, self::PAGE_SLUG);

        register_setting(self::OPTION_GROUP, 'schema_genie_ai_api_key', ['sanitize_callback' => [$this, 'encrypt_api_key']]);
        add_settings_field('schema_genie_ai_api_key', __('API Key', 'schema-genie-ai'), [$this, 'render_api_key_field'], self::PAGE_SLUG, 'schema_genie_ai_azure');

        register_setting(self::OPTION_GROUP, 'schema_genie_ai_azure_endpoint', ['sanitize_callback' => 'esc_url_raw']);
        add_settings_field('schema_genie_ai_azure_endpoint', __('Azure Endpoint', 'schema-genie-ai'), function () {
            $val = get_option('schema_genie_ai_azure_endpoint', '');
            echo '<input type="url" name="schema_genie_ai_azure_endpoint" value="' . esc_attr($val) . '" class="regular-text" />';
        }, self::PAGE_SLUG, 'schema_genie_ai_azure');

        register_setting(self::OPTION_GROUP, 'schema_genie_ai_azure_api_version', ['sanitize_callback' => 'sanitize_text_field']);
        add_settings_field('schema_genie_ai_azure_api_version', __('API Version', 'schema-genie-ai'), function () {
            $val = get_option('schema_genie_ai_azure_api_version', '2025-01-01-preview');
            echo '<input type="text" name="schema_genie_ai_azure_api_version" value="' . esc_attr($val) . '" class="regular-text" />';
        }, self::PAGE_SLUG, 'schema_genie_ai_azure');

        register_setting(self::OPTION_GROUP, 'schema_genie_ai_model', ['sanitize_callback' => 'sanitize_text_field']);
        add_settings_field('schema_genie_ai_model', __('Model / Deployment Name', 'schema-genie-ai'), function () {
            $val = get_option('schema_genie_ai_model', 'gpt-4o');
            echo '<input type="text" name="schema_genie_ai_model" value="' . esc_attr($val) . '" class="regular-text" />';
            echo '<p class="description">' . esc_html__('Azure deployment name, e.g., gpt-4o or gpt-4o-mini', 'schema-genie-ai') . '</p>';
        }, self::PAGE_SLUG, 'schema_genie_ai_azure');

        // Section: Generation Settings
        add_settings_section('schema_genie_ai_generation', __('Generation Settings', 'schema-genie-ai'), null, self::PAGE_SLUG);

        register_setting(self::OPTION_GROUP, 'schema_genie_ai_temperature', ['sanitize_callback' => function ($v) { return max(0, min(2, floatval($v))); }]);
        add_settings_field('schema_genie_ai_temperature', __('Temperature', 'schema-genie-ai'), function () {
            $val = get_option('schema_genie_ai_temperature', '0.1');
            echo '<input type="number" name="schema_genie_ai_temperature" value="' . esc_attr($val) . '" min="0" max="2" step="0.1" class="small-text" />';
            echo '<p class="description">' . esc_html__('Lower = more deterministic. Recommended: 0.1', 'schema-genie-ai') . '</p>';
        }, self::PAGE_SLUG, 'schema_genie_ai_generation');

        register_setting(self::OPTION_GROUP, 'schema_genie_ai_max_tokens', ['sanitize_callback' => function ($v) { return max(500, min(8000, intval($v))); }]);
        add_settings_field('schema_genie_ai_max_tokens', __('Max Tokens', 'schema-genie-ai'), function () {
            $val = get_option('schema_genie_ai_max_tokens', '2000');
            echo '<input type="number" name="schema_genie_ai_max_tokens" value="' . esc_attr($val) . '" min="500" max="8000" step="100" class="small-text" />';
        }, self::PAGE_SLUG, 'schema_genie_ai_generation');

        register_setting(self::OPTION_GROUP, 'schema_genie_ai_timeout', ['sanitize_callback' => function ($v) { return max(10, min(120, intval($v))); }]);
        add_settings_field('schema_genie_ai_timeout', __('Request Timeout (seconds)', 'schema-genie-ai'), function () {
            $val = get_option('schema_genie_ai_timeout', '45');
            echo '<input type="number" name="schema_genie_ai_timeout" value="' . esc_attr($val) . '" min="10" max="120" step="5" class="small-text" />';
        }, self::PAGE_SLUG, 'schema_genie_ai_generation');

        register_setting(self::OPTION_GROUP, 'schema_genie_ai_content_limit', ['sanitize_callback' => function ($v) { return max(1000, min(10000, intval($v))); }]);
        add_settings_field('schema_genie_ai_content_limit', __('Content Character Limit', 'schema-genie-ai'), function () {
            $val = get_option('schema_genie_ai_content_limit', '4000');
            echo '<input type="number" name="schema_genie_ai_content_limit" value="' . esc_attr($val) . '" min="1000" max="10000" step="500" class="small-text" />';
            echo '<p class="description">' . esc_html__('How many characters of article content to send to AI.', 'schema-genie-ai') . '</p>';
        }, self::PAGE_SLUG, 'schema_genie_ai_generation');

        register_setting(self::OPTION_GROUP, 'schema_genie_ai_auto_generate', ['sanitize_callback' => function ($v) { return $v ? '1' : '0'; }]);
        add_settings_field('schema_genie_ai_auto_generate', __('Auto-generate on Publish', 'schema-genie-ai'), function () {
            $val = get_option('schema_genie_ai_auto_generate', '0');
            echo '<label><input type="checkbox" name="schema_genie_ai_auto_generate" value="1" ' . checked($val, '1', false) . ' /> ';
            echo esc_html__('Automatically generate schema when a new post is published (first time only, async).', 'schema-genie-ai') . '</label>';
        }, self::PAGE_SLUG, 'schema_genie_ai_generation');

        register_setting(self::OPTION_GROUP, 'schema_genie_ai_enabled_post_types', ['sanitize_callback' => function ($v) { return is_array($v) ? array_map('sanitize_key', $v) : []; }]);
        add_settings_field('schema_genie_ai_enabled_post_types', __('Post Types to Detect', 'schema-genie-ai'), function () {
            $saved = get_option('schema_genie_ai_enabled_post_types', []);
            $public_types = get_post_types(['public' => true], 'objects');
            unset($public_types['attachment']);
            if (empty($saved)) $saved = array_keys($public_types);
            foreach ($public_types as $slug => $obj) {
                $chk = in_array($slug, $saved, true) ? 'checked' : '';
                echo '<label style="display:inline-block;margin-right:16px;margin-bottom:6px;"><input type="checkbox" name="schema_genie_ai_enabled_post_types[]" value="' . esc_attr($slug) . '" ' . $chk . ' /> ' . esc_html($obj->labels->singular_name ?? $obj->label) . '</label>';
            }
            echo '<p class="description">' . esc_html__('Select which post types should be detected for missing schemas.', 'schema-genie-ai') . '</p>';
        }, self::PAGE_SLUG, 'schema_genie_ai_generation');
    }

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
        echo '<br><button type="button" id="sgai-test-connection" class="button button-small" style="margin-top:5px;">' . esc_html__('Test Connection', 'schema-genie-ai') . '</button>';
        echo '<span id="sgai-test-result" style="margin-left:10px;"></span>';
    }

    public function encrypt_api_key($new_key) {
        if (empty($new_key)) return get_option('schema_genie_ai_api_key', '');
        return self::encrypt($new_key);
    }

    public static function encrypt(string $plaintext): string {
        $key = hash('sha256', wp_salt('auth'), true);
        $iv  = substr(hash('sha256', wp_salt('secure_auth'), true), 0, 16);
        return base64_encode(openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv));
    }

    public static function decrypt(string $encrypted): string {
        if (empty($encrypted)) return '';
        $key = hash('sha256', wp_salt('auth'), true);
        $iv  = substr(hash('sha256', wp_salt('secure_auth'), true), 0, 16);
        $d = openssl_decrypt(base64_decode($encrypted), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $d === false ? '' : $d;
    }

    public static function get_api_key(): string {
        return self::decrypt(get_option('schema_genie_ai_api_key', ''));
    }

    // =========================================================================
    //  RENDER PAGE — 3-Tab UI
    // =========================================================================

    public function render_page() {
        if (!current_user_can('manage_options')) return;

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';
        $has_rank_math = class_exists('RankMath') || defined('RANK_MATH_VERSION');
        $nonce = wp_create_nonce('schema_genie_ai_nonce');
        ?>
        <style>
            .sgai-wrap{max-width:1100px}
            .sgai-header{background:linear-gradient(135deg,#1d2327,#2c3338);color:#fff;padding:24px 30px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between}
            .sgai-header h1{color:#fff;margin:0;font-size:22px;padding:0}
            .sgai-header .sgai-version{background:rgba(255,255,255,.15);padding:4px 12px;border-radius:20px;font-size:12px;color:#c3c4c7}
            .sgai-section-box{background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:24px 30px;margin-bottom:20px}
            .sgai-section-box h2{margin-top:0;padding-top:0;font-size:16px;border-bottom:1px solid #f0f0f0;padding-bottom:12px}
            .sgai-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
            .sgai-card{background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px;text-align:center}
            .sgai-card .val{font-size:28px;font-weight:700;line-height:1.2}
            .sgai-card .lbl{font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px}
            .sgai-queue-log{max-height:350px;overflow-y:auto;font-size:12px;border:1px solid #e0e0e0;border-radius:4px;padding:8px;background:#f9f9f9;margin-top:10px}
            .sgai-queue-log .item{padding:3px 0;border-bottom:1px solid #eee}
            /* Pagination — Ant Design style */
            .sgai-pagination{display:flex;align-items:center;gap:8px;margin-top:12px;flex-wrap:wrap;justify-content:flex-end}
            .sgai-pagination .sgai-perpage select{border:1px solid #d9d9d9;border-radius:6px;padding:4px 8px;font-size:13px;color:#333;background:#fff;cursor:pointer;height:32px}
            .sgai-pagination .sgai-perpage select:hover{border-color:#1677ff}
            .sgai-pagination .sgai-pg-btn{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border:1px solid #d9d9d9;border-radius:6px;background:#fff;color:#333;font-size:13px;text-decoration:none;cursor:pointer;transition:all .2s;line-height:1;box-sizing:border-box}
            .sgai-pagination .sgai-pg-btn:hover{border-color:#1677ff;color:#1677ff}
            .sgai-pagination .sgai-pg-btn.active{border-color:#1677ff;color:#1677ff;font-weight:600}
            .sgai-pagination .sgai-pg-btn.disabled{color:#d9d9d9;cursor:not-allowed;border-color:#d9d9d9}
            .sgai-pagination .sgai-pg-btn.disabled:hover{color:#d9d9d9;border-color:#d9d9d9}
            .sgai-pagination .sgai-pg-dots{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;color:#999;font-size:14px;letter-spacing:2px}
            .sgai-pagination .sgai-pg-info{font-size:13px;color:#666;margin-right:8px}
        </style>
        <div class="wrap sgai-wrap">
            <div class="sgai-header">
                <div>
                    <h1>🧞 Schema Genie AI</h1>
                    <p style="margin:4px 0 0;color:#a7aaad;font-size:13px;"><?php esc_html_e('AI-powered structured data generator', 'schema-genie-ai'); ?></p>
                </div>
                <div style="text-align:right">
                    <span class="sgai-version">v<?php echo esc_html(SCHEMA_GENIE_AI_VERSION); ?></span>
                    <?php if ($has_rank_math): ?><br><span style="font-size:11px;color:#72aee6;margin-top:4px;display:inline-block">✓ Rank Math</span><?php endif; ?>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">⚙️ <?php esc_html_e('Settings', 'schema-genie-ai'); ?></a>
                <a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=missing" class="nav-tab <?php echo $active_tab === 'missing' ? 'nav-tab-active' : ''; ?>">📋 <?php esc_html_e('Schema Overview', 'schema-genie-ai'); ?></a>
                <a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=log" class="nav-tab <?php echo $active_tab === 'log' ? 'nav-tab-active' : ''; ?>">📝 <?php esc_html_e('AI Request Log', 'schema-genie-ai'); ?></a>
            </h2>

            <?php
            switch ($active_tab) {
                case 'missing':
                    $this->render_tab_missing($nonce);
                    break;
                case 'log':
                    $this->render_tab_log();
                    break;
                default:
                    $this->render_tab_settings($nonce);
                    break;
            }
            ?>
        </div>
        <?php
    }

    // =========================================================================
    //  TAB: Settings
    // =========================================================================

    private function render_tab_settings(string $nonce) {
        global $wpdb;
        $enabled_types = self::get_enabled_post_types();
        $types_in = "'" . implode("','", array_map('esc_sql', $enabled_types)) . "'";

        $total_schemas = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_schema_genie_ai_status' AND meta_value = 'success'");
        $total_errors  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_schema_genie_ai_status' AND meta_value = 'error'");
        $total_content = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ({$types_in}) AND post_status = 'publish'");
        $pending = max(0, $total_content - $total_schemas);
        $pct = $total_content > 0 ? round(($total_schemas / $total_content) * 100) : 0;
        ?>
        <!-- Dashboard Cards -->
        <div class="sgai-cards" style="margin-top:16px;">
            <div class="sgai-card"><div style="font-size:24px">✅</div><div class="val" style="color:#00a32a"><?php echo esc_html($total_schemas); ?></div><div class="lbl">Schemas</div></div>
            <div class="sgai-card"><div style="font-size:24px">⏳</div><div class="val" style="color:#dba617"><?php echo esc_html($pending); ?></div><div class="lbl">Pending</div></div>
            <div class="sgai-card"><div style="font-size:24px">❌</div><div class="val" style="color:#d63638"><?php echo esc_html($total_errors); ?></div><div class="lbl">Errors</div></div>
            <div class="sgai-card"><div style="font-size:24px">📊</div><div class="val" style="color:#2271b1"><?php echo esc_html($pct); ?>%</div><div class="lbl">Coverage</div></div>
        </div>

        <!-- Settings Form -->
        <div class="sgai-section-box">
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); do_settings_sections(self::PAGE_SLUG); submit_button(__('Save Settings', 'schema-genie-ai')); ?>
            </form>
        </div>

        <!-- Test Connection Script -->
        <script>
        jQuery(function($){
            var $saveBtn=$('input[type="submit"]'),$apiKeyInput=$('input[name="schema_genie_ai_api_key"]'),hasStoredKey=<?php echo json_encode(!empty(get_option('schema_genie_ai_api_key',''))); ?>;
            if(!hasStoredKey&&$apiKeyInput.val().trim()===''){$saveBtn.prop('disabled',true).css('opacity','0.5');}
            $apiKeyInput.on('input',function(){if($(this).val().trim()!==''||hasStoredKey){$saveBtn.prop('disabled',false).css('opacity','1')}else{$saveBtn.prop('disabled',true).css('opacity','0.5')}});
            $('#sgai-test-connection').on('click',function(){
                var $btn=$(this).prop('disabled',true),$result=$('#sgai-test-result').text('Testing...').css('color','#666'),rawKey=$apiKeyInput.val().trim();
                if(!rawKey){$result.text('\u2717 Enter API key first.').css('color','#d63638');$btn.prop('disabled',false);return;}
                $.post(ajaxurl,{action:'sgai_test_connection',nonce:'<?php echo esc_js($nonce); ?>',raw_key:rawKey},function(r){
                    $btn.prop('disabled',false);
                    if(r.success){$result.text('\u2713 '+r.data.message).css('color','#00a32a');$saveBtn.prop('disabled',false).css('opacity','1')}
                    else{var m=r.data.message;if(r.data.url)m+=' | URL: '+r.data.url;$result.text('\u2717 '+m).css('color','#d63638')}
                }).fail(function(){$btn.prop('disabled',false);$result.text('\u2717 Request failed').css('color','#d63638')});
            });
        });
        </script>
        <?php
    }

    // =========================================================================
    //  TAB: Missing Schemas
    // =========================================================================

    private function render_tab_missing(string $nonce) {
        global $wpdb;
        $enabled_types = self::get_enabled_post_types();
        $types_in = "'" . implode("','", array_map('esc_sql', $enabled_types)) . "'";

        // WPML detection
        $has_wpml = defined('ICL_SITEPRESS_VERSION') || function_exists('icl_get_languages');
        $wpml_languages = [];
        $icl_table = $wpdb->prefix . 'icl_translations';
        if ($has_wpml) {
            $wpml_languages = apply_filters('wpml_active_languages', [], ['skip_missing' => 0]);
        }

        // Filters
        $filter_type   = isset($_GET['post_type_filter']) ? sanitize_key($_GET['post_type_filter']) : '';
        $filter_lang   = isset($_GET['lang_filter']) ? sanitize_key($_GET['lang_filter']) : '';
        $filter_status = isset($_GET['status_filter']) ? sanitize_key($_GET['status_filter']) : '';
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = isset($_GET['per_page']) ? max(10, min(100, (int) $_GET['per_page'])) : 20;
        $offset = ($paged - 1) * $per_page;

        $where_type = $filter_type ? $wpdb->prepare("AND p.post_type = %s", $filter_type) : "AND p.post_type IN ({$types_in})";

        // Status filter
        $where_status = '';
        if ($filter_status === 'none') {
            $where_status = "AND (pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = 'none')";
        } elseif ($filter_status === 'error') {
            $where_status = "AND pm.meta_value = 'error'";
        } elseif ($filter_status === 'generating') {
            $where_status = "AND pm.meta_value = 'generating'";
        } elseif ($filter_status === 'success') {
            $where_status = "AND pm.meta_value = 'success'";
        } elseif ($filter_status === 'missing') {
            $where_status = "AND (pm.meta_value IS NULL OR pm.meta_value != 'success')";
        }

        // WPML language join & filter
        $join_wpml = '';
        $where_lang = '';
        $select_lang = '';
        if ($has_wpml) {
            $join_wpml = " LEFT JOIN {$icl_table} icl ON p.ID = icl.element_id AND icl.element_type = CONCAT('post_', p.post_type)";
            $select_lang = ", COALESCE(icl.language_code, '--') as lang_code";
            if ($filter_lang) {
                $where_lang = $wpdb->prepare(" AND icl.language_code = %s", $filter_lang);
            }
        }

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_schema_genie_ai_status' {$join_wpml} WHERE p.post_status = 'publish' {$where_type} {$where_lang} {$where_status}");

        $posts = $wpdb->get_results("SELECT p.ID, p.post_title, p.post_type, p.post_date, COALESCE(pm.meta_value, 'none') as schema_status, COALESCE(pe.meta_value, '') as schema_error {$select_lang} FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_schema_genie_ai_status' LEFT JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = '_schema_genie_ai_error' {$join_wpml} WHERE p.post_status = 'publish' {$where_type} {$where_lang} {$where_status} ORDER BY p.ID DESC LIMIT {$per_page} OFFSET {$offset}", ARRAY_A);

        $total_pages = ceil($total / $per_page);
        $public_types = get_post_types(['public' => true], 'objects');
        unset($public_types['attachment']);
        $base_url = admin_url('options-general.php?page=' . self::PAGE_SLUG . '&tab=missing');
        // Raw URL for JS (admin_url returns &amp; which breaks JS)
        $base_url_js = esc_url_raw($base_url);

        // Language flag map for display
        $lang_flags = [];
        $lang_names = [];
        if ($has_wpml && !empty($wpml_languages)) {
            foreach ($wpml_languages as $code => $info) {
                $lang_flags[$code] = isset($info['country_flag_url']) ? $info['country_flag_url'] : '';
                $lang_names[$code] = isset($info['translated_name']) ? $info['translated_name'] : strtoupper($code);
            }
        }
        ?>
        <div class="sgai-section-box" style="margin-top:16px;">
            <h2>📋 <?php esc_html_e('Schema Overview', 'schema-genie-ai'); ?> <span style="font-weight:normal;font-size:13px;color:#666;">(<?php echo esc_html($total); ?> found)</span></h2>

            <!-- Filters -->
            <div style="margin-bottom:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <select id="sgai-type-filter" style="min-width:140px;">
                    <option value=""><?php esc_html_e('All Post Types', 'schema-genie-ai'); ?></option>
                    <?php foreach ($enabled_types as $slug): $lbl = isset($public_types[$slug]) ? $public_types[$slug]->labels->singular_name : $slug; ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($filter_type, $slug); ?>><?php echo esc_html($lbl); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="sgai-status-filter" style="min-width:120px;">
                    <option value=""><?php esc_html_e('All Statuses', 'schema-genie-ai'); ?></option>
                    <option value="missing" <?php selected($filter_status, 'missing'); ?>><?php esc_html_e('Missing (All)', 'schema-genie-ai'); ?></option>
                    <option value="none" <?php selected($filter_status, 'none'); ?>><?php esc_html_e('None', 'schema-genie-ai'); ?></option>
                    <option value="error" <?php selected($filter_status, 'error'); ?>><?php esc_html_e('Error', 'schema-genie-ai'); ?></option>
                    <option value="generating" <?php selected($filter_status, 'generating'); ?>><?php esc_html_e('Generating', 'schema-genie-ai'); ?></option>
                    <option value="success" <?php selected($filter_status, 'success'); ?>><?php esc_html_e('✅ Done', 'schema-genie-ai'); ?></option>
                </select>
                <?php if ($has_wpml && !empty($wpml_languages)): ?>
                <select id="sgai-lang-filter" style="min-width:120px;">
                    <option value=""><?php esc_html_e('All Languages', 'schema-genie-ai'); ?></option>
                    <?php foreach ($wpml_languages as $code => $info): ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($filter_lang, $code); ?>>
                            <?php echo esc_html(($info['translated_name'] ?? strtoupper($code)) . ' (' . strtoupper($code) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <button type="button" id="sgai-filter-btn" class="button"><?php esc_html_e('Filter', 'schema-genie-ai'); ?></button>
                <span style="flex:1"></span>
                <button type="button" id="sgai-bulk-generate" class="button button-primary" disabled>🚀 <?php esc_html_e('Generate Selected', 'schema-genie-ai'); ?></button>
                <button type="button" id="sgai-bulk-stop" class="button" style="display:none;">⏹ <?php esc_html_e('Stop', 'schema-genie-ai'); ?></button>
            </div>

            <!-- Table -->
            <table class="wp-list-table widefat fixed striped" id="sgai-missing-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column"><input type="checkbox" id="sgai-select-all" /></td>
                        <th style="width:50px">ID</th>
                        <th><?php esc_html_e('Title', 'schema-genie-ai'); ?></th>
                        <th style="width:100px"><?php esc_html_e('Type', 'schema-genie-ai'); ?></th>
                        <?php if ($has_wpml): ?><th style="width:60px"><?php esc_html_e('Lang', 'schema-genie-ai'); ?></th><?php endif; ?>
                        <th style="width:100px"><?php esc_html_e('Status', 'schema-genie-ai'); ?></th>
                        <th style="width:120px"><?php esc_html_e('Published', 'schema-genie-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $col_count = $has_wpml ? 7 : 6; ?>
                    <?php if (empty($posts)): ?>
                        <tr><td colspan="<?php echo $col_count; ?>" style="text-align:center;padding:20px;color:#666;">📭 <?php esc_html_e('No posts found matching your filters.', 'schema-genie-ai'); ?></td></tr>
                    <?php else: foreach ($posts as $p):
                        $edit_link = get_edit_post_link($p['ID']);
                        $type_label = isset($public_types[$p['post_type']]) ? $public_types[$p['post_type']]->labels->singular_name : $p['post_type'];
                        $status_badge = match($p['schema_status']) {
                            'success'    => '<span style="color:#00a32a">✅ Done</span>',
                            'error'      => '<span style="color:#d63638" title="' . esc_attr($p['schema_error']) . '">❌ Error</span>',
                            'generating' => '<span style="color:#2271b1">⏳ Generating</span>',
                            default      => '<span style="color:#888">— None</span>',
                        };
                        $post_lang = isset($p['lang_code']) ? $p['lang_code'] : '';
                        $lang_display = '';
                        if ($has_wpml && $post_lang) {
                            $flag_url = isset($lang_flags[$post_lang]) ? $lang_flags[$post_lang] : '';
                            $lang_label = isset($lang_names[$post_lang]) ? $lang_names[$post_lang] : strtoupper($post_lang);
                            $lang_display = $flag_url
                                ? '<img src="' . esc_url($flag_url) . '" alt="' . esc_attr($post_lang) . '" style="width:18px;height:12px;vertical-align:middle;margin-right:4px;" />' . esc_html(strtoupper($post_lang))
                                : esc_html(strtoupper($post_lang));
                        }
                    ?>
                        <tr>
                            <th class="check-column"><input type="checkbox" class="sgai-row-cb" value="<?php echo esc_attr($p['ID']); ?>" /></th>
                            <td><?php echo esc_html($p['ID']); ?></td>
                            <td><a href="<?php echo esc_url($edit_link); ?>" target="_blank"><?php echo esc_html($p['post_title'] ?: '(no title)'); ?></a></td>
                            <td><?php echo esc_html($type_label); ?></td>
                            <?php if ($has_wpml): ?><td><?php echo $lang_display ?: '<span style="color:#999">—</span>'; ?></td><?php endif; ?>
                            <td class="sgai-status-<?php echo esc_attr($p['ID']); ?>"><?php echo $status_badge; ?></td>
                            <td><?php echo esc_html(mysql2date('Y-m-d', $p['post_date'])); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1):
                $filter_args = '';
                if ($filter_type) $filter_args .= '&post_type_filter=' . $filter_type;
                if ($filter_lang) $filter_args .= '&lang_filter=' . $filter_lang;
                if ($filter_status) $filter_args .= '&status_filter=' . $filter_status;
                $pag_base = $base_url . $filter_args;
            ?>
            <div class="sgai-pagination">
                <span class="sgai-pg-info"><?php echo esc_html($total); ?> items</span>
                <span class="sgai-perpage">
                    <select onchange="window.location.href='<?php echo esc_js($pag_base); ?>&per_page='+this.value">
                        <?php foreach ([10, 20, 25, 50, 100] as $pp): ?>
                            <option value="<?php echo $pp; ?>" <?php selected($per_page, $pp); ?>><?php echo $pp; ?> / page</option>
                        <?php endforeach; ?>
                    </select>
                </span>
                <?php if ($paged > 1): ?>
                    <a class="sgai-pg-btn" href="<?php echo esc_url(add_query_arg(['paged' => $paged - 1, 'per_page' => $per_page], $pag_base)); ?>">&lsaquo;</a>
                <?php else: ?>
                    <span class="sgai-pg-btn disabled">&lsaquo;</span>
                <?php endif; ?>
                <?php
                $range = 2;
                $show_pages = [];
                $show_pages[] = 1;
                for ($i = max(2, $paged - $range); $i <= min($total_pages - 1, $paged + $range); $i++) {
                    $show_pages[] = $i;
                }
                if ($total_pages > 1) $show_pages[] = $total_pages;
                $show_pages = array_unique($show_pages);
                sort($show_pages);
                $prev = 0;
                foreach ($show_pages as $pg):
                    if ($prev && $pg - $prev > 1): ?>
                        <span class="sgai-pg-dots">···</span>
                    <?php endif;
                    if ($pg === $paged): ?>
                        <span class="sgai-pg-btn active"><?php echo $pg; ?></span>
                    <?php else: ?>
                        <a class="sgai-pg-btn" href="<?php echo esc_url(add_query_arg(['paged' => $pg, 'per_page' => $per_page], $pag_base)); ?>"><?php echo $pg; ?></a>
                    <?php endif;
                    $prev = $pg;
                endforeach; ?>
                <?php if ($paged < $total_pages): ?>
                    <a class="sgai-pg-btn" href="<?php echo esc_url(add_query_arg(['paged' => $paged + 1, 'per_page' => $per_page], $pag_base)); ?>">&rsaquo;</a>
                <?php else: ?>
                    <span class="sgai-pg-btn disabled">&rsaquo;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Queue Progress -->
            <div id="sgai-queue-panel" style="display:none;margin-top:16px;">
                <h3 style="margin:0 0 8px;">⚡ <?php esc_html_e('Generation Queue', 'schema-genie-ai'); ?></h3>
                <div style="background:#eee;height:24px;border-radius:4px;overflow:hidden;">
                    <div id="sgai-queue-bar" style="background:#2271b1;height:100%;width:0%;transition:width .3s;"></div>
                </div>
                <p id="sgai-queue-status" style="font-size:13px;color:#666;margin:6px 0;"></p>
                <div id="sgai-queue-log" class="sgai-queue-log"></div>
            </div>
        </div>

        <script>
        jQuery(function($){
            var nonce='<?php echo esc_js($nonce); ?>',running=false,stopRequested=false;
            var filterBaseUrl=<?php echo wp_json_encode($base_url_js); ?>;

            // Filter button
            $('#sgai-filter-btn').on('click',function(){
                var f=$('#sgai-type-filter').val();
                var s=$('#sgai-status-filter').val();
                var url=filterBaseUrl+(f?'&post_type_filter='+f:'');
                if(s) url+='&status_filter='+s;
                <?php if ($has_wpml): ?>
                var l=$('#sgai-lang-filter').val();
                if(l) url+='&lang_filter='+l;
                <?php endif; ?>
                window.location.href=url;
            });

            // Select all
            $('#sgai-select-all').on('change',function(){$('.sgai-row-cb').prop('checked',this.checked);toggleBulkBtn()});
            $(document).on('change','.sgai-row-cb',toggleBulkBtn);
            function toggleBulkBtn(){$('#sgai-bulk-generate').prop('disabled',$('.sgai-row-cb:checked').length===0||running)}

            // Bulk Generate
            $('#sgai-bulk-generate').on('click',function(){
                var ids=[];$('.sgai-row-cb:checked').each(function(){ids.push($(this).val())});
                if(!ids.length)return;
                if(!confirm('Generate schemas for '+ids.length+' posts? Each call uses API tokens.'))return;

                // Acquire bulk lock
                $.post(ajaxurl,{action:'sgai_bulk_start',nonce:nonce},function(r){
                    if(!r.success){alert(r.data.message||'Bulk lock failed');return;}
                    running=true;stopRequested=false;
                    $('#sgai-bulk-generate').prop('disabled',true);
                    $('#sgai-bulk-stop').show();
                    $('#sgai-queue-panel').show();
                    var $bar=$('#sgai-queue-bar'),$status=$('#sgai-queue-status'),$log=$('#sgai-queue-log');
                    $log.empty();
                    var total=ids.length,done=0,errors=0;

                    // Mark all selected rows as Queued
                    ids.forEach(function(qid){
                        $('.sgai-status-'+qid).html('<span style="color:#8c8f94">🕐 Queued</span>');
                    });

                    function next(){
                        if(stopRequested||ids.length===0){
                            finish();return;
                        }
                        var id=ids.shift();done++;
                        $status.text('Processing '+done+' of '+total+'...');
                        $bar.css('width',((done/total)*100)+'%');
                        // Show In Progress before AJAX call
                        $('.sgai-status-'+id).html('<span style="color:#2271b1"><span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span>In Progress</span>');
                        $log.prepend('<div class="item" id="sgai-log-'+id+'">⏳ <strong>#'+id+'</strong> — Processing...</div>');
                        $.post(ajaxurl,{action:'sgai_generate_schema',post_id:id,nonce:nonce,trigger_type:'bulk'},function(r){
                            if(r.success){
                                $('#sgai-log-'+id).replaceWith('<div class="item">✅ <strong>#'+id+'</strong> — '+r.data.message+'</div>');
                                $('.sgai-status-'+id).html('<span style="color:#00a32a">✅ Done</span>');
                            }else{
                                errors++;
                                $('#sgai-log-'+id).replaceWith('<div class="item">❌ <strong>#'+id+'</strong> — '+(r.data.message||'Unknown error')+'</div>');
                                $('.sgai-status-'+id).html('<span style="color:#d63638">❌ Error</span>');
                            }
                            setTimeout(next,7000);
                        }).fail(function(){
                            errors++;
                            $('#sgai-log-'+id).replaceWith('<div class="item">❌ <strong>#'+id+'</strong> — Request failed</div>');
                            $('.sgai-status-'+id).html('<span style="color:#d63638">❌ Error</span>');
                            setTimeout(next,7000);
                        });
                    }

                    function finish(){
                        running=false;
                        $status.text('Done! Generated: '+(done-errors)+', Errors: '+errors+(stopRequested?' (stopped by user)':''));
                        $bar.css('width','100%');
                        $('#sgai-bulk-stop').hide();
                        $('#sgai-bulk-generate').prop('disabled',false);
                        $.post(ajaxurl,{action:'sgai_bulk_complete',nonce:nonce});
                    }

                    next();
                });
            });

            // Stop button
            $('#sgai-bulk-stop').on('click',function(){stopRequested=true;$(this).prop('disabled',true).text('Stopping...');});
        });
        </script>
        <?php
    }

    // =========================================================================
    //  TAB: AI Request Log
    // =========================================================================

    private function render_tab_log() {
        $paged     = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $status_f  = isset($_GET['status_filter']) ? sanitize_key($_GET['status_filter']) : '';
        $trigger_f = isset($_GET['trigger_filter']) ? sanitize_key($_GET['trigger_filter']) : '';
        $per_page  = isset($_GET['per_page']) ? max(10, min(100, (int) $_GET['per_page'])) : 20;

        $logs  = Schema_Genie_AI_Request_Log::get_logs($paged, $per_page, $status_f, $trigger_f);
        $total = Schema_Genie_AI_Request_Log::get_total_count($status_f, $trigger_f);
        $stats = Schema_Genie_AI_Request_Log::get_stats();
        $total_pages = ceil($total / $per_page);
        $base_url = admin_url('options-general.php?page=' . self::PAGE_SLUG . '&tab=log');
        ?>
        <!-- Log Stats -->
        <div class="sgai-cards" style="margin-top:16px;">
            <div class="sgai-card"><div class="val" style="color:#2271b1"><?php echo esc_html($stats['total']); ?></div><div class="lbl">Total Requests</div></div>
            <div class="sgai-card"><div class="val" style="color:#00a32a"><?php echo esc_html($stats['success']); ?></div><div class="lbl">Success</div></div>
            <div class="sgai-card"><div class="val" style="color:#d63638"><?php echo esc_html($stats['error']); ?></div><div class="lbl">Errors</div></div>
            <div class="sgai-card"><div class="val" style="color:#8c5e19"><?php echo esc_html(number_format($stats['total_tokens'])); ?></div><div class="lbl">Total Tokens</div></div>
        </div>

        <div class="sgai-section-box">
            <h2>📝 <?php esc_html_e('AI Request Log', 'schema-genie-ai'); ?>
                <button type="button" id="sgai-sync-logs" class="button" style="margin-left:12px;vertical-align:middle;font-size:13px;">🔄 <?php esc_html_e('Sync Missing Logs', 'schema-genie-ai'); ?></button>
                <span id="sgai-sync-result" style="font-size:13px;color:#666;margin-left:8px;"></span>
            </h2>

            <!-- Filters -->
            <div style="margin-bottom:12px;display:flex;gap:10px;flex-wrap:wrap;">
                <select id="sgai-log-status" style="min-width:120px;">
                    <option value="">All Status</option>
                    <option value="success" <?php selected($status_f, 'success'); ?>>Success</option>
                    <option value="error" <?php selected($status_f, 'error'); ?>>Error</option>
                </select>
                <select id="sgai-log-trigger" style="min-width:120px;">
                    <option value="">All Triggers</option>
                    <option value="manual" <?php selected($trigger_f, 'manual'); ?>>Manual</option>
                    <option value="bulk" <?php selected($trigger_f, 'bulk'); ?>>Bulk</option>
                    <option value="cron" <?php selected($trigger_f, 'cron'); ?>>Cron</option>
                    <option value="auto_save" <?php selected($trigger_f, 'auto_save'); ?>>Auto Save</option>
                </select>
                <button type="button" id="sgai-log-filter" class="button">Filter</button>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:140px"><?php esc_html_e('Date/Time', 'schema-genie-ai'); ?></th>
                        <th><?php esc_html_e('Post', 'schema-genie-ai'); ?></th>
                        <th style="width:80px"><?php esc_html_e('Type', 'schema-genie-ai'); ?></th>
                        <th style="width:80px"><?php esc_html_e('Trigger', 'schema-genie-ai'); ?></th>
                        <th style="width:90px"><?php esc_html_e('User', 'schema-genie-ai'); ?></th>
                        <th style="width:70px"><?php esc_html_e('Status', 'schema-genie-ai'); ?></th>
                        <th style="width:80px"><?php esc_html_e('Tokens', 'schema-genie-ai'); ?></th>
                        <th style="width:80px"><?php esc_html_e('Duration', 'schema-genie-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="8" style="text-align:center;padding:20px;color:#666;"><?php esc_html_e('No log entries yet.', 'schema-genie-ai'); ?></td></tr>
                    <?php else: foreach ($logs as $log):
                        $status_icon = $log['status'] === 'success' ? '🟢' : ($log['status'] === 'error' ? '🔴' : '🟡');
                        $edit_link = get_edit_post_link($log['post_id']);
                    ?>
                        <tr>
                            <td><?php echo esc_html($log['created_at']); ?></td>
                            <td><a href="<?php echo esc_url($edit_link); ?>" target="_blank">#<?php echo esc_html($log['post_id']); ?> <?php echo esc_html($log['post_title']); ?></a></td>
                            <td><?php echo esc_html($log['post_type']); ?></td>
                            <td><code><?php echo esc_html($log['trigger_type']); ?></code></td>
                            <td><?php echo esc_html($log['triggered_by']); ?></td>
                            <td><?php echo $status_icon; ?> <?php echo esc_html($log['status']); ?></td>
                            <td><?php echo esc_html(number_format($log['tokens_total'])); ?></td>
                            <td><?php echo $log['duration_ms'] > 0 ? esc_html(round($log['duration_ms'] / 1000, 1)) . 's' : '—'; ?></td>
                        </tr>
                        <?php if ($log['status'] === 'error' && !empty($log['error_message'])): ?>
                        <tr><td colspan="8" style="background:#fef0f0;padding:4px 12px;font-size:12px;color:#d63638;">↳ <?php echo esc_html($log['error_message']); ?></td></tr>
                        <?php endif; ?>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1):
                $filter_args = '';
                if ($status_f) $filter_args .= '&status_filter=' . $status_f;
                if ($trigger_f) $filter_args .= '&trigger_filter=' . $trigger_f;
                $pag_base = $base_url . $filter_args;
            ?>
            <div class="sgai-pagination">
                <span class="sgai-pg-info"><?php echo esc_html($total); ?> items</span>
                <span class="sgai-perpage">
                    <select onchange="window.location.href='<?php echo esc_js($pag_base); ?>&per_page='+this.value">
                        <?php foreach ([10, 20, 25, 50, 100] as $pp): ?>
                            <option value="<?php echo $pp; ?>" <?php selected($per_page, $pp); ?>><?php echo $pp; ?> / page</option>
                        <?php endforeach; ?>
                    </select>
                </span>
                <?php if ($paged > 1): ?>
                    <a class="sgai-pg-btn" href="<?php echo esc_url(add_query_arg(['paged' => $paged - 1, 'per_page' => $per_page], $pag_base)); ?>">&lsaquo;</a>
                <?php else: ?>
                    <span class="sgai-pg-btn disabled">&lsaquo;</span>
                <?php endif; ?>
                <?php
                $range = 2;
                $show_pages = [];
                $show_pages[] = 1;
                for ($i = max(2, $paged - $range); $i <= min($total_pages - 1, $paged + $range); $i++) {
                    $show_pages[] = $i;
                }
                if ($total_pages > 1) $show_pages[] = $total_pages;
                $show_pages = array_unique($show_pages);
                sort($show_pages);
                $prev = 0;
                foreach ($show_pages as $pg):
                    if ($prev && $pg - $prev > 1): ?>
                        <span class="sgai-pg-dots">···</span>
                    <?php endif;
                    if ($pg === $paged): ?>
                        <span class="sgai-pg-btn active"><?php echo $pg; ?></span>
                    <?php else: ?>
                        <a class="sgai-pg-btn" href="<?php echo esc_url(add_query_arg(['paged' => $pg, 'per_page' => $per_page], $pag_base)); ?>"><?php echo $pg; ?></a>
                    <?php endif;
                    $prev = $pg;
                endforeach; ?>
                <?php if ($paged < $total_pages): ?>
                    <a class="sgai-pg-btn" href="<?php echo esc_url(add_query_arg(['paged' => $paged + 1, 'per_page' => $per_page], $pag_base)); ?>">&rsaquo;</a>
                <?php else: ?>
                    <span class="sgai-pg-btn disabled">&rsaquo;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(function($){
            var logBaseUrl='<?php echo esc_js(esc_url_raw($base_url)); ?>';

            $('#sgai-log-filter').on('click',function(){
                var s=$('#sgai-log-status').val(),t=$('#sgai-log-trigger').val();
                var url=logBaseUrl;
                if(s)url+='&status_filter='+s;
                if(t)url+='&trigger_filter='+t;
                window.location.href=url;
            });

            // Sync Missing Logs
            $('#sgai-sync-logs').on('click',function(){
                var $btn=$(this),$res=$('#sgai-sync-result');
                $btn.prop('disabled',true).text('Syncing...');
                $res.text('');
                $.post(ajaxurl,{action:'sgai_backfill_logs',nonce:'<?php echo wp_create_nonce('schema_genie_ai_nonce'); ?>'},function(r){
                    if(r.success){
                        $res.css('color','#00a32a').text(r.data.message);
                        if(r.data.count>0){
                            setTimeout(function(){location.reload();},1500);
                        }
                    }else{
                        $res.css('color','#d63638').text(r.data.message||'Sync failed');
                    }
                    $btn.prop('disabled',false).html('\ud83d\udd04 Sync Missing Logs');
                }).fail(function(){
                    $res.css('color','#d63638').text('Request failed');
                    $btn.prop('disabled',false).html('\ud83d\udd04 Sync Missing Logs');
                });
            });
        });
        </script>
        <?php
    }
}

// =============================================================================
//  AJAX Handlers
// =============================================================================

// Missing posts for bulk — includes all enabled post types
add_action('wp_ajax_sgai_bulk_get_posts', function () {
    check_ajax_referer('schema_genie_ai_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();

    global $wpdb;
    $enabled = Schema_Genie_AI_Settings::get_enabled_post_types();
    $types_in = "'" . implode("','", array_map('esc_sql', $enabled)) . "'";

    $post_ids = $wpdb->get_col("
        SELECT p.ID FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_schema_genie_ai_status'
        WHERE p.post_type IN ({$types_in})
        AND p.post_status = 'publish'
        AND (pm.meta_value IS NULL OR pm.meta_value != 'success')
        ORDER BY p.ID ASC
    ");

    wp_send_json_success(['post_ids' => array_map('intval', $post_ids)]);
});

// Bulk start — acquire global lock
add_action('wp_ajax_sgai_bulk_start', function () {
    check_ajax_referer('schema_genie_ai_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Not authorized']);

    if (get_transient('sgai_bulk_running')) {
        $running_user = get_transient('sgai_bulk_running');
        wp_send_json_error(['message' => 'Bulk generation is already running (started by user ID: ' . $running_user . '). Please wait for it to finish.']);
    }

    set_transient('sgai_bulk_running', get_current_user_id(), 3600);
    wp_send_json_success(['message' => 'Bulk lock acquired.']);
});

// Bulk complete — release global lock
add_action('wp_ajax_sgai_bulk_complete', function () {
    check_ajax_referer('schema_genie_ai_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error();

    delete_transient('sgai_bulk_running');
    wp_send_json_success();
});

// Test API connection
add_action('wp_ajax_sgai_test_connection', function () {
    check_ajax_referer('schema_genie_ai_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Not authorized']);

    $api_key = isset($_POST['raw_key']) ? sanitize_text_field(wp_unslash($_POST['raw_key'])) : '';
    if (empty($api_key)) wp_send_json_error(['message' => 'No API key provided.']);

    $endpoint    = get_option('schema_genie_ai_azure_endpoint', '');
    $api_version = get_option('schema_genie_ai_azure_api_version', '2025-01-01-preview');
    $model       = get_option('schema_genie_ai_model', 'gpt-4o');

    if (empty($endpoint)) wp_send_json_error(['message' => 'Azure endpoint is not configured. Save your endpoint first.']);

    $url = rtrim($endpoint, '/') . '/openai/deployments/' . urlencode($model) . '/chat/completions?api-version=' . urlencode($api_version);

    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json', 'api-key' => $api_key],
        'body' => wp_json_encode(['messages' => [['role' => 'user', 'content' => 'Say "OK" in one word.']], 'max_tokens' => 5]),
        'timeout' => 15, 'sslverify' => true,
    ]);

    if (is_wp_error($response)) wp_send_json_error(['message' => 'Network error: ' . $response->get_error_message()]);

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
