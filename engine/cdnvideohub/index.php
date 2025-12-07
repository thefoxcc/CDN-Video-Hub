<?php
/**
 * Модуль CDN Video Hub
 *
 * Файл отвечает за обновление данных на сайте
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

use LazyDev\CDNVideoHub\CDNVideoHub;
use LazyDev\CDNVideoHub\Base;
use LazyDev\CDNVideoHub\Cache;
use LazyDev\CDNVideoHub\FileLogger;

include_once __DIR__ . '/loader.php';

if (!Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['updateON'])) {
	return;
}

if (!Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['work'])) {
	$CDNVideoHubModule[Base::$modName]['config']['work'] = 2;
}

if ($CDNVideoHubModule[Base::$modName]['config']['work'] == 2 && !isset($ajaxWorkCDNVideoHub)) {
$tpl->result['content'] .= <<<HTML
<script>
let newsIdVideoHub = {$row['id']};
document.addEventListener('DOMContentLoaded', () => {
    const controller  = new AbortController();
    const timeoutId   = setTimeout(() => controller.abort(), 10_000);

    fetch('/engine/cdnvideohub/ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:   new URLSearchParams({ id: newsIdVideoHub }),
        signal: controller.signal
    })
    .then(async response => {
        clearTimeout(timeoutId);

        if (!response.ok) {
            if (response.status === 500) {
                throw new Error('Internal Server Error (500).');
            }
            throw new Error(`HTTP error: \${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log(data);
    })
    .catch(err => {
        if (err.name === 'AbortError') {
            console.log('Time out error.');
        } else {
            console.log('Uncaught Error. ' + err.message);
        }
    });
});
</script>
HTML;
	return;
}

if ($CDNVideoHubModule[Base::$modName]['config']['work'] == 2 && isset($ajaxWorkCDNVideoHub) && $ajaxWorkCDNVideoHub) {
	$row = $db->super_query("SELECT id, category, xfields FROM " . PREFIX . "_post WHERE `id`={$id}");
}
$logger = new FileLogger(ENGINE_DIR . '/cdnvideohub/log/update.txt', [
    'daily' => true,
    'max_bytes' => 5 * 1024 * 1024,
    'max_files' => 5
]);

$endCheck = Cache::getFile($row['id'], '/end'); // Проверяем конец проверки
if ($endCheck == 'Конец проверки. ID новости: ' . $row['id']) {
    $logger->log('END', 'Новость больше не потребует проверки на обновление. ID новости: ' . $row['id'], []);
	return;
}

$remove = ['\t', '\n', '\r'];

if ($row['id'] && Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['api'])) { // Проверка на корректные данные id новости и наличие api токена
	if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['kinopoisk']) || Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['imdb']) || Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['myAnimeList']) || Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['myDramaList'])) { // Проверка на заполнение хотя бы одного поля с базой id
        $postCategory = [];
        if (Base::checkIsset($row['category'])) {
            $postCategory = Base::arrayWork($row['category']); // Убираем пустые данные в категории, такое бывает
        }

        if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['disableCategories']) && array_intersect($postCategory, $CDNVideoHubModule[Base::$modName]['config']['disableCategories'])) {
            $logger->info('Пропуск категории. ID новости: ' . $row['id'], []);
            return;// Завершаем работу модуля если новость находиться в той категории которую мы отключили в настройках модуля
        }

        $search = [];
        $xfieldsCDNVideoHub = xfieldsdataload($row['xfields']);

        if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['kinopoisk']) && Base::checkIsset($xfieldsCDNVideoHub[$CDNVideoHubModule[Base::$modName]['config']['kinopoisk']])) {
            $search[] = 'titles?externalIds.aggregator.id=1&externalIds.externalId=' . $xfieldsCDNVideoHub[$CDNVideoHubModule[Base::$modName]['config']['kinopoisk']];
        }

        if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['imdb']) && Base::checkIsset($xfieldsCDNVideoHub[$CDNVideoHubModule[Base::$modName]['config']['imdb']])) {
            $imdbIdCDN = str_replace('tt', '', $xfieldsCDNVideoHub[$CDNVideoHubModule[Base::$modName]['config']['imdb']]);
            $search[] = 'titles?externalIds.aggregator.id=2&externalIds.externalId=' . $imdbIdCDN;
        }

        if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['myAnimeList']) && Base::checkIsset($xfieldsCDNVideoHub[$CDNVideoHubModule[Base::$modName]['config']['myAnimeList']])) {
            $search[] = 'titles?externalIds.aggregator.id=3&externalIds.externalId=' . $xfieldsCDNVideoHub[$CDNVideoHubModule[Base::$modName]['config']['myAnimeList']];
        }

        if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['myDramaList']) && Base::checkIsset($xfieldsCDNVideoHub[$CDNVideoHubModule[Base::$modName]['config']['myDramaList']])) {
            $mdlIdCDN = $xfieldsCDNVideoHub[$CDNVideoHubModule[Base::$modName]['config']['myDramaList']];
            if (!is_numeric($mdlIdCDN) && Base::position($mdlIdCDN, '-')) {
                $mdlIdCDN = explode('-', $mdlIdCDN)[0];
                $mdlIdCDN = trim($mdlIdCDN);
            }
            $search[] = 'titles?externalIds.aggregator.id=4&externalIds.externalId=' . $mdlIdCDN;
        }

        if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['idApi']) && Base::checkIsset($xfieldsCDNVideoHub[$CDNVideoHubModule[Base::$modName]['config']['idApi']])) {
            $search[] = 'titles?id=' . $xfieldsCDNVideoHub[$CDNVideoHubModule[Base::$modName]['config']['idApi']];
        }

        if (!$search) {
            $logger->info('Нет данных для запроса в API. ID новости: ' . $row['id'], []);
            return;
        }

        $lastCheck = Cache::getFile($row['id'], '/last'); // Проверяем когда была последняя проверка
        if ($lastCheck) {
            $whenCheck = (new DateTime())->setTimestamp($lastCheck);
            $dateDiff = new DateInterval('PT' . $CDNVideoHubModule[Base::$modName]['config']['timeCheck'] . 'H');
            $whenCheck->add($dateDiff);
            $timeNow = new DateTime();
            if ($timeNow < $whenCheck) {
                return; // Время ещё не пришло
            }
        }

        Cache::setFile(time(), $row['id'], '/last'); // Записываем когда делали проверку
        $foundCDNVideoHub = false;
        foreach ($search as $searchItem) {
            try {
                $dataCDNVideoHub = CDNVideoHub::request($searchItem);
            } catch (Throwable $e) {
                $logger && $logger->error('Ошибка запроса к API CDNVideoHub при получении списка.', ['search' => $searchItem, 'exception' => $e]);
            }

            $dataCDNVideoHub = json_decode($dataCDNVideoHub, true);

            if (Base::checkIsset($dataCDNVideoHub) && is_array($dataCDNVideoHub) && count($dataCDNVideoHub)) {
                $foundCDNVideoHub = true;
                break;
            }
        }

        $catType = '';
        if ($foundCDNVideoHub) {
            if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['onModeration'])) {
                if (Base::checkIsset($dataCDNVideoHub[0]['tags']) && is_array($dataCDNVideoHub[0]['tags']) && in_array('licensed', $dataCDNVideoHub[0]['tags'])) {
                    $db->query("UPDATE " . PREFIX . "_post SET approve=0 WHERE id='{$row['id']}'");
                    $logger->log('LICENSED', 'Новость снята с публикации из-за лицензирования.', ['newsId' => $row['id'], 'apiId' => $dataCDNVideoHub[0]['id'] ?? null]);
                    Cache::setFile('Новость снята с публикации из-за лицензирования. ID новости: ' . $row['id'], $row['id'], '/end'); // Записываем конец
                    return;
                }
            }

            if (Base::checkIsset($dataCDNVideoHub[0]['seasons'])) {
                $catType = 'serial';
                $checkUpdateSerials = CDNVideoHub::checkSerials($dataCDNVideoHub, $CDNVideoHubModule, $row, $xfieldsCDNVideoHub, '');
                if ($checkUpdateSerials !== false) {
                    try {
                        $dataCDNVideoHubSeason = CDNVideoHub::request('seasons?id=' . $checkUpdateSerials['id']);
                    } catch (Throwable $e) {
                        $logger && $logger->error('Ошибка при запросе сезона.', [
                            'seasonId'  => $checkUpdateSerials['id'] ?? null,
                            'apiId'     => $dataCDNVideoHub[0]['id'] ?? null,
                            'exception' => $e
                        ]);
                    }
                    $dataCDNVideoHubSeason = json_decode($dataCDNVideoHubSeason, true);
                    if (Base::checkIsset($dataCDNVideoHubSeason[0]['episodes'])) {
                        $maxEpisodeArray = Base::searchMax($dataCDNVideoHubSeason[0]['episodes'], 'number');
                        if (Base::checkIsset($maxEpisodeArray['id'])) {
                            try {
                                $dataCDNVideoHubEpisode = CDNVideoHub::request('episodes?id=' . $maxEpisodeArray['id']);
                            } catch (Throwable $e) {
                                $logger && $logger->error('Ошибка при запросе эпизода.', [
                                    'episodeId' => $maxEpisodeArray['id'] ?? null,
                                    'apiId'     => $dataCDNVideoHub[0]['id'] ?? null,
                                    'exception' => $e
                                ]);
                            }
                            $dataCDNVideoHubEpisode = json_decode($dataCDNVideoHubEpisode, true);
                            if (Base::checkIsset($dataCDNVideoHubEpisode[0]['content']) && Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['disableVoice'])) {
                                $stopUpdate = false;
                                foreach ($dataCDNVideoHubEpisode[0]['content'] as $episodeVoice) {
                                    if (in_array($episodeVoice['voiceStudioId'], $CDNVideoHubModule[Base::$modName]['config']['disableVoice'])) {
                                        $stopUpdate = true;
                                    } else {
                                        $stopUpdate = false;
                                        break;
                                    }
                                }

                                if ($stopUpdate) {
                                    return;
                                }
                            }
                        }
                    }
                }
            } elseif (Base::checkIsset($dataCDNVideoHub[0]['content'])) {
                $catType = 'film';
                $maxQualityArray = ['videoTypeId' => 0];
                if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['maxQuality']) && Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['quality'])) {
                    if (Base::checkIsset($dataCDNVideoHub[0]['content'][0]['videoTypeId'])) {
                        $maxQualityArray = Base::searchMax($dataCDNVideoHub[0]['content'], 'videoTypeId'); // Берём лучшее качество
                    }
                    if ($maxQualityArray['videoTypeId'] >= $CDNVideoHubModule[Base::$modName]['config']['maxQuality']) { // Если качество равно или лучше максимально допустимого для проверок
                        Cache::setFile('Конец проверки. ID новости: ' . $row['id'], $row['id'], '/end'); // Записываем конец
                    }

                    if ($maxQualityArray['videoTypeId'] <= $flipQualityKeyCDNVideoHub[$xfieldsCDNVideoHub[$CDNVideoHubModule[Base::$modName]['config']['quality']]]) { // Сравниваем текущее качество на сайте с качество в апи
                        return; // Нового качества нет.
                    }
                }
            } else {
                $logger->log('SKIP', 'Фильм не был обновлен, ошибка данных.', ['apiId' => $dataCDNVideoHub[0]['id'] ?? null]);
                return;
            }

            include_once (DLEPlugins::Check(ENGINE_DIR . '/classes/htmlpurifier/HTMLPurifier.standalone.php'));
            include_once (DLEPlugins::Check(ENGINE_DIR . '/classes/parse.class.php'));
            $parse = new ParseFilter();
            if ($config['allow_admin_wysiwyg']) {
                $parse->allow_code = false;
            }

            $maxSeasonArray = ['number' => '', 'episodesCount' => ''];
            if ($catType == 'serial') {
                if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['seasonBySeason'])) {
                    if (!$xfieldsCDNVideoHub[$CDNVideoHubModule[Base::$modName]['config']['serialSeasonNumber']]) {
                        $maxSeasonArray = Base::searchMax($dataCDNVideoHub[0]['seasons'], 'number');
                    } else {
                        foreach ($dataCDNVideoHub[0]['seasons'] as $season) {
                            if ($season['number'] == $xfieldsCDNVideoHub[$CDNVideoHubModule[Base::$modName]['config']['serialSeasonNumber']]) {
                                $maxSeasonArray = $season;
                                break;
                            }
                        }
                    }
                } else {
                    $maxSeasonArray = Base::searchMax($dataCDNVideoHub[0]['seasons'], 'number');
                }
            }

            $idCats = []; // Работа с категориями
            $categoryArray = [];
            if (Base::checkIsset($dataCDNVideoHub[0]['meta']) && Base::checkIsset($dataCDNVideoHub[0]['meta']['genres'])) { // Берём категории жанров
                $categoryArray = array_merge($dataCDNVideoHub[0]['meta']['genres'], $categoryArray);
            }

            if (Base::checkIsset($dataCDNVideoHub[0]['meta']) && Base::checkIsset($dataCDNVideoHub[0]['meta']['countries'])) { // Берём категории стран
                $categoryArray = array_merge($dataCDNVideoHub[0]['meta']['countries'], $categoryArray);
            }

            if ($categoryArray && Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['cat'])) {
                $categoryArray = array_flip(array_map('mb_strtolower', $categoryArray));
                foreach ($CDNVideoHubModule[Base::$modName]['config']['cat'] as $catIndex => $subCatArray) {
                    $allMatch = true;

                    foreach ($subCatArray as $info) {
                        if (!isset($categoryArray[mb_strtolower($info)])) {
                            $allMatch = false;
                            break;
                        }
                    }

                    if ($allMatch) {
                        $idCats[] = $catIndex;
                    }
                }

                if ($idCats) {
                    $idCats = array_unique($idCats); // Уникализируем категори
                    if ($postCategory) {
                        $idCats = array_diff($idCats, $postCategory); // Берём только уникальные категории которых ещё нет у новости
                    }
                }
            }

            $updatePost = [];
            $xfSearchWords = [];
            $quality = [];
            $allXfieldsArray = Base::xfListLoad();

            if ($catType == 'film') {
                if (Base::checkIsset($dataCDNVideoHub[0]['content']) && Base::checkIsset($dataCDNVideoHub[0]['content'][0]['videoTypeId'])) {
                    foreach ($dataCDNVideoHub[0]['content'] as $qualityArray) {
                        if (Base::checkIsset($qualityArray['videoTypeId'])) {
                            $quality[$qualityArray['videoTypeId']] = $qualityKeyCDNVideoHub[$qualityArray['videoTypeId']];
                        }
                    }
                }
            } elseif ($catType == 'serial') {
                if (Base::checkIsset($dataCDNVideoHubEpisode) && Base::checkIsset($dataCDNVideoHubEpisode[0]['content'])) {
                    foreach ($dataCDNVideoHubEpisode[0]['content'] as $qualityArray) {
                        if (Base::checkIsset($qualityArray['videoTypeId'])) {
                            $quality[$qualityArray['videoTypeId']] = $qualityKeyCDNVideoHub[$qualityArray['videoTypeId']];
                        }
                    }
                }
            }

            // Если сюда дошли, значит есть обновление данных
            foreach ($CDNVideoHubModule[Base::$modName]['config']['fieldUpdate'] as $value) {
                if ($value == 'p.cat') { // Категории нам не нужно заполнять
                    continue;
                }

                if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['onlyEmpty'])) { // Только пустые
                    if ((Base::checkIsset($postFields[$value]) && Base::checkIsset($row[$postFields[$value]])) || Base::checkIsset($xfieldsCDNVideoHub[$value])) {
                        if (!Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['fieldUpdateOverride'])) { // Если перезаписи нет
                            continue;
                        }

                        if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['fieldUpdateOverride']) && is_array($CDNVideoHubModule[Base::$modName]['config']['fieldUpdateOverride']) && !in_array($value, $CDNVideoHubModule[Base::$modName]['config']['fieldUpdateOverride'])) { // Если поле не для перезаписи то пропускаем
                            continue;
                        }
                    }
                }

                $tempTemplate = '';
                if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['data'][$value])) {
                    $tempTemplate = $CDNVideoHubModule[Base::$modName]['config']['data'][$value];
                }

                if (!$tempTemplate) {
                    continue;
                }

                if (strpos($tempTemplate, 'title:ru') !== false) {
                    $Title['value'] = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['name']) && Base::checkIsset($dataCDNVideoHub[0]['name']['values'])) {
                        $Title = Base::findByKey($dataCDNVideoHub[0]['name']['values'], 'language', 'RUS');
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('title:ru', $Title['value'], $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('title:ru', $Title['value'], $tempTemplate);
                }

                if (strpos($tempTemplate, 'title:original') !== false) {
                    $Title['value'] = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['name']) && Base::checkIsset($dataCDNVideoHub[0]['name']['values'])) {
                        $Title = Base::findByKey($dataCDNVideoHub[0]['name']['values'], 'language', 'ENG');
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('title:original', $Title['value'], $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('title:original', $Title['value'], $tempTemplate);
                }

                if (strpos($tempTemplate, 'id-kinopoisk') !== false) {
                    $IdAgr['externalId'] = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['externalIds']) && Base::checkIsset($dataCDNVideoHub[0]['externalIds'][0]['externalId'])) {
                        $IdAgr = Base::findByKey($dataCDNVideoHub[0]['externalIds'], 'externalAggregator', 'kp');
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('id-kinopoisk', $IdAgr['externalId'], $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('id-kinopoisk', $IdAgr['externalId'], $tempTemplate);
                }

                if (strpos($tempTemplate, 'id-imdb') !== false) {
                    $IdAgr['externalId'] = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['externalIds']) && Base::checkIsset($dataCDNVideoHub[0]['externalIds'][0]['externalId'])) {
                        $IdAgr = Base::findByKey($dataCDNVideoHub[0]['externalIds'], 'externalAggregator', 'imdb');
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('id-imdb', $IdAgr['externalId'], $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('id-imdb', $IdAgr['externalId'], $tempTemplate);
                }

                if (strpos($tempTemplate, 'id-mali') !== false) {
                    $IdAgr['externalId'] = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['externalIds']) && Base::checkIsset($dataCDNVideoHub[0]['externalIds'][0]['externalId'])) {
                        $IdAgr = Base::findByKey($dataCDNVideoHub[0]['externalIds'], 'externalAggregator', 'mali');
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('id-mali', $IdAgr['externalId'], $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('id-mali', $IdAgr['externalId'], $tempTemplate);
                }

                if (strpos($tempTemplate, 'id-mdl') !== false) {
                    $IdAgr['externalId'] = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['externalIds']) && Base::checkIsset($dataCDNVideoHub[0]['externalIds'][0]['externalId'])) {
                        $IdAgr = Base::findByKey($dataCDNVideoHub[0]['externalIds'], 'externalAggregator', 'mdl');
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('id-mdl', $IdAgr['externalId'], $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('id-mdl', $IdAgr['externalId'], $tempTemplate);
                }

                if (strpos($tempTemplate, 'id-cdnvideohub') !== false) {
                    $tempTemplate = CDNVideoHub::tagBlock('id-cdnvideohub', $dataCDNVideoHub[0]['id'], $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('id-cdnvideohub', $dataCDNVideoHub[0]['id'], $tempTemplate);
                }

                if (strpos($tempTemplate, 'description:ru') !== false) {
                    $Description['value'] = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['description']) && Base::checkIsset($dataCDNVideoHub[0]['description']['values'])) {
                        $Description = Base::findByKey($dataCDNVideoHub[0]['description']['values'], 'language', 'RUS');
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('description:ru', $Description['value'], $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('description:ru', $Description['value'], $tempTemplate);
                }

                if (strpos($tempTemplate, 'description:eng') !== false) {
                    $Description['value'] = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['description']) && Base::checkIsset($dataCDNVideoHub[0]['description']['values'])) {
                        $Description = Base::findByKey($dataCDNVideoHub[0]['description']['values'], 'language', 'ENG');
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('description:eng', $Description['value'], $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('description:eng', $Description['value'], $tempTemplate);
                }

                if (strpos($tempTemplate, 'description-short:ru') !== false) {
                    $Description['value'] = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['shortDescription']) && Base::checkIsset($dataCDNVideoHub[0]['shortDescription']['values'])) {
                        $Description = Base::findByKey($dataCDNVideoHub[0]['shortDescription']['values'], 'language', 'RUS');
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('description-short:ru', $Description['value'], $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('description-short:ru', $Description['value'], $tempTemplate);
                }

                if (strpos($tempTemplate, 'description-short:eng') !== false) {
                    $Description['value'] = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['shortDescription']) && Base::checkIsset($dataCDNVideoHub[0]['shortDescription']['values'])) {
                        $Description = Base::findByKey($dataCDNVideoHub[0]['shortDescription']['values'], 'language', 'ENG');
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('description-short:eng', $Description['value'], $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('description-short:eng', $Description['value'], $tempTemplate);
                }

                if (strpos($tempTemplate, 'type') !== false) {
                    $typeVid = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['type'])) {
                        $typeVid = $typeCDNVideo[$dataCDNVideoHub[0]['type']];
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('type', $typeVid, $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('type', $typeVid, $tempTemplate);
                }

                if (strpos($tempTemplate, 'type') !== false) {
                    $typeVid = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['type'])) {
                        $typeVid = $typeCDNVideo[$dataCDNVideoHub[0]['type']];
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('type', $typeVid, $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('type', $typeVid, $tempTemplate);
                }

                if (strpos($tempTemplate, 'premiere=') !== false) {
                    $premiere = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['meta']) && Base::checkIsset($dataCDNVideoHub[0]['meta']['premieres'])) {
                        $tempTemplate = preg_replace_callback("#\{premiere=(.+?)\}#i", function ($matches) use ($dataCDNVideoHub) {
                            return langdate($matches[1], strtotime($dataCDNVideoHub[0]['meta']['premieres'][0]['date']), false, false);
                        }, $tempTemplate);
                    } else {
                        $tempTemplate = preg_replace("#\{premiere=(.+?)\}#i", '', $tempTemplate);
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('premiere', $premiere, $tempTemplate);
                }

                if (strpos($tempTemplate, 'premiere}') !== false) {
                    $premiere = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['meta']) && Base::checkIsset($dataCDNVideoHub[0]['meta']['premieres'])) {
                        $premiere = date('Y-m-d H:i', strtotime($dataCDNVideoHub[0]['meta']['premieres'][0]['date']));
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('premiere', $premiere, $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('premiere', $premiere, $tempTemplate);
                }

                if (strpos($tempTemplate, 'country') !== false) {
                    $country = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['meta']) && Base::checkIsset($dataCDNVideoHub[0]['meta']['countries'])) {
                        $tempCountry = [];
                        foreach ($dataCDNVideoHub[0]['meta']['countries'] as $countryValue) {
                            $tempCountry[] = $countryCDNVideoHub[$countryValue];
                        }

                        $country = implode(', ', $tempCountry);
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('country', $country, $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('country', $country, $tempTemplate);
                }

                if (strpos($tempTemplate, 'genres') !== false) {
                    $genres = '';
                    if (Base::checkIsset($dataCDNVideoHub[0]['meta']) && Base::checkIsset($dataCDNVideoHub[0]['meta']['genres'])) {
                        $tempGenres = [];
                        foreach ($dataCDNVideoHub[0]['meta']['genres'] as $genresValue) {
                            $tempGenres[] = $genresCDNVideoHub[$genresValue];
                        }

                        $genres = implode(', ', $tempGenres);
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('genres', $genres, $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('genres', $genres, $tempTemplate);
                }

                if (strpos($tempTemplate, 'quality:all') !== false) {
                    $tempTemplate = CDNVideoHub::tagBlock('quality:all', implode(', ', $quality), $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('quality:all', implode(', ', $quality), $tempTemplate);
                }

                if (strpos($tempTemplate, 'quality:best') !== false) {
                    $bestQuality = '';
                    if (count($quality)) {
                        $flipQ = array_flip($quality);
                        $bestQuality = $qualityKeyCDNVideoHub[max($flipQ)];
                    }
                    $tempTemplate = CDNVideoHub::tagBlock('quality:best', $bestQuality, $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('quality:best', $bestQuality, $tempTemplate);
                }

                if (strpos($tempTemplate, 'translation:all') !== false) {
                    $voice = [];
                    if ($catType == 'film') {
                        if (Base::checkIsset($dataCDNVideoHub[0]['content'])) {
                            foreach ($dataCDNVideoHub[0]['content'] as $voiceInArray) {
                                if (Base::checkIsset($voiceInArray['voiceStudioId']) && Base::checkIsset($voiceStudioCDNVideoHubKey[$voiceInArray['voiceStudioId']])) {
                                    $voice[$voiceInArray['voiceStudioId']] = $voiceStudioCDNVideoHubKey[$voiceInArray['voiceStudioId']];
                                } elseif (Base::checkIsset($voiceInArray['voiceTypeCode']) && Base::checkIsset($voiceCDNTypeArray[$voiceInArray['voiceTypeCode']])) {
                                    $voice[$voiceInArray['voiceTypeCode']] = $voiceCDNTypeArray[$voiceInArray['voiceTypeCode']];
                                }
                            }
                        }
                    } elseif ($catType == 'serial') {
                        if (Base::checkIsset($dataCDNVideoHubEpisode)) {
                            foreach ($dataCDNVideoHubEpisode[0]['content'] as $voiceInArray) {
                                if (Base::checkIsset($voiceInArray['voiceStudioId']) && Base::checkIsset($voiceStudioCDNVideoHubKey[$voiceInArray['voiceStudioId']])) {
                                    $voice[$voiceInArray['voiceStudioId']] = $voiceStudioCDNVideoHubKey[$voiceInArray['voiceStudioId']];
                                } elseif (Base::checkIsset($voiceInArray['voiceTypeCode']) && Base::checkIsset($voiceCDNTypeArray[$voiceInArray['voiceTypeCode']])) {
                                    $voice[$voiceInArray['voiceTypeCode']] = $voiceCDNTypeArray[$voiceInArray['voiceTypeCode']];
                                }
                            }
                        }
                    }

                    $tempTemplate = CDNVideoHub::tagBlock('translation:all', implode(', ', $voice), $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('translation:all', implode(', ', $voice), $tempTemplate);
                }

                if (strpos($tempTemplate, '{season}') !== false) {
                    $tempTemplate = CDNVideoHub::tagBlock('season', $maxSeasonArray['number'], $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('season', $maxSeasonArray['number'], $tempTemplate);
                }

                foreach (['season:1', 'season:2', 'season:3', 'season:4', 'season:5', 'season:6', 'season:7', 'season:8'] as $tagSeason) {
                    if (strpos($tempTemplate, '{' . $tagSeason . '}') !== false && $maxSeasonArray['number']) {
                        $tempData = CDNVideoHub::esTag($tagSeason, $maxSeasonArray['number']);
                        $tempTemplate = CDNVideoHub::tagBlock($tagSeason, $tempData, $tempTemplate);
                        $tempTemplate = CDNVideoHub::tagValue($tagSeason, $tempData, $tempTemplate);
                    }
                }

                if (strpos($tempTemplate, '{episode}') !== false) {
                    $tempTemplate = CDNVideoHub::tagBlock('episode', $maxSeasonArray['episodesCount'], $tempTemplate);
                    $tempTemplate = CDNVideoHub::tagValue('episode', $maxSeasonArray['episodesCount'], $tempTemplate);
                }

                foreach (['episode:1', 'episode:2', 'episode:3', 'episode:4', 'episode:5', 'episode:6', 'episode:7', 'episode:8'] as $tagSeason) {
                    if (strpos($tempTemplate, '{' . $tagSeason . '}') !== false && $maxSeasonArray['episodesCount']) {
                        $tempData = CDNVideoHub::esTag($tagSeason, $maxSeasonArray['episodesCount']);
                        $tempTemplate = CDNVideoHub::tagBlock($tagSeason, $tempData, $tempTemplate);
                        $tempTemplate = CDNVideoHub::tagValue($tagSeason, $tempData, $tempTemplate);
                    }
                }

                if (strpos($tempTemplate, '[') !== false) {
                    $tempTemplate = preg_replace('#\[(.+?)\](.*?)\[\/\\1\]#s', '', $tempTemplate);
                }

                if (strpos($tempTemplate, '{') !== false) {
                    $tempTemplate = preg_replace('#{(.+?)}#s', '', $tempTemplate);
                }

                if (in_array($value, ['p.title', 'p.shortStory', 'p.fullStory'])) {
                    if ($value == 'p.title') {
                        $tempTemplate = $parse->process(trim(strip_tags($tempTemplate)));
                        $tempTemplate = $db->safesql($tempTemplate);
                        $updatePost[] = "title='{$tempTemplate}'";
                    }

                    if ($value == 'p.shortStory' || $value == 'p.fullStory') {
                        $tempTemplate = $parse->process($tempTemplate);
                        if ($config['allow_admin_wysiwyg']) {
                            $tempTemplate = $db->safesql($parse->BB_Parse($tempTemplate));
                        } else {
                            $tempTemplate = $db->safesql($parse->BB_Parse($tempTemplate, false));
                        }

                        if ($value == 'p.shortStory') {
                            $updatePost[] = "short_story='{$tempTemplate}'";
                        } else {
                            $updatePost[] = "full_story='{$tempTemplate}'";
                        }
                    }
                } else {
                    if ($config['version_id'] >= 19) {
                        if (isset($allXfieldsArray['fields'][$value]) && $allXfieldsArray['fields'][$value]['use_as_links'] == 1) {
                            $tempArray = explode(',', $tempTemplate);
                            foreach ($tempArray as $value2) {
                                $value2 = trim($value2);
                                $value2 = str_replace('&amp;#x2C;', ',', $value2);

                                if ($value2) {
                                    $xfSearchWords[] = [$db->safesql($value), $db->safesql($value2)];
                                }
                            }
                        }
                    } else {
                        $thisXf = array_filter($allXfieldsArray, function ($item) use ($value) {
                            return $item[0] == $value;
                        });

                        if (count($thisXf) > 0) {
                            $thisXf = array_values($thisXf);
                            if ($thisXf[0][6]) {
                                $tempArray = explode(',', $tempTemplate);
                                foreach ($tempArray as $value2) {
                                    $value2 = trim($value2);
                                    $value2 = str_replace('&amp;#x2C;', ',', $value2);

                                    if ($value2) {
                                        $xfSearchWords[] = [$db->safesql($value), $db->safesql($value2)];
                                    }
                                }
                            }
                        }
                    }

                    if ($tempTemplate) {
                        $xfieldsCDNVideoHub[$value] = $tempTemplate;
                    }
                }
            }

            if (!empty($xfieldsCDNVideoHub)) {
                $filecontents = Base::xfieldsString($xfieldsCDNVideoHub);

                if (count($filecontents)) {
                    $filecontents = $db->safesql(implode('||', $filecontents));
                    $updatePost[] = "xfields='" . $filecontents . "'";
                }

                if (count($xfSearchWords)) {
                    $db->query("DELETE FROM " . PREFIX . "_xfsearch WHERE news_id='{$row['id']}'");
                    if ($row['approve']) {
                        $tempArray = [];
                        foreach ($xfSearchWords as $value) {
                            $tempArray[] = "('" . $row['id'] . "', '" . $value[0] . "', '" . $value[1] . "')";
                        }

                        $xf_search_words = implode(", ", $tempArray);
                        $db->query("INSERT INTO " . PREFIX . "_xfsearch (news_id, tagname, tagvalue) VALUES " . $xf_search_words);
                    }
                }
            }

            unset($xfieldsCDNVideoHub);

            if (in_array('p.cat', $CDNVideoHubModule[Base::$modName]['config']['fieldUpdate']) && $idCats) {
                $postCategory = array_merge($postCategory, $idCats);
                $postCategory = array_unique($postCategory);
                $updatePost[] = "category='" . implode(",", $postCategory) . "'";
                if ($config['version_id'] > 13.1) {
                    $db->query("DELETE FROM " . PREFIX . "_post_extras_cats WHERE news_id='{$row['id']}'");
                    if ($row['approve']) {
                        $catPostId = [];
                        foreach ($idCats as $value) {
                            $catPostId[] = "('" . $row['id'] . "', '" . trim($value) . "')";
                        }

                        $catPostId = implode(', ', $catPostId);
                        $db->query("INSERT INTO " . PREFIX . "_post_extras_cats (news_id, cat_id) VALUES " . $catPostId);
                    }
                }
            }

            if ($CDNVideoHubModule[Base::$modName]['config']['updateDate']) {
                $updatePost[] = "date='" . date("Y-m-d H:i:s") . "'";
            }

            if ($updatePost) {
                $updatePost = implode(", ", $updatePost);
                $db->query("UPDATE " . PREFIX . "_post SET {$updatePost} WHERE id='{$row['id']}'");
                $logger->log('UPDATE', 'Данные обновлены. ID новости: ' . $row['id'], []);
            }
        }
    }
}

?>