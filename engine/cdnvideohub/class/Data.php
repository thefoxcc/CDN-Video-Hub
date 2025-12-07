<?php
/**
 * Конфиг и языковый файл
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

namespace LazyDev\CDNVideoHub;

class Data
{
    static private $data = [];

    /**
     * Загрузить конфиг и языковый пакет
     */
    static function load()
    {
		$path = realpath(__DIR__ . '/..');

        self::$data['config'] = include $path . '/data/config.php';
		self::$data['config']['lang'] = self::$data['config']['lang'] ?? 'ru';
        self::$data['lang'] = include $path . '/lang/' . self::$data['config']['lang'] . '.lng';
    }

    /**
     * Вернуть массив данных
     *
     * @param   string  $key
     * @return  array
     */
    static function receive($key)
    {
        return self::$data[$key];
    }

    /**
     * Получить данные с массива по ключу
     *
     * @param    string|array   $key
     * @param    string         $type
     * @return   mixed
     */
    public static function get($key, $type)
    {
        if (is_array($key) && !empty(self::$data[$type])) {
            return Base::multiArray(self::$data[$type], $key, count($key));
        }
		
		if (!empty(self::$data[$type][$key])) {
			return self::$data[$type][$key];
		}
		
		return false;
    }

}

?>