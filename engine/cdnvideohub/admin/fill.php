<?php
/**
 * Админ панель. Страница массовой простановки данных
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
if ($config['version_id'] >= 19) {
    foreach ($allXfield['fields'] as $value) {
        $allField[$value['name']] = $xfieldArray[$value['name']] = $value['description'];
    }
} else {
    foreach ($allXfield as $value) {
        $allField[$value[0]] = $xfieldArray[$value[0]] = $value[1];
    }
}

$getCountNews = $db->super_query("SELECT COUNT(*) as count FROM " . PREFIX . "_post")['count'];

echo <<<HTML
<form id="fill">
	<div class="panel panel-flat">
		<div class="panel-body" style="font-size:20px; font-weight:bold;border-bottom: 1px solid #ddd;">{$CDNVideoHubModule[$modName]['lang']['admin']['fill']['headNews']}</div>
		<div class="panel-body">{$CDNVideoHubModule[$modName]['lang']['admin']['fill']['newsDescr']}</div>
		<hr>
		<div class="table-responsive">
			<table class="table">
HTML;
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['fill']['updateDate'],
	$CDNVideoHubModule[$modName]['lang']['admin']['fill']['updateDate_descr'],
	Admin::checkBox('updateDate', false, 'updateDate')
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['fill']['onlyEmpty'],
	$CDNVideoHubModule[$modName]['lang']['admin']['fill']['onlyEmpty_descr'],
	Admin::checkBox('onlyEmpty', false, 'onlyEmpty')
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['fill']['fieldUpdate'],
	$CDNVideoHubModule[$modName]['lang']['admin']['fill']['fieldUpdate_descr'],
	Admin::selectFill('fieldUpdate[]', $allField, [], [true, true, false, false, true])
);
Admin::row(
	$CDNVideoHubModule[$modName]['lang']['admin']['fill']['fieldUpdateOverride'],
	$CDNVideoHubModule[$modName]['lang']['admin']['fill']['fieldUpdateOverride_descr'],
	Admin::selectFill('fieldUpdateOverride[]', $allField, [], [true, true, false, false, true])
);
echo <<<HTML
			</table>
		</div>
		<div class="panel-body">
			<div class="progress">
				<div id="progressbar" class="progress-bar progress-blue" style="width:0%;"><span></span></div>
			</div>
		</div>
		<div class="panel-body">
			{$CDNVideoHubModule[$modName]['lang']['admin']['fill']['newsCount']} {$getCountNews}<br>
			{$CDNVideoHubModule[$modName]['lang']['admin']['fill']['nowCheck']} <span class="text-danger"><span id="newscount">0</span></span><br>
			{$CDNVideoHubModule[$modName]['lang']['admin']['fill']['resultCheck']} <span id="progress"></span>
		</div>
		<div class="panel-footer">
			<button id="saveFieldsButt" type="submit" class="btn bg-teal btn-sm"><i class="fa fa-floppy-o position-left"></i>{$CDNVideoHubModule[$modName]['lang']['admin']['fill']['start']}</button>
		</div>
		<input type="hidden" id="setOk" name="setOk" value="0">
	</div>
</form>
HTML;

$jsAdminScript[] = <<<HTML
let totalNews = {$getCountNews};

$(function() {
    $('body').on('click', '#saveFieldsButt', function(e) {
		e.preventDefault();
		let data = $('form#fieldSettings').serialize();
        setNewsData();
		return false;
    });
});

function setNewsData() {
	let startCount = $('#setOk').val();
	setNews(startCount);
	
	return false;
}

function setNews(startCount) {
	let data = $('form#fill').serialize();
	$.post('engine/' + coreAdmin.mod + '/admin/ajax/ajax.php', {startCount: startCount, action: 'setNews', dle_hash: dle_login_hash, data: data}, function(data) {
		if (data) {
			if (data.status == 'ok') {
				$('#newscount').html(data.newsData);
				$('#setOk').val(data.newsData);
				let proc = data.newsData == 0 ? 100 : Math.round((100 * data.newsData) / totalNews);
				if (proc > 100) {
					proc = 100;
				}
				
				$('#progressbar').css('width', proc + '%');

				if (data.newsData == 0 || data.newsData >= totalNews) {
					Growl.info({
						title: 'Успех',
						text: '{$CDNVideoHubModule[$modName]['lang']['admin']['fill']['newsComplete']}'
					});
					$('#setOk').val('0');
					$('#saveFieldsButt').attr('disabled', false);
				} else { 
					setTimeout("setNews(" + data.newsData + ")", 300);
				}
			}
			
			if (data.error !== undefined && data.error != '') {
				Growl.error({
					title: 'Ошибка',
					text: '{$CDNVideoHubModule[$modName]['lang']['admin']['fill']['errorСheck']}'
				});
			}
		}
	}, 'json').fail(function() {
		Growl.error({
			title: 'Ошибка',
			text: '{$CDNVideoHubModule[$modName]['lang']['admin']['fill']['errorСheck']}'
		});
	
		$('#saveFieldsButt').attr('disabled', false);
	});

	return false;
}

HTML;

?>