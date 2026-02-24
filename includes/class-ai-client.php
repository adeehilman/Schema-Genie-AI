<?php
/**
 * Azure OpenAI API client wrapper.
 * Handles all communication with the Azure OpenAI Chat Completions API.
 */
defined('ABSPATH') || exit;

class Schema_Genie_AI_Client {

    /**
     * Rate limit: max calls per minute.
     * Set to 9 for safety margin (API limit is 10).
     */
    const RATE_LIMIT = 9;
    const RATE_WINDOW = 60; // seconds

    /**
     * Call Azure OpenAI Chat Completions API.
     *
     * @param string $system_prompt The system prompt.
     * @param string $user_prompt   The user prompt (article content).
     * @return array Parsed JSON response from AI.
     * @throws Exception On error or invalid response.
     */
    public function call(string $system_prompt, string $user_prompt): array {
        // Check rate limit BEFORE making the call
        $this->check_rate_limit();

        // Record this call timestamp immediately
        $this->record_rate_call();

        // Build request
        $api_key = Schema_Genie_AI_Settings::get_api_key();
        if (empty($api_key)) {
            throw new Exception(__('API key not configured. Go to Settings > Schema Genie AI.', 'schema-genie-ai'));
        }

        $endpoint    = get_option('schema_genie_ai_azure_endpoint', '');
        $api_version = get_option('schema_genie_ai_azure_api_version', '2025-01-01-preview');
        $model       = get_option('schema_genie_ai_model', 'gpt-4o');
        $timeout     = (int) get_option('schema_genie_ai_timeout', 45);
        $max_tokens  = (int) get_option('schema_genie_ai_max_tokens', 2000);
        $temperature = (float) get_option('schema_genie_ai_temperature', 0.1);

        if (empty($endpoint)) {
            throw new Exception(__('Azure endpoint not configured.', 'schema-genie-ai'));
        }

        // Azure OpenAI API URL
        $url = rtrim($endpoint, '/') .
               '/openai/deployments/' . urlencode($model) .
               '/chat/completions?api-version=' . urlencode($api_version);

        $headers = [
            'Content-Type' => 'application/json',
            'api-key'      => $api_key,
        ];

        $body = [
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $user_prompt],
            ],
            'temperature'     => $temperature,
            'max_tokens'      => $max_tokens,
            'response_format' => ['type' => 'json_object'],
        ];

        // Make the request
        $response = wp_remote_post($url, [
            'headers'   => $headers,
            'body'      => wp_json_encode($body),
            'timeout'   => $timeout,
            'sslverify' => true,
        ]);

        // Handle WP errors (network, timeout, etc.)
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            throw new Exception(
                sprintf(__('API request failed: %s', 'schema-genie-ai'), $error_msg)
            );
        }

        // Check HTTP status
        $status_code = wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $error_detail = $this->extract_api_error($raw_body);
            throw new Exception(
                sprintf(
                    __('Azure API returned HTTP %d: %s', 'schema-genie-ai'),
                    $status_code,
                    $error_detail
                )
            );
        }

        // Parse outer response
        $response_data = json_decode($raw_body, true);
        if (!is_array($response_data)) {
            throw new Exception(__('Could not parse API response JSON.', 'schema-genie-ai'));
        }

        // Extract the AI message content
        if (!isset($response_data['choices'][0]['message']['content'])) {
            throw new Exception(__('Unexpected API response structure (no choices).', 'schema-genie-ai'));
        }

        $ai_content = $response_data['choices'][0]['message']['content'];

        // Extract token usage for cost tracking
        $usage = isset($response_data['usage']) ? $response_data['usage'] : [];

        // Parse the AI's JSON output
        $parsed = json_decode($ai_content, true);
        if (!is_array($parsed)) {
            throw new Exception(
                sprintf(
                    __('AI returned invalid JSON. Raw: %s', 'schema-genie-ai'),
                    mb_substr($ai_content, 0, 500)
                )
            );
        }

        // Attach usage info
        $parsed['_usage'] = $usage;
        $parsed['_raw']   = $ai_content;

        return $parsed;
    }

    /**
     * Extract a human-readable error from the Azure API error response.
     */
    private function extract_api_error(string $body): string {
        $data = json_decode($body, true);
        if (isset($data['error']['message'])) {
            return $data['error']['message'];
        }
        return mb_substr($body, 0, 300);
    }

    /**
     * Get call timestamps within the current rate window (sliding window).
     *
     * @return array Array of Unix timestamps of recent calls.
     */
    private function get_rate_calls(): array {
        $calls = get_transient('sgai_rate_timestamps');
        if (!is_array($calls)) {
            return [];
        }
        // Prune calls older than the rate window
        $cutoff = time() - self::RATE_WINDOW;
        return array_values(array_filter($calls, function ($ts) use ($cutoff) {
            return $ts > $cutoff;
        }));
    }

    /**
     * Check if we've exceeded the rate limit using a sliding window.
     *
     * @throws Exception If rate limit is exceeded.
     */
    private function check_rate_limit() {
        $recent_calls = $this->get_rate_calls();
        if (count($recent_calls) >= self::RATE_LIMIT) {
            // Calculate how many seconds until the oldest call expires
            $oldest = min($recent_calls);
            $wait = ($oldest + self::RATE_WINDOW) - time();
            throw new Exception(
                sprintf(
                    __('Rate limit reached (%d calls/minute). Please wait %d seconds and try again.', 'schema-genie-ai'),
                    self::RATE_LIMIT,
                    max(1, $wait)
                )
            );
        }
    }

    /**
     * Record a new API call timestamp for rate limiting.
     */
    private function record_rate_call() {
        $calls = $this->get_rate_calls();
        $calls[] = time();
        // Store with a generous TTL (2x window) so stale data gets cleaned up
        set_transient('sgai_rate_timestamps', $calls, self::RATE_WINDOW * 2);
    }
}
