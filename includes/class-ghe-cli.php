<?php

if (! defined('ABSPATH')) {
    exit;
}

class GHE_CLI
{
    public static function register_hooks(): void
    {
        WP_CLI::add_command('gift-hub sync', [self::class, 'sync']);
    }

    /**
     * Sync gift pages from local dataset.
     *
     * ## EXAMPLES
     *
     *     wp gift-hub sync
     *     wp gift-hub sync --warm-cache --batch-size=5 --sleep-ms=500
     */
    public static function sync(array $args = [], array $assoc_args = []): void
    {
        $importer = new GHE_Importer();
        $report = $importer->sync();

        $warm_cache = ! empty($assoc_args['warm-cache']);
        $batch_size = isset($assoc_args['batch-size']) ? max(1, (int) $assoc_args['batch-size']) : 5;
        $sleep_ms = isset($assoc_args['sleep-ms']) ? max(0, (int) $assoc_args['sleep-ms']) : 500;

        WP_CLI::log('Gift Hub sync completed.');
        WP_CLI::log(sprintf('Created: %d', $report['created']));
        WP_CLI::log(sprintf('Updated: %d', $report['updated']));
        WP_CLI::log(sprintf('Skipped: %d', $report['skipped']));
        WP_CLI::log(sprintf('Validated render checks: %d', $report['validated']));

        if ($warm_cache && class_exists('GHE_Amazon_PAAPI')) {
            $asins = [];
            foreach ($report['posts'] as $post_id) {
                $top_picks = get_post_meta((int) $post_id, '_ghe_top_picks', true);
                if (! is_array($top_picks)) {
                    continue;
                }

                foreach ($top_picks as $pick) {
                    if (! is_array($pick) || empty($pick['asin'])) {
                        continue;
                    }

                    $asin = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $pick['asin']));
                    if ($asin !== '') {
                        $asins[] = $asin;
                    }
                }
            }

            $warm_report = GHE_Amazon_PAAPI::warm_cache($asins, $batch_size, $sleep_ms);
            WP_CLI::log(sprintf('PA-API warm cache attempted: %d', (int) $warm_report['attempted']));
            WP_CLI::log(sprintf('PA-API warm cache hits: %d', (int) $warm_report['warmed']));
            if ((int) $warm_report['missing'] > 0) {
                WP_CLI::warning(sprintf('PA-API unresolved ASINs: %d', (int) $warm_report['missing']));
            }
        }

        if ($report['validation_failures'] > 0) {
            WP_CLI::warning(sprintf('Validation failures: %d', $report['validation_failures']));
        }

        if (! empty($report['errors'])) {
            foreach ($report['errors'] as $error) {
                WP_CLI::warning((string) $error);
            }
        }

        if ($report['validation_failures'] > 0) {
            WP_CLI::error('One or more gift pages failed validation.', false);
            return;
        }

        WP_CLI::success('All synced pages rendered successfully.');
    }
}
