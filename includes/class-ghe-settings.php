<?php

if (! defined('ABSPATH')) {
    exit;
}

class GHE_Settings
{
    private const OPTION_PAAPI_ENABLED = 'ghe_paapi_enabled';
    private const OPTION_PAAPI_MARKETPLACE = 'ghe_paapi_marketplace';
    private const OPTION_PAAPI_ACCESS_KEY = 'ghe_paapi_access_key';
    private const OPTION_PAAPI_SECRET_KEY = 'ghe_paapi_secret_key';
    private const OPTION_PAAPI_PARTNER_TAG = 'ghe_paapi_partner_tag';
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
        register_setting(self::SETTINGS_GROUP, self::OPTION_PAAPI_ENABLED, [
            'type' => 'boolean',
            'sanitize_callback' => [self::class, 'sanitize_bool'],
            'default' => false,
        ]);

        register_setting(self::SETTINGS_GROUP, self::OPTION_PAAPI_MARKETPLACE, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_marketplace'],
            'default' => 'US',
        ]);

        register_setting(self::SETTINGS_GROUP, self::OPTION_PAAPI_ACCESS_KEY, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_paapi_credential'],
            'default' => '',
        ]);

        register_setting(self::SETTINGS_GROUP, self::OPTION_PAAPI_SECRET_KEY, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_paapi_credential'],
            'default' => '',
        ]);

        register_setting(self::SETTINGS_GROUP, self::OPTION_PAAPI_PARTNER_TAG, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitize_associate_tag'],
            'default' => '',
        ]);

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
            'ghe_paapi_settings',
            __('Amazon Product Advertising API (PA-API v5)', 'gift-hub-engine'),
            '__return_false',
            self::SETTINGS_PAGE
        );

        add_settings_field(
            self::OPTION_PAAPI_ENABLED,
            __('Enable PA-API enrichment', 'gift-hub-engine'),
            [self::class, 'render_paapi_enabled_field'],
            self::SETTINGS_PAGE,
            'ghe_paapi_settings'
        );

        add_settings_field(
            self::OPTION_PAAPI_MARKETPLACE,
            __('Marketplace', 'gift-hub-engine'),
            [self::class, 'render_paapi_marketplace_field'],
            self::SETTINGS_PAGE,
            'ghe_paapi_settings'
        );

        add_settings_field(
            self::OPTION_PAAPI_ACCESS_KEY,
            __('Access Key', 'gift-hub-engine'),
            [self::class, 'render_paapi_access_key_field'],
            self::SETTINGS_PAGE,
            'ghe_paapi_settings'
        );

        add_settings_field(
            self::OPTION_PAAPI_SECRET_KEY,
            __('Secret Key', 'gift-hub-engine'),
            [self::class, 'render_paapi_secret_key_field'],
            self::SETTINGS_PAGE,
            'ghe_paapi_settings'
        );

        add_settings_field(
            self::OPTION_PAAPI_PARTNER_TAG,
            __('Partner Tag', 'gift-hub-engine'),
            [self::class, 'render_paapi_partner_tag_field'],
            self::SETTINGS_PAGE,
            'ghe_paapi_settings'
        );

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

    public static function render_paapi_enabled_field(): void
    {
        $enabled = self::is_paapi_enabled();
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_PAAPI_ENABLED) . '" value="1" ' . checked($enabled, true, false) . ' /> ';
        echo esc_html__('Fetch canonical Amazon title/image/url from PA-API cache when available.', 'gift-hub-engine') . '</label>';
    }

    public static function render_paapi_marketplace_field(): void
    {
        $value = self::get_paapi_marketplace();
        $choices = [
            'US' => __('United States (amazon.com)', 'gift-hub-engine'),
            'UK' => __('United Kingdom (amazon.co.uk)', 'gift-hub-engine'),
            'DE' => __('Germany (amazon.de)', 'gift-hub-engine'),
        ];

        echo '<select name="' . esc_attr(self::OPTION_PAAPI_MARKETPLACE) . '">';
        foreach ($choices as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public static function render_paapi_access_key_field(): void
    {
        $value = self::get_option_string(self::OPTION_PAAPI_ACCESS_KEY);
        echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_PAAPI_ACCESS_KEY) . '" value="' . esc_attr($value) . '" autocomplete="off" />';
    }

    public static function render_paapi_secret_key_field(): void
    {
        $value = self::get_option_string(self::OPTION_PAAPI_SECRET_KEY);
        echo '<input type="password" class="regular-text" name="' . esc_attr(self::OPTION_PAAPI_SECRET_KEY) . '" value="' . esc_attr($value) . '" autocomplete="off" />';
    }

    public static function render_paapi_partner_tag_field(): void
    {
        $value = self::get_option_string(self::OPTION_PAAPI_PARTNER_TAG);
        echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_PAAPI_PARTNER_TAG) . '" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . esc_html__('This Partner Tag is used in PA-API requests.', 'gift-hub-engine') . '</p>';
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

    public static function is_paapi_enabled(): bool
    {
        $enabled = get_option(self::OPTION_PAAPI_ENABLED, false);
        return self::sanitize_bool($enabled);
    }

    public static function get_paapi_marketplace(): string
    {
        $marketplace = get_option(self::OPTION_PAAPI_MARKETPLACE, 'US');
        return self::sanitize_marketplace(is_string($marketplace) ? $marketplace : 'US');
    }

    /**
     * @return array{access_key:string,secret_key:string,partner_tag:string,marketplace:string}
     */
    public static function get_paapi_config(): array
    {
        return [
            'access_key' => self::get_option_string(self::OPTION_PAAPI_ACCESS_KEY),
            'secret_key' => self::get_option_string(self::OPTION_PAAPI_SECRET_KEY),
            'partner_tag' => self::get_option_string(self::OPTION_PAAPI_PARTNER_TAG),
            'marketplace' => self::get_paapi_marketplace(),
        ];
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

    public static function sanitize_bool($value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function sanitize_marketplace($value): string
    {
        $value = strtoupper(sanitize_text_field((string) $value));
        if (! in_array($value, ['US', 'UK', 'DE'], true)) {
            return 'US';
        }
        return $value;
    }

    public static function sanitize_paapi_credential($value): string
    {
        return preg_replace('/\s+/', '', sanitize_text_field((string) $value));
    }

    private static function get_option_string(string $key): string
    {
        $value = get_option($key, '');
        return is_string($value) ? trim($value) : '';
    }
}
