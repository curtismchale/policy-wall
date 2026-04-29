<?php

/**
 * Minimal WordPress function stubs for testing.
 *
 * Covers every call made at file-include time so the plugin can be loaded
 * without a full WordPress install. Uses if(!function_exists) guards so
 * Patchwork can patch these definitions on a per-test basis via Brain\Monkey.
 *
 * Functions only called inside methods (check_ajax_referer, wp_send_json_*,
 * etc.) are NOT defined here — Brain\Monkey defines them fresh per test.
 */

namespace {
    if (! defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }
    if (!function_exists('add_action')) {
        function add_action() { return true; }
    }
    if (!function_exists('add_filter')) {
        function add_filter() { return true; }
    }
    if (!function_exists('add_shortcode')) {
        function add_shortcode() {}
    }
    if (!function_exists('plugin_dir_path')) {
        // Mirrors real WP behaviour: returns the directory of the given file
        // with a trailing slash. Critical so that include_once paths resolve
        // to the actual plugin files when bootstrap loads policy-wall.php.
        function plugin_dir_path(string $file): string {
            return dirname($file) . '/';
        }
    }
    if (!function_exists('register_activation_hook')) {
        function register_activation_hook() {}
    }
    if (!function_exists('register_deactivation_hook')) {
        function register_deactivation_hook() {}
    }
    if (!function_exists('register_uninstall_hook')) {
        function register_uninstall_hook() {}
    }
    if (!function_exists('is_plugin_active')) {
        function is_plugin_active(): bool { return false; }
    }
    if (!function_exists('absint')) {
        function absint(mixed $n): int { return abs((int) $n); }
    }
    // esc_html is used by the XSS fix in getPolicySelectOptions(); implement
    // it as a real escaping function so the XSS test verifies actual output.
    if (!function_exists('esc_html')) {
        function esc_html(string $text): string {
            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }
    // get_post_meta is called inside saveUserToPolicy(); stubbed here so the
    // happy-path test can override it per-test via Brain\Monkey.
    if (!function_exists('get_post_meta')) {
        function get_post_meta(): mixed { return ''; }
    }
    if (!function_exists('wp_kses_post')) {
        function wp_kses_post(string $content): string { return $content; }
    }
    if (!function_exists('apply_filters')) {
        function apply_filters(string $_tag, mixed $value): mixed { return $value; }
    }
    if (!function_exists('update_user_meta')) {
        function update_user_meta(): mixed { return true; }
    }
    if (!function_exists('shortcode_atts')) {
        function shortcode_atts(array $pairs, mixed $atts): array {
            $atts = (array) $atts;
            $out  = [];
            foreach ($pairs as $name => $default) {
                $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
            }
            return $out;
        }
    }
    if (!function_exists('get_post')) {
        function get_post(): mixed { return null; }
    }
    if (!function_exists('get_transient')) {
        function get_transient(): mixed { return false; }
    }
    if (!function_exists('set_transient')) {
        function set_transient(): bool { return true; }
    }
    if (!function_exists('get_posts')) {
        function get_posts(): array { return []; }
    }
    if (!function_exists('is_user_logged_in')) {
        function is_user_logged_in(): bool { return false; }
    }
    if (!function_exists('is_admin')) {
        function is_admin(): bool { return false; }
    }
    if (!function_exists('wp_doing_ajax')) {
        function wp_doing_ajax(): bool { return false; }
    }
    if (!function_exists('is_page')) {
        function is_page(): bool { return false; }
    }
    if (!function_exists('wp_get_current_user')) {
        function wp_get_current_user(): object { return new stdClass(); }
    }
    if (!function_exists('wp_safe_redirect')) {
        function wp_safe_redirect(): void {}
    }
    if (!function_exists('get_option')) {
        function get_option(): mixed { return false; }
    }
    if (!function_exists('get_permalink')) {
        function get_permalink(): string { return ''; }
    }
    if (!function_exists('user_can')) {
        function user_can(): bool { return false; }
    }
    if (!function_exists('get_user_meta')) {
        function get_user_meta(): mixed { return ''; }
    }
}
