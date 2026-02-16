<?php

if (! defined('ABSPATH')) {
    exit;
}

class GHE_Settings
{
    private const OPTION_DOMAIN = 'ghe_amazon_domain';
    private const OPTION_TAG = 'ghe_associate_tag';
    private const SETTINGS_GROUP = 'ghe_settings_group';
    private const SETTINGS_PAGE = 'ghe-settings';

    public static function register_hooks(): void
    {
        if (! is_admin()) {
            return;
        }

        add_action('admin_menu', [self::class, 'register_settings_page']);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function register_settings_page(): void
    {
        add_options_page(
            __('Gift Hub Engine', 'gift-hub-engine'),
            __('Gift Hub Engine', 'gift-hub-engine'),
            'manage_options',
            self::SETTINGS_PAGE,
            [self::class, 'render_settings_page']
        );
    }

    public static function register_settings(): void
    {
        register_setting(self::SETTINGS_GROUP, self::OPTION_DOMAIN, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_domain'],
            'default' => 'amazon.com',
        ]);

        register_setting(self::SETTINGS_GROUP, self::OPTION_TAG, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_associate_tag'],
            'default' => '',
        ]);

        add_settings_section(
            'ghe_amazon_settings',
            __('Amazon affiliate defaults', 'gift-hub-engine'),
            '__return_false',
            self::SETTINGS_PAGE
        );

        add_settings_field(
            self::OPTION_DOMAIN,
            __('Default Amazon marketplace domain', 'gift-hub-engine'),
            [self::class, 'render_domain_field'],
            self::SETTINGS_PAGE,
            'ghe_amazon_settings'
        );

        add_settings_field(
            self::OPTION_TAG,
            __('Default Associate/Tracking tag', 'gift-hub-engine'),
            [self::class, 'render_tag_field'],
            self::SETTINGS_PAGE,
            'ghe_amazon_settings'
        );
    }

    public static function render_domain_field(): void
    {
        $value = self::get_domain();
        echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_DOMAIN) . '" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . esc_html__('Examples: amazon.com, amazon.co.uk, amazon.de', 'gift-hub-engine') . '</p>';
    }

    public static function render_tag_field(): void
    {
        $value = self::get_associate_tag();
        echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_TAG) . '" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . esc_html__('Used when a top_picks item omits a url.', 'gift-hub-engine') . '</p>';
    }

    public static function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Gift Hub Engine', 'gift-hub-engine'); ?></h1>
            <form action="options.php" method="post">
                <?php settings_fields(self::SETTINGS_GROUP); ?>
                <?php do_settings_sections(self::SETTINGS_PAGE); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function get_domain(): string
    {
        $domain = get_option(self::OPTION_DOMAIN, 'amazon.com');
        return self::sanitize_domain(is_string($domain) ? $domain : 'amazon.com');
    }

    public static function get_associate_tag(): string
    {
        $tag = get_option(self::OPTION_TAG, '');
        return self::sanitize_associate_tag(is_string($tag) ? $tag : '');
    }

    public static function build_default_product_url(string $asin): string
    {
        $asin = strtoupper(preg_replace('/[^A-Z0-9]/', '', $asin));
        if ($asin === '') {
            return '#';
        }

        $domain = self::get_domain();
        $tag = self::get_associate_tag();
        $url = 'https://' . $domain . '/dp/' . rawurlencode($asin) . '/';

        if ($tag !== '') {
            $url = add_query_arg(['tag' => $tag], $url);
        }

        return esc_url_raw($url);
    }

    public static function sanitize_domain($value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('#^https?://#', '', $value);
        $value = trim((string) $value, '/');
        if ($value === '') {
            return 'amazon.com';
        }
        return sanitize_text_field($value);
    }

    public static function sanitize_associate_tag($value): string
    {
        return sanitize_text_field(trim((string) $value));
    }
}
