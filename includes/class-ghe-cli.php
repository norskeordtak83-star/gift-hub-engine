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
     */
    public static function sync(): void
    {
        $importer = new GHE_Importer();
        $report = $importer->sync();

        WP_CLI::log('Gift Hub sync completed.');
        WP_CLI::log(sprintf('Created: %d', $report['created']));
        WP_CLI::log(sprintf('Updated: %d', $report['updated']));
        WP_CLI::log(sprintf('Skipped: %d', $report['skipped']));
        WP_CLI::log(sprintf('Validated render checks: %d', $report['validated']));

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
