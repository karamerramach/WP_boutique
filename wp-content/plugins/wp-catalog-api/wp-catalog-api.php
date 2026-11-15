<?php
/**
 * Plugin Name: WP Catalogue Commerce
 * Description: Boutique découplée WordPress avec catalogue, stock, panier et commandes via une API REST personnalisée.
 * Version: 1.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Karam Erramach
 * License: GPL-2.0-or-later
 * Text Domain: wp-catalog-api
 */

defined('ABSPATH') || exit;

final class WP_Catalog_API {
    public const VERSION = '1.1.0';
    public const REST_NAMESPACE = 'wpcatalog/v1';
    public const POST_TYPE = 'catalog_product';
    public const ORDER_TYPE = 'catalog_order';
    public const TAXONOMY = 'catalog_category';

    private static ?self $instance = null;

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_content_types']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('admin_init', [$this, 'maybe_upgrade']);
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_product_meta_box'], 10, 2);
        add_action('save_post_' . self::POST_TYPE, [$this, 'sync_product_data'], 99, 2);
        add_action('save_post_' . self::ORDER_TYPE, [$this, 'save_order_meta_box'], 10, 2);
        add_filter('manage_' . self::ORDER_TYPE . '_posts_columns', [$this, 'order_admin_columns']);
        add_action('manage_' . self::ORDER_TYPE . '_posts_custom_column', [$this, 'render_order_admin_column'], 10, 2);
        add_action('before_delete_post', [$this, 'delete_product_data']);
        add_filter('allowed_http_origins', [$this, 'filter_allowed_origins']);
    }

    public static function activate(): void {
        self::instance()->register_content_types();
        self::create_table();
        self::add_role_capabilities();
        update_option('wpcatalog_db_version', self::VERSION);
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    private static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'wpcatalog_products';
    }

    private static function create_table(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            post_id bigint(20) unsigned NOT NULL,
            sku varchar(80) DEFAULT NULL,
            price decimal(12,2) NOT NULL DEFAULT 0.00,
            sale_price decimal(12,2) NOT NULL DEFAULT 0.00,
            currency char(3) NOT NULL DEFAULT 'MAD',
            stock_status varchar(20) NOT NULL DEFAULT 'instock',
            stock_quantity int(11) NOT NULL DEFAULT 0,
            manage_stock tinyint(1) NOT NULL DEFAULT 0,
            brand varchar(120) NOT NULL DEFAULT '',
            featured tinyint(1) NOT NULL DEFAULT 0,
            sales_count bigint(20) unsigned NOT NULL DEFAULT 0,
            attributes longtext NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (post_id),
            UNIQUE KEY sku (sku),
            KEY price (price),
            KEY sale_price (sale_price),
            KEY stock_status (stock_status),
            KEY featured (featured),
            KEY brand (brand),
            KEY sales_count (sales_count)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    public function maybe_upgrade(): void {
        if (version_compare((string) get_option('wpcatalog_db_version', '1.0.0'), self::VERSION, '<')) {
            self::create_table();
            update_option('wpcatalog_db_version', self::VERSION);
        }
    }

    private static function add_role_capabilities(): void {
        $caps = [
            'edit_catalog_product', 'read_catalog_product', 'delete_catalog_product',
            'edit_catalog_products', 'edit_others_catalog_products', 'publish_catalog_products',
            'read_private_catalog_products', 'delete_catalog_products', 'delete_private_catalog_products',
            'delete_published_catalog_products', 'delete_others_catalog_products', 'edit_private_catalog_products',
            'edit_published_catalog_products', 'create_catalog_products',
        ];
        foreach (['administrator', 'editor'] as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($caps as $cap) {
                    $role->add_cap($cap);
                }
            }
        }
    }

    public function register_content_types(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Produits', 'wp-catalog-api'),
                'singular_name' => __('Produit', 'wp-catalog-api'),
                'add_new_item' => __('Ajouter un produit', 'wp-catalog-api'),
                'edit_item' => __('Modifier le produit', 'wp-catalog-api'),
                'all_items' => __('Tous les produits', 'wp-catalog-api'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-store',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => ['catalog_product', 'catalog_products'],
            'map_meta_cap' => true,
        ]);

        register_taxonomy(self::TAXONOMY, [self::POST_TYPE], [
            'labels' => [
                'name' => __('Catégories', 'wp-catalog-api'),
                'singular_name' => __('Catégorie', 'wp-catalog-api'),
                'all_items' => __('Toutes les catégories', 'wp-catalog-api'),
                'add_new_item' => __('Ajouter une catégorie', 'wp-catalog-api'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => false,
            'hierarchical' => true,
            'rewrite' => false,
        ]);

        register_post_type(self::ORDER_TYPE, [
            'labels' => [
                'name' => __('Commandes', 'wp-catalog-api'),
                'singular_name' => __('Commande', 'wp-catalog-api'),
                'all_items' => __('Toutes les commandes', 'wp-catalog-api'),
                'edit_item' => __('Consulter la commande', 'wp-catalog-api'),
            ],
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=' . self::POST_TYPE,
            'show_in_rest' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public function filter_allowed_origins(array $origins): array {
        $configured = apply_filters('wpcatalog_allowed_origins', []);
        if (!is_array($configured)) {
            return $origins;
        }
        foreach ($configured as $origin) {
            if (is_string($origin) && preg_match('#^https?://[a-z0-9.-]+(?::\d+)?$#i', $origin)) {
                $origins[] = untrailingslashit($origin);
            }
        }
        return array_values(array_unique($origins));
    }

    private static function default_store_settings(): array {
        return [
            'store_name' => 'NOVA Boutique',
            'currency' => 'MAD',
            'contact_email' => get_option('admin_email'),
            'shipping_fee' => '35.00',
            'free_shipping_threshold' => '600.00',
            'cod_enabled' => 1,
            'bank_transfer_enabled' => 1,
            'bank_transfer_instructions' => 'Nous vous communiquerons les coordonnées bancaires après validation de la commande.',
        ];
    }

    private static function store_settings(): array {
        $saved = get_option('wpcatalog_store_settings', []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::default_store_settings());
    }

    public function register_settings_page(): void {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            __('Paramètres de la boutique', 'wp-catalog-api'),
            __('Paramètres boutique', 'wp-catalog-api'),
            'manage_options',
            'wpcatalog-settings',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_POST['wpcatalog_save_settings'])) {
            check_admin_referer('wpcatalog_save_settings_action', 'wpcatalog_settings_nonce');
            $raw = isset($_POST['wpcatalog_settings']) && is_array($_POST['wpcatalog_settings']) ? wp_unslash($_POST['wpcatalog_settings']) : [];
            $currency = strtoupper(sanitize_text_field((string) ($raw['currency'] ?? 'MAD')));
            $email = sanitize_email((string) ($raw['contact_email'] ?? get_option('admin_email')));
            $new = [
                'store_name' => sanitize_text_field((string) ($raw['store_name'] ?? 'NOVA Boutique')),
                'currency' => preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'MAD',
                'contact_email' => is_email($email) ? $email : get_option('admin_email'),
                'shipping_fee' => is_numeric($raw['shipping_fee'] ?? null) ? number_format(max(0, (float) $raw['shipping_fee']), 2, '.', '') : '0.00',
                'free_shipping_threshold' => is_numeric($raw['free_shipping_threshold'] ?? null) ? number_format(max(0, (float) $raw['free_shipping_threshold']), 2, '.', '') : '0.00',
                'cod_enabled' => !empty($raw['cod_enabled']) ? 1 : 0,
                'bank_transfer_enabled' => !empty($raw['bank_transfer_enabled']) ? 1 : 0,
                'bank_transfer_instructions' => sanitize_textarea_field((string) ($raw['bank_transfer_instructions'] ?? '')),
            ];
            update_option('wpcatalog_store_settings', $new);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Paramètres enregistrés.', 'wp-catalog-api') . '</p></div>';
        }
        $settings = self::store_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Paramètres de la boutique', 'wp-catalog-api'); ?></h1>
            <p><?php esc_html_e('Configurez les informations principales utilisées par la vitrine et le calcul de livraison.', 'wp-catalog-api'); ?></p>
            <form method="post">
                <?php wp_nonce_field('wpcatalog_save_settings_action', 'wpcatalog_settings_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tr><th><label for="wpcatalog_store_name">Nom de la boutique</label></th><td><input class="regular-text" id="wpcatalog_store_name" name="wpcatalog_settings[store_name]" value="<?php echo esc_attr($settings['store_name']); ?>" required></td></tr>
                    <tr><th><label for="wpcatalog_currency">Devise</label></th><td><input class="small-text" id="wpcatalog_currency" name="wpcatalog_settings[currency]" maxlength="3" value="<?php echo esc_attr($settings['currency']); ?>"><p class="description">Code ISO 4217, par exemple MAD, EUR ou USD.</p></td></tr>
                    <tr><th><label for="wpcatalog_contact_email">E-mail de contact</label></th><td><input class="regular-text" type="email" id="wpcatalog_contact_email" name="wpcatalog_settings[contact_email]" value="<?php echo esc_attr($settings['contact_email']); ?>"></td></tr>
                    <tr><th><label for="wpcatalog_shipping_fee">Frais de livraison</label></th><td><input class="regular-text" type="number" min="0" step="0.01" id="wpcatalog_shipping_fee" name="wpcatalog_settings[shipping_fee]" value="<?php echo esc_attr($settings['shipping_fee']); ?>"></td></tr>
                    <tr><th><label for="wpcatalog_free_shipping_threshold">Seuil de livraison offerte</label></th><td><input class="regular-text" type="number" min="0" step="0.01" id="wpcatalog_free_shipping_threshold" name="wpcatalog_settings[free_shipping_threshold]" value="<?php echo esc_attr($settings['free_shipping_threshold']); ?>"><p class="description">Mettre 0 pour désactiver la livraison offerte.</p></td></tr>
                    <tr><th>Moyens de paiement</th><td>
                        <label><input type="checkbox" name="wpcatalog_settings[cod_enabled]" value="1" <?php checked((int) $settings['cod_enabled'], 1); ?>> Paiement à la livraison</label><br>
                        <label><input type="checkbox" name="wpcatalog_settings[bank_transfer_enabled]" value="1" <?php checked((int) $settings['bank_transfer_enabled'], 1); ?>> Virement bancaire</label>
                    </td></tr>
                    <tr><th><label for="wpcatalog_bank_transfer_instructions">Instructions de virement</label></th><td><textarea class="large-text" rows="4" id="wpcatalog_bank_transfer_instructions" name="wpcatalog_settings[bank_transfer_instructions]"><?php echo esc_textarea($settings['bank_transfer_instructions']); ?></textarea></td></tr>
                </table>
                <p><button type="submit" name="wpcatalog_save_settings" value="1" class="button button-primary">Enregistrer les paramètres</button></p>
            </form>
        </div>
        <?php
    }

    public function add_meta_boxes(): void {
        add_meta_box('wpcatalog_product_data', __('Données commerciales', 'wp-catalog-api'), [$this, 'render_product_meta_box'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('wpcatalog_order_data', __('Détails de la commande', 'wp-catalog-api'), [$this, 'render_order_meta_box'], self::ORDER_TYPE, 'normal', 'high');
    }

    public function render_product_meta_box(WP_Post $post): void {
        wp_nonce_field('wpcatalog_save_product', 'wpcatalog_product_nonce');
        $sku = (string) get_post_meta($post->ID, '_wpcatalog_sku', true);
        $price = (string) get_post_meta($post->ID, '_wpcatalog_price', true);
        $sale_price = (string) get_post_meta($post->ID, '_wpcatalog_sale_price', true);
        $settings = self::store_settings();
        $currency = (string) (get_post_meta($post->ID, '_wpcatalog_currency', true) ?: $settings['currency']);
        $stock = (string) (get_post_meta($post->ID, '_wpcatalog_stock_status', true) ?: 'instock');
        $quantity = (int) get_post_meta($post->ID, '_wpcatalog_stock_quantity', true);
        $manage_stock = (bool) get_post_meta($post->ID, '_wpcatalog_manage_stock', true);
        $brand = (string) get_post_meta($post->ID, '_wpcatalog_brand', true);
        $featured = (bool) get_post_meta($post->ID, '_wpcatalog_featured', true);
        $attributes = get_post_meta($post->ID, '_wpcatalog_attributes', true);
        $attributes_json = wp_json_encode(is_array($attributes) ? $attributes : [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        ?>
        <style>.wpcatalog-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;max-width:960px}.wpcatalog-fields label{display:block;font-weight:600;margin-bottom:5px}.wpcatalog-fields input,.wpcatalog-fields select,.wpcatalog-fields textarea{width:100%}.wpcatalog-fields .wide{grid-column:1/-1}.wpcatalog-flags{display:flex;gap:24px;align-items:center;flex-wrap:wrap}.wpcatalog-flags label{display:inline-flex;gap:7px;align-items:center}.wpcatalog-flags input{width:auto}</style>
        <div class="wpcatalog-fields">
            <div><label for="wpcatalog_sku">Référence / SKU</label><input id="wpcatalog_sku" name="wpcatalog_sku" type="text" maxlength="80" value="<?php echo esc_attr($sku); ?>"></div>
            <div><label for="wpcatalog_brand">Marque</label><input id="wpcatalog_brand" name="wpcatalog_brand" type="text" maxlength="120" value="<?php echo esc_attr($brand); ?>"></div>
            <div><label for="wpcatalog_price">Prix normal</label><input id="wpcatalog_price" name="wpcatalog_price" type="number" min="0" step="0.01" value="<?php echo esc_attr($price); ?>" required></div>
            <div><label for="wpcatalog_sale_price">Prix promotionnel (facultatif)</label><input id="wpcatalog_sale_price" name="wpcatalog_sale_price" type="number" min="0" step="0.01" value="<?php echo esc_attr($sale_price); ?>"></div>
            <div><label for="wpcatalog_currency">Devise (ISO 4217)</label><input id="wpcatalog_currency" name="wpcatalog_currency" type="text" maxlength="3" value="<?php echo esc_attr($currency); ?>"></div>
            <div><label for="wpcatalog_stock_quantity">Quantité en stock</label><input id="wpcatalog_stock_quantity" name="wpcatalog_stock_quantity" type="number" min="0" step="1" value="<?php echo esc_attr((string) $quantity); ?>"></div>
            <div><label for="wpcatalog_stock_status">Disponibilité (si stock non suivi)</label><select id="wpcatalog_stock_status" name="wpcatalog_stock_status"><?php foreach (['instock' => 'En stock', 'outofstock' => 'Rupture de stock', 'backorder' => 'Disponible sur commande'] as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($stock, $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
            <div class="wide wpcatalog-flags">
                <label><input type="checkbox" name="wpcatalog_manage_stock" value="1" <?php checked($manage_stock); ?>> Gérer automatiquement le stock</label>
                <label><input type="checkbox" name="wpcatalog_featured" value="1" <?php checked($featured); ?>> Mettre en avant</label>
            </div>
            <div class="wide"><label for="wpcatalog_attributes">Caractéristiques (objet JSON)</label><textarea id="wpcatalog_attributes" name="wpcatalog_attributes" rows="6" spellcheck="false"><?php echo esc_textarea($attributes_json ?: '{}'); ?></textarea><p class="description">Exemple : {"couleur":"noir","puissance":"1200 W","garantie":"2 ans"}. Les clés peuvent servir aux filtres de l’API.</p></div>
        </div>
        <?php
    }

    public function save_product_meta_box(int $post_id, WP_Post $post): void {
        if (!isset($_POST['wpcatalog_product_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpcatalog_product_nonce'])), 'wpcatalog_save_product')) {
            return;
        }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
            return;
        }
        $sku = isset($_POST['wpcatalog_sku']) ? sanitize_text_field(wp_unslash($_POST['wpcatalog_sku'])) : '';
        $brand = isset($_POST['wpcatalog_brand']) ? sanitize_text_field(wp_unslash($_POST['wpcatalog_brand'])) : '';
        $price_raw = isset($_POST['wpcatalog_price']) ? trim((string) wp_unslash($_POST['wpcatalog_price'])) : '0';
        $sale_raw = isset($_POST['wpcatalog_sale_price']) ? trim((string) wp_unslash($_POST['wpcatalog_sale_price'])) : '';
        $currency = isset($_POST['wpcatalog_currency']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['wpcatalog_currency']))) : 'MAD';
        $stock = isset($_POST['wpcatalog_stock_status']) ? sanitize_key(wp_unslash($_POST['wpcatalog_stock_status'])) : 'instock';
        $quantity_raw = isset($_POST['wpcatalog_stock_quantity']) ? trim((string) wp_unslash($_POST['wpcatalog_stock_quantity'])) : '0';
        $quantity = filter_var($quantity_raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($quantity === false) {
            return;
        }
        $manage_stock = isset($_POST['wpcatalog_manage_stock']) ? 1 : 0;
        $featured = isset($_POST['wpcatalog_featured']) ? 1 : 0;
        $attributes_raw = isset($_POST['wpcatalog_attributes']) ? (string) wp_unslash($_POST['wpcatalog_attributes']) : '{}';
        $attributes = json_decode($attributes_raw, true);
        if (strlen($sku) > 80 || strlen($brand) > 120 || !is_numeric($price_raw) || (float) $price_raw < 0 || ($sale_raw !== '' && (!is_numeric($sale_raw) || (float) $sale_raw < 0 || (float) $sale_raw >= (float) $price_raw)) || !preg_match('/^[A-Z]{3}$/', $currency) || !in_array($stock, ['instock', 'outofstock', 'backorder'], true) || !is_array($attributes) || (array_is_list($attributes) && $attributes !== [])) {
            return;
        }
        $attributes = self::sanitize_attributes($attributes);
        if (is_wp_error($attributes)) {
            return;
        }
        if ($sku !== '') {
            global $wpdb;
            $duplicate = $wpdb->get_var($wpdb->prepare('SELECT post_id FROM ' . self::table_name() . ' WHERE sku = %s AND post_id <> %d LIMIT 1', $sku, $post_id));
            if ($duplicate) {
                add_filter('redirect_post_location', static fn($location) => add_query_arg('wpcatalog_duplicate_sku', '1', $location));
                return;
            }
        }
        if ($manage_stock) {
            $stock = $quantity > 0 ? 'instock' : 'outofstock';
        }
        update_post_meta($post_id, '_wpcatalog_sku', $sku);
        update_post_meta($post_id, '_wpcatalog_brand', $brand);
        update_post_meta($post_id, '_wpcatalog_price', number_format((float) $price_raw, 2, '.', ''));
        update_post_meta($post_id, '_wpcatalog_sale_price', $sale_raw === '' ? '0.00' : number_format((float) $sale_raw, 2, '.', ''));
        update_post_meta($post_id, '_wpcatalog_currency', $currency);
        update_post_meta($post_id, '_wpcatalog_stock_status', $stock);
        update_post_meta($post_id, '_wpcatalog_stock_quantity', $quantity);
        update_post_meta($post_id, '_wpcatalog_manage_stock', $manage_stock);
        update_post_meta($post_id, '_wpcatalog_featured', $featured);
        update_post_meta($post_id, '_wpcatalog_attributes', $attributes);
    }

    public function sync_product_data(int $post_id, WP_Post $post): void {
        if (wp_is_post_revision($post_id) || self::POST_TYPE !== $post->post_type) {
            return;
        }
        global $wpdb;
        $sku = (string) get_post_meta($post_id, '_wpcatalog_sku', true);
        $price = (float) get_post_meta($post_id, '_wpcatalog_price', true);
        $sale_price = (float) get_post_meta($post_id, '_wpcatalog_sale_price', true);
        $currency = (string) (get_post_meta($post_id, '_wpcatalog_currency', true) ?: 'MAD');
        $stock = (string) (get_post_meta($post_id, '_wpcatalog_stock_status', true) ?: 'instock');
        $quantity = absint(get_post_meta($post_id, '_wpcatalog_stock_quantity', true));
        $manage_stock = (int) (bool) get_post_meta($post_id, '_wpcatalog_manage_stock', true);
        $brand = (string) get_post_meta($post_id, '_wpcatalog_brand', true);
        $featured = (int) (bool) get_post_meta($post_id, '_wpcatalog_featured', true);
        $attributes = get_post_meta($post_id, '_wpcatalog_attributes', true);
        if (!is_array($attributes)) {
            $attributes = [];
        }
        if ($manage_stock) {
            $stock = $quantity > 0 ? 'instock' : 'outofstock';
        }
        $table = self::table_name();
        $existing_sku_owner = $sku !== '' ? $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$table} WHERE sku = %s AND post_id <> %d LIMIT 1", $sku, $post_id)) : null;
        if ($existing_sku_owner) {
            $sku = '';
            update_post_meta($post_id, '_wpcatalog_sku', '');
        }
        $existing_sales = (int) $wpdb->get_var($wpdb->prepare("SELECT sales_count FROM {$table} WHERE post_id = %d", $post_id));
        $wpdb->replace(
            $table,
            [
                'post_id' => $post_id,
                'sku' => $sku !== '' ? $sku : null,
                'price' => number_format(max(0, $price), 2, '.', ''),
                'sale_price' => ($sale_price > 0 && $sale_price < $price) ? number_format($sale_price, 2, '.', '') : '0.00',
                'currency' => preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'MAD',
                'stock_status' => in_array($stock, ['instock', 'outofstock', 'backorder'], true) ? $stock : 'instock',
                'stock_quantity' => $quantity,
                'manage_stock' => $manage_stock,
                'brand' => sanitize_text_field($brand),
                'featured' => $featured,
                'sales_count' => max(0, $existing_sales),
                'attributes' => wp_json_encode($attributes),
                'updated_at' => current_time('mysql', true),
            ],
            ['%d', '%s', '%f', '%f', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s']
        );
    }

    public function delete_product_data(int $post_id): void {
        $post = get_post($post_id);
        if ($post && self::POST_TYPE === $post->post_type) {
            global $wpdb;
            $wpdb->delete(self::table_name(), ['post_id' => $post_id], ['%d']);
        }
    }

    public function render_order_meta_box(WP_Post $post): void {
        wp_nonce_field('wpcatalog_save_order', 'wpcatalog_order_nonce');
        $items = get_post_meta($post->ID, '_wpcatalog_order_items', true);
        $items = is_array($items) ? $items : [];
        $status = (string) get_post_meta($post->ID, '_wpcatalog_order_status', true);
        $statuses = ['en_attente' => 'En attente', 'confirmee' => 'Confirmée', 'en_preparation' => 'En préparation', 'expediee' => 'Expédiée', 'terminee' => 'Terminée', 'annulee' => 'Annulée'];
        ?>
        <p><strong>Référence :</strong> <?php echo esc_html((string) get_post_meta($post->ID, '_wpcatalog_order_number', true)); ?></p>
        <p><strong>Client :</strong> <?php echo esc_html(trim((string) get_post_meta($post->ID, '_wpcatalog_customer_first_name', true) . ' ' . (string) get_post_meta($post->ID, '_wpcatalog_customer_last_name', true))); ?></p>
        <p><strong>E-mail :</strong> <?php echo esc_html((string) get_post_meta($post->ID, '_wpcatalog_customer_email', true)); ?> &nbsp; <strong>Téléphone :</strong> <?php echo esc_html((string) get_post_meta($post->ID, '_wpcatalog_customer_phone', true)); ?></p>
        <p><strong>Adresse :</strong> <?php echo esc_html((string) get_post_meta($post->ID, '_wpcatalog_customer_address', true) . ', ' . (string) get_post_meta($post->ID, '_wpcatalog_customer_city', true)); ?></p>
        <table class="widefat striped"><thead><tr><th>Article</th><th>Qté</th><th>Prix unitaire</th><th>Total</th></tr></thead><tbody>
        <?php foreach ($items as $item) : ?><tr><td><?php echo esc_html($item['name'] ?? 'Produit'); ?><br><small><?php echo esc_html($item['sku'] ?? ''); ?></small></td><td><?php echo esc_html((string) ($item['quantity'] ?? 0)); ?></td><td><?php echo esc_html(number_format_i18n((float) ($item['unit_price'] ?? 0), 2)); ?></td><td><?php echo esc_html(number_format_i18n((float) ($item['line_total'] ?? 0), 2)); ?></td></tr><?php endforeach; ?>
        </tbody></table>
        <p><strong>Sous-total :</strong> <?php echo esc_html(number_format_i18n((float) get_post_meta($post->ID, '_wpcatalog_subtotal', true), 2)); ?> &nbsp; <strong>Livraison :</strong> <?php echo esc_html(number_format_i18n((float) get_post_meta($post->ID, '_wpcatalog_shipping', true), 2)); ?> &nbsp; <strong>Total :</strong> <?php echo esc_html(number_format_i18n((float) get_post_meta($post->ID, '_wpcatalog_total', true), 2)); ?> <?php echo esc_html((string) get_post_meta($post->ID, '_wpcatalog_currency', true)); ?></p>
        <p><strong>Paiement :</strong> <?php echo esc_html((string) get_post_meta($post->ID, '_wpcatalog_payment_method', true) === 'cod' ? 'Paiement à la livraison' : 'Virement bancaire'); ?></p>
        <?php $note = (string) get_post_meta($post->ID, '_wpcatalog_customer_note', true); if ($note !== '') : ?><p><strong>Note du client :</strong> <?php echo esc_html($note); ?></p><?php endif; ?>
        <p><label for="wpcatalog_order_status"><strong>État de la commande</strong></label><br><select name="wpcatalog_order_status" id="wpcatalog_order_status"><?php foreach ($statuses as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></p>
        <?php
    }

    public function order_admin_columns(array $columns): array {
        return [
            'cb' => $columns['cb'] ?? '',
            'title' => __('Référence de commande', 'wp-catalog-api'),
            'wpcatalog_customer' => __('Client', 'wp-catalog-api'),
            'wpcatalog_total' => __('Total', 'wp-catalog-api'),
            'wpcatalog_status' => __('État', 'wp-catalog-api'),
            'date' => $columns['date'] ?? __('Date', 'wp-catalog-api'),
        ];
    }

    public function render_order_admin_column(string $column, int $post_id): void {
        if ($column === 'wpcatalog_customer') {
            $name = trim((string) get_post_meta($post_id, '_wpcatalog_customer_first_name', true) . ' ' . (string) get_post_meta($post_id, '_wpcatalog_customer_last_name', true));
            echo esc_html($name ?: '—');
        } elseif ($column === 'wpcatalog_total') {
            $total = (float) get_post_meta($post_id, '_wpcatalog_total', true);
            $currency = (string) get_post_meta($post_id, '_wpcatalog_currency', true);
            echo esc_html(number_format_i18n($total, 2) . ' ' . $currency);
        } elseif ($column === 'wpcatalog_status') {
            $status = (string) get_post_meta($post_id, '_wpcatalog_order_status', true);
            $labels = ['en_attente' => 'En attente', 'confirmee' => 'Confirmée', 'en_preparation' => 'En préparation', 'expediee' => 'Expédiée', 'terminee' => 'Terminée', 'annulee' => 'Annulée'];
            echo esc_html($labels[$status] ?? '—');
        }
    }

    public function save_order_meta_box(int $post_id, WP_Post $post): void {
        if (!isset($_POST['wpcatalog_order_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpcatalog_order_nonce'])), 'wpcatalog_save_order')) {
            return;
        }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
            return;
        }
        $new_status = isset($_POST['wpcatalog_order_status']) ? sanitize_key(wp_unslash($_POST['wpcatalog_order_status'])) : 'en_attente';
        $allowed = ['en_attente', 'confirmee', 'en_preparation', 'expediee', 'terminee', 'annulee'];
        if (!in_array($new_status, $allowed, true)) {
            return;
        }
        $old_status = (string) get_post_meta($post_id, '_wpcatalog_order_status', true);
        $stock_already_restored = (bool) get_post_meta($post_id, '_wpcatalog_stock_restored', true);
        if ($stock_already_restored && $new_status !== 'annulee') {
            return;
        }
        update_post_meta($post_id, '_wpcatalog_order_status', $new_status);
        if ($new_status === 'annulee' && $old_status !== 'annulee' && !(bool) get_post_meta($post_id, '_wpcatalog_stock_restored', true)) {
            $items = get_post_meta($post_id, '_wpcatalog_order_items', true);
            $items = is_array($items) ? $items : [];
            global $wpdb;
            $table = self::table_name();
            foreach ($items as $item) {
                $product_id = absint($item['product_id'] ?? 0);
                $quantity = absint($item['quantity'] ?? 0);
                if (!$product_id || !$quantity) {
                    continue;
                }
                $row = $wpdb->get_row($wpdb->prepare("SELECT manage_stock FROM {$table} WHERE post_id = %d", $product_id), ARRAY_A);
                if ($row && (int) $row['manage_stock'] === 1) {
                    $wpdb->query($wpdb->prepare("UPDATE {$table} SET stock_quantity = stock_quantity + %d, stock_status = 'instock', sales_count = GREATEST(0, sales_count - %d), updated_at = %s WHERE post_id = %d", $quantity, $quantity, current_time('mysql', true), $product_id));
                    $new_quantity = (int) $wpdb->get_var($wpdb->prepare("SELECT stock_quantity FROM {$table} WHERE post_id = %d", $product_id));
                    update_post_meta($product_id, '_wpcatalog_stock_quantity', $new_quantity);
                    update_post_meta($product_id, '_wpcatalog_stock_status', $new_quantity > 0 ? 'instock' : 'outofstock');
                } else {
                    $wpdb->query($wpdb->prepare("UPDATE {$table} SET sales_count = GREATEST(0, sales_count - %d), updated_at = %s WHERE post_id = %d", $quantity, current_time('mysql', true), $product_id));
                }
            }
            update_post_meta($post_id, '_wpcatalog_stock_restored', 1);
        }
    }

    public function register_routes(): void {
        register_rest_route(self::REST_NAMESPACE, '/store', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_store_info'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/products', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'list_products'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_product'],
                'permission_callback' => [$this, 'can_manage_catalog'],
            ],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/products/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_product'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_product'],
                'permission_callback' => [$this, 'can_manage_catalog'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'remove_product'],
                'permission_callback' => [$this, 'can_manage_catalog'],
            ],
        ]);
        register_rest_route(self::REST_NAMESPACE, '/categories', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'list_categories'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/orders/checkout', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'checkout'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/orders/track', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'track_order'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::REST_NAMESPACE, '/me', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_current_user'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);
        register_rest_route(self::REST_NAMESPACE, '/admin/stats', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_admin_stats'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
    }

    public function can_manage_catalog(): bool {
        return is_user_logged_in() && current_user_can('edit_catalog_products');
    }

    private static function request_payload(WP_REST_Request $request): array {
        $json = $request->get_json_params();
        if (is_array($json)) {
            return $json;
        }
        $body = $request->get_body_params();
        return is_array($body) ? $body : [];
    }

    public function get_store_info(): WP_REST_Response {
        $settings = self::store_settings();
        return new WP_REST_Response(['data' => [
            'name' => $settings['store_name'],
            'currency' => $settings['currency'],
            'contact_email' => $settings['contact_email'],
            'shipping_fee' => (float) $settings['shipping_fee'],
            'free_shipping_threshold' => (float) $settings['free_shipping_threshold'],
            'payment_methods' => [
                'cod' => (bool) $settings['cod_enabled'],
                'bank_transfer' => (bool) $settings['bank_transfer_enabled'],
            ],
            'bank_transfer_instructions' => $settings['bank_transfer_instructions'],
        ]]);
    }

    public function list_products(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = self::table_name();
        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = min(48, max(1, absint($request->get_param('per_page') ?: 12)));
        $offset = ($page - 1) * $per_page;
        $where = ["p.post_type = %s", "p.post_status = %s"];
        $params = [self::POST_TYPE, 'publish'];
        $joins = " LEFT JOIN {$table} pd ON pd.post_id = p.ID ";

        $search = trim((string) $request->get_param('q'));
        if ($search !== '') {
            $search = substr($search, 0, 100);
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(p.post_title LIKE %s OR p.post_excerpt LIKE %s OR p.post_content LIKE %s OR pd.sku LIKE %s OR pd.brand LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $category = trim((string) $request->get_param('category'));
        if ($category !== '') {
            $term = get_term_by('slug', sanitize_title($category), self::TAXONOMY);
            if (!$term || is_wp_error($term)) {
                return new WP_REST_Response(['code' => 'invalid_category', 'message' => __('Catégorie inconnue.', 'wp-catalog-api')], 400);
            }
            $joins .= " INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = '" . esc_sql(self::TAXONOMY) . "' ";
            $where[] = 'tt.term_id = %d';
            $params[] = (int) $term->term_id;
        }

        foreach (['min_price' => '>=', 'max_price' => '<='] as $parameter => $operator) {
            $value = $request->get_param($parameter);
            if ($value !== null && $value !== '') {
                if (!is_numeric($value) || (float) $value < 0) {
                    return new WP_REST_Response(['code' => 'invalid_' . $parameter, 'message' => __('Le filtre de prix doit être un nombre positif ou nul.', 'wp-catalog-api')], 400);
                }
                $effective_price = 'CASE WHEN pd.sale_price > 0 AND pd.sale_price < pd.price THEN pd.sale_price ELSE pd.price END';
                $where[] = $effective_price . ' ' . $operator . ' %f';
                $params[] = (float) $value;
            }
        }
        $min_price = $request->get_param('min_price');
        $max_price = $request->get_param('max_price');
        if ($min_price !== null && $min_price !== '' && $max_price !== null && $max_price !== '' && (float) $min_price > (float) $max_price) {
            return new WP_REST_Response(['code' => 'invalid_price_range', 'message' => __('Le prix minimum ne peut pas dépasser le prix maximum.', 'wp-catalog-api')], 400);
        }

        $stock = (string) $request->get_param('stock');
        if ($stock !== '') {
            if (!in_array($stock, ['instock', 'outofstock', 'backorder'], true)) {
                return new WP_REST_Response(['code' => 'invalid_stock', 'message' => __('Filtre de disponibilité non valide.', 'wp-catalog-api')], 400);
            }
            $where[] = 'pd.stock_status = %s';
            $params[] = $stock;
        }
        $brand = trim((string) $request->get_param('brand'));
        if ($brand !== '') {
            $where[] = 'pd.brand LIKE %s';
            $params[] = '%' . $wpdb->esc_like(substr($brand, 0, 80)) . '%';
        }
        if (filter_var($request->get_param('featured'), FILTER_VALIDATE_BOOLEAN)) {
            $where[] = 'pd.featured = 1';
        }
        if (filter_var($request->get_param('on_sale'), FILTER_VALIDATE_BOOLEAN)) {
            $where[] = '(pd.sale_price > 0 AND pd.sale_price < pd.price)';
        }

        $attributes = $request->get_param('attr');
        if ($attributes !== null && $attributes !== '') {
            if (!is_array($attributes) || count($attributes) > 20) {
                return new WP_REST_Response(['code' => 'invalid_attributes', 'message' => __('Les filtres de caractéristiques sont invalides.', 'wp-catalog-api')], 400);
            }
            foreach ($attributes as $key => $value) {
                $key = sanitize_key((string) $key);
                if ($key === '' || !is_scalar($value) || strlen((string) $value) > 120) {
                    return new WP_REST_Response(['code' => 'invalid_attribute_filter', 'message' => __('Chaque caractéristique doit avoir une clé et une valeur courte.', 'wp-catalog-api')], 400);
                }
                $where[] = "JSON_UNQUOTE(JSON_EXTRACT(pd.attributes, %s)) = %s";
                $params[] = '$."' . $key . '"';
                $params[] = (string) $value;
            }
        }

        $effective_price = 'CASE WHEN pd.sale_price > 0 AND pd.sale_price < pd.price THEN pd.sale_price ELSE pd.price END';
        $order = (string) $request->get_param('orderby');
        $order_sql = match ($order) {
            'price_asc' => $effective_price . ' ASC, p.post_date DESC',
            'price_desc' => $effective_price . ' DESC, p.post_date DESC',
            'popular' => 'pd.sales_count DESC, p.post_date DESC',
            default => 'p.post_date DESC',
        };
        $where_sql = implode(' AND ', $where);
        $from_sql = "FROM {$wpdb->posts} p {$joins} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(p.ID) {$from_sql}", $params));
        $query_params = $params;
        $query_params[] = $per_page;
        $query_params[] = $offset;
        $ids = $wpdb->get_col($wpdb->prepare("SELECT p.ID {$from_sql} ORDER BY {$order_sql} LIMIT %d OFFSET %d", $query_params));
        $products = [];
        foreach ($ids as $id) {
            $post = get_post((int) $id);
            if ($post instanceof WP_Post) {
                $products[] = $this->format_product($post);
            }
        }
        $total_pages = (int) ceil($total / $per_page);
        $response = new WP_REST_Response(['data' => $products, 'meta' => ['page' => $page, 'per_page' => $per_page, 'total' => $total, 'total_pages' => $total_pages]]);
        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) $total_pages);
        return $response;
    }

    public function get_product(WP_REST_Request $request) {
        $post = get_post(absint($request['id']));
        if (!$post || self::POST_TYPE !== $post->post_type) {
            return new WP_Error('product_not_found', __('Produit introuvable.', 'wp-catalog-api'), ['status' => 404]);
        }
        if ('publish' !== $post->post_status && !current_user_can('edit_post', $post->ID)) {
            return new WP_Error('product_not_found', __('Produit introuvable.', 'wp-catalog-api'), ['status' => 404]);
        }
        return rest_ensure_response(['data' => $this->format_product($post)]);
    }

    public function create_product(WP_REST_Request $request) {
        $payload = self::request_payload($request);
        $validated = $this->validate_product_payload($payload, null);
        if (is_wp_error($validated)) {
            return $validated;
        }
        $post_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_title' => $validated['title'],
            'post_content' => $validated['content'],
            'post_excerpt' => $validated['excerpt'],
            'post_status' => $validated['status'],
            'post_author' => get_current_user_id(),
        ], true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        $this->persist_product_fields($post_id, $validated);
        $this->persist_product_categories($post_id, $validated['categories']);
        $response = new WP_REST_Response(['data' => $this->format_product(get_post($post_id))], 201);
        $response->header('Location', rest_url(self::REST_NAMESPACE . '/products/' . $post_id));
        return $response;
    }

    public function update_product(WP_REST_Request $request) {
        $post_id = absint($request['id']);
        $post = get_post($post_id);
        if (!$post || self::POST_TYPE !== $post->post_type) {
            return new WP_Error('product_not_found', __('Produit introuvable.', 'wp-catalog-api'), ['status' => 404]);
        }
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('forbidden', __('Vous ne pouvez pas modifier ce produit.', 'wp-catalog-api'), ['status' => 403]);
        }
        $payload = self::request_payload($request);
        $validated = $this->validate_product_payload($payload, $post);
        if (is_wp_error($validated)) {
            return $validated;
        }
        $post_data = ['ID' => $post_id];
        foreach (['title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'status' => 'post_status'] as $key => $field) {
            if (array_key_exists($key, $payload)) {
                $post_data[$field] = $validated[$key];
            }
        }
        $updated = wp_update_post($post_data, true);
        if (is_wp_error($updated)) {
            return $updated;
        }
        $this->persist_product_fields($post_id, $validated);
        if (array_key_exists('categories', $payload)) {
            $this->persist_product_categories($post_id, $validated['categories']);
        }
        return rest_ensure_response(['data' => $this->format_product(get_post($post_id))]);
    }

    public function remove_product(WP_REST_Request $request) {
        $post_id = absint($request['id']);
        $post = get_post($post_id);
        if (!$post || self::POST_TYPE !== $post->post_type) {
            return new WP_Error('product_not_found', __('Produit introuvable.', 'wp-catalog-api'), ['status' => 404]);
        }
        if (!current_user_can('delete_post', $post_id)) {
            return new WP_Error('forbidden', __('Vous ne pouvez pas supprimer ce produit.', 'wp-catalog-api'), ['status' => 403]);
        }
        wp_delete_post($post_id, true);
        return rest_ensure_response(['deleted' => true, 'id' => $post_id]);
    }

    public function list_categories(): WP_REST_Response {
        $terms = get_terms(['taxonomy' => self::TAXONOMY, 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC']);
        if (is_wp_error($terms)) {
            return new WP_REST_Response(['data' => []]);
        }
        $data = array_map(static fn(WP_Term $term) => [
            'id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'count' => (int) $term->count,
            'parent' => (int) $term->parent,
        ], $terms);
        return new WP_REST_Response(['data' => $data]);
    }

    public function checkout(WP_REST_Request $request) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $limit_key = 'wpcatalog_checkout_' . md5($ip);
        $attempts = (int) get_transient($limit_key);
        if ($attempts >= 8) {
            return new WP_Error('checkout_rate_limited', __('Trop de tentatives. Réessayez un peu plus tard.', 'wp-catalog-api'), ['status' => 429]);
        }
        set_transient($limit_key, $attempts + 1, HOUR_IN_SECONDS);

        $payload = self::request_payload($request);
        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        $first_name = sanitize_text_field((string) ($customer['first_name'] ?? ''));
        $last_name = sanitize_text_field((string) ($customer['last_name'] ?? ''));
        $email = sanitize_email((string) ($customer['email'] ?? ''));
        $phone = sanitize_text_field((string) ($customer['phone'] ?? ''));
        $address = sanitize_textarea_field((string) ($customer['address'] ?? ''));
        $city = sanitize_text_field((string) ($customer['city'] ?? ''));
        $note = sanitize_textarea_field((string) ($customer['note'] ?? ''));
        if ($first_name === '' || strlen($first_name) > 80 || $last_name === '' || strlen($last_name) > 80 || !is_email($email) || strlen($phone) < 6 || strlen($phone) > 30 || $address === '' || strlen($address) > 500 || $city === '' || strlen($city) > 120 || strlen($note) > 1000) {
            return new WP_Error('invalid_customer', __('Veuillez renseigner un nom, un e-mail valide, un téléphone, une adresse et une ville.', 'wp-catalog-api'), ['status' => 400]);
        }
        $items_input = $payload['items'] ?? null;
        if (!is_array($items_input) || count($items_input) < 1 || count($items_input) > 20) {
            return new WP_Error('invalid_items', __('Le panier doit contenir entre 1 et 20 lignes.', 'wp-catalog-api'), ['status' => 400]);
        }
        $quantities = [];
        foreach ($items_input as $item) {
            if (!is_array($item)) {
                return new WP_Error('invalid_item', __('Ligne de panier invalide.', 'wp-catalog-api'), ['status' => 400]);
            }
            $product_id = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
            if ($product_id === false || $quantity === false) {
                return new WP_Error('invalid_quantity', __('Quantité invalide. Chaque article doit avoir une quantité entre 1 et 20.', 'wp-catalog-api'), ['status' => 400]);
            }
            $quantities[$product_id] = ($quantities[$product_id] ?? 0) + $quantity;
            if ($quantities[$product_id] > 20) {
                return new WP_Error('invalid_quantity', __('La quantité maximale par produit est de 20.', 'wp-catalog-api'), ['status' => 400]);
            }
        }

        $settings = self::store_settings();
        $payment = sanitize_key((string) ($payload['payment_method'] ?? 'cod'));
        $payment_enabled = [
            'cod' => !empty($settings['cod_enabled']),
            'bank_transfer' => !empty($settings['bank_transfer_enabled']),
        ];
        if (!isset($payment_enabled[$payment]) || !$payment_enabled[$payment]) {
            return new WP_Error('invalid_payment_method', __('Ce moyen de paiement n’est pas disponible.', 'wp-catalog-api'), ['status' => 400]);
        }

        global $wpdb;
        $table = self::table_name();
        $order_items = [];
        $reservations = [];
        $subtotal = 0.0;
        $currency = '';
        foreach ($quantities as $product_id => $quantity) {
            $post = get_post($product_id);
            if (!$post || $post->post_type !== self::POST_TYPE || $post->post_status !== 'publish') {
                return new WP_Error('product_unavailable', __('Un produit du panier n’est plus disponible.', 'wp-catalog-api'), ['status' => 409]);
            }
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE post_id = %d", $product_id), ARRAY_A);
            if (!$row) {
                $this->sync_product_data($product_id, $post);
                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE post_id = %d", $product_id), ARRAY_A);
            }
            if (!$row || $row['stock_status'] === 'outofstock') {
                return new WP_Error('product_out_of_stock', sprintf(__('Le produit « %s » est indisponible.', 'wp-catalog-api'), get_the_title($post)), ['status' => 409]);
            }
            if ((int) $row['manage_stock'] === 1 && (int) $row['stock_quantity'] < $quantity) {
                return new WP_Error('insufficient_stock', sprintf(__('Stock insuffisant pour « %s ». Quantité disponible : %d.', 'wp-catalog-api'), get_the_title($post), (int) $row['stock_quantity']), ['status' => 409]);
            }
            $item_currency = (string) $row['currency'];
            if ($item_currency !== (string) $settings['currency']) {
                return new WP_Error('store_currency_mismatch', __('La devise du produit doit correspondre à la devise configurée dans les paramètres de la boutique.', 'wp-catalog-api'), ['status' => 400]);
            }
            if ($currency !== '' && $currency !== $item_currency) {
                return new WP_Error('mixed_currencies', __('Tous les produits d’une commande doivent utiliser la même devise.', 'wp-catalog-api'), ['status' => 400]);
            }
            $currency = $item_currency;
            $regular_price = (float) $row['price'];
            $sale_price = (float) $row['sale_price'];
            $unit_price = ($sale_price > 0 && $sale_price < $regular_price) ? $sale_price : $regular_price;
            $line_total = round($unit_price * $quantity, 2);
            $subtotal += $line_total;
            $order_items[] = [
                'product_id' => (int) $product_id,
                'name' => get_the_title($post),
                'sku' => (string) $row['sku'],
                'quantity' => (int) $quantity,
                'unit_price' => $unit_price,
                'regular_price' => $regular_price,
                'line_total' => $line_total,
            ];
            $reservations[] = ['product_id' => (int) $product_id, 'quantity' => (int) $quantity, 'manage_stock' => (int) $row['manage_stock'] === 1];
        }
        $currency = $currency ?: (string) $settings['currency'];
        $threshold = (float) $settings['free_shipping_threshold'];
        $shipping = ($threshold > 0 && $subtotal >= $threshold) ? 0.0 : (float) $settings['shipping_fee'];
        $total = round($subtotal + $shipping, 2);

        // Atomically reserve tracked inventory before creating the order.
        $reserved = [];
        foreach ($reservations as $reservation) {
            $product_id = $reservation['product_id'];
            $quantity = $reservation['quantity'];
            if ($reservation['manage_stock']) {
                $changed = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET stock_status = IF(stock_quantity - %d > 0, 'instock', 'outofstock'), stock_quantity = stock_quantity - %d, sales_count = sales_count + %d, updated_at = %s WHERE post_id = %d AND manage_stock = 1 AND stock_quantity >= %d",
                    $quantity, $quantity, $quantity, current_time('mysql', true), $product_id, $quantity
                ));
                if ($changed !== 1) {
                    $this->rollback_reservations($reserved);
                    return new WP_Error('stock_changed', __('Le stock vient de changer. Actualisez votre panier et réessayez.', 'wp-catalog-api'), ['status' => 409]);
                }
                $reserved[] = $reservation;
                $new_quantity = (int) $wpdb->get_var($wpdb->prepare("SELECT stock_quantity FROM {$table} WHERE post_id = %d", $product_id));
                update_post_meta($product_id, '_wpcatalog_stock_quantity', $new_quantity);
                update_post_meta($product_id, '_wpcatalog_stock_status', $new_quantity > 0 ? 'instock' : 'outofstock');
            } else {
                $wpdb->query($wpdb->prepare("UPDATE {$table} SET sales_count = sales_count + %d, updated_at = %s WHERE post_id = %d", $quantity, current_time('mysql', true), $product_id));
                $reserved[] = $reservation;
            }
        }

        $order_number = 'CMD-' . gmdate('Ymd') . '-' . strtoupper(wp_generate_password(5, false, false));
        $order_id = wp_insert_post([
            'post_type' => self::ORDER_TYPE,
            'post_status' => 'publish',
            'post_title' => $order_number . ' — ' . $first_name . ' ' . $last_name,
            'post_author' => get_current_user_id(),
        ], true);
        if (is_wp_error($order_id)) {
            $this->rollback_reservations($reserved);
            return $order_id;
        }
        update_post_meta($order_id, '_wpcatalog_order_number', $order_number);
        update_post_meta($order_id, '_wpcatalog_order_status', 'en_attente');
        update_post_meta($order_id, '_wpcatalog_order_items', $order_items);
        update_post_meta($order_id, '_wpcatalog_customer_first_name', $first_name);
        update_post_meta($order_id, '_wpcatalog_customer_last_name', $last_name);
        update_post_meta($order_id, '_wpcatalog_customer_email', $email);
        update_post_meta($order_id, '_wpcatalog_customer_phone', $phone);
        update_post_meta($order_id, '_wpcatalog_customer_address', $address);
        update_post_meta($order_id, '_wpcatalog_customer_city', $city);
        update_post_meta($order_id, '_wpcatalog_customer_note', $note);
        update_post_meta($order_id, '_wpcatalog_payment_method', $payment);
        update_post_meta($order_id, '_wpcatalog_subtotal', number_format($subtotal, 2, '.', ''));
        update_post_meta($order_id, '_wpcatalog_shipping', number_format($shipping, 2, '.', ''));
        update_post_meta($order_id, '_wpcatalog_total', number_format($total, 2, '.', ''));
        update_post_meta($order_id, '_wpcatalog_currency', $currency);

        $summary_lines = [];
        foreach ($order_items as $item) {
            $summary_lines[] = sprintf('%s × %d — %s %s', $item['name'], $item['quantity'], number_format_i18n($item['line_total'], 2), $currency);
        }
        $message = "Commande : {$order_number}\n\n" . implode("\n", $summary_lines) . "\n\nSous-total : " . number_format_i18n($subtotal, 2) . " {$currency}\nLivraison : " . number_format_i18n($shipping, 2) . " {$currency}\nTotal : " . number_format_i18n($total, 2) . " {$currency}\n\nClient : {$first_name} {$last_name}\nTéléphone : {$phone}\nAdresse : {$address}, {$city}\nPaiement : " . ($payment === 'cod' ? 'À la livraison' : 'Virement bancaire');
        if ($payment === 'bank_transfer' && $settings['bank_transfer_instructions'] !== '') {
            $message .= "\nInstructions de virement : " . $settings['bank_transfer_instructions'];
        }
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        wp_mail($email, sprintf(__('Confirmation de votre commande %s', 'wp-catalog-api'), $order_number), "Bonjour {$first_name},\n\nNous avons bien reçu votre commande.\n\n{$message}\n\nMerci pour votre confiance.", $headers);
        if (is_email($settings['contact_email'])) {
            wp_mail($settings['contact_email'], sprintf(__('Nouvelle commande %s', 'wp-catalog-api'), $order_number), $message, $headers);
        }

        return new WP_REST_Response(['data' => [
            'order_number' => $order_number,
            'status' => 'en_attente',
            'subtotal' => round($subtotal, 2),
            'shipping' => round($shipping, 2),
            'total' => $total,
            'currency' => $currency,
            'payment_method' => $payment,
            'message' => __('Votre commande a été enregistrée. Un récapitulatif vient de vous être envoyé par e-mail.', 'wp-catalog-api'),
        ]], 201);
    }

    private function rollback_reservations(array $reservations): void {
        global $wpdb;
        $table = self::table_name();
        foreach ($reservations as $reservation) {
            $product_id = absint($reservation['product_id']);
            $quantity = absint($reservation['quantity']);
            if ($reservation['manage_stock']) {
                $wpdb->query($wpdb->prepare("UPDATE {$table} SET stock_quantity = stock_quantity + %d, stock_status = 'instock', sales_count = GREATEST(0, sales_count - %d), updated_at = %s WHERE post_id = %d", $quantity, $quantity, current_time('mysql', true), $product_id));
                $new_quantity = (int) $wpdb->get_var($wpdb->prepare("SELECT stock_quantity FROM {$table} WHERE post_id = %d", $product_id));
                update_post_meta($product_id, '_wpcatalog_stock_quantity', $new_quantity);
                update_post_meta($product_id, '_wpcatalog_stock_status', $new_quantity > 0 ? 'instock' : 'outofstock');
            } else {
                $wpdb->query($wpdb->prepare("UPDATE {$table} SET sales_count = GREATEST(0, sales_count - %d), updated_at = %s WHERE post_id = %d", $quantity, current_time('mysql', true), $product_id));
            }
        }
    }

    public function track_order(WP_REST_Request $request) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $limit_key = 'wpcatalog_track_' . md5($ip);
        $attempts = (int) get_transient($limit_key);
        if ($attempts >= 15) {
            return new WP_Error('tracking_rate_limited', __('Trop de recherches de commande. Réessayez plus tard.', 'wp-catalog-api'), ['status' => 429]);
        }
        set_transient($limit_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
        $payload = self::request_payload($request);
        $order_number = strtoupper(sanitize_text_field((string) ($payload['order_number'] ?? '')));
        $email = strtolower(sanitize_email((string) ($payload['email'] ?? '')));
        if (!preg_match('/^CMD-[0-9]{8}-[A-Z0-9]{5}$/', $order_number) || !is_email($email)) {
            return new WP_Error('invalid_tracking_details', __('Saisissez une référence de commande et l’e-mail utilisé lors de l’achat.', 'wp-catalog-api'), ['status' => 400]);
        }
        $orders = get_posts([
            'post_type' => self::ORDER_TYPE,
            'post_status' => 'publish',
            'numberposts' => 1,
            'meta_key' => '_wpcatalog_order_number',
            'meta_value' => $order_number,
        ]);
        if (!$orders) {
            return new WP_Error('order_not_found', __('Commande introuvable. Vérifiez les informations saisies.', 'wp-catalog-api'), ['status' => 404]);
        }
        $order = $orders[0];
        $stored_email = strtolower((string) get_post_meta($order->ID, '_wpcatalog_customer_email', true));
        if (!hash_equals($stored_email, $email)) {
            return new WP_Error('order_not_found', __('Commande introuvable. Vérifiez les informations saisies.', 'wp-catalog-api'), ['status' => 404]);
        }
        $items = get_post_meta($order->ID, '_wpcatalog_order_items', true);
        $safe_items = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $safe_items[] = ['name' => (string) ($item['name'] ?? ''), 'quantity' => (int) ($item['quantity'] ?? 0)];
        }
        return rest_ensure_response(['data' => [
            'order_number' => $order_number,
            'status' => (string) get_post_meta($order->ID, '_wpcatalog_order_status', true),
            'created_at' => get_post_time(DATE_ATOM, true, $order),
            'total' => (float) get_post_meta($order->ID, '_wpcatalog_total', true),
            'currency' => (string) get_post_meta($order->ID, '_wpcatalog_currency', true),
            'items' => $safe_items,
        ]]);
    }

    public function get_current_user(): WP_REST_Response {
        $user = wp_get_current_user();
        return new WP_REST_Response(['data' => [
            'id' => (int) $user->ID,
            'name' => $user->display_name,
            'roles' => array_values($user->roles),
            'can_manage_catalog' => current_user_can('edit_catalog_products'),
        ]]);
    }

    public function get_admin_stats(): WP_REST_Response {
        global $wpdb;
        $counts = wp_count_posts(self::POST_TYPE);
        $orders = wp_count_posts(self::ORDER_TYPE);
        $table = self::table_name();
        $low_stock = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE manage_stock = 1 AND stock_quantity <= 5 AND stock_status = 'instock'");
        $revenue = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(pm.meta_value AS DECIMAL(12,2))) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_key = '_wpcatalog_total' AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} cancelled WHERE cancelled.post_id = p.ID AND cancelled.meta_key = '_wpcatalog_order_status' AND cancelled.meta_value = 'annulee')",
            self::ORDER_TYPE
        ));
        return new WP_REST_Response(['data' => [
            'published_products' => (int) ($counts->publish ?? 0),
            'draft_products' => (int) ($counts->draft ?? 0),
            'orders' => (int) ($orders->publish ?? 0),
            'low_stock_products' => $low_stock,
            'gross_order_total' => $revenue,
        ]]);
    }

    private function validate_product_payload(array $payload, ?WP_Post $existing) {
        $current_attributes = $existing ? get_post_meta($existing->ID, '_wpcatalog_attributes', true) : [];
        $current_attributes = is_array($current_attributes) ? $current_attributes : [];
        $settings = self::store_settings();
        $data = [
            'title' => $existing ? $existing->post_title : '',
            'content' => $existing ? $existing->post_content : '',
            'excerpt' => $existing ? $existing->post_excerpt : '',
            'status' => $existing ? $existing->post_status : 'publish',
            'sku' => $existing ? (string) get_post_meta($existing->ID, '_wpcatalog_sku', true) : '',
            'brand' => $existing ? (string) get_post_meta($existing->ID, '_wpcatalog_brand', true) : '',
            'price' => $existing ? (string) get_post_meta($existing->ID, '_wpcatalog_price', true) : '0',
            'sale_price' => $existing ? (string) get_post_meta($existing->ID, '_wpcatalog_sale_price', true) : '0',
            'currency' => $existing ? (string) (get_post_meta($existing->ID, '_wpcatalog_currency', true) ?: $settings['currency']) : (string) $settings['currency'],
            'stock_status' => $existing ? (string) (get_post_meta($existing->ID, '_wpcatalog_stock_status', true) ?: 'instock') : 'instock',
            'stock_quantity' => $existing ? absint(get_post_meta($existing->ID, '_wpcatalog_stock_quantity', true)) : 0,
            'manage_stock' => $existing ? (bool) get_post_meta($existing->ID, '_wpcatalog_manage_stock', true) : false,
            'featured' => $existing ? (bool) get_post_meta($existing->ID, '_wpcatalog_featured', true) : false,
            'attributes' => $current_attributes,
            'categories' => $existing ? wp_get_object_terms($existing->ID, self::TAXONOMY, ['fields' => 'ids']) : [],
        ];
        foreach (['title', 'content', 'excerpt', 'status', 'sku', 'brand', 'price', 'sale_price', 'currency', 'stock_status', 'stock_quantity', 'manage_stock', 'featured', 'attributes', 'categories'] as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            switch ($field) {
                case 'title': $data['title'] = sanitize_text_field((string) $payload[$field]); break;
                case 'content': $data['content'] = wp_kses_post((string) $payload[$field]); break;
                case 'excerpt': $data['excerpt'] = sanitize_textarea_field((string) $payload[$field]); break;
                case 'status':
                    $status = sanitize_key((string) $payload[$field]);
                    if (!in_array($status, ['publish', 'draft', 'private'], true)) return new WP_Error('invalid_status', __('Le statut doit être publish, draft ou private.', 'wp-catalog-api'), ['status' => 400]);
                    $data['status'] = $status;
                    break;
                case 'sku':
                    $data['sku'] = trim(sanitize_text_field((string) $payload[$field]));
                    if (strlen($data['sku']) > 80) return new WP_Error('invalid_sku', __('La référence ne peut pas dépasser 80 caractères.', 'wp-catalog-api'), ['status' => 400]);
                    break;
                case 'brand':
                    $data['brand'] = sanitize_text_field((string) $payload[$field]);
                    if (strlen($data['brand']) > 120) return new WP_Error('invalid_brand', __('La marque ne peut pas dépasser 120 caractères.', 'wp-catalog-api'), ['status' => 400]);
                    break;
                case 'price':
                    if (!is_numeric($payload[$field]) || (float) $payload[$field] < 0 || (float) $payload[$field] > 9999999999.99) return new WP_Error('invalid_price', __('Le prix doit être un nombre positif ou nul.', 'wp-catalog-api'), ['status' => 400]);
                    $data['price'] = number_format((float) $payload[$field], 2, '.', '');
                    break;
                case 'sale_price':
                    if ($payload[$field] === '' || $payload[$field] === null) { $data['sale_price'] = '0.00'; break; }
                    if (!is_numeric($payload[$field]) || (float) $payload[$field] < 0) return new WP_Error('invalid_sale_price', __('Le prix promotionnel doit être un nombre positif.', 'wp-catalog-api'), ['status' => 400]);
                    $data['sale_price'] = number_format((float) $payload[$field], 2, '.', '');
                    break;
                case 'currency':
                    $currency = strtoupper(sanitize_text_field((string) $payload[$field]));
                    if (!preg_match('/^[A-Z]{3}$/', $currency)) return new WP_Error('invalid_currency', __('La devise doit être un code ISO à trois lettres.', 'wp-catalog-api'), ['status' => 400]);
                    $data['currency'] = $currency;
                    break;
                case 'stock_status':
                    $stock = sanitize_key((string) $payload[$field]);
                    if (!in_array($stock, ['instock', 'outofstock', 'backorder'], true)) return new WP_Error('invalid_stock_status', __('Disponibilité non valide.', 'wp-catalog-api'), ['status' => 400]);
                    $data['stock_status'] = $stock;
                    break;
                case 'stock_quantity':
                    $quantity = filter_var($payload[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                    if ($quantity === false) return new WP_Error('invalid_stock_quantity', __('La quantité en stock doit être un entier positif ou nul.', 'wp-catalog-api'), ['status' => 400]);
                    $data['stock_quantity'] = $quantity;
                    break;
                case 'manage_stock': $data['manage_stock'] = (bool) rest_sanitize_boolean($payload[$field]); break;
                case 'featured': $data['featured'] = (bool) rest_sanitize_boolean($payload[$field]); break;
                case 'attributes':
                    if (!is_array($payload[$field]) || count($payload[$field]) > 30) return new WP_Error('invalid_attributes', __('Les caractéristiques doivent être un objet de 30 entrées maximum.', 'wp-catalog-api'), ['status' => 400]);
                    $data['attributes'] = self::sanitize_attributes($payload[$field]);
                    if (is_wp_error($data['attributes'])) return $data['attributes'];
                    break;
                case 'categories':
                    if (!is_array($payload[$field]) || count($payload[$field]) > 50) return new WP_Error('invalid_categories', __('Les catégories doivent être un tableau d’identifiants.', 'wp-catalog-api'), ['status' => 400]);
                    $category_ids = array_values(array_unique(array_filter(array_map('absint', $payload[$field]))));
                    foreach ($category_ids as $category_id) {
                        $term = get_term($category_id, self::TAXONOMY);
                        if (!$term || is_wp_error($term)) return new WP_Error('invalid_category', __('Une catégorie est invalide.', 'wp-catalog-api'), ['status' => 400]);
                    }
                    $data['categories'] = $category_ids;
                    break;
            }
        }
        if ($data['title'] === '') return new WP_Error('invalid_title', __('Le nom du produit est obligatoire.', 'wp-catalog-api'), ['status' => 400]);
        if ((float) $data['sale_price'] > 0 && (float) $data['sale_price'] >= (float) $data['price']) return new WP_Error('invalid_sale_price', __('Le prix promotionnel doit être inférieur au prix normal.', 'wp-catalog-api'), ['status' => 400]);
        if ($data['manage_stock']) $data['stock_status'] = $data['stock_quantity'] > 0 ? 'instock' : 'outofstock';

        global $wpdb;
        if ($data['sku'] !== '') {
            $sql = 'SELECT post_id FROM ' . self::table_name() . ' WHERE sku = %s';
            $params = [$data['sku']];
            if ($existing) { $sql .= ' AND post_id <> %d'; $params[] = $existing->ID; }
            if ($wpdb->get_var($wpdb->prepare($sql . ' LIMIT 1', $params))) return new WP_Error('duplicate_sku', __('Cette référence est déjà utilisée.', 'wp-catalog-api'), ['status' => 409]);
        }
        return $data;
    }

    private static function sanitize_attributes(array $attributes) {
        $clean = [];
        foreach ($attributes as $key => $value) {
            $key = sanitize_key((string) $key);
            if ($key === '' || !is_scalar($value) || strlen((string) $value) > 120) {
                return new WP_Error('invalid_attribute', __('Les clés des caractéristiques doivent être valides et leurs valeurs courtes.', 'wp-catalog-api'), ['status' => 400]);
            }
            $clean[$key] = sanitize_text_field((string) $value);
        }
        return $clean;
    }

    private function persist_product_fields(int $post_id, array $data): void {
        update_post_meta($post_id, '_wpcatalog_sku', $data['sku']);
        update_post_meta($post_id, '_wpcatalog_brand', $data['brand']);
        update_post_meta($post_id, '_wpcatalog_price', $data['price']);
        update_post_meta($post_id, '_wpcatalog_sale_price', $data['sale_price']);
        update_post_meta($post_id, '_wpcatalog_currency', $data['currency']);
        update_post_meta($post_id, '_wpcatalog_stock_status', $data['stock_status']);
        update_post_meta($post_id, '_wpcatalog_stock_quantity', $data['stock_quantity']);
        update_post_meta($post_id, '_wpcatalog_manage_stock', $data['manage_stock'] ? 1 : 0);
        update_post_meta($post_id, '_wpcatalog_featured', $data['featured'] ? 1 : 0);
        update_post_meta($post_id, '_wpcatalog_attributes', $data['attributes']);
        $post = get_post($post_id);
        if ($post instanceof WP_Post) $this->sync_product_data($post_id, $post);
    }

    private function persist_product_categories(int $post_id, array $category_ids): void {
        wp_set_object_terms($post_id, $category_ids, self::TAXONOMY, false);
    }

    private function format_product(WP_Post $post): array {
        $terms = get_the_terms($post->ID, self::TAXONOMY);
        $categories = [];
        if (is_array($terms)) {
            foreach ($terms as $term) $categories[] = ['id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug];
        }
        $attributes = get_post_meta($post->ID, '_wpcatalog_attributes', true);
        $regular = (float) get_post_meta($post->ID, '_wpcatalog_price', true);
        $sale = (float) get_post_meta($post->ID, '_wpcatalog_sale_price', true);
        $on_sale = $sale > 0 && $sale < $regular;
        global $wpdb;
        $sales_count = (int) $wpdb->get_var($wpdb->prepare('SELECT sales_count FROM ' . self::table_name() . ' WHERE post_id = %d', $post->ID));
        return [
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'slug' => $post->post_name,
            'excerpt' => get_the_excerpt($post),
            'description' => apply_filters('the_content', $post->post_content),
            'sku' => (string) get_post_meta($post->ID, '_wpcatalog_sku', true),
            'brand' => (string) get_post_meta($post->ID, '_wpcatalog_brand', true),
            'price' => $on_sale ? $sale : $regular,
            'regular_price' => $regular,
            'sale_price' => $on_sale ? $sale : null,
            'on_sale' => $on_sale,
            'currency' => (string) (get_post_meta($post->ID, '_wpcatalog_currency', true) ?: 'MAD'),
            'stock_status' => (string) (get_post_meta($post->ID, '_wpcatalog_stock_status', true) ?: 'instock'),
            'stock_quantity' => absint(get_post_meta($post->ID, '_wpcatalog_stock_quantity', true)),
            'manage_stock' => (bool) get_post_meta($post->ID, '_wpcatalog_manage_stock', true),
            'featured' => (bool) get_post_meta($post->ID, '_wpcatalog_featured', true),
            'sales_count' => $sales_count,
            'attributes' => is_array($attributes) ? $attributes : [],
            'categories' => $categories,
            'image' => get_the_post_thumbnail_url($post, 'large') ?: null,
            'permalink' => get_permalink($post),
            'created_at' => get_post_time(DATE_ATOM, true, $post),
            'updated_at' => get_post_modified_time(DATE_ATOM, true, $post),
        ];
    }
}

register_activation_hook(__FILE__, ['WP_Catalog_API', 'activate']);
register_deactivation_hook(__FILE__, ['WP_Catalog_API', 'deactivate']);
WP_Catalog_API::instance();
