<?php
/**
* Ядро функций
*
* @link https://lazydev.pro/
* @author LazyDev <email@lazydev.pro>
**/

namespace LazyDev\CDNVideoHub;

class Base
{
    static $modName = 'cdnvideohub';
	static $modTitle = 'CDNVideoHub';
	static $version = '0.0.0';

	static function findByKey($array, $key, $search)
	{
		$find = array_filter($array, function ($item) use($key, $search) {
           return $item[$key] == $search;
		});

       if ($find) {
           $find = array_values($find)[0];
       }

       return $find;
	}

    static function searchMax($array, $column)
    {
        $numbers = array_column($array, $column);
        $max = max($numbers);
        $maxKey = array_search($max, $numbers);

        return $array[$maxKey];
    }

	static function getCatData($id, $type, $separate)
	{
		global $cat_info;

		if (!$id || !$type) {
			return '';
		}

		$cat_id = false;

		$id = explode (',', $id);
		foreach ($id as $val) {
			$val = intval($val);

			if (isset($cat_info[$val]['id']) && $cat_info[$val]['id'] && $val) {
				$cat_id = $val;
				break;
			}
		}

		if (!$cat_id) {
			return '';
		}

		$id = $cat_id;

		$parent_id = $cat_info[$id]['parentid'];

		$data = $cat_info[$id][$type];

		while ($parent_id) {
			if (!$cat_info[$parent_id]['id']) {
				break;
			}

			$data = $cat_info[$parent_id][$type] . $separate . $data;

			$parent_id = $cat_info[$parent_id]['parentid'];
			if ($parent_id && $cat_info[$parent_id]['parentid'] == $cat_info[$parent_id]['id']) {
				break;
			}
		}

		return $data;
	}

	/**
	 * Проверяем на данные
	 *
	 * @param   string   $var
	 * @return  array
	 */
	static function arrayWork($var)
	{
		$Array = explode(',', $var);
		$Array = array_map('trim', $Array);

		return array_filter($Array);
	}

	/**
	 * Проверяем на данные
	 *
	 * @param   mixed   $var
	 * @return  mixed
	 */
	static function checkIsset($var)
	{
		return isset($var) && $var;
	}

	/**
	 * Обработка доп полей
	 *
	 * @param   array   $xfields
	 * @return  array
	 */
	static function xfieldsString($xfields)
	{
		$fileContents = [];
		foreach ($xfields as $xfielddataname => $xfielddatavalue) {
			if ($xfielddatavalue === '') {
				continue;
			}

			$xfielddataname = str_replace(["|", "\r\n"], ["&#124;", "__NEWL__"], $xfielddataname);
			$xfielddatavalue = str_replace(["|", "\r\n"], ["&#124;", "__NEWL__"], $xfielddatavalue);
			$fileContents[] = "$xfielddataname|$xfielddatavalue";
		}

		return $fileContents;
	}

	/**
	 * Сохранение
	 *
	 **/
	static function save($data, $path)
	{
		$handler = fopen($path, 'w');
		fwrite($handler, "<?php\n\n//" . self::$modTitle . " by LazyDev\n\nreturn ");
		fwrite($handler, var_export($data, true));
		fwrite($handler, ";\n");
		fclose($handler);
	}

    static function xfListLoad()
    {
        global $config;

        $path = ENGINE_DIR . '/data/xfields.txt';
        $path2 = ENGINE_DIR . '/data/xfields.json';

        if ($config['version_id'] >= 19) {
            if (!file_exists($path2)) {
                return ['fields' => []];
            } else {
                $fields = file_get_contents($path2);

                if ($fields !== false) {
                    return json_decode($fields, true);
                } else {
                    return ['fields' => []];
                }
            }
        } else {
            $fields2 = file($path);
            if (!is_array($fields2)) {
                return [];
            } elseif (count($fields2)) {
                $fields = [];
                foreach ($fields2 as $name => $value) {
                    if (trim($value)) {
                        $tmp_arr = explode('|', trim($value, "\t\n\r\0\x0B"));
                        foreach ($tmp_arr as $name2 => $value2) {
                            $value2 = str_replace("&#124;", "|", $value2);
                            $value2 = str_replace("__NEWL__", "\r\n", $value2);
                            $value2 = html_entity_decode($value2, ENT_QUOTES, 'UTF-8');
                            $fields[$name][$name2] = $value2;
                        }
                    }
                }

                return $fields;
            }
        }

        return [];
    }

    /**
    * Разбор serialize строки
    *
    * @param    string   $dataForm
    * @return   array
    **/
	static function unserializeJs($dataForm)
	{
		$newArray = [];
		if ($dataForm) {
			parse_str($dataForm, $arrayPost);
			$newArray = self::loop($arrayPost);
		}

		return $newArray;
	}

	/**
	 * loop
	 *
	 * @param    array   $array
	 * @return   array
	 **/
	static function loop($array) {
        global $config;

		$allXfieldsArray = self::xfListLoad();
		foreach($array as $key => $value) {
			if (is_array($value)) {
				$array[$key] = self::loop($value);
			}

			$textArea = false;
			if (!in_array($value, ['p.title', 'p.cat', 'p.shortStory', 'p.fullStory', 'p.metaTitle', 'p.metaDescr', 'p.altName'])) {
                if ($config['version_id'] >= 19) {
                    if (isset($allXfieldsArray['fields'][$key]) && $allXfieldsArray['fields'][$key]['type'] == 'textarea' && !$allXfieldsArray['fields'][$key]['safe_mode']) {
                        $textArea = true;
                    }
                } else {
                    $thisXf = array_filter($allXfieldsArray, function ($item) use ($key) {
                        return $item[0] == $key;
                    });

                    if (count($thisXf) > 0) {
                        $thisXf = array_values($thisXf);
                        if ($thisXf[0][3] == 'textarea' && !$thisXf[0][8]) {
                            $textArea = true;
                        }
                    }
                }
			}

			if ($textArea) {
				$array[$key] = stripslashes($value);
			} elseif (!is_array($value) && !in_array($key, ['p.shortStory', 'p.fullStory'])) {
				$array[$key] = self::typeValue($value);
			} elseif (in_array($key, ['p.shortStory', 'p.fullStory'])) {
				$array[$key] = stripslashes($value);
			}
		}

		return $array;
	}

    /**
    * Типизация данных
    *
    * @param    mixed   $v
    * @return   float|int|string
    **/
	static function typeValue($v)
	{
		if (is_numeric($v)) {
			$v = is_float($v) ? floatval($v) : intval($v);
		} else {
			$v = strip_tags(stripslashes($v));
		}

		return $v;
	}

    /**
    * Json для js
    *
    * @param    array   $v
    * @return   string
    **/
    static function json($v)
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE);
    }

    /**
    * Получить данные с массива по массиву ключей
    *
    * @param    array   $a
    * @param    array   $k
    * @param    int     $c
    * @return   string
    **/
    static public function multiArray($a, $k, $c)
    {
        return ($c > 1) ? self::multiArray($a[$k[count($k) - $c]], $k, ($c - 1)) : $a[$k[(count($k) - 1)]];
    }

    /**
    * Вспомогательная функция для поиска подстроки
    *
    * @param string $string
    * @param string $find
    * @return   mixed
    */
    static function position($string, $find)
    {
        if (function_exists('mb_strpos')) {
            return mb_strpos($string, $find, 0, 'UTF-8');
        } elseif (function_exists('iconv_strrpos')) {
            return iconv_strpos($string, $find, 0, 'UTF-8');
        }

        return strpos($string, $find);
    }

	/**
	 * ssl or not
	 *
	 * @return   bool
	 **/
	static function isSSL() {
		return (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === 443) || (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] === 443) || (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') || (isset($_SERVER['CF_VISITOR']) && $_SERVER['CF_VISITOR'] === '{"scheme":"https"}') || (isset($_SERVER['HTTP_CF_VISITOR']) && $_SERVER['HTTP_CF_VISITOR'] === '{"scheme":"https"}');
	}
}

?>