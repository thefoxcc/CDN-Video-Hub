<?php
declare(strict_types=1);
set_time_limit(0);
ini_set('memory_limit', '256M');

define('DATALIFEENGINE', true);
define('ROOT_DIR', realpath(__DIR__ . '/../..'));
define('ENGINE_DIR', ROOT_DIR . '/engine');

$CDNVideoHubModuleConfig = include ENGINE_DIR . '/cdnvideohub/data/config.php';
$licenseType = (int)($CDNVideoHubModuleConfig['license']['type'] ?? 3);

if (!$_GET['key'] || $_GET['key'] !== $CDNVideoHubModuleConfig['license']['key_license']) {
    die();
}

require_once(ENGINE_DIR . '/classes/plugins.class.php');
require_once (DLEPlugins::Check(ENGINE_DIR . '/modules/functions.php'));
require_once ENGINE_DIR . '/cdnvideohub/loader.php';

use LazyDev\CDNVideoHub\License;
use LazyDev\CDNVideoHub\Cache;

final class Http
{
    public static function getJson($url, $bearer, $timeout = 12, $connectTimeout = 6)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $bearer
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException("cURL error #$errno: $err");
        }

        if ($http === 204) {
            return [];
        }

        if ($http !== 200) {
            throw new RuntimeException("HTTP $http for $url");
        }

        if ($raw === '' || $raw === false) {
            return [];
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            throw new RuntimeException('Malformed JSON');
        }

        return $data;
    }
}

final class State
{
    private string $file;
    private int $maxKeys;

    public function __construct($file, $maxKeys = 2000)
    {
        $this->file = $file;
        $this->maxKeys = max(100, $maxKeys);
    }

    public function load()
    {
        if (!is_file($this->file)) {
            return [];
        }

        $raw = @file_get_contents($this->file);
        if ($raw === false || $raw === '') {
            return [];
        }

        $arr = json_decode($raw, true);

        if (!is_array($arr)) {
            return [];
        }

        $set = [];
        foreach ($arr as $k) {
            if (is_string($k) && $k !== '') {
                $set[$k] = true;
            }
        }

        return $set;
    }

    public function saveMerged($currentSet, $newKeysFront)
    {
        $existing = array_keys($currentSet);

        $merged = [];
        foreach ($newKeysFront as $k) {
            if (!isset($merged[$k])) {
                $merged[$k] = true;
            }
        }

        foreach ($existing as $k) {
            if (!isset($merged[$k])) {
                $merged[$k] = true;
            }
        }

        $list = array_slice(array_keys($merged), 0, $this->maxKeys);

        $dir = dirname($this->file);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $tmp = $this->file . '.' . bin2hex(random_bytes(3)) . '.tmp';

        if (file_put_contents($tmp, json_encode($list, JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new RuntimeException('Cannot write temp state file');
        }

        if (!@rename($tmp, $this->file)) {
            @unlink($tmp);
            throw new RuntimeException('Cannot move state file');
        }

        @chmod($this->file, 0664);
    }
}

final class Checker
{
    private string $baseUrl;
    private string $bearer;
    private State $state;
    private int $maxPages;

    public function __construct($baseUrl, $bearer, $state, $maxPages = 200)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->bearer = $bearer;
        $this->state = $state;
        $this->maxPages = max(1, $maxPages);
    }

    public function run()
    {
        $known = $this->state->load();
        $items = [];
        $newKeys = [];
        $stopped = false;

        for ($page = 1; $page <= $this->maxPages; $page++) {
            $url = $this->baseUrl . '/titles?tags.id=1&page=' . $page;
            $data = Http::getJson($url, $this->bearer);
            if (empty($data)) {
                break;
            }

            foreach ($data as $row) {
                $keys = $this->extractExternalKeys($row);
                if (!$keys) {
                    continue;
                }

                $anyKnown = false;
                foreach ($keys as $k) {
                    if (isset($known[$k])) {
                        $anyKnown = true;
                        break;
                    }
                }

                if ($anyKnown) {
                    $stopped = true;
                    break 2;
                }

                $items[] = $row;

                foreach ($keys as $k) {
                    $newKeys[] = $k;
                    $known[$k] = true;
                }
            }
        }

        if ($newKeys) {
            $this->state->saveMerged($known, $newKeys);
        }

        return [
            'items' => $items,
            'new_keys' => $newKeys,
            'stopped_on_known' => $stopped,
            'pages_scanned' => min($this->maxPages, max(1, (int)ceil(count($items)/30))),
        ];
    }

    private function extractExternalKeys($row)
    {
        $out = [];
        if (!empty($row['externalIds']) && is_array($row['externalIds'])) {
            foreach ($row['externalIds'] as $eid) {
                $aggr = isset($eid['externalAggregator']) ? (string)$eid['externalAggregator'] : '';
                $val  = isset($eid['externalId']) ? (string)$eid['externalId'] : '';
                if ($aggr !== '' && $val !== '') {
                    $out[] = strtolower($aggr) . ':' . $val;
                }
            }

            return array_values(array_unique($out));
        }

        return $out;
    }
}

$apiBase = 'https://api.cdnvideohub.com';
$bearer = $CDNVideoHubModuleConfig['api'];

$stateFile = ENGINE_DIR . '/cdnvideohub/data/api_titles_seen_keys.json';
$state = new State($stateFile, 50);
$checker = new Checker($apiBase, $bearer, $state, 150);

$mapKeyAggregator = [
    'kp' => $CDNVideoHubModuleConfig['kinopoisk'],
    'imdb' => $CDNVideoHubModuleConfig['imdb'],
    'mdl' => $CDNVideoHubModuleConfig['myDramaList'],
    'mal' => $CDNVideoHubModuleConfig['myAnimeList'],
    'mali' => $CDNVideoHubModuleConfig['myAnimeList']
];

$hiddenNews = [];
License::ensureHidePlayerColumn($db);

try {
    $res = $checker->run();

    if (is_array($res['items']) && count($res['items'])) {
        foreach ($res['items'] as $k => $v) {
            $ext = [];
            if (empty($v['externalIds']) || !is_array($v['externalIds'])) {
                continue;
            }

            foreach ($v['externalIds'] as $eid) {
                if (!isset($eid['externalAggregator'], $eid['externalId'])) {
                    continue;
                }

                $aggKey = strtolower((string)$eid['externalAggregator']);
                $agg = $mapKeyAggregator[$aggKey] ?? null;
                if (!$agg) {
                    continue;
                }

                $ext[$agg] = trim((string)$eid['externalId']);
            }

            if (!$ext) {
                continue;
            }

            foreach ($ext as $xf => $val) {
                $sql = "SELECT id FROM " . PREFIX . "_post WHERE xfields REGEXP '(^|\\\|\\\|){$xf}\\\|{$val}(\\\|\\\||$)'";
                $rSql = $db->query($sql);
                if ($rSql->num_rows > 0) {
                    License::matchAndStore($db, [$v]);
                    while ($row = $db->get_row($rSql)) {
                        $matched++;
                        $hiddenNews[] = (int)$row['id'];
                        switch ($licenseType) {
                            case 2:
                                deletenewsbyid($row['id']);
                                break;
                            case 1:
                                $db->query("UPDATE " . PREFIX . "_post SET approve=0 WHERE id='{$row['id']}'");
                                break;
                            default:
                                break;
                        }
                    }

                    break;
                }
            }
        }
    }

    if ($hiddenNews) {
        $ids = array_unique(array_filter(array_map('intval', $hiddenNews)));
        if ($ids) {
            $idsList = implode(',', $ids);
            $db->query("UPDATE " . PREFIX . "_cdnvideohub_license SET hide_player=1 WHERE news_id IN ({$idsList})");
            foreach ($ids as $newsId) {
                Cache::setFile('1', $newsId, '/player_hide');
            }
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
