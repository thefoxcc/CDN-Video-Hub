<?php
/**
 * Конфиг и языковый файл
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

namespace LazyDev\CDNVideoHub;

if (!defined('DATALIFEENGINE')) {
    header("HTTP/1.1 403 Forbidden");
    header('Location: ../../');
    die("Hacking attempt!");
}

class License
{
    protected static $tmpDir = ENGINE_DIR . '/cdnvideohub/log/cdnvideohub_sync';

    protected static $extMap = [
        'kp'   => 'kinopoisk',
        'imdb' => 'imdb',
        'mdl'  => 'mdl',
        'mal'  => 'mal',
        'mali' => 'mal'
    ];

    public static function ensureTmp()
    {
        if (!is_dir(self::$tmpDir)) {
            @mkdir(self::$tmpDir, 0755, true);
        }
    }

    public static function jobFile($job)
    {
        self::ensureTmp();
        return self::$tmpDir . '/job_' . preg_replace('~[^a-z0-9_\-]~i', '_', $job) . '.json';
    }

    public static function loadJob($job)
    {
        $f = self::jobFile($job);
        if (is_file($f)) {
            $raw = file_get_contents($f);
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return [
            'page' => 1,
            'processed' => 0,
            'inserted' => 0,
            'matched' => 0,
            'logs' => [],
            'finished' => false,
        ];
    }

    public static function saveJob($job, $data)
    {
        file_put_contents(self::jobFile($job), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function log(&$state, $line)
    {
        $state['logs'][] = '[' . date('H:i:s') . "] " . $line;
        if (count($state['logs']) > 500) {
            $state['logs'] = array_slice($state['logs'], -500);
        }
    }

    public static function fetchPage($baseUrl, $page)
    {
        $resp = CDNVideoHub::request('titles?tags.id=1&page=' . max(1, $page));

        $data = json_decode($resp, true);
        return is_array($data) ? $data : [];
    }

    public static function matchAndStore($db, $apiItems)
    {
        global $CDNVideoHubModule;

        $matched = 0;
        $inserted = 0;
        $time = time();
        $map = [
            'kinopoisk' => $CDNVideoHubModule[Base::$modName]['config']['kinopoisk'],
            'imdb' => $CDNVideoHubModule[Base::$modName]['config']['imdb'],
            'mdl' => $CDNVideoHubModule[Base::$modName]['config']['myDramaList'],
            'mal' => $CDNVideoHubModule[Base::$modName]['config']['myAnimeList'],
            'mali' => $CDNVideoHubModule[Base::$modName]['config']['myAnimeList']
        ];

        $table = PREFIX . "_cdnvideohub_license";

        foreach ($apiItems as $item) {
            if (empty($item['externalIds']) || !is_array($item['externalIds'])) {
                continue;
            }

            $ext = [];
            foreach ($item['externalIds'] as $eid) {
                if (!isset($eid['externalAggregator'], $eid['externalId'])) {
                    continue;
                }

                $aggKey = strtolower((string)$eid['externalAggregator']);
                $agg = self::$extMap[$aggKey] ?? null;
                if (!$agg) {
                    continue;
                }

                $ext[$agg] = trim((string)$eid['externalId']);
            }

            if (!$ext) {
                continue;
            }

            $title = '';
            if (!empty($item['name']['values'][0]['value'])) {
                $title = (string)$item['name']['values'][0]['value'];
            }

            foreach ($ext as $agg => $val) {
                if (isset($map[$agg]) && $map[$agg]) {
                    $sql = "SELECT id FROM " . PREFIX . "_post WHERE xfields REGEXP '(^|\\\|\\\|){$map[$agg]}\\\|{$val}(\\\|\\\||$)'";

                    $rSql = $db->query($sql);
                    if ($rSql->num_rows > 0) {
                        while ($row = $db->get_row($rSql)) {
                            $matched++;

                            $sqlCheck = "SELECT id FROM {$table} WHERE news_id=" . (int)$row['id'] . " AND aggregator='" . $db->safesql($agg) . "' LIMIT 1";
                            $have = $db->super_query($sqlCheck);
                            if ($have && !empty($have['id'])) {
                                $id = (int)$have['id'];
                                $db->query("UPDATE {$table} SET aggregator_external_id='".$db->safesql($val)."', title='".$db->safesql($title)."', hide_player=1, updated_at={$time} WHERE id={$id}");
                            } else {
                                $db->query(
                                    "INSERT INTO {$table} (news_id, aggregator, aggregator_external_id, hide_player, title, created_at, updated_at) VALUES (".
                                    (int)$row['id'] . ", '".$db->safesql($agg)."', '".$db->safesql($val)."', 1, '".$db->safesql($title)."', {$time}, {$time})"
                                );
                                $inserted++;
                            }
                        }
                        break;
                    }
                }
            }

        }

        return compact('matched','inserted');
    }
}