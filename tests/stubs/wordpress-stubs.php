<?php
/**
 * WordPress function stubs for PHPStan
 */

function wp_verify_nonce($nonce, $action) { return true; }
function sanitize_text_field($str) { return $str; }
function esc_html($text) { return $text; }
function esc_attr($text) { return $text; }
function esc_url($url) { return $url; }
function wp_die($message) { exit($message); }
function current_user_can($capability) { return true; }
function get_current_user_id() { return 1; }
function is_admin() { return false; }
function wp_create_user($username, $password, $email) { return 1; }
function update_user_meta($user_id, $meta_key, $meta_value) { return true; }
function get_user_meta($user_id, $key, $single = false) { return ''; }
function wp_cache_get($key, $group = '') { return false; }
function wp_cache_set($key, $data, $group = '', $expire = 0) { return true; }
function apply_filters($tag, $value) { return $value; }
function do_action($tag) { return null; }
function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) { return true; }
function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) { return true; }
