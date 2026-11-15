<?php
/**
 * NOVA Boutique theme functions.
 *
 * @package NOVA_Boutique
 */

defined('ABSPATH') || exit;

const NOVA_BOUTIQUE_VERSION = '1.0.0';

function nova_boutique_setup(): void {
    load_theme_textdomain('nova-boutique', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', [
        'height'      => 120,
        'width'       => 320,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    register_nav_menus([
        'primary' => __('Navigation principale', 'nova-boutique'),
    ]);
}
add_action('after_setup_theme', 'nova_boutique_setup');

function nova_boutique_enqueue_assets(): void {
    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();
    $style_file = $theme_dir . '/style.css';
    $script_file = $theme_dir . '/assets/js/app.js';

    wp_enqueue_style(
        'nova-boutique-fonts',
        'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap',
        [],
        null
    );
    wp_enqueue_style(
        'nova-boutique-style',
        get_stylesheet_uri(),
        ['nova-boutique-fonts'],
        file_exists($style_file) ? (string) filemtime($style_file) : NOVA_BOUTIQUE_VERSION
    );

    if (is_front_page() || is_page_template('page-templates/storefront.php')) {
        wp_enqueue_script(
            'nova-boutique-app',
            $theme_uri . '/assets/js/app.js',
            [],
            file_exists($script_file) ? (string) filemtime($script_file) : NOVA_BOUTIQUE_VERSION,
            true
        );
        $config = [
            'apiBase' => esc_url_raw(rest_url('wpcatalog/v1')),
            'locale'  => 'fr-MA',
        ];
        wp_add_inline_script(
            'nova-boutique-app',
            'window.WPCatalogConfig = ' . wp_json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';',
            'before'
        );
    }
}
add_action('wp_enqueue_scripts', 'nova_boutique_enqueue_assets');

function nova_boutique_document_title(string $title): string {
    if (is_front_page()) {
        return __('NOVA Boutique — Équipement & solutions', 'nova-boutique');
    }
    return $title;
}
add_filter('pre_get_document_title', 'nova_boutique_document_title');

/** Keep the theme clean of default block-theme global styles that are not used by this classic storefront. */
function nova_boutique_remove_unused_global_styles(): void {
    if (is_front_page() || is_page_template('page-templates/storefront.php')) {
        wp_dequeue_style('global-styles');
        wp_dequeue_style('classic-theme-styles');
    }
}
add_action('wp_enqueue_scripts', 'nova_boutique_remove_unused_global_styles', 100);
