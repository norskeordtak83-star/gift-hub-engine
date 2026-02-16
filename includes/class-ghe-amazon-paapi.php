<?php

if (! defined('ABSPATH')) {
    exit;
}

class GHE_Amazon_PAAPI
{
    private const CACHE_OPTION = 'ghe_paapi_cache';
    private const CACHE_TTL = 604800; // 7 days.

    /**
     * @return array<string,string>|null
     */
    public static function get_item(string $asin): ?array
    {
        $asin = self::normalize_asin($asin);
        if ($asin === '' || ! GHE_Settings::is_paapi_enabled()) {
            return null;
        }

        $config = GHE_Settings::get_paapi_config();
        if (empty($config['access_key']) || empty($config['secret_key']) || empty($config['partner_tag'])) {
            return null;
        }

        $marketplace = GHE_Settings::get_paapi_marketplace();
        $cache_key = self::cache_key($marketplace, $asin);
        $cache = self::get_cache();
        $now = time();

        if (isset($cache[$cache_key]) && is_array($cache[$cache_key])) {
            $entry = $cache[$cache_key];
            $expires_at = isset($entry['expires_at']) ? (int) $entry['expires_at'] : 0;
            if (! empty($entry['data']) && is_array($entry['data']) && $expires_at > $now) {
                return self::sanitize_payload($entry['data']);
            }

            $backoff_until = isset($entry['backoff_until']) ? (int) $entry['backoff_until'] : 0;
            if ($backoff_until > $now) {
                return null;
            }
        }

        $fresh = self::request_item($asin, $marketplace, $config);
        if (is_array($fresh) && ! empty($fresh)) {
            $cache[$cache_key] = [
                'data' => $fresh,
                'expires_at' => $now + self::CACHE_TTL,
                'backoff_until' => 0,
                'error_count' => 0,
                'updated_at' => $now,
            ];
            self::save_cache($cache);
            return self::sanitize_payload($fresh);
        }

        $existing = isset($cache[$cache_key]) && is_array($cache[$cache_key]) ? $cache[$cache_key] : [];
        $error_count = max(1, (int) ($existing['error_count'] ?? 0) + 1);
        $backoff_seconds = min(24 * HOUR_IN_SECONDS, (int) pow(2, min(10, $error_count)) * 60);

        $cache[$cache_key] = [
            'data' => isset($existing['data']) && is_array($existing['data']) ? $existing['data'] : [],
            'expires_at' => isset($existing['expires_at']) ? (int) $existing['expires_at'] : 0,
            'backoff_until' => $now + $backoff_seconds,
            'error_count' => $error_count,
            'updated_at' => $now,
        ];
        self::save_cache($cache);

        if (! empty($cache[$cache_key]['data']) && is_array($cache[$cache_key]['data'])) {
            return self::sanitize_payload($cache[$cache_key]['data']);
        }

        return null;
    }

    /**
     * @param string[] $asins
     */
    public static function warm_cache(array $asins, int $batch_size = 5, int $sleep_ms = 500): array
    {
        $summary = [
            'attempted' => 0,
            'warmed' => 0,
            'missing' => 0,
        ];

        if (! GHE_Settings::is_paapi_enabled()) {
            return $summary;
        }

        $batch_size = max(1, min(10, $batch_size));
        $sleep_ms = max(0, min(5000, $sleep_ms));

        $queue = [];
        foreach ($asins as $asin) {
            $normalized = self::normalize_asin((string) $asin);
            if ($normalized !== '') {
                $queue[$normalized] = $normalized;
            }
        }

        $chunks = array_chunk(array_values($queue), $batch_size);
        foreach ($chunks as $chunk_index => $chunk) {
            foreach ($chunk as $asin) {
                $summary['attempted']++;
                $item = self::get_item($asin);
                if ($item) {
                    $summary['warmed']++;
                } else {
                    $summary['missing']++;
                }
            }

            if ($sleep_ms > 0 && $chunk_index < count($chunks) - 1) {
                usleep($sleep_ms * 1000);
            }
        }

        return $summary;
    }

    private static function request_item(string $asin, string $marketplace, array $config): ?array
    {
        $market = self::marketplace_map($marketplace);

        $payload = [
            'ItemIds' => [$asin],
            'Resources' => [
                'Images.Primary.Large',
                'ItemInfo.Title',
                'DetailPageURL',
            ],
            'PartnerTag' => (string) $config['partner_tag'],
            'PartnerType' => 'Associates',
            'Marketplace' => $market['marketplace'],
        ];

        $body = wp_json_encode($payload);
        if (! is_string($body) || $body === '') {
            return null;
        }

        $headers = self::build_signed_headers(
            $market['region'],
            $market['host'],
            $body,
            (string) $config['access_key'],
            (string) $config['secret_key']
        );

        $response = wp_remote_post($market['endpoint'], [
            'timeout' => 10,
            'headers' => $headers,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $json = wp_remote_retrieve_body($response);
        $decoded = json_decode((string) $json, true);
        if (! is_array($decoded)) {
            return null;
        }

        $item = $decoded['ItemsResult']['Items'][0] ?? null;
        if (! is_array($item)) {
            return null;
        }

        $title = sanitize_text_field((string) ($item['ItemInfo']['Title']['DisplayValue'] ?? ''));
        $image_url = esc_url_raw((string) ($item['Images']['Primary']['Large']['URL'] ?? ''));
        $detail_url = esc_url_raw((string) ($item['DetailPageURL'] ?? ''));

        $clean_url = self::canonicalize_url($detail_url, $market['detail_host'], $asin);
        if ($title === '' && $image_url === '' && $clean_url === '') {
            return null;
        }

        return [
            'title' => $title,
            'image_url' => $image_url,
            'url' => $clean_url,
        ];
    }

    private static function canonicalize_url(string $detail_url, string $detail_host, string $asin): string
    {
        $asin = self::normalize_asin($asin);
        $path = '/dp/' . rawurlencode($asin) . '/';

        if ($detail_url !== '') {
            $parsed_path = wp_parse_url($detail_url, PHP_URL_PATH);
            if (is_string($parsed_path) && $parsed_path !== '') {
                if (preg_match('#/dp/[A-Z0-9]{10}#i', $parsed_path, $matches)) {
                    $path = rtrim($matches[0], '/') . '/';
                } else {
                    $path = rtrim($parsed_path, '/') . '/';
                }
            }
        }

        return esc_url_raw('https://' . $detail_host . $path);
    }

    private static function normalize_asin(string $asin): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', $asin));
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_cache(): array
    {
        $cache = get_option(self::CACHE_OPTION, []);
        return is_array($cache) ? $cache : [];
    }

    /** @param array<string,mixed> $cache */
    private static function save_cache(array $cache): void
    {
        update_option(self::CACHE_OPTION, $cache, false);
    }

    private static function cache_key(string $marketplace, string $asin): string
    {
        return strtolower($marketplace) . ':' . strtoupper($asin);
    }

    /**
     * @return array{host:string,endpoint:string,marketplace:string,region:string,detail_host:string}
     */
    private static function marketplace_map(string $marketplace): array
    {
        $map = [
            'US' => [
                'host' => 'webservices.amazon.com',
                'endpoint' => 'https://webservices.amazon.com/paapi5/getitems',
                'marketplace' => 'www.amazon.com',
                'region' => 'us-east-1',
                'detail_host' => 'www.amazon.com',
            ],
            'UK' => [
                'host' => 'webservices.amazon.co.uk',
                'endpoint' => 'https://webservices.amazon.co.uk/paapi5/getitems',
                'marketplace' => 'www.amazon.co.uk',
                'region' => 'eu-west-1',
                'detail_host' => 'www.amazon.co.uk',
            ],
            'DE' => [
                'host' => 'webservices.amazon.de',
                'endpoint' => 'https://webservices.amazon.de/paapi5/getitems',
                'marketplace' => 'www.amazon.de',
                'region' => 'eu-west-1',
                'detail_host' => 'www.amazon.de',
            ],
        ];

        return $map[$marketplace] ?? $map['US'];
    }

    /**
     * @return array<string,string>
     */
    private static function build_signed_headers(string $region, string $host, string $body, string $access_key, string $secret_key): array
    {
        $service = 'ProductAdvertisingAPI';
        $method = 'POST';
        $canonical_uri = '/paapi5/getitems';
        $content_type = 'application/json; charset=utf-8';

        $amz_date = gmdate('Ymd\\THis\\Z');
        $date_stamp = gmdate('Ymd');

        $payload_hash = hash('sha256', $body);
        $canonical_headers = 'content-encoding:amz-1.0' . "\n"
            . 'content-type:' . $content_type . "\n"
            . 'host:' . $host . "\n"
            . 'x-amz-date:' . $amz_date . "\n"
            . 'x-amz-target:com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems' . "\n";
        $signed_headers = 'content-encoding;content-type;host;x-amz-date;x-amz-target';
        $canonical_request = implode("\n", [
            $method,
            $canonical_uri,
            '',
            $canonical_headers,
            $signed_headers,
            $payload_hash,
        ]);

        $credential_scope = $date_stamp . '/' . $region . '/' . $service . '/' . 'aws4_request';
        $string_to_sign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amz_date,
            $credential_scope,
            hash('sha256', $canonical_request),
        ]);

        $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $secret_key, true);
        $k_region = hash_hmac('sha256', $region, $k_date, true);
        $k_service = hash_hmac('sha256', $service, $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
        $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

        return [
            'Content-Encoding' => 'amz-1.0',
            'Content-Type' => $content_type,
            'Host' => $host,
            'X-Amz-Date' => $amz_date,
            'X-Amz-Target' => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems',
            'Authorization' => 'AWS4-HMAC-SHA256 Credential=' . $access_key . '/' . $credential_scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $signature,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,string>
     */
    private static function sanitize_payload(array $payload): array
    {
        return [
            'title' => sanitize_text_field((string) ($payload['title'] ?? '')),
            'image_url' => esc_url_raw((string) ($payload['image_url'] ?? '')),
            'url' => esc_url_raw((string) ($payload['url'] ?? '')),
        ];
    }
}
