<?php
/**
 * Обновление модуля в автоматическом режиме
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

namespace LazyDev\CDNVideoHub;

use ZipArchive;

if (!defined('DATALIFEENGINE')) {
    header("HTTP/1.1 403 Forbidden");
    die("Hacking attempt!");
}

class CDNVideoHubUpdater
{
    const MODULE_DIR = ENGINE_DIR . '/cdnvideohub';
    const DATA_DIR   = ENGINE_DIR . '/cdnvideohub/data';
    const TMP_DIR    = ENGINE_DIR . '/cdnvideohub/_tmp_update';

    const STATE_FILE = self::DATA_DIR . '/update_state.json';

    const ZIP_PREFIX_ENGINE = 'Module/engine/cdnvideohub/';

    const CURL_TIMEOUT = 30;
    const CURL_CONNECT_TIMEOUT = 10;

    protected static function defaultState() {
        return [
            'meta_url' => 'https://cdnvideohub.com/downloads/distr/dle/update.json',
            'check_interval_minutes' => 10080, // 7 дней
            'last_check_at' => 0,
            'remote_version' => '',
            'remote_updated_at' => '',
            'archive_url' => '',
            'last_result' => '',
        ];
    }

    protected static function loadState() {
        if (!is_dir(self::DATA_DIR)) @mkdir(self::DATA_DIR, 0775, true);
        if (!file_exists(self::STATE_FILE)) {
            return self::defaultState();
        }

        $json = @file_get_contents(self::STATE_FILE);
        if ($json === false) {
            return self::defaultState();
        }

        $data = json_decode($json, true);
        return is_array($data) ? array_merge(self::defaultState(), $data) : self::defaultState();
    }

    protected static function saveState($state) {
        if (!is_dir(self::DATA_DIR)) {
            @mkdir(self::DATA_DIR, 0775, true);
        }

        $tmp = self::STATE_FILE . '.tmp';
        $fp = @fopen($tmp, 'wb');
        if (!$fp) {
            return false;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            @unlink($tmp);
            return false;
        }

        $ok = fwrite($fp, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($ok) {
            return @rename($tmp, self::STATE_FILE);
        }

        @unlink($tmp);
        return false;
    }

    public static function dueForCheck() {
        $st = self::loadState();
        $now = time();
        return ($st['last_check_at'] + (int)$st['check_interval_minutes'] * 60) <= $now;
    }

    public static function checkForUpdate(&$error = null) {
        $st = self::loadState();
        $metaUrl = trim((string)$st['meta_url']);
        if (!$metaUrl) { $error = 'Пустой meta_url'; return null; }

        $resp = self::httpGetJson($metaUrl, $e);
        $st['last_check_at'] = time();

        if (!$resp || $e) {
            $st['last_result'] = 'ERR: ' . ($e ?: 'fetch failed');
            self::saveState($st);
            $error = $e ?: 'Не удалось получить JSON';
            return null;
        }

        $st['remote_version']   = isset($resp['version']) ? (string)$resp['version'] : '';
        $st['remote_updated_at']= isset($resp['updated_at']) ? (string)$resp['updated_at'] : '';
        $st['archive_url']      = isset($resp['url']) ? (string)$resp['url'] : '';
        $st['last_result']      = 'OK';
        self::saveState($st);

        return [
            'version'    => $st['remote_version'],
            'updated_at' => $st['remote_updated_at'],
            'url'        => $st['archive_url'],
            'notes'      => isset($resp['notes']) ? (string)$resp['notes'] : ''
        ];
    }

    public static function hasUpdate() {
        @include_once self::MODULE_DIR . '/version.php';
        $local = defined('CDNVIDEOHUB_VERSION') ? CDNVIDEOHUB_VERSION : '0.0.0';
        $st = self::loadState();
        $remote = (string)$st['remote_version'];
        if (!$remote) {
            return false;
        }

        return version_compare($remote, $local, '>');
    }

    public static function runUpdate(&$log = []) {
        $st = self::loadState();
        if (empty($st['archive_url'])) {
            $log[] = 'Не задан archive_url';
            return false;
        }

        if (!is_dir(self::TMP_DIR) && !@mkdir(self::TMP_DIR, 0775, true)) {
            $log[] = 'Не удалось создать временную папку: ' . self::TMP_DIR;
            return false;
        }

        $zipPath = self::TMP_DIR . '/cdnvideohub_update.zip';

        $log[] = 'Скачивание архива...';
        if (!self::httpDownloadToFile($st['archive_url'], $zipPath, $err)) {
            $log[] = 'Ошибка скачивания: ' . $err;
            return false;
        }

        $log[] = 'Распаковка (только /engine/cdnvideohub, кроме /data)...';
        $ok = self::extractSelective($zipPath, $log);

        if (file_exists($zipPath)) {
            @unlink($zipPath);
            $log[] = 'Временный архив удалён';
        }

        return $ok;
    }

    protected static function extractSelective($zipPath, &$log) {
        if (!class_exists('ZipArchive')) {
            $log[] = 'ZipArchive недоступен';
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $log[] = 'Не удалось открыть zip';
            return false;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (strpos($entry, self::ZIP_PREFIX_ENGINE) !== 0) continue;

            $rel = substr($entry, strlen(self::ZIP_PREFIX_ENGINE));
            if ($rel === false) {
                continue;
            }

            if ($rel === 'data' || strpos($rel, 'data/') === 0 || strpos($rel, 'data\\') === 0) {
                continue;
            }

            $dest = self::MODULE_DIR . '/' . $rel;

            if (substr($entry, -1) === '/') {
                if (!is_dir($dest)) @mkdir($dest, 0775, true);
                continue;
            }

            $dir = dirname($dest);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                $log[] = 'Не удалось создать каталог: ' . $dir;
                $zip->close();
                return false;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false) {
                $log[] = 'Не удалось извлечь: ' . $entry;
                $zip->close();
                return false;
            }

            if (file_put_contents($dest, $content) === false) {
                $log[] = 'Не удалось записать: ' . $dest;
                $zip->close();
                return false;
            }
        }

        $zip->close();
        $log[] = 'Файлы обновлены (папка data/ сохранена без изменений).';
        return true;
    }

    protected static function httpGetJson($url, &$error = null) {
        $body = self::httpFetch($url, $error);
        if ($body === false || $error) {
            return null;
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            $error = 'Некорректный JSON';
            return null;
        }

        return $data;
    }

    protected static function httpDownloadToFile($url, $path, &$error = null) {
        $ch = curl_init($url);
        if (!$ch) {
            $error = 'curl_init failed';
            return false;
        }

        $fp = fopen($path, 'wb');
        if (!$fp) {
            $error = 'Не удалось открыть файл для записи';
            curl_close($ch);
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => self::CURL_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::CURL_TIMEOUT,
            CURLOPT_USERAGENT => 'CDNVideoHub-Updater/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $ok = curl_exec($ch);
        if ($ok === false) {
            $error = curl_error($ch);
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        fclose($fp);

        if ($ok === false || $code >= 400) {
            @unlink($path);
            if (!$error) {
                $error = "HTTP $code";
            }
            return false;
        }

        return true;
    }

    protected static function httpFetch($url, &$error = null) {
        $ch = curl_init($url);
        if (!$ch) {
            $error = 'curl_init failed';
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => self::CURL_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::CURL_TIMEOUT,
            CURLOPT_USERAGENT => 'CDNVideoHub-Updater/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code >= 400) {
            if (!$error) $error = "HTTP $code";
            return false;
        }

        return $body;
    }

    public static function getState() {
        return self::loadState();
    }

    public static function setIntervalMinutes($minutes) {
        $st = self::loadState();
        $st['check_interval_minutes'] = max(10, $minutes);
        return self::saveState($st);
    }

    public static function setMetaUrl($url) {
        $st = self::loadState();
        $st['meta_url'] = trim($url);
        return self::saveState($st);
    }
}
