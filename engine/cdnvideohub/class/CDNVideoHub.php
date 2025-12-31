<?php
/**
 * Главный класс с набором методов для работы с базой CDNVideoHub
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

namespace LazyDev\CDNVideoHub;

class CDNVideoHub
{
	static private $instance;

	static public function getInstance() {
		if (!isset(self::$instance)) {
			self::$instance = new CDNVideoHub();
		}

		return self::$instance;
	}

	/**
	 * Запрос по API
	 *
	 * @param $link	string
	 *
	 * @return mixed
	 **/
	static function request($link)
	{
        global $apiLink;

        $link = $apiLink . $link;

        $headers = [
            "Authorization: Bearer " . Data::get('api', 'config'),
            "Accept: application/json",
            "Content-Type: application/json",
            "User-Agent: dle module"
        ];

        $maxRetries = 3;
        $retryCount = 0;
        if (function_exists('curl_init')) {
            while ($retryCount < $maxRetries) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $link);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                $output = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $headersStr = substr($output, 0, $headerSize);
                curl_close($ch);

                if ($httpCode === 429) {
                    $retryAfter = 1;
                    if (!empty($headersStr)) {
                        $headersArray = explode("\r\n", $headersStr);
                        foreach ($headersArray as $header) {
                            if (stripos($header, 'Retry-After:') === 0) {
                                $retryAfterValue = trim(substr($header, 12));
                                if (is_numeric($retryAfterValue)) {
                                    $retryAfter = (int)$retryAfterValue;
                                }
                                break;
                            }
                        }
                    }

                    $retryCount++;

                    if ($retryCount >= $maxRetries) {
                        return $output;
                    }

                    sleep($retryAfter);
                    continue;
                }

                return $output;
            }
        }

        return file_get_contents($link);
    }

	/**
	 * Заполнение тегов-блоков
	 *
	 * @param $name string
	 * @param $var mixed
	 * @param $content string
	 *
	 * @return string
	 **/
	static function tagBlock($name, $var, $content)
	{
		$content = preg_replace("'\\[{$name}\\](.*?)\\[/{$name}\\]'is", $var ? "\\1" : "", $content);
		$content = preg_replace("'\\[not-{$name}\\](.*?)\\[/not-{$name}\\]'is", $var ? "" : "\\1", $content);

		return $content;
	}

	/**
	 * Заполнение тега
	 *
	 * @param $name string
	 * @param $var mixed
	 * @param $content string
	 *
	 * @return string
	 **/
	static function tagValue($name, $var, $content)
	{
		$content = str_replace('{' . $name . '}', $var, $content);

		return $content;
	}

	/**
	 * Параметры для сезонов и серий
	 *
	 * @param $type string
	 * @param $data int
	 *
	 * @return string
	 **/
	static function esTag($type, $data)
	{
		$rangeStart = 1;
		$rangeEnd = $data;

		switch ($type) {
			case 'episode:1':
			case 'season:1':
				return ++$data;

			case 'episode:2':
			case 'season:2':
				return $data == 1 ? 1 : '1-' . $data;

			case 'episode:3':
			case 'season:3':
				return $data > 1 ? implode(',', range($rangeStart, $rangeEnd)) : $data;

			case 'episode:4':
			case 'season:4':
				$rangeStart = $data == 1 ? 1 : $data - 1;
				$rangeEnd = $data + 1;
				break;

			case 'episode:5':
			case 'season:5':
				if ($data > 1 && $data <= 5) {
					return '1-' . implode(',', range($rangeStart, $rangeEnd));
				} elseif ($data > 5) {
					$rangeStart = $data - 1;
				}
				break;

			case 'episode:6':
			case 'season:6':
				if ($data > 1 && $data <= 5) {
					return '1-' . implode(',', range($rangeStart, $rangeEnd));
				} elseif ($data > 5) {
					$rangeStart = $data - 2;
				}
				break;
			case 'episode:7':
			case 'season:7':
				if ($data <= 3) {
					return implode(',', range($rangeStart, $rangeEnd));
				} elseif ($data > 3) {
					return implode(',', range($rangeEnd-2, $rangeEnd));
				}
				break;
			case 'episode:8':
			case 'season:8':
				if ($data <= 3) {
					return implode(',', range($rangeStart, $rangeEnd+1));
				} elseif ($data > 3) {
					return implode(',', range($rangeEnd-2, $rangeEnd+1));
				}
				break;
			default:
				return $data;
		}

		return '1-' . implode(',', range($rangeStart, $rangeEnd));
	}

	/**
	 * Проверка сериалов на новый контент
	 *
	 *
	 * @return mixed
	 **/
	static function checkSerials($dataCDNVideoHub, $CDNVideoHubModule, $row, $xfields, $seasonXF = '')
	{
        $maxSeasonArray = Base::searchMax($dataCDNVideoHub[0]['seasons'], 'number');

        if (Base::checkIsset($CDNVideoHubModule[Base::$modName]['config']['seasonBySeason'])) { // Посезонная работа модуля
            if ($seasonXF && $seasonXF == 'max') {
                return $maxSeasonArray;
            }

            if ($seasonXF && is_int($seasonXF) && $seasonXF > 0) {
                $seasonSite = $seasonXF;
            } else {
                $seasonSite = $xfields[$CDNVideoHubModule[Base::$modName]['config']['serialSeasonNumber']]; // Берём сезон новости
            }

            $foundSeason  = null;

            foreach ($dataCDNVideoHub[0]['seasons'] as $season) {
                if ($season['number'] == $seasonSite) {
                    $foundSeason = $season;
                    break;
                }
            }

            if ($foundSeason != null && is_array($foundSeason) && count($foundSeason) > 0) { // Если нужный сезон найден
                if ($maxSeasonArray['number'] > $seasonSite) { // Если в массиве есть ещё сезоны значит проверок больше не нужно
                    Cache::setFile('Конец проверки. ID новости: ' . $row['id'], $row['id'], '/end'); // Записываем конец проверок
                }

                if (!$xfields[$CDNVideoHubModule[Base::$modName]['config']['serialEpisodeNumber']]) {
                    $xfields[$CDNVideoHubModule[Base::$modName]['config']['serialEpisodeNumber']] = 0;
                }

                if ($foundSeason['episodesCount'] > $xfields[$CDNVideoHubModule[Base::$modName]['config']['serialEpisodeNumber']]) { // Если серий больше чем на сайте
                    return $foundSeason; // Новая серия
                }
            }
        } else {
            if (!$maxSeasonArray) {
				return false; // На всякий если данные о сезонах отсутствуют
			}

            if ($maxSeasonArray['number'] > $xfields[$CDNVideoHubModule[Base::$modName]['config']['serialSeasonNumber']]) { // Если сезон больше чем на сайте
                return $maxSeasonArray; // Новый сезон
            }

            if ($maxSeasonArray['episodesCount'] > $xfields[$CDNVideoHubModule[Base::$modName]['config']['serialEpisodeNumber']]) { // Если серий больше чем на сайте
                return $maxSeasonArray; // Новая серия
            }
        }

        return false; // Возможно ошибки в данных/это не сериал/нет данных/нет нового эпизода/сезона
	}

	private function __construct() {}
	private function __clone() {}
	private function __wakeup() {}
}

?>