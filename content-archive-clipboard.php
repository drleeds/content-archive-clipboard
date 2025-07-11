<?php
/**
 * Plugin Name: Content Archive Clipboard
 * Plugin URI: https://yoursite.com/plugins/content-archive-clipboard
 * Description: Provides a single-page archive of all blog posts with clipboard copy functionality, filtering, and export options.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: content-archive-clipboard
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CAC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CAC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CAC_VERSION', '1.0.0');

class ContentArchiveClipboard {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // AJAX handlers
        add_action('wp_ajax_cac_get_posts', array($this, 'ajax_get_posts'));
        add_action('wp_ajax_nopriv_cac_get_posts', array($this, 'ajax_get_posts'));
        add_action('wp_ajax_cac_export_posts', array($this, 'ajax_export_posts'));
        add_action('wp_ajax_nopriv_cac_export_posts', array($this, 'ajax_export_posts'));
        
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        
        // Shortcode
        add_shortcode('content_archive', array($this, 'shortcode_handler'));
        
        // Gutenberg block
        add_action('init', array($this, 'register_block'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('content-archive-clipboard', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function activate() {
        // Set default options
        $default_options = array(
            'date_format' => 'Y-m-d',
            'show_categories' => true,
            'enable_csv_export' => true,
            'enable_markdown_export' => true,
            'posts_per_load' => 50,
            'cache_duration' => 3600, // 1 hour
            'copy_button_text' => __('Copy to Clipboard', 'content-archive-clipboard'),
            'export_button_text' => __('Export', 'content-archive-clipboard')
        );
        
        if (!get_option('cac_settings')) {
            add_option('cac_settings', $default_options);
        }
        
        // Clear any existing cache
        delete_transient('cac_posts_cache');
    }
    
    public function deactivate() {
        // Clear cache on deactivation
        delete_transient('cac_posts_cache');
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_script(
            'cac-frontend',
            CAC_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            CAC_VERSION,
            true
        );
        
        wp_enqueue_style(
            'cac-frontend',
            CAC_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            CAC_VERSION
        );
        
        wp_localize_script('cac-frontend', 'cac_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cac_nonce'),
            'strings' => array(
                'copy_success' => __('Content copied to clipboard!', 'content-archive-clipboard'),
                'copy_error' => __('Failed to copy content. Please try again.', 'content-archive-clipboard'),
                'loading' => __('Loading...', 'content-archive-clipboard'),
                'no_posts' => __('No posts found.', 'content-archive-clipboard')
            )
        ));
    }
    
    public function enqueue_admin_assets($hook) {
        if ('settings_page_content-archive-clipboard' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'cac-admin',
            CAC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CAC_VERSION
        );
    }
    
    public function shortcode_handler($atts) {
        $atts = shortcode_atts(array(
            'posts_per_page' => -1,
            'post_type' => 'post',
            'show_filters' => true,
            'show_export' => true
        ), $atts, 'content_archive');
        
        return $this->render_archive($atts);
    }
    
    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }
        
        wp_register_script(
            'cac-block-editor',
            CAC_PLUGIN_URL . 'assets/js/block-editor.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
            CAC_VERSION,
            true
        );
        
        register_block_type('content-archive-clipboard/archive', array(
            'editor_script' => 'cac-block-editor',
            'render_callback' => array($this, 'block_render_callback')
        ));
    }
    
    public function block_render_callback($attributes) {
        $defaults = array(
            'postsPerPage' => -1,
            'postType' => 'post',
            'showFilters' => true,
            'showExport' => true
        );
        
        $attributes = wp_parse_args($attributes, $defaults);
        
        $atts = array(
            'posts_per_page' => $attributes['postsPerPage'],
            'post_type' => $attributes['postType'],
            'show_filters' => $attributes['showFilters'],
            'show_export' => $attributes['showExport']
        );
        
        return $this->render_archive($atts);
    }
    
    private function render_archive($atts) {
        $settings = get_option('cac_settings', array());
        
        ob_start();
        ?>
        <div class="content-archive-clipboard" data-atts="<?php echo esc_attr(json_encode($atts)); ?>">
            <?php if ($atts['show_filters']): ?>
            <div class="cac-filters">
                <div class="cac-filter-row">
                    <div class="cac-filter-group">
                        <label for="cac-date-from"><?php _e('From:', 'content-archive-clipboard'); ?></label>
                        <input type="date" id="cac-date-from" class="cac-date-filter" />
                    </div>
                    <div class="cac-filter-group">
                        <label for="cac-date-to"><?php _e('To:', 'content-archive-clipboard'); ?></label>
                        <input type="date" id="cac-date-to" class="cac-date-filter" />
                    </div>
                    <?php if ($settings['show_categories'] ?? true): ?>
                    <div class="cac-filter-group">
                        <label for="cac-category"><?php _e('Category:', 'content-archive-clipboard'); ?></label>
                        <select id="cac-category" class="cac-category-filter">
                            <option value=""><?php _e('All Categories', 'content-archive-clipboard'); ?></option>
                            <?php
                            $categories = get_categories();
                            foreach ($categories as $category) {
                                echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="cac-filter-group">
                        <button type="button" class="cac-filter-btn button"><?php _e('Filter', 'content-archive-clipboard'); ?></button>
                        <button type="button" class="cac-clear-btn button"><?php _e('Clear', 'content-archive-clipboard'); ?></button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="cac-actions">
                <button type="button" class="cac-copy-btn button button-primary" aria-live="polite">
                    <?php echo esc_html($settings['copy_button_text'] ?? __('Copy to Clipboard', 'content-archive-clipboard')); ?>
                </button>
                <?php if ($atts['show_export']): ?>
                <div class="cac-export-group">
                    <button type="button" class="cac-export-btn button">
                        <?php echo esc_html($settings['export_button_text'] ?? __('Export', 'content-archive-clipboard')); ?>
                    </button>
                    <div class="cac-export-options" style="display: none;">
                        <?php if ($settings['enable_csv_export'] ?? true): ?>
                        <button type="button" class="cac-export-csv button"><?php _e('Export CSV', 'content-archive-clipboard'); ?></button>
                        <?php endif; ?>
                        <?php if ($settings['enable_markdown_export'] ?? true): ?>
                        <button type="button" class="cac-export-md button"><?php _e('Export Markdown', 'content-archive-clipboard'); ?></button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="cac-loading" style="display: none;">
                <p><?php _e('Loading posts...', 'content-archive-clipboard'); ?></p>
            </div>
            
            <div class="cac-posts-container">
                <div class="cac-posts-list">
                    <?php echo $this->get_posts_html($atts); ?>
                </div>
            </div>
            
            <div class="cac-status" role="status" aria-live="polite" aria-atomic="true"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function get_posts_html($atts = array(), $filters = array()) {
        $settings = get_option('cac_settings', array());
        $cache_key = 'cac_posts_' . md5(serialize(array($atts, $filters)));
        
        $cached_html = get_transient($cache_key);
        if ($cached_html !== false) {
            return $cached_html;
        }
        
        $query_args = array(
            'post_type' => $atts['post_type'] ?? 'post',
            'post_status' => 'publish',
            'posts_per_page' => $atts['posts_per_page'] ?? -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        );
        
        // Apply filters
        if (!empty($filters['date_from'])) {
            $query_args['date_query']['after'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $query_args['date_query']['before'] = $filters['date_to'];
        }
        if (!empty($filters['category'])) {
            $query_args['cat'] = intval($filters['category']);
        }
        
        $posts = get_posts($query_args);
        
        if (empty($posts)) {
            return '<p class="cac-no-posts">' . __('No posts found.', 'content-archive-clipboard') . '</p>';
        }
        
        $html = '<div class="cac-posts-table">';
        $html .= '<div class="cac-post-header">';
        $html .= '<div class="cac-post-title-header">' . __('Title', 'content-archive-clipboard') . '</div>';
        $html .= '<div class="cac-post-date-header">' . __('Date', 'content-archive-clipboard') . '</div>';
        $html .= '</div>';
        
        $date_format = $settings['date_format'] ?? 'Y-m-d';
        
        foreach ($posts as $post) {
            $html .= '<div class="cac-post-row">';
            $html .= '<div class="cac-post-title">' . esc_html($post->post_title) . '</div>';
            $html .= '<div class="cac-post-date">' . esc_html(get_the_date($date_format, $post)) . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        // Cache the result
        set_transient($cache_key, $html, $settings['cache_duration'] ?? 3600);
        
        return $html;
    }
    
    public function ajax_get_posts() {
        check_ajax_referer('cac_nonce', 'nonce');
        
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? '')
        );
        
        $atts = json_decode(stripslashes($_POST['atts'] ?? '{}'), true);
        
        $html = $this->get_posts_html($atts, $filters);
        
        wp_send_json_success(array('html' => $html));
    }
    
    public function ajax_export_posts() {
        check_ajax_referer('cac_nonce', 'nonce');
        
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? '')
        );
        $atts = json_decode(stripslashes($_POST['atts'] ?? '{}'), true);
        
        $posts = $this->get_posts_for_export($atts, $filters);
        
        if ($format === 'csv') {
            $content = $this->generate_csv($posts);
            $filename = 'content-archive-' . date('Y-m-d') . '.csv';
            $mime_type = 'text/csv';
        } else {
            $content = $this->generate_markdown($posts);
            $filename = 'content-archive-' . date('Y-m-d') . '.md';
            $mime_type = 'text/markdown';
        }
        
        wp_send_json_success(array(
            'content' => base64_encode($content),
            'filename' => $filename,
            'mime_type' => $mime_type
        ));
    }
    
    private function get_posts_for_export($atts, $filters) {
        $query_args = array(
            'post_type' => $atts['post_type'] ?? 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        );
        
        // Apply filters
        if (!empty($filters['date_from'])) {
            $query_args['date_query']['after'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $query_args['date_query']['before'] = $filters['date_to'];
        }
        if (!empty($filters['category'])) {
            $query_args['cat'] = intval($filters['category']);
        }
        
        return get_posts($query_args);
    }
    
    private function generate_csv($posts) {
        $output = fopen('php://temp', 'w');
        
        // Add header
        fputcsv($output, array('Title', 'Date', 'URL'));
        
        $settings = get_option('cac_settings', array());
        $date_format = $settings['date_format'] ?? 'Y-m-d';
        
        foreach ($posts as $post) {
            fputcsv($output, array(
                $post->post_title,
                get_the_date($date_format, $post),
                get_permalink($post)
            ));
        }
        
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        
        return $content;
    }
    
    private function generate_markdown($posts) {
        $content = "# Content Archive\n\n";
        $content .= "Generated on " . current_time('Y-m-d H:i:s') . "\n\n";
        $content .= "## Posts\n\n";
        
        $settings = get_option('cac_settings', array());
        $date_format = $settings['date_format'] ?? 'Y-m-d';
        
        foreach ($posts as $post) {
            $date = get_the_date($date_format, $post);
            $url = get_permalink($post);
            $content .= "- [{$post->post_title}]({$url}) - {$date}\n";
        }
        
        return $content;
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('Content Archive Settings', 'content-archive-clipboard'),
            __('Content Archive', 'content-archive-clipboard'),
            'manage_options',
            'content-archive-clipboard',
            array($this, 'admin_page')
        );
    }
    
    public function admin_init() {
        register_setting(
            'cac_settings_group',
            'cac_settings',
            array($this, 'sanitize_settings')
        );
        
        add_settings_section(
            'cac_main_section',
            __('Display Settings', 'content-archive-clipboard'),
            array($this, 'main_section_callback'),
            'content-archive-clipboard'
        );
        
        add_settings_field(
            'date_format',
            __('Date Format', 'content-archive-clipboard'),
            array($this, 'date_format_callback'),
            'content-archive-clipboard',
            'cac_main_section'
        );
        
        add_settings_field(
            'show_categories',
            __('Show Category Filter', 'content-archive-clipboard'),
            array($this, 'show_categories_callback'),
            'content-archive-clipboard',
            'cac_main_section'
        );
        
        add_settings_field(
            'copy_button_text',
            __('Copy Button Text', 'content-archive-clipboard'),
            array($this, 'copy_button_text_callback'),
            'content-archive-clipboard',
            'cac_main_section'
        );
        
        add_settings_field(
            'export_button_text',
            __('Export Button Text', 'content-archive-clipboard'),
            array($this, 'export_button_text_callback'),
            'content-archive-clipboard',
            'cac_main_section'
        );
        
        add_settings_field(
            'enable_csv_export',
            __('Enable CSV Export', 'content-archive-clipboard'),
            array($this, 'enable_csv_export_callback'),
            'content-archive-clipboard',
            'cac_main_section'
        );
        
        add_settings_field(
            'enable_markdown_export',
            __('Enable Markdown Export', 'content-archive-clipboard'),
            array($this, 'enable_markdown_export_callback'),
            'content-archive-clipboard',
            'cac_main_section'
        );
        
        add_settings_field(
            'cache_duration',
            __('Cache Duration (seconds)', 'content-archive-clipboard'),
            array($this, 'cache_duration_callback'),
            'content-archive-clipboard',
            'cac_main_section'
        );
    }
    
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['date_format'] = sanitize_text_field($input['date_format'] ?? 'Y-m-d');
        $sanitized['show_categories'] = !empty($input['show_categories']);
        $sanitized['copy_button_text'] = sanitize_text_field($input['copy_button_text'] ?? 'Copy to Clipboard');
        $sanitized['export_button_text'] = sanitize_text_field($input['export_button_text'] ?? 'Export');
        $sanitized['enable_csv_export'] = !empty($input['enable_csv_export']);
        $sanitized['enable_markdown_export'] = !empty($input['enable_markdown_export']);
        $sanitized['cache_duration'] = absint($input['cache_duration'] ?? 3600);
        
        // Clear cache when settings change
        delete_transient('cac_posts_cache');
        
        return $sanitized;
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Content Archive Settings', 'content-archive-clipboard'); ?></h1>
            
            <div class="cac-admin-info">
                <h2><?php _e('How to Use', 'content-archive-clipboard'); ?></h2>
                <p><?php _e('Add the content archive to any page or post using one of these methods:', 'content-archive-clipboard'); ?></p>
                <ul>
                    <li><strong><?php _e('Shortcode:', 'content-archive-clipboard'); ?></strong> <code>[content_archive]</code></li>
                    <li><strong><?php _e('Gutenberg Block:', 'content-archive-clipboard'); ?></strong> <?php _e('Search for "Content Archive" in the block inserter', 'content-archive-clipboard'); ?></li>
                </ul>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('cac_settings_group');
                do_settings_sections('content-archive-clipboard');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    public function main_section_callback() {
        echo '<p>' . __('Configure the default settings for your content archive.', 'content-archive-clipboard') . '</p>';
    }
    
    public function date_format_callback() {
        $settings = get_option('cac_settings', array());
        $value = $settings['date_format'] ?? 'Y-m-d';
        ?>
        <input type="text" name="cac_settings[date_format]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description">
            <?php _e('Date format for displaying post dates. Use PHP date format codes.', 'content-archive-clipboard'); ?>
            <br>
            <?php _e('Examples: Y-m-d (2023-12-31), F j, Y (December 31, 2023), d/m/Y (31/12/2023)', 'content-archive-clipboard'); ?>
        </p>
        <?php
    }
    
    public function show_categories_callback() {
        $settings = get_option('cac_settings', array());
        $checked = $settings['show_categories'] ?? true;
        ?>
        <input type="checkbox" name="cac_settings[show_categories]" value="1" <?php checked($checked); ?> />
        <label><?php _e('Show category filter dropdown', 'content-archive-clipboard'); ?></label>
        <?php
    }
    
    public function copy_button_text_callback() {
        $settings = get_option('cac_settings', array());
        $value = $settings['copy_button_text'] ?? 'Copy to Clipboard';
        ?>
        <input type="text" name="cac_settings[copy_button_text]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <?php
    }
    
    public function export_button_text_callback() {
        $settings = get_option('cac_settings', array());
        $value = $settings['export_button_text'] ?? 'Export';
        ?>
        <input type="text" name="cac_settings[export_button_text]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <?php
    }
    
    public function enable_csv_export_callback() {
        $settings = get_option('cac_settings', array());
        $checked = $settings['enable_csv_export'] ?? true;
        ?>
        <input type="checkbox" name="cac_settings[enable_csv_export]" value="1" <?php checked($checked); ?> />
        <label><?php _e('Allow CSV export of post list', 'content-archive-clipboard'); ?></label>
        <?php
    }
    
    public function enable_markdown_export_callback() {
        $settings = get_option('cac_settings', array());
        $checked = $settings['enable_markdown_export'] ?? true;
        ?>
        <input type="checkbox" name="cac_settings[enable_markdown_export]" value="1" <?php checked($checked); ?> />
        <label><?php _e('Allow Markdown export of post list', 'content-archive-clipboard'); ?></label>
        <?php
    }
    
    public function cache_duration_callback() {
        $settings = get_option('cac_settings', array());
        $value = $settings['cache_duration'] ?? 3600;
        ?>
        <input type="number" name="cac_settings[cache_duration]" value="<?php echo esc_attr($value); ?>" min="0" class="small-text" />
        <p class="description"><?php _e('How long to cache post lists (in seconds). Set to 0 to disable caching.', 'content-archive-clipboard'); ?></p>
        <?php
    }
}

// Initialize the plugin
ContentArchiveClipboard::get_instance();