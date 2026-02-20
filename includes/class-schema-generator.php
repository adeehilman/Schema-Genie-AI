<?php
/**
 * Schema Generator — the core orchestrator.
 *
 * Extracts article content, builds AI prompt, calls Azure OpenAI,
 * validates response, merges with master template, stores in post meta.
 */
defined('ABSPATH') || exit;

class Schema_Genie_AI_Generator {

    private $ai_client;
    private $template;

    public function __construct() {
        $this->ai_client = new Schema_Genie_AI_Client();
        $this->template  = new Schema_Genie_AI_Template();
    }

    /**
     * Generate the complete JSON-LD schema for a post.
     *
     * @param int $post_id The post ID.
     * @return array The final merged schema graph.
     * @throws Exception On failure.
     */
    public function generate(int $post_id): array {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            throw new Exception(__('Post not found or not published.', 'schema-genie-ai'));
        }

        // Update status to "generating"
        update_post_meta($post_id, '_schema_genie_ai_status', 'generating');
        delete_post_meta($post_id, '_schema_genie_ai_error');

        // Step 1: Extract article data
        $article_data = $this->extract_article_data($post);

        // Step 2: Build prompts
        $system_prompt = $this->build_system_prompt();
        $user_prompt   = $this->build_user_prompt($article_data);

        // Step 3: Call Azure OpenAI
        $ai_response = $this->ai_client->call($system_prompt, $user_prompt);

        // Store raw response for debugging
        if (defined('WP_DEBUG') && WP_DEBUG && isset($ai_response['_raw'])) {
            update_post_meta($post_id, '_schema_genie_ai_raw', $ai_response['_raw']);
        }

        // Store token usage
        if (isset($ai_response['_usage'])) {
            update_post_meta($post_id, '_schema_genie_ai_tokens', wp_json_encode($ai_response['_usage']));
        }

        // Remove internal keys before validation
        unset($ai_response['_usage'], $ai_response['_raw']);

        // Step 4: Validate AI output
        $validated_schemas = $this->validate_ai_response($ai_response);

        // Step 5: Merge with master template
        $final_graph = $this->merge_schemas($post_id, $validated_schemas, $article_data);

        // Step 6: Store in post meta
        update_post_meta($post_id, '_schema_genie_ai_data', wp_json_encode($final_graph));
        update_post_meta($post_id, '_schema_genie_ai_generated', current_time('mysql'));
        update_post_meta($post_id, '_schema_genie_ai_status', 'success');

        // Clean up any old Rank Math sync data from previous plugin versions
        // (writing to rank_math_schema causes JS crashes in Rank Math's admin)
        delete_post_meta($post_id, 'rank_math_schema');
        delete_post_meta($post_id, '_schema_genie_ai_rm_synced');

        return $final_graph;
    }


    /**
     * Extract structured data from the article.
     */
    private function extract_article_data(WP_Post $post): array {
        $content_limit = (int) get_option('schema_genie_ai_content_limit', 4000);

        // Strip HTML but preserve heading structure as text markers
        $content = $post->post_content;

        // Convert headings to text markers before stripping
        $content = preg_replace('/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/si', "\n## $2\n", $content);

        // Strip remaining HTML
        $content = wp_strip_all_tags($content);

        // Normalize whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        // Truncate
        if (mb_strlen($content) > $content_limit) {
            $content = mb_substr($content, 0, $content_limit) . '...';
        }

        // Categories
        $categories = [];
        $cat_terms = get_the_terms($post->ID, 'category');
        if ($cat_terms && !is_wp_error($cat_terms)) {
            foreach ($cat_terms as $term) {
                $categories[] = $term->name;
            }
        }

        // Tags
        $tags = [];
        $tag_terms = get_the_terms($post->ID, 'post_tag');
        if ($tag_terms && !is_wp_error($tag_terms)) {
            foreach ($tag_terms as $term) {
                $tags[] = $term->name;
            }
        }

        // Excerpt
        $excerpt = $post->post_excerpt;
        if (empty($excerpt)) {
            $excerpt = wp_trim_words($content, 30, '...');
        }

        return [
            'post_id'    => $post->ID,
            'title'      => get_the_title($post->ID),
            'permalink'  => get_permalink($post->ID),
            'excerpt'    => $excerpt,
            'content'    => $content,
            'categories' => $categories,
            'tags'       => $tags,
            'date'       => get_the_date('Y-m-d', $post->ID),
            'modified'   => get_the_modified_date('Y-m-d', $post->ID),
            'author'     => 'István Cocron',
        ];
    }

    /**
     * Build the AI system prompt.
     */
    private function build_system_prompt(): string {
        $cities = $this->template->get_service_cities();
        $cities_list = implode(', ', $cities);

        return <<<PROMPT
You are a Schema.org structured data engineer specializing in German law firm websites.
Your task is to analyze an article and generate JSON-LD schema entities.

CRITICAL RULES:
1. Return ONLY a valid JSON object. No markdown, no explanation, no code fences, no extra text.
2. The JSON must have a top-level key "schemas" containing an array of schema objects.
3. Each schema object MUST have a valid "@type" property.
4. All text content must remain in the original language of the article (German).

SCHEMA TYPES TO GENERATE:

A) "WebPage" enrichment object:
   - If the article contains Q&A-style content (questions and answers), return a WebPage object with:
     - "@type": ["WebPage", "FAQPage"]
     - "mainEntity": array of Question objects with acceptedAnswer
   - Extract REAL questions from the content. Do NOT fabricate questions.
   - If no Q&A content exists, return "@type": "WebPage" without FAQ.
   - Include ONLY @type and mainEntity (if FAQ). Other WebPage fields are handled separately.

B) "LegalService" entities (one per city: {$cities_list}):
   - ONLY generate these if the article discusses specific legal services, legal practice areas, or attorney services.
   - Generate one LegalService entity for EACH city: {$cities_list}
   - Each must include: name, url, image, description, areaServed, provider, availableLanguage, legalName, serviceType
   - "name" format: "Rechtsanwalt István Cocron – [Legal Area] in [City]"
   - "description" format: "Fachanwalt für [Area] in [City] – [short service description]."
   - "image": "https://ra-cocron.de/wp-content/uploads/2024/06/cr-favicon.png"
   - "provider": { "@type": "Organization", "name": "Rechtsanwalt Cocron GmbH & Co. KG", "url": "https://ra-cocron.de" }
   - "availableLanguage": ["de"]
   - "legalName": "Rechtsanwalt Cocron GmbH & Co. KG"
   - If the article is NOT about legal services, DO NOT include any LegalService entities.

C) "HowTo" (optional):
   - ONLY if the article contains step-by-step instructions or procedural content.
   - Include: name, description, step (array of HowToStep with name and text)
   - Each step must be concise and actionable.
   - If no procedural content exists, DO NOT include HowTo.

D) "NewsArticle":
   - ALWAYS generate this.
   - Include: headline, datePublished, dateModified, publisher, mainEntityOfPage, articleSection, inLanguage, name
   - "publisher": { "@type": "Organization", "name": "Rechtsanwalt Cocron GmbH & Co. KG", "logo": { "@type": "ImageObject", "url": "https://ra-cocron.de/wp-content/uploads/2024/06/cr-favicon.png" } }
   - "articleSection": must be one of the German legal practice areas (e.g., "Erbrecht", "Arbeitsrecht", "Familienrecht", "Strafrecht", "Verkehrsrecht", "Mietrecht", "Handelsrecht", "Gesellschaftsrecht", "Insolvenzrecht", "Allgemeines Recht") — choose the most relevant one.
   - "inLanguage": "de"

CONSTRAINTS:
- Do NOT generate Place, Organization, WebSite, Person, or ImageObject schemas. Those are generated separately.
- Do NOT include "@context" in any schema object. It is added at the graph level.
- Keep descriptions under 160 characters where applicable.
- All URLs must use "https://ra-cocron.de" as the base.
PROMPT;
    }

    /**
     * Build the user prompt with article data.
     */
    private function build_user_prompt(array $data): string {
        $cats = !empty($data['categories']) ? implode(', ', $data['categories']) : 'None';
        $tags = !empty($data['tags']) ? implode(', ', $data['tags']) : 'None';

        return <<<PROMPT
Analyze this article and generate the appropriate schema markup:

Article URL: {$data['permalink']}
Title: {$data['title']}
Published: {$data['date']}
Modified: {$data['modified']}
Author: {$data['author']}
Categories: {$cats}
Tags: {$tags}

Excerpt:
{$data['excerpt']}

Content:
{$data['content']}

Generate the JSON schema objects for this article following the system instructions.
PROMPT;
    }

    /**
     * Validate and sanitize the AI response.
     *
     * @param array $parsed The parsed JSON from AI.
     * @return array Validated array of schema entities.
     * @throws Exception If validation fails.
     */
    private function validate_ai_response(array $parsed): array {
        if (!isset($parsed['schemas']) || !is_array($parsed['schemas'])) {
            throw new Exception(
                __('AI response missing "schemas" array. Keys found: ', 'schema-genie-ai') .
                implode(', ', array_keys($parsed))
            );
        }

        $allowed_types = [
            'WebPage', 'FAQPage', 'LegalService', 'HowTo',
            'NewsArticle', 'Article',
        ];

        $validated = [];

        foreach ($parsed['schemas'] as $index => $schema) {
            if (!is_array($schema)) {
                continue;
            }

            // Must have @type
            if (!isset($schema['@type'])) {
                continue;
            }

            // Check @type is allowed (can be string or array)
            $types = is_array($schema['@type']) ? $schema['@type'] : [$schema['@type']];
            $valid_type = false;
            foreach ($types as $t) {
                if (in_array($t, $allowed_types, true)) {
                    $valid_type = true;
                    break;
                }
            }
            if (!$valid_type) {
                continue;
            }

            // Sanitize recursively
            $schema = $this->sanitize_recursive($schema);

            $validated[] = $schema;
        }

        if (empty($validated)) {
            throw new Exception(__('No valid schema entities in AI response after validation.', 'schema-genie-ai'));
        }

        return $validated;
    }

    /**
     * Recursively sanitize all string values in a schema array.
     */
    private function sanitize_recursive(array $data): array {
        foreach ($data as $key => &$value) {
            if (is_string($value)) {
                // Allow basic HTML entities but strip tags
                $value = wp_kses($value, []);
                $value = trim($value);
            } elseif (is_array($value)) {
                $value = $this->sanitize_recursive($value);
            }
            // Numbers, booleans pass through unchanged
        }
        return $data;
    }

    /**
     * Merge AI-generated schemas with the master template.
     */
    private function merge_schemas(int $post_id, array $ai_schemas, array $article_data): array {
        // Start with the master template
        $graph = $this->template->get_master_template($post_id);
        $permalink = $article_data['permalink'];

        // Track if AI provided a FAQPage-enhanced WebPage
        $has_faq_webpage = false;

        foreach ($ai_schemas as $schema) {
            $types = is_array($schema['@type']) ? $schema['@type'] : [$schema['@type']];

            // Handle WebPage/FAQPage — merge into existing WebPage from master template
            if (in_array('WebPage', $types, true) || in_array('FAQPage', $types, true)) {
                if (in_array('FAQPage', $types, true) && isset($schema['mainEntity'])) {
                    $has_faq_webpage = true;
                    // Find and upgrade the WebPage in the graph
                    foreach ($graph as &$entity) {
                        if (isset($entity['@type']) && $entity['@type'] === 'WebPage') {
                            // Upgrade to combined type
                            $entity['@type'] = ['WebPage', 'FAQPage'];
                            $entity['mainEntity'] = $schema['mainEntity'];
                            break;
                        }
                    }
                    unset($entity);
                }
                // Don't add WebPage as separate entity (already in master template)
                continue;
            }

            // LegalService — add the article URL
            if (in_array('LegalService', $types, true)) {
                if (!isset($schema['url'])) {
                    $schema['url'] = $permalink;
                }
                $graph[] = $schema;
                continue;
            }

            // HowTo — add as-is
            if (in_array('HowTo', $types, true)) {
                $graph[] = $schema;
                continue;
            }

            // NewsArticle — enrich with mainEntityOfPage
            if (in_array('NewsArticle', $types, true) || in_array('Article', $types, true)) {
                if (!isset($schema['mainEntityOfPage'])) {
                    $schema['mainEntityOfPage'] = [
                        '@type' => 'WebPage',
                        '@id'   => $permalink,
                    ];
                }
                $graph[] = $schema;
                continue;
            }

            // Any other allowed type
            $graph[] = $schema;
        }

        return $graph;
    }
}
