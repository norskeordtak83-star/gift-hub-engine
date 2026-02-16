<?php

if (! defined('ABSPATH')) {
    exit;
}

class GHE_Template
{
    private const RELATED_LIMIT = 8;

    public static function register_hooks(): void
    {
        add_filter('template_include', [self::class, 'maybe_use_plugin_template']);
        add_action('wp_head', [self::class, 'output_faq_schema']);
        add_shortcode('gift_hub_list', [self::class, 'render_gift_hub_list_shortcode']);

        add_action('save_post_' . GHE_Post_Types::CPT, [self::class, 'clear_related_cache']);
        add_action('set_object_terms', [self::class, 'clear_related_cache_on_term_set'], 10, 6);
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
        $top_picks = self::get_top_picks_for_post($post->ID);
        if (! empty($top_picks)) {
            $top_picks_count = count($top_picks);
        }

        $related_posts = self::get_related_gift_pages($post->ID, self::RELATED_LIMIT);

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
                                <?php $term_url = get_term_link($term); ?>
                                <?php if (! is_wp_error($term_url)) : ?>
                                    <li><a href="<?php echo esc_url($term_url); ?>"><?php echo esc_html($term->name); ?></a></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <?php echo self::render_explore_more($post); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <section class="ghe-sections">
                <?php foreach ($sections as $heading) : ?>
                    <h2><?php echo esc_html($heading); ?></h2>
                    <p><?php esc_html_e('Use these ideas as evergreen inspiration and adapt them to your recipient’s local availability and preferences.', 'gift-hub-engine'); ?></p>
                <?php endforeach; ?>
            </section>

            <?php if (! empty($related_posts)) : ?>
                <section class="ghe-related">
                    <h2><?php esc_html_e('Related Gift Ideas', 'gift-hub-engine'); ?></h2>
                    <ul class="ghe-link-list">
                        <?php foreach ($related_posts as $related_post) : ?>
                            <li><a href="<?php echo esc_url(get_permalink($related_post)); ?>"><?php echo esc_html(get_the_title($related_post)); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

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
            .ghe-link-list{padding-left:1rem}
            .ghe-link-list li{margin:0.3rem 0}
            .ghe-product-slot{border:1px solid #e8e8e8;border-radius:10px;padding:0.85rem}
            .ghe-product-image{display:block;width:100%;max-width:240px;height:auto;border-radius:8px;margin:0 0 .65rem 0}
            .ghe-product-image-placeholder{display:grid;place-items:center;width:100%;max-width:240px;min-height:120px;border:1px dashed #d9d9d9;border-radius:8px;margin:0 0 .65rem 0;background:#fafafa}
            .ghe-affiliate-btn{display:inline-block;background:#0a7cff;color:#fff;padding:.45rem .7rem;border-radius:8px;text-decoration:none}
            @media(min-width:768px){.ghe-grid{grid-template-columns:1fr 1fr}}
        </style>
        <?php
        return (string) ob_get_clean();
    }

    public static function render_gift_hub_list_shortcode(array $atts = []): string
    {
        $atts = shortcode_atts([
            'taxonomy' => 'interest',
            'term' => '',
            'limit' => 24,
        ], $atts);

        $taxonomy = sanitize_key((string) $atts['taxonomy']);
        if (! in_array($taxonomy, GHE_Post_Types::TAXONOMIES, true)) {
            return '';
        }

        $term_input = sanitize_text_field((string) $atts['term']);
        $limit = max(1, min(100, (int) $atts['limit']));

        $term_obj = null;
        if ($term_input !== '') {
            $term_obj = get_term_by('slug', sanitize_title($term_input), $taxonomy);
            if (! $term_obj) {
                $term_obj = get_term_by('name', $term_input, $taxonomy);
            }
        }

        if (! $term_obj instanceof WP_Term) {
            return '';
        }

        $query = new WP_Query([
            'post_type' => GHE_Post_Types::CPT,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => ['title' => 'ASC', 'ID' => 'ASC'],
            'tax_query' => [[
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => [$term_obj->term_id],
            ]],
            'no_found_rows' => true,
        ]);

        if (! $query->have_posts()) {
            return '';
        }

        ob_start();
        ?>
        <section class="ghe-hub-list">
            <h2><?php echo esc_html(sprintf(__('More gift ideas for %s', 'gift-hub-engine'), $term_obj->name)); ?></h2>
            <ul class="ghe-link-list">
                <?php foreach ($query->posts as $gift_post) : ?>
                    <li><a href="<?php echo esc_url(get_permalink($gift_post)); ?>"><?php echo esc_html(get_the_title($gift_post)); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php
        wp_reset_postdata();
        return (string) ob_get_clean();
    }


    /**
     * @return array<int,array<string,string>>
     */
    public static function get_top_picks_for_post(int $post_id): array
    {
        $raw = get_post_meta($post_id, '_ghe_top_picks', true);
        if (! is_array($raw) || empty($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $asin = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($item['asin'] ?? '')));
            if ($asin === '') {
                continue;
            }

            $url = esc_url_raw((string) ($item['url'] ?? ''));
            if ($url === '') {
                $url = GHE_Settings::build_default_product_url($asin);
            }

            $label = sanitize_text_field((string) ($item['label'] ?? ''));
            $notes = sanitize_textarea_field((string) ($item['notes'] ?? ''));

            $image_url = esc_url_raw((string) ($item['image_url'] ?? ''));
            if (! self::is_allowed_custom_image_url($image_url)) {
                $image_url = '';
            }

            $enrichment = class_exists('GHE_Amazon_PAAPI') ? GHE_Amazon_PAAPI::get_item($asin) : null;
            if (is_array($enrichment)) {
                if (! empty($enrichment['title'])) {
                    $label = sanitize_text_field((string) $enrichment['title']);
                }

                if (! empty($enrichment['image_url'])) {
                    $image_url = esc_url_raw((string) $enrichment['image_url']);
                }

                if (! empty($enrichment['url'])) {
                    $url = esc_url_raw((string) $enrichment['url']);
                }
            }

            $items[] = [
                'asin' => $asin,
                'label' => $label,
                'notes' => $notes,
                'url' => $url,
                'image_url' => $image_url,
            ];
        }

        return $items;
    }

    /** @return array<string,string>|null */
    public static function get_top_pick_by_index(int $post_id, int $index): ?array
    {
        $index = max(1, $index) - 1;
        $top_picks = self::get_top_picks_for_post($post_id);
        return $top_picks[$index] ?? null;
    }

    private static function is_allowed_custom_image_url(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        return strpos($host, 'amazon.') === false && strpos($host, 'amzn.') === false;
    }

    public static function clear_related_cache(int $post_id): void
    {
        if (get_post_type($post_id) !== GHE_Post_Types::CPT) {
            return;
        }

        delete_transient(self::related_cache_key($post_id));
    }

    public static function clear_related_cache_on_term_set(int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids): void
    {
        if (! in_array($taxonomy, GHE_Post_Types::TAXONOMIES, true)) {
            return;
        }

        self::clear_related_cache($object_id);
    }

    /**
     * @return WP_Post[]
     */
    private static function get_related_gift_pages(int $post_id, int $limit): array
    {
        $cached = get_transient(self::related_cache_key($post_id));
        if (is_array($cached)) {
            return array_slice(array_values(array_filter(array_map('get_post', $cached))), 0, $limit);
        }

        $terms_by_tax = self::get_post_terms_map($post_id);
        if (empty($terms_by_tax)) {
            set_transient(self::related_cache_key($post_id), [], 12 * HOUR_IN_SECONDS);
            return [];
        }

        $tax_query = ['relation' => 'OR'];
        foreach ($terms_by_tax as $taxonomy => $term_ids) {
            $tax_query[] = [
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => $term_ids,
            ];
        }

        $query = new WP_Query([
            'post_type' => GHE_Post_Types::CPT,
            'post_status' => 'publish',
            'post__not_in' => [$post_id],
            'posts_per_page' => 64,
            'no_found_rows' => true,
            'fields' => 'ids',
            'tax_query' => $tax_query,
        ]);

        if (empty($query->posts)) {
            set_transient(self::related_cache_key($post_id), [], 12 * HOUR_IN_SECONDS);
            return [];
        }

        $scores = [];
        foreach ($query->posts as $candidate_id) {
            $scores[(int) $candidate_id] = self::calculate_related_score($terms_by_tax, (int) $candidate_id);
        }

        uasort($scores, static function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            if ($a['title'] !== $b['title']) {
                return strcmp($a['title'], $b['title']);
            }
            return $a['id'] <=> $b['id'];
        });

        $ids = array_slice(array_keys($scores), 0, $limit);
        set_transient(self::related_cache_key($post_id), $ids, 12 * HOUR_IN_SECONDS);

        return array_values(array_filter(array_map('get_post', $ids)));
    }

    /**
     * @return array<string,int[]>
     */
    private static function get_post_terms_map(int $post_id): array
    {
        $result = [];
        foreach (GHE_Post_Types::TAXONOMIES as $taxonomy) {
            $terms = get_the_terms($post_id, $taxonomy);
            if (! is_array($terms) || empty($terms)) {
                continue;
            }

            $result[$taxonomy] = array_values(array_unique(array_map(static function (WP_Term $term): int {
                return (int) $term->term_id;
            }, $terms)));
        }

        return $result;
    }

    /**
     * @param array<string,int[]> $source_terms
     * @return array{score:int,id:int,title:string}
     */
    private static function calculate_related_score(array $source_terms, int $candidate_id): array
    {
        $weights = [
            'interest' => 100,
            'occasion' => 40,
            'audience' => 20,
            'budget' => 10,
        ];

        $score = 0;
        foreach ($weights as $taxonomy => $weight) {
            $candidate_terms = wp_get_post_terms($candidate_id, $taxonomy, ['fields' => 'ids']);
            if (! is_array($candidate_terms) || empty($candidate_terms) || empty($source_terms[$taxonomy])) {
                continue;
            }

            $overlap = array_intersect($source_terms[$taxonomy], array_map('intval', $candidate_terms));
            if (! empty($overlap)) {
                $score += $weight + count($overlap);
            }
        }

        return [
            'score' => $score,
            'id' => $candidate_id,
            'title' => (string) get_the_title($candidate_id),
        ];
    }

    private static function render_explore_more(WP_Post $post): string
    {
        $term_links = [];

        foreach (GHE_Post_Types::TAXONOMIES as $taxonomy) {
            $terms = get_the_terms($post, $taxonomy);
            if (! is_array($terms) || empty($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $url = get_term_link($term);
                if (is_wp_error($url) || ! $url) {
                    $url = self::get_shortcode_fallback_url($taxonomy, $term);
                }

                if (! $url) {
                    continue;
                }

                $key = $taxonomy . ':' . (string) $term->term_id;
                $term_links[$key] = [
                    'url' => (string) $url,
                    'label' => sprintf('%s: %s', ucfirst($taxonomy), $term->name),
                ];
            }
        }

        if (empty($term_links)) {
            return '';
        }

        ksort($term_links);

        ob_start();
        ?>
        <section class="ghe-explore-more">
            <h2><?php esc_html_e('Explore more', 'gift-hub-engine'); ?></h2>
            <ul class="ghe-link-list">
                <?php foreach ($term_links as $item) : ?>
                    <li><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function get_shortcode_fallback_url(string $taxonomy, WP_Term $term): string
    {
        $archive_url = get_post_type_archive_link(GHE_Post_Types::CPT);
        if (! is_string($archive_url) || $archive_url === '') {
            $archive_url = home_url('/');
        }

        return add_query_arg([
            $taxonomy => $term->slug,
            'post_type' => GHE_Post_Types::CPT,
        ], $archive_url);
    }

    private static function related_cache_key(int $post_id): string
    {
        return 'ghe_related_' . $post_id;
    }
}
