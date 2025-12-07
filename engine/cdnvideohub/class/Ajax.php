<?php
/**
 * Класс AJAX обработки админ панели
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

namespace LazyDev\CDNVideoHub;

class Ajax
{
    /**
     * Определяем AJAX действие
     *
     * @param    string    $action
     *
     **/
    static function ajaxAction($action)
    {
        in_array($action, get_class_methods(self::class)) && self::$action();
    }

	/**
	 * Сохранение настроек
	 *
	 **/
	static function saveConfig()
	{
		global $CDNVideoHubModule;

		$arrayConfig = Base::unserializeJs($_POST['data']);
		if (isset($CDNVideoHubModule[Base::$modName]['config']['lang']) && $CDNVideoHubModule[Base::$modName]['config']['lang']) {
			$arrayConfig['lang'] = $CDNVideoHubModule[Base::$modName]['config']['lang'];
		}
        $arrayConfig = array_filter($arrayConfig, function ($value, $key) {
            return $value && $value != '-';
        }, ARRAY_FILTER_USE_BOTH);
		Base::save($arrayConfig, ENGINE_DIR . '/' . Base::$modName . '/data/config.php');

		echo Base::json(['text' => $CDNVideoHubModule[Base::$modName]['lang']['admin']['settings']['saved']]);
	}

	/**
	 * Очистка кэша
	 *
	 **/
	static function clearCache()
	{
		global $CDNVideoHubModule;

		Cache::clear($_POST['data']);
		echo Base::json(['text' => $CDNVideoHubModule[Base::$modName]['lang']['admin']['other']['cache_cleared']]);
	}

    /**
     * Включение/Выключение тёмной темы
     *
     */
    static function setDark()
    {
		$cookieName = 'admin_' . Base::$modName . '_dark';
        if (isset($_COOKIE[$cookieName])) {
            set_cookie($cookieName, '', -1);
            $_COOKIE[$cookieName] = null;
        } else {
            set_cookie($cookieName, 'yes', 300);
            $_COOKIE[$cookieName] = 'yes';
        }

        echo 'yes';
    }
}

?>