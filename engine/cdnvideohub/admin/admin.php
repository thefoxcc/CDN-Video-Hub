<?php
/**
* Админ панель
*
* @link https://lazydev.pro/
* @author LazyDev <email@lazydev.pro>
**/

use LazyDev\CDNVideoHub\Admin;
use LazyDev\CDNVideoHub\Base;

if (!defined('DATALIFEENGINE') || !defined('LOGGED_IN')) {
	header('HTTP/1.1 403 Forbidden');
	header('Location: ../../');
	die('Hacking attempt!');
}

include realpath(__DIR__ . '/..') . '/loader.php';

$modName = Base::$modName;
$modVer = Base::$version;

$jsAdminScript = [];
$additionalJsAdminScript = [];

$action = strip_tags($_GET['action']) ?: 'main';
$action = totranslit($action, true, false);

$secondAction = strip_tags($_GET['secondAction']) ?: '';
if ($secondAction) {
	$secondAction = totranslit($secondAction, true, false);
}

$speedbar = [];

if ($action == 'main') {
	$speedbar[] = '<li><i class="fa fa-home position-left"></i>' . $CDNVideoHubModule[Base::$modName]['lang']['admin']['speedbar']['main'] . '</li>';
} else {
	$speedbar[] = '<li><i class="fa fa-home position-left"></i><a href="?mod=' . $modName . '" style="color:#2c82c9">' . $CDNVideoHubModule[Base::$modName]['lang']['admin']['speedbar']['main'] . '</a></li>';
}

if (in_array($action, ['settings', 'fill', 'grab', 'license'])) {
	$speedbar[] = '<li>' . $CDNVideoHubModule[Base::$modName]['lang']['admin']['speedbar'][$action] . '</li>';
}

if ($speedbar) {
	$speedbar = implode('', $speedbar);
$speedbar = <<<HTML
	<ul class="breadcrumb">{$speedbar}</ul>
HTML;
}

if (!isset($_POST['action2'])) {
    include ENGINE_DIR . '/' . $modName . '/admin/template/main.php';
}

if (file_exists(ENGINE_DIR . '/' . $modName . '/admin/' . $action . '.php')) {
	include ENGINE_DIR . '/' . $modName . '/admin/' . $action . '.php';
}

if (!isset($_POST['action2'])) {
    include ENGINE_DIR . '/' . $modName . '/admin/template/footer.php';
}
?>