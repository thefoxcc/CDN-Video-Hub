<?php
/**
 * Модуль CDN Video Hub
 *
 * Файл отвечает за массовое проставление данных
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

use LazyDev\CDNVideoHub\CDNVideoHub;
use LazyDev\CDNVideoHub\Base;
use LazyDev\CDNVideoHub\Cache;
use LazyDev\CDNVideoHub\Data;
use LazyDev\CDNVideoHub\FileLogger;

$filelog = $filelog ?? 'grab';

$postData = $postData ?? Base::unserializeJs($_POST['data']);

$fromPages = intval($_POST['fromPages']);
$pageStep = intval($_POST['pageStep']);

$notAdded = intval($_POST['notAdded']);
$added = intval($_POST['added']);

$remove = ['\t', '\n', '\r'];

if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['api'])) { // Проверка на корректные данные id новости и наличие api токена
    $logger = new FileLogger(ENGINE_DIR . '/cdnvideohub/log/' . $filelog . '.txt', [
        'daily' => true,
        'max_bytes' => 5 * 1024 * 1024,
        'max_files' => 5
    ]);

    $logger->info('Старт граббинга');
    $logger->info('Страница ' . $fromPages);

    include_once (DLEPlugins::Check(ENGINE_DIR . '/classes/htmlpurifier/HTMLPurifier.standalone.php'));
    include_once (DLEPlugins::Check(ENGINE_DIR . '/classes/parse.class.php'));
    $parse = new ParseFilter();
    if ($config['allow_admin_wysiwyg']) {
        $parse->allow_code = false;
    }

    $allXfieldsArray = Base::xfListLoad();
    $memberName = $db->safesql($member_id['name']);
    $userid = (int)$member_id['user_id'];
    $allow_comm = (int)$postData['allow_comm'];
    $approve = (int)$postData['approve'];
    $allow_main = (int)$postData['allow_main'];

    $search = 'titles?order[createdAt]=desc&page=' . $fromPages;

    if (isset($postData['type']) && $postData['type'] == 1) {
        $search.= '&exists[seasons]=0';
    }

    if (isset($postData['type']) && $postData['type'] == 2) {
        $search.= '&exists[seasons]=1';
    }

//    $genres = [];
//
//    if (isset($postData['typeContent']) && $postData['typeContent']) {
//        $genres[] = $postData['typeContent'];
//    }
//
//    if (isset($postData['genres']) && is_array($postData['genres']) && $postData['genres']) {
//        $genres[] = implode(',', $postData['genres']);
//    }

    if (isset($postData['genres']) && $postData['genres']) {
        $search.= '&meta.genres.id[]=' . $postData['genres'];
    }

    $fromPages++;
    $pageStep++;

    try {
        $dataCDNVideoHubTemp = CDNVideoHub::request($search);
    } catch (Throwable $e) {
        $logger && $logger->error('Ошибка запроса к API CDNVideoHub при получении списка.', ['search' => $search, 'exception' => $e]);
        echo Base::json(['error' => 'error', 'text' => 'API request error']);
        return;
    }

    $dataCDNVideoHubTemp = json_decode($dataCDNVideoHubTemp, true);

    $makeXfieldsRegexp = function ($db, $name, $value) {
        $name  = preg_quote(trim($name), '/');
        $value = preg_quote(trim($value), '/');

        $pattern = "(^|[|]{2}){$name}[|]{$value}([|]{2}|$)";
        return "xfields REGEXP('" . $db->safesql($pattern) . "')";
    };

    if (!$dataCDNVideoHubTemp) {
        $logger->info('Конец граббинга');
        echo Base::json(['error' => 'error', 'text' => 'Конец граббинга']);
        return;
    }

    foreach ($dataCDNVideoHubTemp as $item) {
        $dataCDNVideoHub[0] = $item;

        if (!isset($postData['licensed']) && isset($dataCDNVideoHub[0]['tags'])) {
            if (is_array($dataCDNVideoHub[0]['tags']) && in_array('licensed', $dataCDNVideoHub[0]['tags'])) {
                $logger->log('LICENSED', 'Релиз не добавлен, причина: Лицензия.', ['apiId' => $dataCDNVideoHub[0]['id'] ?? null]);
                continue;
            }

            if (!is_array($dataCDNVideoHub[0]['tags']) && is_string($dataCDNVideoHub[0]['tags']) && $dataCDNVideoHub[0]['tags'] == 'licensed') {
                $logger->log('LICENSED', 'Релиз не добавлен, причина: Лицензия.', ['apiId' => $dataCDNVideoHub[0]['id'] ?? null]);
                continue;
            }
        }

        if (Base::checkIsset($dataCDNVideoHub[0]['seasons'])) {
            $catType = 'serial';
            $maxSeasonArray = [];
            if (Base::checkIsset($dataCDNVideoHub[0]['seasons'])) {
                $maxSeasonArray = Base::searchMax($dataCDNVideoHub[0]['seasons'], 'number');
                if ($maxSeasonArray) {
                    try {
                        $dataCDNVideoHubSeason = CDNVideoHub::request('seasons?id=' . $maxSeasonArray['id']);
                    } catch (Throwable $e) {
                        $logger && $logger->error('Ошибка при запросе сезона.', [
                            'seasonId'  => $maxSeasonArray['id'] ?? null,
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
                        }
                    }
                }
            }
        } elseif (Base::checkIsset($dataCDNVideoHub[0]['content'])) {
            $catType = 'film';
            $maxQualityArray = ['videoTypeId' => 0];
            if (isset($dataCDNVideoHub[0]['content'][0]['videoTypeId'])) {
                $maxQualityArray = Base::searchMax($dataCDNVideoHub[0]['content'], 'videoTypeId'); // Берём лучшее качество
            }
        } else {
            $logger->log('SKIP', 'Фильм не был добавлен, ошибка данных.', ['apiId' => $dataCDNVideoHub[0]['id'] ?? null]);
            $notAdded++;
            continue;
        }

        $sqlCheck = [];
        $externalIds = $dataCDNVideoHub[0]['externalIds'] ?? [];
        $extMap = [];
        if (is_array($externalIds)) {
            foreach ($externalIds as $row) {
                if (!empty($row['externalAggregator']) && isset($row['externalId']) && $row['externalId'] !== '') {
                    $extMap[$row['externalAggregator']] = (string)$row['externalId'];
                }
            }
        }

        $rulesKey = [
            'kinopoisk' => $extMap['kp'] ?? null,
            'imdb' => $extMap['imdb'] ?? null,
            'myAnimeList' => $extMap['mali'] ?? null,
            'myDramaList' => $extMap['mdl'] ?? null,
            'idApi' => $dataCDNVideoHub[0]['id'] ?? null
        ];

        $rulesKeyValue = [
            'kinopoisk' => 'KinoPoisk',
            'imdb' => 'IMDb',
            'myAnimeList' => 'My Anime List',
            'myDramaList' => 'My Drama List',
            'idApi' => 'ID Api'
        ];

        $strLoggerAdd = [];
        foreach ($rulesKey as $cfgKey => $value) {
            if (!Base::checkIsset($CDNVideoHubModule[Base::$modName]['config'][$cfgKey]) || !Base::checkIsset($value)) {
                continue;
            }

            $strLoggerAdd[$rulesKeyValue[$cfgKey]] = $value;
            $sqlCheck[] = $makeXfieldsRegexp($db, (string)$CDNVideoHubModule[Base::$modName]['config'][$cfgKey], (string)$value);
        }

        if ($sqlCheck) {
            $sqlWhere = implode(' OR ', $sqlCheck);
            $row = $db->super_query("SELECT id FROM " . PREFIX . "_post WHERE {$sqlWhere} LIMIT 1");
            $checkId = (int)($row['id'] ?? 0);

            if ($checkId > 0) {
                $notAdded++;
                $logger->log('SKIP', 'Фильм не был добавлен так как уже существует. ID новости: ' . $checkId, $strLoggerAdd);
                continue;
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

        foreach ($postData['fieldGrab'] as $value) {
            if ($value == 'p.cat') { // Категории нам не нужно заполнять
                continue;
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
                    $updatePost['title'] = $tempTemplate;
                }

                if ($value == 'p.shortStory' || $value == 'p.fullStory') {
                    $tempTemplate = $parse->process($tempTemplate);
                    if ($config['allow_admin_wysiwyg']) {
                        $tempTemplate = $db->safesql($parse->BB_Parse($tempTemplate));
                    } else {
                        $tempTemplate = $db->safesql($parse->BB_Parse($tempTemplate, false));
                    }

                    if ($value == 'p.shortStory') {
                        $updatePost['short_story'] = $tempTemplate;
                    } else {
                        $updatePost['full_story'] = $tempTemplate;
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
                    $xfields[$value] = $tempTemplate;
                }
            }
        }

        $updatePost['date'] = date("Y-m-d H:i:s");

        if (!empty($xfields)) {
            $filecontents = Base::xfieldsString($xfields);

            if (count($filecontents)) {
                $filecontents = $db->safesql(implode('||', $filecontents));
                $updatePost['xfields'] = $filecontents;
            }
        }

        if ($idCats) {
            $postCategory = array_unique($idCats);
            $updatePost['category'] = implode(",", $postCategory);
        }

        $alt_name = '';
        if ($updatePost['title']) {
            $alt_name = $db->safesql(totranslit(stripslashes($updatePost['title']), true, false, $config['translit_url']));
        }

        $added++;
        $db->query( "INSERT INTO " . PREFIX . "_post (date, autor, short_story, full_story, xfields, title, category, alt_name, allow_comm, approve, allow_main) values ('{$updatePost['date']}', '{$memberName}', '{$updatePost['short_story']}', '{$updatePost['full_story']}', '{$updatePost['xfields']}', '{$updatePost['title']}', '{$updatePost['category']}', '{$alt_name}', '{$allow_comm}', '{$approve}', '{$allow_main}')" );

        $id = $db->insert_id();
        $logger->log('ADDED', 'Фильм добавлен. ID новости: ' . $id, $strLoggerAdd);

        $db->query("INSERT INTO " . PREFIX . "_post_extras (news_id, user_id) VALUES('{$id}', '{$userid}')");

        if (count($xfSearchWords)) {
            if ($approve) {
                $tempArray = [];
                foreach ($xfSearchWords as $value) {
                    $tempArray[] = "('" . $id . "', '" . $value[0] . "', '" . $value[1] . "')";
                }

                $xf_search_words = implode(", ", $tempArray);
                $db->query("INSERT INTO " . PREFIX . "_xfsearch (news_id, tagname, tagvalue) VALUES " . $xf_search_words);
            }
        }
        unset($xfields);

        if ($idCats) {
            if ($config['version_id'] > 13.1) {
                if ($approve) {
                    $catPostId = [];
                    foreach ($postCategory as $value) {
                        $catPostId[] = "('" . $id . "', '" . trim($value) . "')";
                    }

                    $catPostId = implode(', ', $catPostId);
                    $db->query("INSERT INTO " . PREFIX . "_post_extras_cats (news_id, cat_id) VALUES " . $catPostId);
                }
            }
        }
    }

    $logger->info('Конец граббинга');
    echo Base::json(['status' => 'ok', 'page' => $pageStep, 'nextPage' => $fromPages, 'added' => $added, 'notAdded' => $notAdded]);
}

?>