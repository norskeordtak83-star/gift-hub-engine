<?php

if (! defined('ABSPATH')) {
    exit;
}

class GHE_Importer
{
    public const DATA_FILE = 'data/categories.json';

    /**
     * @return array{created:int,updated:int,skipped:int,errors:string[],validated:int,validation_failures:int,posts:int[]}
     */
    public function sync(): array
    {
        $payload = $this->read_dataset();
        $report = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'validated' => 0,
            'validation_failures' => 0,
            'posts' => [],
        ];

        if (empty($payload['pages']) || ! is_array($payload['pages'])) {
            $report['errors'][] = __('Dataset is empty or missing pages.', 'gift-hub-engine');
            return $report;
        }

        foreach ($payload['pages'] as $page) {
            if (! is_array($page)) {
                $report['skipped']++;
                continue;
            }

            $result = $this->upsert_page($page);
            if ($result['status'] === 'created') {
                $report['created']++;
            } elseif ($result['status'] === 'updated') {
                $report['updated']++;
            } else {
                $report['skipped']++;
            }

            if (! empty($result['error'])) {
                $report['errors'][] = $result['error'];
                continue;
            }

            if (! empty($result['post_id'])) {
                $report['posts'][] = (int) $result['post_id'];
            }
        }

        $report['posts'] = array_values(array_unique($report['posts']));

        foreach ($report['posts'] as $post_id) {
            $report['validated']++;
            if (! GHE_Template::validate_render($post_id)) {
                $report['validation_failures']++;
            }
        }

        return $report;
    }

    /** @return array<string,mixed> */
    private function read_dataset(): array
    {
        $file = trailingslashit(GHE_PLUGIN_DIR) . self::DATA_FILE;

        if (! file_exists($file)) {
            return [];
        }

        $json = file_get_contents($file);
        if (! $json) {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $page */
    private function upsert_page(array $page): array
    {
        $slug = sanitize_title((string) ($page['slug'] ?? ''));
        $title = sanitize_text_field((string) ($page['title'] ?? ''));

        if (! $slug || ! $title) {
            return ['status' => 'skipped', 'error' => __('Missing slug/title in dataset page row.', 'gift-hub-engine')];
        }

        $existing = get_page_by_path($slug, OBJECT, GHE_Post_Types::CPT);
        $is_update = $existing instanceof WP_Post;

        $post_data = [
            'post_type' => GHE_Post_Types::CPT,
            'post_status' => 'publish',
            'post_name' => $slug,
            'post_title' => $title,
            'post_content' => wp_kses_post((string) ($page['intro'] ?? '')),
        ];

        if ($is_update) {
            $post_data['ID'] = $existing->ID;
            $post_id = wp_update_post($post_data, true);
            $status = 'updated';
        } else {
            $post_id = wp_insert_post($post_data, true);
            $status = 'created';
        }

        if (is_wp_error($post_id)) {
            return ['status' => 'skipped', 'error' => $post_id->get_error_message()];
        }

        $this->save_page_meta((int) $post_id, $page);
        $this->assign_terms((int) $post_id, $page);

        return ['status' => $status, 'post_id' => (int) $post_id];
    }

    /** @param array<string,mixed> $page */
    private function save_page_meta(int $post_id, array $page): void
    {
        update_post_meta($post_id, '_ghe_intro', wp_kses_post((string) ($page['intro'] ?? '')));

        $sections = array_values(array_filter(array_map('sanitize_text_field', (array) ($page['section_headings'] ?? []))));
        update_post_meta($post_id, '_ghe_sections', $sections);

        $faq = [];
        foreach ((array) ($page['faq'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $question = sanitize_text_field((string) ($item['question'] ?? ''));
            $answer = sanitize_textarea_field((string) ($item['answer'] ?? ''));
            if ($question && $answer) {
                $faq[] = ['question' => $question, 'answer' => $answer];
            }
        }
        update_post_meta($post_id, '_ghe_faq', $faq);

        $top_picks = max(1, (int) ($page['top_picks_count'] ?? 6));
        update_post_meta($post_id, '_ghe_top_picks_count', $top_picks);

        $top_picks_items = [];
        foreach ((array) ($page['top_picks'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $asin = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($item['asin'] ?? '')));
            if ($asin === '') {
                continue;
            }

            $label = sanitize_text_field((string) ($item['label'] ?? ''));
            $notes = sanitize_textarea_field((string) ($item['notes'] ?? ''));
            $url = esc_url_raw((string) ($item['url'] ?? ''));
            $image_url = esc_url_raw((string) ($item['image_url'] ?? ''));

            $top_picks_items[] = [
                'asin' => $asin,
                'label' => $label,
                'url' => $url,
                'notes' => $notes,
                'image_url' => $image_url,
            ];
        }

        update_post_meta($post_id, '_ghe_top_picks', $top_picks_items);
    }

    /** @param array<string,mixed> $page */
    private function assign_terms(int $post_id, array $page): void
    {
        foreach (GHE_Post_Types::TAXONOMIES as $taxonomy) {
            $terms = array_values(array_filter(array_map('sanitize_text_field', (array) ($page[$taxonomy] ?? []))));
            if (empty($terms)) {
                continue;
            }
            wp_set_post_terms($post_id, $terms, $taxonomy, false);
        }
    }
}
