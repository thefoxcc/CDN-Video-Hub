<?php
/**
* Главная страница админ панель
*
* @link https://lazydev.pro/
* @author LazyDev <email@lazydev.pro>
**/

use LazyDev\CDNVideoHub\Admin;
use LazyDev\CDNVideoHub\Base;
use LazyDev\CDNVideoHub\CDNVideoHubUpdater;

include_once ENGINE_DIR . '/cdnvideohub/version.php';

$action   = isset($_REQUEST['cvh_action']) ? $_REQUEST['cvh_action'] : '';
$messages = [];

if (CDNVideoHubUpdater::dueForCheck()) {
    CDNVideoHubUpdater::checkForUpdate($err);
    if ($err) $messages[] = '<div class="alert alert-warning">Проверка обновлений: ' . htmlspecialchars($err) . '</div>';
}
$modUpdated = false;
if (!empty($_POST['user_hash']) && $_POST['user_hash'] == $dle_login_hash) {
    if ($action === 'check_update') {
        $res = CDNVideoHubUpdater::checkForUpdate($err);
        if ($err) {
            $messages[] = '<div class="alert alert-danger">Ошибка проверки: ' . htmlspecialchars($err) . '</div>';
        } else {
            if ($res['version'] == CDNVIDEOHUB_VERSION) {
                $messages[] = '<div class="alert alert-success">Проверено. У вас последняя версия модуля.</div>';
            } else {
                $messages[] = '<div class="alert alert-success">Проверено. Доступная версия: ' . htmlspecialchars($res['version'] ?: '—') . '</div>';
            }
        }
    }

    if ($action === 'do_update') {
        $log = [];
        if (CDNVideoHubUpdater::runUpdate($log)) {
            if ((float)CDNVIDEOHUB_VERSION >= 1.4) {
                $db->query("UPDATE ". PREFIX . "_plugins_files SET `replacecode` = 'include_once ENGINE_DIR . \'/cdnvideohub/lib/siteplayer.php\';\ninclude_once ENGINE_DIR . \'/cdnvideohub/index.php\';' WHERE `replacecode` = 'include_once ENGINE_DIR . \'/cdnvideohub/lib/siteplayer.php\';\ninclude_once ENGINE_DIR . \'/cdnvideohub/index.php\';\n\$xfields = xfieldsload();'");
                clear_all_caches();
            }
            $modUpdated = true;
            $messages[] = '<div class="alert alert-success">Модуль обновлён успешно.</div>';
        } else {
            $messages[] = '<div class="alert alert-danger">Ошибка обновления:<br>' . nl2br(htmlspecialchars(implode("\n", $log))) . '</div>';
        }
    }
}

$hasUpdate = !$modUpdated && CDNVideoHubUpdater::hasUpdate();
$st = CDNVideoHubUpdater::getState();
$remote_version = $st['remote_version'] ?: '';
$remote_date    = $st['remote_updated_at'] ?: '';
$check_minutes  = (int)$st['check_interval_minutes'];
$meta_url       = htmlspecialchars((string)$st['meta_url']);

echo <<<HTML
<div class="panel panel-default">
    <div class="panel-heading">Обновление модуля CDN Video Hub</div>
    <div class="panel-body">

HTML;
foreach ($messages as $m) {
    echo $m;
}
$remote_version = $remote_version ?: '-';
$ver = $modUpdated ? $st['remote_version'] : CDNVIDEOHUB_VERSION;
$remote_date = $remote_date ? ' от ' . $remote_date : '';
echo <<<HTML
        <p>Текущая версия: <b>{$ver}</b></p>
HTML;
if ($ver !== $remote_version) {
    echo <<<HTML
        <p>Доступная версия: <b>{$remote_version}</b>{$remote_date}</p>
HTML;
}
if ($hasUpdate) {
echo <<<HTML
            <div class="alert alert-info">Доступно обновление! Можно установить прямо сейчас.</div>
            <form method="post" style="display:inline-block;margin-right:10px">
                <input type="hidden" name="user_hash" value="{$dle_login_hash}">
                <input type="hidden" name="cvh_action" value="do_update">
                <button class="btn btn-success" type="submit">Обновить модуль</button>
            </form>
HTML;
} else {
echo <<<HTML
            <div class="alert alert-success">У вас установлена актуальная версия.</div>
HTML;
}
echo <<<HTML
        <form method="post" style="display:inline-block;margin-right:10px">
            <input type="hidden" name="user_hash" value="{$dle_login_hash}">
            <input type="hidden" name="cvh_action" value="check_update">
            <button class="btn btn-primary" type="submit">Проверить обновления сейчас</button>
        </form>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">{$CDNVideoHubModule[$modName]['lang']['admin']['other']['block_menu']}</div>
    <div class="list-bordered">
HTML;
echo Admin::menu([
    [
        'link' => '?mod=' . $modName . '&action=settings',
        'icon' => $config['http_home_url'] . 'engine/' . $modName . '/admin/template/assets/icons/settings.png',
        'title' => $CDNVideoHubModule[Base::$modName]['lang']['admin']['menu']['settings'],
        'descr' => $CDNVideoHubModule[Base::$modName]['lang']['admin']['menu']['settings_descr']
    ],
	[
		'link' => '?mod=' . $modName . '&action=fill',
		'icon' => $config['http_home_url'] . 'engine/' . $modName . '/admin/template/assets/icons/list.png',
		'title' => $CDNVideoHubModule[Base::$modName]['lang']['admin']['menu']['fill'],
		'descr' => $CDNVideoHubModule[Base::$modName]['lang']['admin']['menu']['fill_descr']
	],
    [
        'link' => '?mod=' . $modName . '&action=grab',
        'icon' => $config['http_home_url'] . 'engine/' . $modName . '/admin/template/assets/icons/grab.png',
        'title' => $CDNVideoHubModule[Base::$modName]['lang']['admin']['menu']['grab'],
        'descr' => $CDNVideoHubModule[Base::$modName]['lang']['admin']['menu']['grab_descr']
    ],
    [
        'link' => '?mod=' . $modName . '&action=license',
        'icon' => $config['http_home_url'] . 'engine/' . $modName . '/admin/template/assets/icons/sync.png',
        'title' => $CDNVideoHubModule[Base::$modName]['lang']['admin']['menu']['sync'],
        'descr' => $CDNVideoHubModule[Base::$modName]['lang']['admin']['menu']['sync_descr']
    ]
]);
echo <<<HTML
    </div>
</div>
HTML;

?>