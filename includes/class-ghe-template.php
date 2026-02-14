<?php

if (! defined('ABSPATH')) {
    exit;
}

class GHE_Template
{
    public static function register_hooks(): void
    {
        add_filter('template_include', [self::class, 'maybe_use_plugin_template']);
        add_action('wp_head', [self::class, 'output_faq_schema']);
    }

    public static function maybe_use_plugin_template(string $template): string
    {
        if (! is_singular(GHE_Post_Types::CPT)) {
            return $template;
        }

        $plugin_template = GHE_PLUGIN_DIR . 'templates/single-gift_page.php';
        return file_exists($plugin_template) ? $plugin_template : $template;
    }

    public static function output_faq_schema(): void
    {
        if (! is_singular(GHE_Post_Types::CPT)) {
            return;
        }

        $post_id = get_the_ID();
        $faq = get_post_meta($post_id, '_ghe_faq', true);
        if (! is_array($faq) || empty($faq)) {
            return;
        }

        $entities = [];
        foreach ($faq as $item) {
            if (empty($item['question']) || empty($item['answer'])) {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => wp_strip_all_tags((string) $item['question']),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => wp_strip_all_tags((string) $item['answer']),
                ],
            ];
        }

        if (empty($entities)) {
            return;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    public static function validate_render(int $post_id): bool
    {
        $post = get_post($post_id);
        if (! $post || $post->post_type !== GHE_Post_Types::CPT) {
            return false;
        }

        try {
            $html = self::render_markup($post);
            return strlen(trim($html)) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function render_markup(WP_Post $post): string
    {
        $intro = (string) get_post_meta($post->ID, '_ghe_intro', true);
        $sections = get_post_meta($post->ID, '_ghe_sections', true);
        $faq = get_post_meta($post->ID, '_ghe_faq', true);
        $top_picks_count = max(1, (int) get_post_meta($post->ID, '_ghe_top_picks_count', true));

        $sections = is_array($sections) ? $sections : [];
        $faq = is_array($faq) ? $faq : [];

        ob_start();
        ?>
        <article class="ghe-page">
            <header class="ghe-hero">
                <!-- ad-slot: hero-top -->
                <h1><?php echo esc_html(get_the_title($post)); ?></h1>
                <div class="ghe-intro"><?php echo wpautop(wp_kses_post($intro)); ?></div>
            </header>
            <nav class="ghe-taxonomy-nav" aria-label="<?php esc_attr_e('Quick jumps', 'gift-hub-engine'); ?>">
                <?php foreach (['budget', 'interest'] as $taxonomy) : ?>
                    <?php $terms = get_the_terms($post, $taxonomy); ?>
                    <?php if (is_array($terms) && ! empty($terms)) : ?>
                        <h2><?php echo esc_html(ucfirst($taxonomy)); ?></h2>
                        <ul>
                            <?php foreach ($terms as $term) : ?>
                                <li><a href="<?php echo esc_url(get_term_link($term)); ?>"><?php echo esc_html($term->name); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <section class="ghe-sections">
                <?php foreach ($sections as $heading) : ?>
                    <h2><?php echo esc_html($heading); ?></h2>
                    <p><?php esc_html_e('Use these ideas as evergreen inspiration and adapt them to your recipient’s local availability and preferences.', 'gift-hub-engine'); ?></p>
                <?php endforeach; ?>
            </section>

            <section class="ghe-top-picks">
                <!-- ad-slot: before-top-picks -->
                <h2><?php esc_html_e('Top Picks', 'gift-hub-engine'); ?></h2>
                <div class="ghe-grid">
                    <?php for ($i = 1; $i <= $top_picks_count; $i++) : ?>
                        <?php echo do_shortcode('[gift_hub_product_slot index="' . (int) $i . '"]'); ?>
                    <?php endfor; ?>
                </div>
                <!-- ad-slot: after-top-picks -->
            </section>

            <section class="ghe-faq">
                <h2><?php esc_html_e('Frequently Asked Questions', 'gift-hub-engine'); ?></h2>
                <?php foreach ($faq as $item) : ?>
                    <details>
                        <summary><?php echo esc_html((string) ($item['question'] ?? '')); ?></summary>
                        <p><?php echo esc_html((string) ($item['answer'] ?? '')); ?></p>
                    </details>
                <?php endforeach; ?>
            </section>
        </article>
        <style>
            .ghe-page{padding:1rem;max-width:860px;margin:0 auto;line-height:1.6}
            .ghe-hero{padding:1.25rem;background:#f5f7ff;border-radius:12px}
            .ghe-grid{display:grid;grid-template-columns:1fr;gap:0.75rem}
            .ghe-product-slot{border:1px solid #e8e8e8;border-radius:10px;padding:0.85rem}
            .ghe-affiliate-btn{display:inline-block;background:#0a7cff;color:#fff;padding:.45rem .7rem;border-radius:8px;text-decoration:none}
            @media(min-width:768px){.ghe-grid{grid-template-columns:1fr 1fr}}
        </style>
        <?php
        return (string) ob_get_clean();
    }
}
