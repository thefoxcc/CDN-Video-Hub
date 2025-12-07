<?php
/**
 * Модуль CDN Video Hub
 *
 * Файл отвечает за добавление кнопки простановки данных в редактировании/добавлении новости в админ панели
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

use LazyDev\CDNVideoHub\CDNVideoHub;
use LazyDev\CDNVideoHub\Base;
use LazyDev\CDNVideoHub\Cache;

include_once realpath(__DIR__ . '/..') . '/loader.php';

if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['api'])) {
    $configField = '';

    if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['kinopoisk'])) {
        $configField .= 'configCDNField.idKinopoisk = ' . "'" . $CDNVideoHubModule[Base::$modName]['config']['kinopoisk'] . "';";
    }

    if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['imdb'])) {
        $configField .= 'configCDNField.idImdb = ' . "'" . $CDNVideoHubModule[Base::$modName]['config']['imdb'] . "';";
    }

    if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['myAnimeList'])) {
        $configField .= 'configCDNField.idMyAnimeList = ' . "'" . $CDNVideoHubModule[Base::$modName]['config']['myAnimeList'] . "';";
    }

    if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['myDramaList'])) {
        $configField .= 'configCDNField.idMyDramaList = ' . "'" . $CDNVideoHubModule[Base::$modName]['config']['myDramaList'] . "';";
    }

    if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['idApi'])) {
        $configField .= 'configCDNField.idApi = ' . "'" . $CDNVideoHubModule[Base::$modName]['config']['idApi'] . "';";
    }

    if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['serialSeasonNumber'])) {
        $configField .= 'configCDNField.season = ' . "'" . $CDNVideoHubModule[Base::$modName]['config']['serialSeasonNumber'] . "';";
    }

    $timestamp = filemtime(ROOT_DIR . '/engine/cdnvideohub/inc/js/cdnvideohub.js');

    $playerCDNVideoHubField = <<<HTML
    $('div[id*="xfield_holder_"]:first').before(`<div class="form-group"><label class="control-label col-sm-2">Поиск в базе CDNVideoHub: </label><div class="col-sm-10"><button type="button" onclick="parseVideoCDN('video', '{$dle_login_hash}'); return false;" class="btn bg-danger btn-sm btn-raised">CDN VideoHub Module</button></div></div>`);
HTML;

    if (!$row['id']) $row['id'] = 0;
    $forcedRows = Base::json($CDNVideoHubModule[Base::$modName]['config']['fieldUpdateOverrideInNews'] ?: []);
echo <<<HTML
<script>
var onlyEmptyCDN = '{$CDNVideoHubModule[Base::$modName]['config']['onlyEmptyInNews']}';
var forcedRowsCDN = $forcedRows;
var newsidCDN = {$row['id']};
var configCDNField = {}; {$configField}
</script>
<script type="text/javascript" src="/engine/cdnvideohub/inc/js/cdnvideohub.js?{$timestamp}"></script>
<script>
$(function() {
    {$playerCDNVideoHubField}
});
</script>
HTML;

}