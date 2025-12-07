<?php
/**
 * Loader
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

defined('DATALIFEENGINE') || die();

use LazyDev\CDNVideoHub\Base;
use LazyDev\CDNVideoHub\Data;

include_once ENGINE_DIR . '/classes/plugins.class.php';

spl_autoload_register(function ($class) {
    $prefix = 'LazyDev\\CDNVideoHub\\';
    $baseDir = __DIR__ . '/class/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

Data::load();

Base::$version = '1.4.1';

$CDNVideoHubModule[Base::$modName]['name'] = Base::$modName;
$CDNVideoHubModule[Base::$modName]['config'] = Data::receive('config');
$CDNVideoHubModule[Base::$modName]['lang'] = Data::receive('lang');

include ENGINE_DIR . '/cdnvideohub/lib/vars.php';

?>