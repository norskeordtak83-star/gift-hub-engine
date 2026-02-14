<?php

if (! defined('ABSPATH')) {
    exit;
}

class GHE_Post_Types
{
    public const CPT = 'gift_page';

    /** @var string[] */
    public const TAXONOMIES = ['audience', 'occasion', 'budget', 'interest'];

    public static function register_hooks(): void
    {
        add_action('init', [self::class, 'register_content_types']);
        add_shortcode('gift_hub_product_slot', [self::class, 'render_product_slot_shortcode']);
    }

    public static function register_content_types(): void
    {
        register_post_type(self::CPT, [
            'labels' => [
                'name' => __('Gift Pages', 'gift-hub-engine'),
                'singular_name' => __('Gift Page', 'gift-hub-engine'),
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-gifts',
            'rewrite' => ['slug' => 'gift-ideas'],
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail'],
        ]);

        foreach (self::TAXONOMIES as $taxonomy) {
            register_taxonomy($taxonomy, [self::CPT], [
                'label' => ucfirst($taxonomy),
                'public' => true,
                'hierarchical' => false,
                'show_in_rest' => true,
                'rewrite' => ['slug' => $taxonomy],
            ]);
        }
    }

    public static function render_product_slot_shortcode(array $atts = []): string
    {
        $atts = shortcode_atts([
            'index' => 1,
            'label' => __('View on Amazon', 'gift-hub-engine'),
            'asin' => '',
            'link' => '#',
        ], $atts);

        $index = max(1, (int) $atts['index']);
        $label = esc_html($atts['label']);
        $asin = sanitize_text_field((string) $atts['asin']);
        $link = esc_url($atts['link']);

        $title = $asin ? sprintf(__('Product %1$d (ASIN: %2$s)', 'gift-hub-engine'), $index, $asin) : sprintf(__('Product %d', 'gift-hub-engine'), $index);

        return sprintf(
            '<article class="ghe-product-slot"><h3>%1$s</h3><p>%2$s</p><a class="ghe-affiliate-btn" href="%3$s" rel="nofollow sponsored noopener" target="_blank">%4$s</a></article>',
            esc_html($title),
            esc_html__('Placeholder product box. Replace with your affiliate widget or shortcode.', 'gift-hub-engine'),
            $link,
            $label
        );
    }
}
