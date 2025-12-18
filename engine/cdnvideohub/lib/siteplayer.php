<?php
/**
 * Модуль CDN Video Hub
 *
 * Файл отвечает за вывод плеера на сайте
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

use LazyDev\CDNVideoHub\CDNVideoHub;
use LazyDev\CDNVideoHub\Base;
use LazyDev\CDNVideoHub\Cache;
use LazyDev\CDNVideoHub\License;

include_once ENGINE_DIR . '/cdnvideohub/loader.php';
include ENGINE_DIR . '/cdnvideohub/lib/vars.php';

$playerCDNVideoHub = [];
$playerCDNInclude = '';
$playerCDNHidden = false;

global $db;

static $hideColumnChecked = false;
if (!$hideColumnChecked) {
    License::ensureHidePlayerColumn($db);
    $hideColumnChecked = true;
}

if (!empty($row['id'])) {
    $playerCDNHidden = Cache::getFile($row['id'], '/player_hide');
    if ($playerCDNHidden === false) {
        $licenseHide = $db->super_query("SELECT hide_player FROM " . PREFIX . "_cdnvideohub_license WHERE news_id=" . (int)$row['id'] . " LIMIT 1");
        $playerCDNHidden = isset($licenseHide['hide_player']) ? (int)$licenseHide['hide_player'] : 0;
        Cache::setFile((string)$playerCDNHidden, $row['id'], '/player_hide');
    } else {
        $playerCDNHidden = (int)$playerCDNHidden;
    }
}

if ($playerCDNHidden) {
    $playerCDNInclude = '';
}

if (!$playerCDNHidden && $row['id'] && Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['api']) && Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['partnerId'])) { // Проверка на корректные данные id новости и наличие api токена
    if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['kinopoisk']) || Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['imdb']) || Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['myAnimeList']) || Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['myDramaList'])) { // Проверка на заполнение хотя бы одного поля с базой id
        $postCategory = [];
        if (Base::checkIsset($row['category'])) {
            $postCategory = Base::arrayWork($row['category']); // Убираем пустые данные в категории, такое бывает
        }

        if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['disableCategories']) && array_intersect($postCategory, $CDNVideoHubModule[Base::$modName]['config']['disableCategories'])) {
            return;// Завершаем работу модуля если новость находиться в той категории которую мы отключили в настройках модуля
        }

        $playerCDNVideoHub = Cache::getFile($row['id'], '/player');
        $xfieldsCDNV = xfieldsdataload($row['xfields']);
        if ($playerCDNVideoHub == false) {
            $search = [];

            if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['kinopoisk']) && Base::checkIsset($xfieldsCDNV[$CDNVideoHubModule[Base::$modName]['config']['kinopoisk']])) {
                $search[1] = 'titles?externalIds.aggregator.id=1&externalIds.externalId=' . $xfieldsCDNV[$CDNVideoHubModule[Base::$modName]['config']['kinopoisk']];
                $idCDNVideoHub[1] = $xfieldsCDNV[$CDNVideoHubModule[Base::$modName]['config']['kinopoisk']];
            }

            if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['imdb']) && Base::checkIsset($xfieldsCDNV[$CDNVideoHubModule[Base::$modName]['config']['imdb']])) {
                $imdbIdCDN = str_replace('tt', '', $xfieldsCDNV[$CDNVideoHubModule[Base::$modName]['config']['imdb']]);
                $search[2] = 'titles?externalIds.aggregator.id=2&externalIds.externalId=' . $imdbIdCDN;
                $idCDNVideoHub[2] = $imdbIdCDN;
            }

            if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['myAnimeList']) && Base::checkIsset($xfieldsCDNV[$CDNVideoHubModule[Base::$modName]['config']['myAnimeList']])) {
                $search[3] = 'titles?externalIds.aggregator.id=3&externalIds.externalId=' . $xfieldsCDNV[$CDNVideoHubModule[Base::$modName]['config']['myAnimeList']];
                $idCDNVideoHub[3] = $xfieldsCDNV[$CDNVideoHubModule[Base::$modName]['config']['myAnimeList']];
            }

            if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['myDramaList']) && Base::checkIsset($xfieldsCDNV[$CDNVideoHubModule[Base::$modName]['config']['myDramaList']])) {
                $mdlIdCDN = $xfieldsCDNV[$CDNVideoHubModule[Base::$modName]['config']['myDramaList']];
                if (!is_numeric($mdlIdCDN) && Base::position($mdlIdCDN, '-')) {
                    $mdlIdCDN = explode('-', $mdlIdCDN)[0];
                    $mdlIdCDN = trim($mdlIdCDN);
                }

                if ($mdlIdCDN) {
                    $search[4] = 'titles?externalIds.aggregator.id=4&externalIds.externalId=' . $mdlIdCDN;
                    $idCDNVideoHub[4] = $mdlIdCDN;
                }
            }

            if ($search) {
				foreach ($search as $key => $searchItem) {
					$dataCDNVideoHub = CDNVideoHub::request($searchItem);
					$dataCDNVideoHub = json_decode($dataCDNVideoHub, true);

					if (Base::checkIsset($dataCDNVideoHub) && is_array($dataCDNVideoHub) && count($dataCDNVideoHub) && !Base::checkIsset($dataCDNVideoHub['code'])) {
						$playerCDNVideoHub = ['id' => $idCDNVideoHub[$key], 'site' => $playerVarSite[$key]];
						Cache::setFile(Base::json($playerCDNVideoHub), $row['id'], '/player'); // Записываем когда делали проверку
						break;
					}
				}
			}
        } else {
            $playerCDNVideoHub = json_decode($playerCDNVideoHub, true);
        }

        if ($playerCDNVideoHub && count($playerCDNVideoHub)) {
            include_once ENGINE_DIR . '/cdnvideohub/lib/playerconfig.php';
            $playerCDNInclude = str_replace(
                ['{partner}', '{id}', '{aggregator}', '{banner}', '{voice}'],
                [$CDNVideoHubModule[Base::$modName]['config']['partnerId'], $playerCDNVideoHub['id'], $playerCDNVideoHub['site'], $CDNVideoHubModule[Base::$modName]['config']['hideBanner'] == 1 ? 'false' : 'true', $CDNVideoHubModule[Base::$modName]['config']['onlyVoice'] == 1 ? 'true' : 'false'],
                $playerCDNInclude
            );
        }
    }
}

if (isset($playerCDNInclude) && $playerCDNInclude) {
    $tpl->set('{cdnvideohub-player}', $playerCDNInclude);
    $tpl->set('[cdnvideohub-player]', '');
    $tpl->set('[/cdnvideohub-player]', '');
    $tpl->set_block( "'\\[not-cdnvideohub-player\\](.*?)\\[/not-cdnvideohub-player\\]'si", '');
} else {
    $tpl->set('{cdnvideohub-player}', '');
    $tpl->set('[not-cdnvideohub-player]', '');
    $tpl->set('[/not-cdnvideohub-player]', '');
    $tpl->set_block( "'\\[cdnvideohub-player\\](.*?)\\[/cdnvideohub-player\\]'si", '');
}