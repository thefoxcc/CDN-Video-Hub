<?php
/**
 * Админ панель. Страница настроек модуля.
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

use LazyDev\CDNVideoHub\Admin;
use LazyDev\CDNVideoHub\Base;

$allXfield = Base::xfListLoad();

$allField = [
    'p.title' => $CDNVideoHubModule[$modName]['lang']['admin']['settings']['p.title'],
	'p.cat' => $CDNVideoHubModule[$modName]['lang']['admin']['settings']['p.cat'],
    'p.shortStory' => $CDNVideoHubModule[$modName]['lang']['admin']['settings']['p.short_story'],
    'p.fullStory' => $CDNVideoHubModule[$modName]['lang']['admin']['settings']['p.full_story'],
];
$xfieldArray['-'] = 'Не выбрано';
if ($config['version_id'] >= 19) {
    foreach ($allXfield['fields'] as $value) {
        $allField[$value['name']] = $xfieldArray[$value['name']] = $value['description'];
    }
} else {
    foreach ($allXfield as $value) {
        $allField[$value[0]] = $xfieldArray[$value[0]] = $value[1];
    }
}

$genresArray = ['__genres__' => '__genres__'];
$genresArray += $genresCDNVideoHub;
$genresArray += ['__country__' => '__country__'];
$genresArray += $countryCDNVideoHub;

$disableCategories = CategoryNewsSelection(empty($CDNVideoHubModule[$modName]['config']['disableCategories']) ? 0 : $CDNVideoHubModule[$modName]['config']['disableCategories'], false, false);

$metaField = [
	'title' => $CDNVideoHubModule[$modName]['lang']['admin']['settings']['p.title'],
	'metaTitle' => $CDNVideoHubModule[$modName]['lang']['admin']['settings']['metaTitle'],
	'metaDescr' => $CDNVideoHubModule[$modName]['lang']['admin']['settings']['metaDescr'],
	'altName' => $CDNVideoHubModule[$modName]['lang']['admin']['settings']['altName'],
];

echo <<<HTML
<form action="" method="post">
    <div class="panel panel-flat">
		<div class="navbar navbar-default navbar-component navbar-xs" style="z-index:0;margin-bottom: 0px;">
	        <ul class="nav navbar-nav visible-xs-block">
		        <li class="full-width text-center"><a data-toggle="collapse" data-target="#navbar-filter">
		            <i class="fa fa-bars"></i></a>
                </li>
	        </ul>
            <div class="navbar-collapse collapse" id="navbar-filter">
                <ul class="nav navbar-nav">
                    <li class="active">
						<a onclick="ChangeOption(this, 'block_1');" class="tip">
                        <i class="fa fa-cog"></i> {$CDNVideoHubModule[$modName]['lang']['admin']['settings']['main']}</a>
                    </li>
                    <li>
						<a onclick="ChangeOption(this, 'block_8');" class="tip">
                        <i class="fa fa-play-circle-o"></i> {$CDNVideoHubModule[$modName]['lang']['admin']['settings']['player']}</a>
                    </li>
					<li>
						<a onclick="ChangeOption(this, 'block_2');" class="tip">
                        <i class="fa fa-file-text-o"></i> {$CDNVideoHubModule[$modName]['lang']['admin']['settings']['data']}</a>
                    </li>
                    <li>
						<a onclick="ChangeOption(this, 'block_9');" class="tip">
                        <i class="fa fa-download"></i> {$CDNVideoHubModule[$modName]['lang']['admin']['settings']['grab']}</a>
                    </li>
                    <li>
						<a onclick="ChangeOption(this, 'block_3');" class="tip">
                        <i class="fa fa-upload"></i> {$CDNVideoHubModule[$modName]['lang']['admin']['settings']['update']}</a>
                    </li>
                    <li>
						<a onclick="ChangeOption(this, 'block_4');" class="tip">
                        <i class="fa fa-tasks"></i> {$CDNVideoHubModule[$modName]['lang']['admin']['settings']['auto']}</a>
                    </li>
                    <li>
						<a onclick="ChangeOption(this, 'block_5');" class="tip">
                        <i class="fa fa-th-list"></i> {$CDNVideoHubModule[$modName]['lang']['admin']['settings']['cats']}</a>
                    </li>
                    <li>
						<a onclick="ChangeOption(this, 'block_6');" class="tip">
                        <i class="fa fa-film"></i> {$CDNVideoHubModule[$modName]['lang']['admin']['settings']['films']}</a>
                    </li>
                    <li>
						<a onclick="ChangeOption(this, 'block_7');" class="tip">
                        <i class="fa fa-television"></i> {$CDNVideoHubModule[$modName]['lang']['admin']['settings']['serials']}</a>
                    </li>
                    <li>
						<a onclick="ChangeOption(this, 'block_10');" class="tip">
                        <i class="fa fa-crosshairs"></i> Крон лицензированного контента</a>
                    </li>
                </ul>
            </div>
        </div>
        <div id="block_10" style='display:none'>
			<div class="panel-body" style="font-size:15px; font-weight:bold;">Крон лицензированного контента</div>
			
			<div class="table-responsive">
				<table class="table">
HTML;
Admin::row(
    'Тип работы с лицензированным контентом',
    'Выберите что делать с лицензированным контентом при обнаружении его на сайте',
    Admin::select('license[type]', [1 => 'Отправлять на модерацию', 2 => 'Удалять из сайта'], $CDNVideoHubModule[$modName]['config']['license']['type'] ?? 1, [true, false, false, false, true])
);
$seed = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
shuffle($seed);
$seed = array_flip($seed);
$randomKey = array_rand($seed, 15);
$randomKey = implode($randomKey);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['key'],
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['key_desc'],
    Admin::input(['license[key_license]', 'text', $CDNVideoHubModule[$modName]['config']['license']['key_license'] ?? $randomKey])
);
echo <<<HTML
				</table>
			</div>
		</div>
        <div id="block_9" style='display:none'>
			<div class="panel-body" style="font-size:15px; font-weight:bold;">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['grabHead']}</div>
			<div class="alert alert-info alert-styled-left alert-arrow-left alert-component text-left">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['grabDesc']}</div>
			<div class="table-responsive">
				<table class="table">
HTML;
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['fieldGrab'],
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['fieldGrab_descr'],
    Admin::selectFill('grab[fieldGrab][]', $allField, $CDNVideoHubModule[$modName]['config']['grab']['fieldGrab'], [true, true, false, false, true])
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['grabAuthor'],
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['grabAuthor_desc'],
    Admin::input(['grab[grabAuthor]', 'text', $CDNVideoHubModule[$modName]['config']['grab']['grabAuthor'] ?? $member_id['name']])
);

$seed = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
shuffle($seed);
$seed = array_flip($seed);
$randomKey = array_rand($seed, 15);
$randomKey = implode($randomKey);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['key'],
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['key_desc'],
    Admin::input(['grab[key]', 'text', $CDNVideoHubModule[$modName]['config']['grab']['key'] ?? $randomKey])
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['approve'],
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['approve_descr'],
    Admin::checkBox('grab[approve]', $CDNVideoHubModule[$modName]['config']['grab']['approve'], 'approve')
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['allow_main'],
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['allow_main_descr'],
    Admin::checkBox('grab[allow_main]', $CDNVideoHubModule[$modName]['config']['grab']['allow_main'], 'allow_main')
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['allow_comm'],
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['allow_comm_descr'],
    Admin::checkBox('grab[allow_comm]', $CDNVideoHubModule[$modName]['config']['grab']['allow_comm'], 'allow_comm')
);
echo <<<HTML
				</table>
			</div>
		</div>
        <div id="block_8" style='display:none'>
			<div class="panel-body" style="font-size:15px; font-weight:bold;">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['player']}</div>
			<div class="alert alert-info alert-styled-left alert-arrow-left alert-component text-left">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['playerSection']}</div>
			<div class="table-responsive">
				<table class="table">
HTML;
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['partnerId'],
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['partnerId_descr'],
    Admin::input(['partnerId', 'text', $CDNVideoHubModule[$modName]['config']['partnerId'] ?? ''])
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['hideBanner'],
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['hideBanner_descr'],
    Admin::checkBox('hideBanner', $CDNVideoHubModule[$modName]['config']['hideBanner'], 'hideBanner')
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['onlyVoice'],
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['onlyVoice_descr'],
    Admin::checkBox('onlyVoice', $CDNVideoHubModule[$modName]['config']['onlyVoice'], 'onlyVoice')
);
echo <<<HTML
				</table>
			</div>
		</div>
		<div id="block_1">
			<div class="panel-body" style="font-size:15px; font-weight:bold;">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['main']}</div>
			<div class="table-responsive">
				<table class="table">
HTML;
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['api'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['api_descr'],
	Admin::input(['api', 'text', $CDNVideoHubModule[$modName]['config']['api'] ?? ''])
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['kinopoisk'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['kinopoisk_descr'],
	Admin::select('kinopoisk', $xfieldArray, $CDNVideoHubModule[$modName]['config']['kinopoisk'], [true, false, false, false, false])
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['imdb'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['imdb_descr'],
	Admin::select('imdb', $xfieldArray, $CDNVideoHubModule[$modName]['config']['imdb'], [true, false, false, false, false])
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['myAnimeList'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['myAnimeList_descr'],
	Admin::select('myAnimeList', $xfieldArray, $CDNVideoHubModule[$modName]['config']['myAnimeList'], [true, false, false, false, false])
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['myDramaList'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['myDramaList_descr'],
	Admin::select('myDramaList', $xfieldArray, $CDNVideoHubModule[$modName]['config']['myDramaList'], [true, false, false, false, false])
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['idApi'],
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['idApi_descr'],
    Admin::select('idApi', $xfieldArray, $CDNVideoHubModule[$modName]['config']['idApi'], [true, false, false, false, false])
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['timeCheck'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['timeCheck_descr'],
	Admin::input(['timeCheck', 'number', $CDNVideoHubModule[$modName]['config']['timeCheck'] ?? 10, false, false, 0, 999])
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['disableCategories'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['disableCategories_descr'],
	Admin::selectTag('disableCategories[]', $disableCategories, $CDNVideoHubModule[$modName]['lang']['admin']['settings']['categoriesSelect'])
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['disableVoice'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['disableVoice_descr'],
	Admin::select('disableVoice[]', $voiceStudioCDNVideoHubKey, $CDNVideoHubModule[$modName]['config']['disableVoice'], [false, true, false, $CDNVideoHubModule[$modName]['lang']['admin']['settings']['voiceSelect'], false])
);
echo <<<HTML
				</table>
			</div>
		</div>
		<div id="block_2" style='display:none'>
			<div class="panel-body" style="font-size:15px; font-weight:bold;">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['data']}</div>
			<div class="alert alert-info alert-styled-left alert-arrow-left alert-component text-left">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['dataSection']}</div>
			<div class="alert alert-component text-size-small" style="margin-bottom:0px!important;box-shadow:none!important;">
			Для того чтобы модуль начал заполнять информацию, Вам необходимо заполнить шаблон. В шаблон заполнения данных необходимо добавить тег {X}, где X - значение тега. Также можно использовать связку [X]...[/X], которые выводят текст указанный в них.
			<br><br>
			<button type="button" style="text-shadow: none!important;border-radius: 0;background: #fff;border: 1px solid #009688;color: #000;width: 100%;" onclick="ShowHide(this); return false;" class="btn bg-teal btn-raised btn-sm">Описание тегов</button>
			
			<div id="content_help" style="display: none;">
				<table class="table table-normal table-hover">
					<thead>
						<tr>
							<td>Название тега</td>
							<td>Описание</td>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>{title:ru}</td>
							<td>Название на русском.</td>
						</tr>
						<tr>
							<td>[title:ru] ... [/title:ru]</td>
							<td>Выведет текст внутри тегов если есть Название на русском.</td>
						</tr>
						<tr>
							<td>[not-title:ru] ... [/not-title:ru]</td>
							<td>Выведет текст внутри тегов если нет Названия на русском.</td>
						</tr>
						<tr>
							<td>{title:original}</td>
							<td>Оригинальное название</td>
						</tr>
						<tr>
							<td>[title:original] ... [/title:original]</td>
							<td>Выведет текст внутри тегов если есть Оригинальное название.</td>
						</tr>
						<tr>
							<td>[not-title:original] ... [/not-title:original]</td>
							<td>Выведет текст внутри тегов если нет Оригинального названия.</td>
						</tr>
						<tr>
							<td>{id-kinopoisk}</td>
							<td>ID Кинопоиск.</td>
						</tr>
						<tr>
							<td>[id-kinopoisk] ... [/id-kinopoisk]</td>
							<td>Выведет текст внутри тегов если есть ID Кинопоиска.</td>
						</tr>
						<tr>
							<td>[not-id-kinopoisk] ... [/not-id-kinopoisk]</td>
							<td>Выведет текст внутри тегов если нет ID Кинопоиска.</td>
						</tr>
						<tr>
							<td>{id-imdb}</td>
							<td>ID IMDb.</td>
						</tr>
						<tr>
							<td>[id-imdb] ... [/id-imdb]</td>
							<td>Выведет текст внутри тегов если есть ID IMDb.</td>
						</tr>
						<tr>
							<td>[not-id-imdb] ... [/not-id-imdb]</td>
							<td>Выведет текст внутри тегов если нет ID IMDb.</td>
						</tr>
						<tr>
							<td>{id-mali}</td>
							<td>ID My Anime List.</td>
						</tr>
						<tr>
							<td>[id-mali] ... [/id-mali]</td>
							<td>Выведет текст внутри тегов если есть ID My Anime List.</td>
						</tr>
						<tr>
							<td>[not-id-mali] ... [/not-id-mali]</td>
							<td>Выведет текст внутри тегов если нет ID My Anime List.</td>
						</tr>
						<tr>
							<td>{id-mdl}</td>
							<td>ID My Drama List.</td>
						</tr>
						<tr>
							<td>[id-mdl] ... [/id-mdl]</td>
							<td>Выведет текст внутри тегов если есть ID My Drama List.</td>
						</tr>
						<tr>
							<td>[not-id-mdl] ... [/not-id-mdl]</td>
							<td>Выведет текст внутри тегов если нет ID My Drama List.</td>
						</tr>
						<tr>
							<td>{id-cdnvideohub}</td>
							<td>ID CDN Video Hub.</td>
						</tr>
						<tr>
							<td>[id-cdnvideohub] ... [/id-cdnvideohub]</td>
							<td>Выведет текст внутри тегов если есть ID CDN Video Hub.</td>
						</tr>
						<tr>
							<td>[not-id-cdnvideohub] ... [/not-id-cdnvideohub]</td>
							<td>Выведет текст внутри тегов если нет ID CDN Video Hub.</td>
						</tr>
						<tr>
							<td>{country}</td>
							<td>Страна.</td>
						</tr>
						<tr>
							<td>[country] ... [/country]</td>
							<td>Выведет текст внутри тегов если есть страна.</td>
						</tr>
						<tr>
							<td>[not-country] ... [/not-country]</td>
							<td>Выведет текст внутри тегов если нет страны.</td>
						</tr>
						<tr>
							<td>{genres}</td>
							<td>Жанры.</td>
						</tr>
						<tr>
							<td>[genres] ... [/genres]</td>
							<td>Выведет текст внутри тегов если есть жанры.</td>
						</tr>
						<tr>
							<td>[not-genres] ... [/not-genres]</td>
							<td>Выведет текст внутри тегов если нет жанров.</td>
						</tr>
						<tr>
							<td>{quality:all}</td>
							<td>Все доступные качества.</td>
						</tr>
						<tr>
							<td>[quality:all] ... [/quality:all]</td>
							<td>Выведет текст внутри тегов если есть данные о качестве.</td>
						</tr>
						<tr>
							<td>[not-quality:all] ... [/not-quality:all]</td>
							<td>Выведет текст внутри тегов если нет данных о качестве.</td>
						</tr>
						<tr>
							<td>{quality:best}</td>
							<td>Лучшее качество.</td>
						</tr>
						<tr>
							<td>[quality:best] ... [/quality:best]</td>
							<td>Выведет текст внутри тегов если есть данные о качестве.</td>
						</tr>
						<tr>
							<td>[not-quality:best] ... [/not-quality:best]</td>
							<td>Выведет текст внутри тегов если нет данных о качестве.</td>
						</tr>
						<tr>
							<td>{translation:all}</td>
							<td>Все доступные озвучки.</td>
						</tr>
						<tr>
							<td>[translation:all] ... [/translation:all]</td>
							<td>Выведет текст внутри тегов если есть данные о озвучках.</td>
						</tr>
						<tr>
							<td>[not-translation:all] ... [/not-translation:all]</td>
							<td>Выведет текст внутри тегов если нет данных о озвучках.</td>
						</tr>
						<tr>
							<td>{type}</td>
							<td>Тип релиза: Фильм, Сериал, Короткометражка, OVA, ONA, Специальный выпуск.</td>
						</tr>
						<tr>
							<td>[type] ... [/type]</td>
							<td>Выведет текст внутри тегов если есть данные о типе релиза.</td>
						</tr>
						<tr>
							<td>[not-type] ... [/not-type]</td>
							<td>Выведет текст внутри тегов если нет данных о типе релиза.</td>
						</tr>
						<tr>
							<td>{premiere=формат даты}</td>
							<td>Выводит дату премьеры в заданном в теге формате. Тем самым вы можете выводить не только дату целиком но и ее отдельные части. Формат даты премьеры задается согласно формату принятому в PHP. Например тег {date=d} выведет день месяца премьеры, а тег {date=F} выведет название месяца, а тег {date=d-m-Y H:i} выведет полную дату и время.</td>
						</tr>
						<tr>
							<td>{premiere}</td>
							<td>Дата премьеры.</td>
						</tr>
						<tr>
							<td>[premiere] ... [/premiere]</td>
							<td>Выведет текст внутри тегов если есть данные о дате премьеры.</td>
						</tr>
						<tr>
							<td>[not-premiere] ... [/not-premiere]</td>
							<td>Выведет текст внутри тегов если нет данных о дате премьеры.</td>
						</tr>
						<tr>
							<td>{description:ru}</td>
							<td>Описание на русском.</td>
						</tr>
						<tr>
							<td>[description:ru] ... [/description:ru]</td>
							<td>Выведет текст внутри тегов если есть данные об описании на русском.</td>
						</tr>
						<tr>
							<td>[not-description:ru] ... [/not-description:ru]</td>
							<td>Выведет текст внутри тегов если нет данных об описании на русском.</td>
						</tr>
						<tr>
							<td>{description:eng}</td>
							<td>Описание на английском.</td>
						</tr>
						<tr>
							<td>[description:eng] ... [/description:eng]</td>
							<td>Выведет текст внутри тегов если есть данные об описании на английском.</td>
						</tr>
						<tr>
							<td>[not-description:eng] ... [/not-description:eng]</td>
							<td>Выведет текст внутри тегов если нет данных об описании на английском.</td>
						</tr>
						<tr>
							<td>{description-short:ru}</td>
							<td>Краткое описание на русском.</td>
						</tr>
						<tr>
							<td>[description-short:ru] ... [/description-short:ru]</td>
							<td>Выведет текст внутри тегов если есть данные об кратком описании на русском.</td>
						</tr>
						<tr>
							<td>[not-description-short:ru] ... [/not-description-short:ru]</td>
							<td>Выведет текст внутри тегов если нет данных об кратком описании на русском.</td>
						</tr>
						<tr>
							<td>{description-short:eng}</td>
							<td>Краткое описание на английском.</td>
						</tr>
						<tr>
							<td>[description-short:eng] ... [/description-short:eng]</td>
							<td>Выведет текст внутри тегов если есть данные об кратком описании на английском.</td>
						</tr>
						<tr>
							<td>[not-description-short:eng] ... [/not-description-short:eng]</td>
							<td>Выведет текст внутри тегов если нет данных об кратком описании на английском.</td>
						</tr>
						<tr>
							<td>{season}</td>
							<td>Последний сезон сериала.</td>
						</tr>
						<tr>
							<td>{season:1}</td>
							<td>Последний сезон сериала с добавление +1.</td>
						</tr>
						<tr>
							<td>{season:2}</td>
							<td>Форматированный вывод сезона вида: 1-5.</td>
						</tr>
						<tr>
							<td>{season:3}</td>
							<td>Форматированный вывод сезона вида: 1,2,3,4,5.</td>
						</tr>
						<tr>
							<td>{season:4}</td>
							<td>Форматированный вывод сезона вида: 1-12,13,14. С добавлением +1 сезона к последнему.</td>
						</tr>
						<tr>
							<td>{season:5}</td>
							<td>Форматированный вывод сезона вида: 1-12,13.</td>
						</tr>
						<tr>
							<td>{season:6}</td>
							<td>Форматированный вывод сезона вида: 1-11,12,13.</td>
						</tr>
						<tr>
							<td>{season:7}</td>
							<td>Форматированный вывод сезона вида: 11,12,13.</td>
						</tr>
						<tr>
							<td>{season:8}</td>
							<td>Форматированный вывод сезона вида: 11,12,13,14. С добавлением +1 сезона к последнему.</td>
						</tr>
						<tr>
							<td>[season] ... [/season]</td>
							<td>Выведет текст внутри тегов если есть данные о сезоне.</td>
						</tr>
						<tr>
							<td>[not-season] ... [/not-season]</td>
							<td>Выведет текст внутри тегов если есть данных о сезоне.</td>
						</tr>
						<tr>
							<td>{episode}</td>
							<td>Последний эпизод.</td>
						</tr>
						<tr>
							<td>{episode:1}</td>
							<td>Эпизод сериала с добавление +1.</td>
						</tr>
						<tr>
							<td>{episode:2}</td>
							<td>Форматированный вывод эпизода вида: 1-5.</td>
						</tr>
						<tr>
							<td>{episode:3}</td>
							<td>Форматированный вывод эпизода вида: 1,2,3,4,5.</td>
						</tr>
						<tr>
							<td>{episode:4}</td>
							<td>Форматированный вывод эпизода вида: 1-12,13,14. С добавлением +1 эпизода к последнему.</td>
						</tr>
						<tr>
							<td>{episode:5}</td>
							<td>Форматированный вывод эпизода вида: 1-12,13.</td>
						</tr>
						<tr>
							<td>{episode:6}</td>
							<td>Форматированный вывод эпизода вида: 1-11,12,13.</td>
						</tr>
						<tr>
							<td>{episode:7}</td>
							<td>Форматированный вывод эпизода вида: 11,12,13.</td>
						</tr>
						<tr>
							<td>{episode:8}</td>
							<td>Форматированный вывод эпизода вида: 11,12,13,14. С добавлением +1 эпизода к последнему.</td>
						</tr>
						<tr>
							<td>[episode] ... [/episode]</td>
							<td>Выведет текст внутри тегов если есть данные о эпизоде.</td>
						</tr>
						<tr>
							<td>[not-episode] ... [/not-episode]</td>
							<td>Выведет текст внутри тегов если нет данных о эпизоде.</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
			<div class="table-responsive">
				<table class="table">
HTML;

$allXfieldsArray = Base::xfListLoad();
foreach ($allField as $key => $value) {
	if ($key == 'p.cat') {
		continue;
	}

	$textArea = false;
	if (!in_array($value, ['p.title', 'p.shortStory', 'p.fullStory'])) {
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

    if ($key === 'p.shortStory' || $key === 'p.fullStory' || $textArea) {
        Admin::rowInline(
            $value,
            '',
            Admin::textarea(['data[' . $key . ']', $CDNVideoHubModule[$modName]['config']['data'][$key] ?? ''])
        );
    } else {
		Admin::rowInline(
			$value,
			'',
			Admin::input(['data[' . $key . ']', 'text', $CDNVideoHubModule[$modName]['config']['data'][$key] ?? ''])
		);
	}
}
echo <<<HTML
				</table>
			</div>
		</div>
		<div id="block_5" style='display:none'>
			<div class="panel-body" style="font-size:15px; font-weight:bold;">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['cats']}</div>
			<div class="alert alert-info alert-styled-left alert-arrow-left alert-component text-left">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['catSection']}</div>
			<div class="table-responsive">
				<table class="table">
HTML;
foreach ($cat_info as $value) {
	Admin::row(
		Base::getCatData($value['id'], 'name', ' / '),
		'ID: ' . $value['id'] . ', Alt Name: ' . Base::getCatData($value['id'], 'alt_name', '/'),
		Admin::selectGenres('cat[' . $value['id'] . '][]', $genresArray, $CDNVideoHubModule[$modName]['config']['cat'][$value['id']] ?? [], [true, true, false, $CDNVideoHubModule[$modName]['lang']['admin']['settings']['categoriesSelect'], false])
	);
}
echo <<<HTML
				</table>
			</div>
		</div>
		<div id="block_6" style='display:none'>
			<div class="panel-body" style="font-size:15px; font-weight:bold;">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['films']}</div>
			<div class="table-responsive">
				<table class="table">
HTML;
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['quality'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['quality_descr'],
	Admin::select('quality', $xfieldArray, $CDNVideoHubModule[$modName]['config']['quality'], [true, false, false, false, false])
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['maxQuality'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['maxQuality_descr'],
	Admin::select('maxQuality', $qualityKeyCDNVideoHub, $CDNVideoHubModule[$modName]['config']['maxQuality'], [true, false, false, false, false])
);
echo <<<HTML
				</table>
			</div>
		</div>
		<div id="block_7" style='display:none'>
			<div class="panel-body" style="font-size:15px; font-weight:bold;">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['serials']}</div>
			<div class="table-responsive">
				<table class="table">
HTML;
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['seasonBySeason'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['seasonBySeason_descr'],
	Admin::checkBox('seasonBySeason', $CDNVideoHubModule[$modName]['config']['seasonBySeason'], 'seasonBySeason')
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['serialSeasonNumber'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['serialSeasonNumber_descr'],
	Admin::select('serialSeasonNumber', $xfieldArray, $CDNVideoHubModule[$modName]['config']['serialSeasonNumber'], [true, false, false, false, false]),
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['serialSeasonNumber_helper']
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['serialEpisodeNumber'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['serialEpisodeNumber_descr'],
	Admin::select('serialEpisodeNumber', $xfieldArray, $CDNVideoHubModule[$modName]['config']['serialEpisodeNumber'], [true, false, false, false, false]),
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['serialEpisodeNumber_helper']
);
echo <<<HTML
				</table>
			</div>
		</div>
		<div id="block_3" style='display:none'>
			<div class="panel-body" style="font-size:15px; font-weight:bold;">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['update']}</div>
			<div class="alert alert-info alert-styled-left alert-arrow-left alert-component text-left">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['updateSection']}</div>
			<div class="table-responsive">
				<table class="table">
HTML;
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['updateON'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['updateON_descr'],
	Admin::checkBox('updateON', $CDNVideoHubModule[$modName]['config']['updateON'], 'updateON')
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['onModeration'],
    $CDNVideoHubModule[$modName]['lang']['admin']['settings']['onModeration_descr'],
    Admin::checkBox('onModeration', $CDNVideoHubModule[$modName]['config']['onModeration'], 'onModeration')
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['work'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['work_descr'],
	Admin::select('work', [
		1 => $CDNVideoHubModule[$modName]['lang']['admin']['settings']['work_site'],
		2 => $CDNVideoHubModule[$modName]['lang']['admin']['settings']['work_ajax']
	], $CDNVideoHubModule[$modName]['config']['work'], [true, false, false, false, false])
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['updateDate'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['updateDate_descr'],
	Admin::checkBox('updateDate', $CDNVideoHubModule[$modName]['config']['updateDate'], 'updateDate')
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['onlyEmpty'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['onlyEmpty_descr'],
	Admin::checkBox('onlyEmpty', $CDNVideoHubModule[$modName]['config']['onlyEmpty'], 'onlyEmpty')
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['fieldUpdate'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['fieldUpdate_descr'],
	Admin::selectFill('fieldUpdate[]', $allField, $CDNVideoHubModule[$modName]['config']['fieldUpdate'], [true, true, false, false, true])
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['fieldUpdateOverride'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['fieldUpdateOverride_descr'],
	Admin::selectFill('fieldUpdateOverride[]', $allField, $CDNVideoHubModule[$modName]['config']['fieldUpdateOverride'], [true, true, false, false, true])
);
echo <<<HTML
				</table>
			</div>
		</div>
		<div id="block_4" style='display:none'>
			<div class="panel-body" style="font-size:15px; font-weight:bold;">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['auto']}</div>
			<div class="alert alert-info alert-styled-left alert-arrow-left alert-component text-left">{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['autoSection']}</div>
			<div class="table-responsive">
				<table class="table">
HTML;
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['onlyEmptyInNews'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['onlyEmptyInNews_descr'],
	Admin::checkBox('onlyEmptyInNews', $CDNVideoHubModule[$modName]['config']['onlyEmptyInNews'], 'onlyEmptyInNews')
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['fieldUpdateInNews'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['fieldUpdateInNews_descr'],
	Admin::selectFill('fieldUpdateInNews[]', $allField, $CDNVideoHubModule[$modName]['config']['fieldUpdateInNews'], [true, true, false, false, true])
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['fieldUpdateOverrideInNews'],
	$CDNVideoHubModule[$modName]['lang']['admin']['settings']['fieldUpdateOverrideInNews_descr'],
	Admin::selectFill('fieldUpdateOverrideInNews[]', $allField, $CDNVideoHubModule[$modName]['config']['fieldUpdateOverrideInNews'], [true, true, false, false, true])
);

echo <<<HTML
				</table>
			</div>
		</div>
		
		<div class="panel-footer">
			<button type="submit" class="btn bg-teal btn-sm btn-raised position-left legitRipple"><i class="fa fa-floppy-o position-left"></i>{$CDNVideoHubModule[$modName]['lang']['admin']['other']['save']}</button>
		</div>
	</div>
</form>
HTML;


$jsAdminScript[] = <<<HTML

$(function() {
    $('body').on('submit', 'form', function(e) {
        coreAdmin.ajaxSendOld($('form').serialize(), 'saveConfig', false);
		return false;
    });
});

function ChangeOption(obj, selectedOption) {
    $('#navbar-filter li').removeClass('active');
    $(obj).parent().addClass('active');
    $('[id*=block_]').hide();
    $('#' + selectedOption).show();

    return false;
}

function ShowHide(d) {
	if ($(d).text() === 'Описание тегов') {
		$('#content_help').show();
		$(d).text('Скрыть описание тегов');
		$(d).css('border-color', '#e53935');
	} else {
		$('#content_help').hide();
		$(d).text('Описание тегов');
		$(d).css('border-color', '#009688');
	}
}

HTML;

?>