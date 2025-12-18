<?php
/**
 * Кэш
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

namespace LazyDev\CDNVideoHub;

class Cache
{
	/**
	 * Берем кэш
	 *
	 * @param    string     $id
	 * @param 	 string 	$dir
	 *
	 * @return	 string|bool
	 **/
	static function getFile($id, $dir)
	{
		$dir = ENGINE_DIR . '/' . Base::$modName . '/cache' . $dir;
		$response = false;

		$file = $dir . '/' . $id . '.cache';
		if (file_exists($file)) {
			$response = file_get_contents($file);
		}

		return $response;
	}

	/**
	 * Сохраняем кэш
	 *
	 * @param    mixed     $data
	 * @param    string    $id
	 * @param 	 string    $dir
	 *
	 **/
        static function setFile($data, $id, $dir)
        {
                $dir = ENGINE_DIR . '/' . Base::$modName . '/cache' . $dir;

                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }

                $file = $dir . '/' . $id . '.cache';
                file_put_contents($file, $data, LOCK_EX);
		@chmod($file, 0666);
		return true;
	}

    /**
     * Очищаем кэш
     *
     **/
    static function clear($location = '')
    {
        global $dlefastcache, $config, $mcache;

		$prefix = Base::$modName . '_';
		$dir = ENGINE_DIR . '/' . Base::$modName . '/cache' . $location;

        $cacheDir = opendir($dir);
        while ($file = readdir($cacheDir)) {
            if ($file != '.htaccess' && !is_dir($dir . '/' . $file)) {
                @unlink($dir . '/' . $file);
            }
        }

		return true;
    }
}

?>