<?php
/**
 * Schema Injector — outputs JSON-LD in the wp_head.
 *
 * Strategy:
 * 1. If Rank Math is active → inject via rank_math/json_ld filter (replaces duplicate types)
 * 2. If Rank Math is NOT active → inject standalone <script type="application/ld+json"> via wp_head
 *
 * NOTE: We do NOT write to Rank Math's 'rank_math_schema' post meta because
 * Rank Math's JS does recursive lodash cloneDeep/mapValues on all schema
 * properties and crashes when encountering nested objects without 'metadata.isPrimary'.
 */
defined('ABSPATH') || exit;

class Schema_Genie_AI_Injector {

    public function __construct() {
        if ($this->is_rank_math_active()) {
            add_filter('rank_math/json_ld', [$this, 'inject_via_rank_math'], 99, 2);
        } else {
            add_action('wp_head', [$this, 'inject_standalone'], 1);
        }
    }

    private function is_rank_math_active(): bool {
        return class_exists('RankMath') || defined('RANK_MATH_VERSION');
    }

    /**
     * Inject schemas via Rank Math's json_ld filter.
     * This replaces Rank Math's default Article/WebPage schemas with ours.
     */
    public function inject_via_rank_math(array $data, $jsonld): array {
        $supported = Schema_Genie_AI_Meta_Box::get_supported_post_types();
        if (!is_singular($supported)) return $data;

        $post_id = get_the_ID();
        if (!$post_id) return $data;

        $schema_data = get_post_meta($post_id, '_schema_genie_ai_data', true);
        if (empty($schema_data)) return $data;

        $schemas = json_decode($schema_data, true);
        if (!is_array($schemas)) return $data;

        // Collect our schema types
        $our_types = [];
        foreach ($schemas as $schema) {
            $types = is_array($schema['@type']) ? $schema['@type'] : [$schema['@type']];
            foreach ($types as $t) $our_types[] = $t;
        }

        // Remove Rank Math's default schemas for types we override
        $types_to_replace = ['WebPage', 'Article', 'NewsArticle'];
        foreach ($data as $key => $entity) {
            if (!isset($entity['@type'])) continue;
            $entity_types = is_array($entity['@type']) ? $entity['@type'] : [$entity['@type']];
            foreach ($entity_types as $et) {
                if (in_array($et, $types_to_replace, true) && in_array($et, $our_types, true)) {
                    unset($data[$key]);
                    break;
                }
            }
        }

        // Add our schemas
        foreach ($schemas as $index => $schema) {
            $type_key = is_array($schema['@type']) ? implode('_', $schema['@type']) : $schema['@type'];
            $data['sgai_' . sanitize_key($type_key) . '_' . $index] = $schema;
        }

        return $data;
    }

    public function inject_standalone() {
        $supported = Schema_Genie_AI_Meta_Box::get_supported_post_types();
        if (!is_singular($supported)) return;

        $post_id = get_the_ID();
        if (!$post_id) return;

        $schema_data = get_post_meta($post_id, '_schema_genie_ai_data', true);
        if (empty($schema_data)) return;

        $schemas = json_decode($schema_data, true);
        if (!is_array($schemas)) return;

        $json_ld = ['@context' => 'https://schema.org', '@graph' => array_values($schemas)];
        $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if (defined('WP_DEBUG') && WP_DEBUG) $options |= JSON_PRETTY_PRINT;

        $output = wp_json_encode($json_ld, $options);
        if ($output) {
            echo '<script type="application/ld+json" class="schema-genie-ai">' . $output . '</script>' . "\n";
        }
    }
}
