<?php
/**
 * AJAX обработчик
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

@error_reporting(E_ALL ^ E_WARNING ^ E_DEPRECATED ^ E_NOTICE);
@ini_set('error_reporting', E_ALL ^ E_WARNING ^ E_DEPRECATED ^ E_NOTICE);
@ini_set('display_errors', true);
@ini_set('html_errors', false);

define('DATALIFEENGINE', true);
define('ROOT_DIR', realpath(__DIR__ . '/../..'));
define('ENGINE_DIR', ROOT_DIR . '/engine');

use LazyDev\CDNVideoHub\Base;
use LazyDev\CDNVideoHub\Data;

include_once realpath(__DIR__ ) . '/loader.php';

header('Content-type: text/html; charset=' . $config['charset']);
date_default_timezone_set($config['date_adjust']);
setlocale(LC_NUMERIC, 'C');

$dlefastcache = false;

if ($config['cache_type'] && (float)$config['version_id'] >= 14.2) {
	if ($config['cache_type'] == "2") {
		include_once (DLEPlugins::Check(ENGINE_DIR . '/classes/redis.class.php'));
	} else {
		include_once (DLEPlugins::Check(ENGINE_DIR . '/classes/memcache.class.php'));
	}

	$dlefastcache = new dle_fastcache($config);
}

require_once DLEPlugins::Check(ENGINE_DIR . '/modules/functions.php');

if ($_REQUEST['skin']) {
	$_REQUEST['skin'] = $_REQUEST['dle_skin'] = trim(totranslit($_REQUEST['skin'], false, false));
}

if ($_REQUEST['dle_skin']) {
	$_REQUEST['dle_skin'] = trim(totranslit($_REQUEST['dle_skin'], false, false));
	if ($_REQUEST['dle_skin'] && is_dir(ROOT_DIR . '/templates/' . $_REQUEST['dle_skin'])) {
		$config['skin'] = $_REQUEST['dle_skin'];
	} else {
		$_REQUEST['dle_skin'] = $_REQUEST['skin'] = $config['skin'];
	}
} elseif ($_COOKIE['dle_skin']) {
	$_COOKIE['dle_skin'] = trim(totranslit((string)$_COOKIE['dle_skin'], false, false));

	if ($_COOKIE['dle_skin'] && is_dir(ROOT_DIR . '/templates/' . $_COOKIE['dle_skin'])) {
		$config['skin'] = $_COOKIE['dle_skin'];
	}
}

if ($config['lang_' . $config['skin']] && file_exists( DLEPlugins::Check(ROOT_DIR . '/language/' . $config['lang_' . $config['skin']] . '/website.lng'))) {
	include_once (DLEPlugins::Check(ROOT_DIR . '/language/' . $config['lang_' . $config['skin']] . '/website.lng'));
} else {
	include_once (DLEPlugins::Check(ROOT_DIR . '/language/' . $config['langs'] . '/website.lng'));
}

if (!$config['http_home_url']) {
	$config['http_home_url'] = explode('engine/', $_SERVER['PHP_SELF']);
	$config['http_home_url'] = reset($config['http_home_url']);
}

$isSSL = Base::isSSL();

if (strpos($config['http_home_url'], $_SERVER['HTTP_HOST']) === false) {
	if (strpos($config['http_home_url'], '//') === 0) {
		$config['http_home_url'] = '//' . $_SERVER['HTTP_HOST'];
	} elseif (strpos($config['http_home_url'], '/') === 0) {
		$config['http_home_url'] = '/' . $_SERVER['HTTP_HOST'];
	} elseif(($isSSL && stripos($config['http_home_url'], 'http://') !== false) || !$isSSL) {
		$config['http_home_url'] = 'http://' . $_SERVER['HTTP_HOST'];
	} elseif($isSSL) {
		$config['http_home_url'] = 'https://' . $_SERVER['HTTP_HOST'];
	}

	$config['http_home_url'] .= '/';
}

if (strpos($config['http_home_url'], '//') === 0) {
	$config['http_home_url'] = $isSSL ? $config['http_home_url'] = 'https:' . $config['http_home_url'] : $config['http_home_url'] = 'http:' . $config['http_home_url'];
} elseif (strpos($config['http_home_url'], '/') === 0) {
	$config['http_home_url'] = $isSSL ? $config['http_home_url'] = 'https://' . $_SERVER['HTTP_HOST'] . $config['http_home_url'] : 'http://' . $_SERVER['HTTP_HOST'] . $config['http_home_url'];
} elseif($isSSL && stripos($config['http_home_url'], 'http://') !== false) {
	$config['http_home_url'] = str_replace( 'http://', 'https://', $config['http_home_url']);
}

if (substr($config['http_home_url'], -1, 1) != '/') {
	$config['http_home_url'] .= '/';
}

require_once DLEPlugins::Check(ENGINE_DIR . '/classes/templates.class.php');
dle_session();

$tpl = new dle_template();
if (($config['allow_smartphone'] && !$_SESSION['mobile_disable'] && $tpl->smartphone) || $_SESSION['mobile_enable']) {
	if (is_dir(ROOT_DIR . '/templates/smartphone')) {
		$config['skin'] = 'smartphone';
		$smartphone_detected = true;
		if ($config['allow_comments_wysiwyg'] > 0) {
			$config['allow_comments_wysiwyg'] = 0;
		}
	}
}

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

define('TEMPLATE_DIR', ROOT_DIR . '/templates/' . $config['skin']);

$is_logged = false;
require_once DLEPlugins::Check(ENGINE_DIR . '/modules/sitelogin.php');
if (!$is_logged) {
	$member_id['user_group'] = 5;
}

$id = intval($_POST['id']);
$ajaxWorkCDNVideoHub = 'ajax';

$banner_in_news = $banners = [];
if ($config['allow_banner']) {
	include_once(DLEPlugins::Check(ENGINE_DIR . '/modules/banners.php'));
}

$tpl->dir = TEMPLATE_DIR;

include ENGINE_DIR . '/cdnvideohub/index.php';


echo Base::json(['work' => 'done']);