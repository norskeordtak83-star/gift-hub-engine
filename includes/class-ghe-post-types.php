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
        $button_label = sanitize_text_field((string) $atts['label']);
        $fallback_asin = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $atts['asin']));
        $fallback_link = esc_url((string) $atts['link']);

        $post_id = get_the_ID();
        $pick = null;
        if (is_int($post_id) && $post_id > 0 && get_post_type($post_id) === self::CPT && class_exists('GHE_Template')) {
            $pick = GHE_Template::get_top_pick_by_index($post_id, $index);
        }

        if (is_array($pick)) {
            $title = sanitize_text_field((string) ($pick['label'] ?? ''));
            if ($title === '') {
                $title = __('Product', 'gift-hub-engine');
            }

            $notes = sanitize_textarea_field((string) ($pick['notes'] ?? ''));
            $url = esc_url((string) ($pick['url'] ?? '#'));
            $image_url = esc_url((string) ($pick['image_url'] ?? ''));

            $image_html = '<div class="ghe-product-image-placeholder" aria-hidden="true">📦</div>';
            if ($image_url !== '') {
                $image_html = '<img class="ghe-product-image" src="' . esc_url($image_url) . '" alt="" loading="lazy" decoding="async" />';
            }

            return sprintf(
                '<article class="ghe-product-slot">%1$s<h3>%2$s</h3><p>%3$s</p><a class="ghe-affiliate-btn" href="%4$s" rel="nofollow sponsored noopener" target="_blank">%5$s</a></article>',
                $image_html,
                esc_html($title),
                esc_html($notes),
                $url,
                esc_html($button_label)
            );
        }

        $title = $fallback_asin
            ? sprintf(__('Product %1$d (ASIN: %2$s)', 'gift-hub-engine'), $index, $fallback_asin)
            : sprintf(__('Product %d', 'gift-hub-engine'), $index);

        return sprintf(
            '<article class="ghe-product-slot"><h3>%1$s</h3><p>%2$s</p><a class="ghe-affiliate-btn" href="%3$s" rel="nofollow sponsored noopener" target="_blank">%4$s</a></article>',
            esc_html($title),
            esc_html__('Placeholder product box. Replace with your affiliate widget or shortcode.', 'gift-hub-engine'),
            $fallback_link,
            esc_html($button_label)
        );
    }
}
