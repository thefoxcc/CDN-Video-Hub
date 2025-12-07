<?php
/**
* Дизайн админ панель
*
* @link https://lazydev.pro/
* @author LazyDev <email@lazydev.pro>
**/

if (!defined('DATALIFEENGINE') || !defined('LOGGED_IN')) {
	header('HTTP/1.1 403 Forbidden');
	header('Location: ../../');
	die('Hacking attempt!');
}

use LazyDev\CDNVideoHub\Data;
use LazyDev\CDNVideoHub\Base;

$styleNight = $night = '';
if ($_COOKIE['admin_' . Base::$modName . '_dark']) {
    $night = 'dle_theme_dark';
$styleNight = <<<HTML
<link href="engine/{$modName}/admin/template/assets/dark.css" rel="stylesheet" type="text/css">
<link href="engine/{$modName}/admin/template/assets/tail.select-dark.min.css" rel="stylesheet" type="text/css">
HTML;
	$background_theme = 'background-color: #fbffff!important; color: #000!important;';
	$CDNVideoHubModule[$modName]['lang']['admin']['other']['dark_theme'] = $CDNVideoHubModule[$modName]['lang']['admin']['other']['white_theme'];
} else {
$styleNight = <<<HTML
<link href="engine/{$modName}/admin/template/assets/tail.select-light.min.css" rel="stylesheet" type="text/css">
HTML;
	$background_theme = 'background-color: #282626;';
}

echo <<<HTML
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$CDNVideoHubModule[$modName]['lang']['admin']['title']}</title>
        <link href="engine/skins/fonts/fontawesome/styles.min.css" rel="stylesheet" type="text/css">
        <link href="engine/skins/stylesheets/application.css" rel="stylesheet" type="text/css">
        <link href="{$config['http_home_url']}engine/{$modName}/admin/template/assets/style.css?v4" rel="stylesheet" type="text/css">
        <script src="engine/skins/javascripts/application.js"></script>
        <script>
            let dle_act_lang = [{$CDNVideoHubModule[$modName]['lang']['admin']['other']['jslang']}];
            let cal_language = {
                en: {
                    months: [{$CDNVideoHubModule[$modName]['lang']['admin']['other']['jsmonth']}],
                    dayOfWeekShort: [{$CDNVideoHubModule[$modName]['lang']['admin']['other']['jsday']}]
                }
            };
            let filedefaulttext = '{$CDNVideoHubModule[$modName]['lang']['admin']['other']['jsnotgot']}';
            let filebtntext = '{$CDNVideoHubModule[$modName]['lang']['admin']['other']['jschoose']}';
            let dle_login_hash = '{$dle_login_hash}';
        </script>
        {$styleNight}
    </head>
    <body class="{$night}">
        <div class="navbar navbar-inverse">
            <div class="navbar-header">
                <a class="navbar-brand" href="?mod={$modName}">{$CDNVideoHubModule[$modName]['lang']['name']} {$modVer}</a>
                <ul class="nav navbar-nav visible-xs-block">
                    <li><a data-toggle="collapse" data-target="#navbar-mobile"><i class="fa fa-angle-double-down"></i></a></li>
                    <li><a class="sidebar-mobile-main-toggle"><i class="fa fa-bars"></i></a></li>
                </ul>
            </div>
            <div class="navbar-collapse collapse" id="navbar-mobile">
                <div class="navbar-right">	
                    <ul class="nav navbar-nav">
                    	<li><input type="button" onclick="setDark(); return false;" class="btn bg-teal btn-sm" style="{$background_theme}float: right;border-radius: unset;font-size: 13px;margin-top: 8px;margin-right: 5px;text-shadow: unset; height: 30px;border-radius: 3px;" value="{$CDNVideoHubModule[$modName]['lang']['admin']['other']['dark_theme']}"></li>
                        <li><a href="{$PHP_SELF}?mod={$modName}" title="{$CDNVideoHubModule[$modName]['lang']['admin']['other']['main']}">{$CDNVideoHubModule[$modName]['lang']['admin']['other']['main']}</a></li>
                        <li><a href="{$PHP_SELF}" title="{$CDNVideoHubModule[$modName]['lang']['admin']['other']['all_menu_dle']}">{$CDNVideoHubModule[$modName]['lang']['admin']['other']['all_menu_dle']}</a></li>
                        <li><a href="{$config['http_home_url']}" title="{$CDNVideoHubModule[$modName]['lang']['admin']['other']['site']}" target="_blank">{$CDNVideoHubModule[$modName]['lang']['admin']['other']['site']}</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="page-container">
            <div class="page-content">
                
                <div class="content-wrapper">
                    <div class="page-header page-header-default">
                        <div class="breadcrumb-line">
                            {$speedbar}
                            <input type="button" onclick="clearCache('/last');" class="btn bg-danger btn-sm" style="float: right;border-radius: unset;font-size: 13px;margin-top: 6px;" value="{$CDNVideoHubModule[$modName]['lang']['admin']['cache']['clearLastCache']}">
                            <input type="button" onclick="clearCache('/end');" class="btn bg-primary btn-sm" style="margin-right: 10px;float: right;border-radius: unset;font-size: 13px;margin-top: 6px;" value="{$CDNVideoHubModule[$modName]['lang']['admin']['cache']['clearEndCache']}">
                            <input type="button" onclick="clearCache('/player');" class="btn bg-success btn-sm" style="margin-right: 10px;float: right;border-radius: unset;font-size: 13px;margin-top: 6px;" value="{$CDNVideoHubModule[$modName]['lang']['admin']['cache']['clearPlayerCache']}">
                        </div>
                    </div>
                    <div class="content">
HTML;
$jsAdminScript[] = <<<HTML

let langCache = {
    'clearCache/lastInfo': '{$CDNVideoHubModule[$modName]['lang']['admin']['cache']['clearCacheLastInfo']}',
    'clearCache/endInfo': '{$CDNVideoHubModule[$modName]['lang']['admin']['cache']['clearCacheEndInfo']}',
    'clearCache/playerInfo': '{$CDNVideoHubModule[$modName]['lang']['admin']['cache']['clearCachePlayerInfo']}'
};

HTML;
?>