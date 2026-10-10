<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Siba_leads_didar_client
{
    /** @var string */
    protected $baseUrl;

    /** @var string */
    protected $apiKey;

    /** @var int */
    protected $timeout = 120;

    /**
     * @param array<string,mixed>|string|null $params CI passes a config array from load->library().
     */
    public function __construct($params = null)
    {
        $baseUrl = null;
        $apiKey  = null;

        if (is_array($params)) {
            $baseUrl = $params['base_url'] ?? $params['baseUrl'] ?? null;
            $apiKey  = $params['api_key'] ?? $params['apiKey'] ?? null;
        } elseif (is_string($params) && $params !== '') {
            $baseUrl = $params;
        }

        $this->baseUrl = rtrim($baseUrl ?: siba_leads_didar_base_url(), '/');
        $this->apiKey  = $apiKey ?: siba_leads_didar_api_key();
    }

    /**
     * @return array{ok:bool,message?:string,data?:mixed}
     */
    public function test_connection(): array
    {
        if ($this->apiKey === '') {
            return ['ok' => false, 'message' => _l('siba_leads_didar_api_key_required')];
        }

        $result = $this->post('/api/pipeline/list/0', []);
        if (!$result['ok']) {
            return $result;
        }

        return ['ok' => true, 'message' => _l('siba_leads_didar_connection_ok')];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_deal_pipelines(): array
    {
        $result = $this->post('/api/pipeline/list/0', []);
        if (!$result['ok']) {
            return [];
        }

        $list = $result['data']['Response'] ?? $result['data']['response'] ?? [];
        if (!is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['Type'] ?? '') !== 'Deal' && ($row['type'] ?? '') !== 'Deal') {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_activity_types(): array
    {
        $result = $this->post('/api/activity/GetActivityType', []);
        if (!$result['ok']) {
            return [];
        }

        $list = $result['data']['Response'] ?? [];
        return is_array($list) ? $list : [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_users(): array
    {
        $result = $this->post('/api/User/List', []);
        if (!$result['ok']) {
            return [];
        }

        $list = $result['data']['Response'] ?? [];
        return is_array($list) ? $list : [];
    }

    /**
     * Didar «شیوه آشنایی» (lead/deal sources).
     *
     * @return array<int,array<string,mixed>>
     */
    public function list_sources(): array
    {
        $result = $this->post('/api/Source/GetList', []);
        if (!$result['ok']) {
            return [];
        }

        $list = $result['data']['Response'] ?? $result['data']['response'] ?? [];
        return is_array($list) ? $list : [];
    }

    /**
     * @param array<string,mixed> $criteria
     * @return array<int,array<string,mixed>>
     */
    public function search_deals(array $criteria, int $from = 0, int $limit = 100): array
    {
        $body = [
            'Criteria' => $criteria,
            'From'     => $from,
            'Limit'    => $limit,
        ];

        $result = $this->post('/api/deal/search_v2', $body);
        if (!$result['ok']) {
            return [];
        }

        $response = $result['data']['Response'] ?? [];
        $list     = $response['List'] ?? [];
        return is_array($list) ? $list : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get_deal(string $dealId): ?array
    {
        $dealId = trim($dealId);
        if ($dealId === '') {
            return null;
        }

        $result = $this->post('/api/deal/getdealdetail', ['Id' => $dealId]);
        if (!$result['ok']) {
            return null;
        }

        $row = $result['data']['Response'] ?? null;
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function search_deal_activities(string $dealId, int $from = 0, int $limit = 200): array
    {
        $dealId = trim($dealId);
        if ($dealId === '') {
            return [];
        }

        $body = [
            'FromDate'  => '1800-01-01T00:00:00.000Z',
            'ToDate'    => '9999-12-31T23:59:59.000Z',
            'SortType'  => 1,
            'From'      => $from,
            'Limit'     => $limit,
            'DealId'    => $dealId,
            'IsDeleted' => false,
        ];

        $result = $this->post('/api/activity/search', $body);
        if (!$result['ok']) {
            return [];
        }

        $response = $result['data']['Response'] ?? [];
        $list     = $response['List'] ?? [];
        return is_array($list) ? $list : [];
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array{ok:bool,message?:string,data?:mixed}
     */
    protected function post(string $path, ?array $body): array
    {
        if ($this->apiKey === '') {
            return ['ok' => false, 'message' => _l('siba_leads_didar_api_key_required')];
        }

        $url = $this->baseUrl . $path . '?apikey=' . rawurlencode($this->apiKey);
        $json = $body === null ? '{}' : json_encode($body, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_TIMEOUT        => $this->timeout,
        ];
        $this->apply_curl_ssl_options($opts);
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'message' => 'cURL: ' . $error];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'message' => _l('siba_leads_didar_invalid_response') . ' (HTTP ' . $code . ')'];
        }

        if ($code >= 400) {
            $msg = (string) ($decoded['Message'] ?? $decoded['message'] ?? ('HTTP ' . $code));

            return ['ok' => false, 'message' => $msg, 'data' => $decoded];
        }

        return ['ok' => true, 'data' => $decoded];
    }

    /**
     * @param array<int,mixed> $opts
     */
    protected function apply_curl_ssl_options(array &$opts): void
    {
        if (!function_exists('siba_leads_didar_ssl_verify_enabled')
            || !siba_leads_didar_ssl_verify_enabled()) {
            $opts[CURLOPT_SSL_VERIFYPEER] = false;
            $opts[CURLOPT_SSL_VERIFYHOST] = 0;

            return;
        }

        $opts[CURLOPT_SSL_VERIFYPEER] = true;
        $opts[CURLOPT_SSL_VERIFYHOST] = 2;

        if (function_exists('siba_leads_didar_cacert_path')) {
            $ca = siba_leads_didar_cacert_path();
            if ($ca !== null) {
                $opts[CURLOPT_CAINFO] = $ca;
            }
        }
    }
}
