<?php
/**
 * AJAX обработчик
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

error_reporting(E_ALL ^ E_WARNING ^ E_DEPRECATED ^ E_NOTICE);
ini_set('error_reporting', E_ALL ^ E_WARNING ^ E_DEPRECATED ^ E_NOTICE);

define('DATALIFEENGINE', true);
define('ROOT_DIR', realpath(__DIR__ . '/../../../..'));
define('ENGINE_DIR', ROOT_DIR . '/engine');

header("Content-type: text/html; charset=utf-8");
setlocale(LC_NUMERIC, 'C');

require_once(ENGINE_DIR . '/classes/plugins.class.php');

date_default_timezone_set($config['date_adjust']);

use LazyDev\CDNVideoHub\Base;
use LazyDev\CDNVideoHub\Ajax;
use LazyDev\CDNVideoHub\Data;

include_once ENGINE_DIR . '/cdnvideohub/loader.php';

include_once (DLEPlugins::Check(ENGINE_DIR . '/inc/include/functions.inc.php'));

$selected_language = $config['langs'];

if (isset($_COOKIE['selected_language'])) {
	$_COOKIE['selected_language'] = trim(totranslit($_COOKIE['selected_language'], false, false));

	if ($_COOKIE['selected_language'] != "" && is_dir(ROOT_DIR . '/language/' . $_COOKIE['selected_language'])) {
		$selected_language = $_COOKIE['selected_language'];
	}
}

if (file_exists(DLEPlugins::Check(ROOT_DIR . '/language/' . $selected_language . '/adminpanel.lng'))) {
	include_once(DLEPlugins::Check(ROOT_DIR . '/language/' . $selected_language . '/adminpanel.lng'));
}

if (!$config['http_home_url']) {
	$config['http_home_url'] = explode('engine/' . Base::$modName . '/inc/ajax/ajax.php', $_SERVER['PHP_SELF']);
	$config['http_home_url'] = reset($config['http_home_url']);
}

if (strpos($config['http_home_url'], '//') === 0) {
	$config['http_home_url'] = isSSL() ? 'https:' . $config['http_home_url'] : 'http:' . $config['http_home_url'];
} elseif (strpos($config['http_home_url'], '/') === 0) {
	$config['http_home_url'] = isSSL() ? 'https://' . $_SERVER['HTTP_HOST'] . $config['http_home_url'] : 'http://' . $_SERVER['HTTP_HOST'] . $config['http_home_url'];
} elseif (isSSL() && stripos($config['http_home_url'], 'http://') !== false) {
	$config['http_home_url'] = str_replace('http://', 'https://', $config['http_home_url']);
}

if ($config['http_home_url'][strlen($config['http_home_url']) - 1] !== '/') {
	$config['http_home_url'] .= '/';
}

dle_session();

$user_group = get_vars('usergroup');
if (!$user_group) {
	$user_group = [];
	$db->query('SELECT * FROM ' . USERPREFIX . '_usergroups ORDER BY id ASC');
	while ($row = $db->get_row()) {
		$user_group[$row['id']] = [];
		foreach ($row as $key => $value) {
			$user_group[$row['id']][$key] = stripslashes($value);
		}
	}
	set_vars('usergroup', $user_group);
	$db->free();
}

$cat_info = get_vars('category');
if (!$cat_info) {
	$cat_info = [];
	$db->query('SELECT * FROM ' . PREFIX . '_category ORDER BY posi ASC');
	while ($row = $db->get_row()) {
		$cat_info[$row['id']] = [];
		foreach ($row as $key => $value) {
			$cat_info[$row['id']][$key] = stripslashes($value);
		}
	}
	set_vars('category', $cat_info);
	$db->free();
}

$is_logged = false;
require_once (DLEPlugins::Check(ENGINE_DIR . '/modules/sitelogin.php'));

if (!$is_logged) {
	die('Access denied.');
}

$action = isset($_POST['action']) ? trim(strip_tags($_POST['action'])) : false;
$dle_hash = isset($_POST['user_hash']) ? trim(strip_tags($_POST['user_hash'])) : false;

if (!$dle_hash || $dle_hash != $dle_login_hash) {
	echo Base::json(['text' => $dleLazyDevModule[Base::$modName]['lang']['admin']['other']['error'], 'error' => 'true']);
	exit;
}


if ($action == 'inputVideo') {
    $fields = $_POST['fields'] ?? false;

    if (!$fields) {
        echo Base::json(['error' => 'Нет данных полей для поиска видео.']);
        exit;
    }

    include ENGINE_DIR . '/cdnvideohub/inc/video.php';
}

?>