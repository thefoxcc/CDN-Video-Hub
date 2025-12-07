<?php
/**
 * Модуль CDN Video Hub
 *
 * Файл отвечает за проставление данных в редактировании/добавлении новости в админ панели
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

use LazyDev\CDNVideoHub\CDNVideoHub;
use LazyDev\CDNVideoHub\Base;

if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['api'])) {
    $fieldChoose = '';
    $foundCDNVideoHub = false;
    $news_id = intval($_POST['newsid']);

    $seasonXF = $_POST['season'] == 'max' ? 'max' : intval($_POST['season']);

    foreach ($fields as $field => $search) {
        $apiRequest = '';

        if (!$search) {
            continue;
        }

        if ($field == 'idKinopoisk') {
            $apiRequest = 'titles?externalIds.aggregator.id=1&externalIds.externalId=' . $search;
        }

        if ($field == 'idImdb') {
            $apiRequest = 'titles?externalIds.aggregator.id=2&externalIds.externalId=' . $search;
        }

        if ($field == 'idMyAnimeList') {
            $search = str_replace('tt', '', $search);
            $apiRequest = 'titles?externalIds.aggregator.id=3&externalIds.externalId=' . $search;
        }

        if ($field == 'idMyDramaList') {
            if (!is_numeric($search) && Base::position($search, '-')) {
                $search = explode('-', $search)[0];
                $search = trim($search);
            }

            if ($search) {
                $apiRequest = 'titles?externalIds.aggregator.id=4&externalIds.externalId=' . $search;
            }
        }

        if ($field == 'idApi') {
            $apiRequest = 'titles?id=' . $search;
        }

        if (!$apiRequest) {
            continue;
        }

        $dataCDNVideoHub = CDNVideoHub::request($apiRequest);
        $dataCDNVideoHub = json_decode($dataCDNVideoHub, true);

        if (Base::checkIsset($dataCDNVideoHub) && is_array($dataCDNVideoHub) && count($dataCDNVideoHub)) {
            $foundCDNVideoHub = true;
            break;
        }
    }

    if (!$foundCDNVideoHub) {
        echo Base::json(['error' => 'По вашему запросу ничего не найдено.']);
        die();
    }

    $xfields = [];
    if ($news_id) {
        $row = $db->super_query("SELECT id, title, alt_name, xfields, date, category, approve, short_story, full_story, alt_name, metatitle, descr FROM " . PREFIX . "_post WHERE `id`='{$news_id}'"); // Получаем данные о новости
        if (Base::checkIsset($row['id'])) { // Проверка на вероятность того что это была не новость или она вдруг не существует
            if (Base::checkIsset($row['category'])) {
                $postCategory = Base::arrayWork($row['category']); // Убираем пустые данные в категории, такое бывает
            }

            $xfields = xfieldsdataload($row['xfields']);
        }
    }

    if (Base::checkIsset($dataCDNVideoHub[0]['seasons'])) {
        $catType = 'serial';
        $checkUpdateSerials = CDNVideoHub::checkSerials($dataCDNVideoHub, $CDNVideoHubModule, $row, $xfields, $seasonXF);
        if ($checkUpdateSerials !== false) {
            $dataCDNVideoHubSeason = CDNVideoHub::request('seasons?id=' . $checkUpdateSerials['id']);
            $dataCDNVideoHubSeason = json_decode($dataCDNVideoHubSeason, true);
            if (Base::checkIsset($dataCDNVideoHubSeason[0]['episodes'])) {
                $maxEpisodeArray = Base::searchMax($dataCDNVideoHubSeason[0]['episodes'], 'number');
                if (Base::checkIsset($maxEpisodeArray['id'])) {
                    $dataCDNVideoHubEpisode = CDNVideoHub::request('episodes?id=' . $maxEpisodeArray['id']);
                    $dataCDNVideoHubEpisode = json_decode($dataCDNVideoHubEpisode, true);
                }
            }
        }
    } elseif (Base::checkIsset($dataCDNVideoHub[0]['content'])) {
        $catType = 'film';
        $maxQualityArray = ['videoTypeId' => 0];
        if (Base::checkIsset($dataCDNVideoHub[0]['content'][0]['videoTypeId'])) {
            $maxQualityArray = Base::searchMax($dataCDNVideoHub[0]['content'], 'videoTypeId'); // Берём лучшее качество
        }
    }

    $maxSeasonArray = ['number' => '', 'episodesCount' => ''];
    if ($catType == 'serial') {
        if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['seasonBySeason'])) {
            if ($seasonXF && $seasonXF == 'max') {
                $maxSeasonArray = Base::searchMax($dataCDNVideoHub[0]['seasons'], 'number');
            }

            if ($seasonXF && is_int($seasonXF) && $seasonXF > 0) {
                $xfields[$CDNVideoHubModule[Base::$modName]['config']['serialSeasonNumber']] = $seasonXF;
            }

            foreach ($dataCDNVideoHub[0]['seasons'] as $season) {
                if ($season['number'] == $xfields[$CDNVideoHubModule[Base::$modName]['config']['serialSeasonNumber']]) {
                    $maxSeasonArray = $season;
                    break;
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

    foreach ($CDNVideoHubModule[Base::$modName]['config']['fieldUpdateInNews'] as $value) {
        if ($value == 'p.cat') { // Категории нам не нужно заполнять
            continue;
        }

        if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['onlyEmpty'])) { // Только пустые
            if ((Base::checkIsset($CDNVideoHubModule[Base::$modName]['config'][$value]) && Base::checkIsset($row[$postFields[$value]])) || Base::checkIsset($xfields[$value])) {
                if (!Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['fieldUpdateOverrideInNews'])) { // Если перезаписи нет
                    continue;
                }

                if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['fieldUpdateOverrideInNews']) && is_array($CDNVideoHubModule[Base::$modName]['config']['fieldUpdateOverrideInNews']) && !in_array($value, $CDNVideoHubModule[Base::$modName]['config']['fieldUpdateOverrideInNews'])) { // Если поле не для перезаписи то пропускаем
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
                if (Base::checkIsset($dataCDNVideoHubEpisode) && Base::checkIsset($dataCDNVideoHubEpisode[0]['content'][0]['voiceStudioId'])) {
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

        if ($value == 'p.title') {
            $tempTemplate = trim(strip_tags($tempTemplate));
        }

        if ($tempTemplate) {
            $xfields[$value] = $tempTemplate;
        }
    }

    echo Base::json(['api' => $xfields, 'cat' => $idCats, 'config' => ['editor' => $config['allow_admin_wysiwyg']], 'content' => '1', 'error' => '']);
}