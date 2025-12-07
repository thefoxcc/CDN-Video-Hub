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

echo <<<HTML
<form id="fill">
	<div class="panel panel-flat">
		<div class="panel-body" style="font-size:20px; font-weight:bold;border-bottom: 1px solid #ddd;">{$CDNVideoHubModule[$modName]['lang']['admin']['grab']['head']}</div>
		<div class="panel-body">{$CDNVideoHubModule[$modName]['lang']['admin']['grab']['desc']}</div>
		<hr>
		<div class="table-responsive">
			<table class="table">
HTML;
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['type']['title'],
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['type']['descr'],
    Admin::select('type', [
        0 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['type']['all'],
        1 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['type']['film'],
        2 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['type']['serial']
    ], [0], [true, false, false, false, true])
);
//Admin::row(
//    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['title'],
//    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['descr'],
//    Admin::select('typeContent', [
//        0 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['all'],
//        1 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['anime'],
//        4 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['dorama'],
//        5 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['west'],
//        6 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['asian'],
//        7 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['india'],
//        8 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['latin'],
//        2 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['turk'],
//        3 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['chinaAnime'],
//        10 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['child'],
//        11 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['study'],
//        12 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['sng'],
//        13 => $CDNVideoHubModule[$modName]['lang']['admin']['grab']['typeContent']['music']
//    ], [0], [true, false, false, false, true])
//);
//$genresIdCDNVideoHubCopy = $genresIdCDNVideoHub;
//unset($genresIdCDNVideoHubCopy[1], $genresIdCDNVideoHubCopy[2], $genresIdCDNVideoHubCopy[3], $genresIdCDNVideoHubCopy[4], $genresIdCDNVideoHubCopy[5], $genresIdCDNVideoHubCopy[6], $genresIdCDNVideoHubCopy[7], $genresIdCDNVideoHubCopy[8], $genresIdCDNVideoHubCopy[10], $genresIdCDNVideoHubCopy[11], $genresIdCDNVideoHubCopy[12], $genresIdCDNVideoHubCopy[13]);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['genres']['title'],
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['genres']['descr'],
    Admin::select('genres', $genresIdCDNVideoHub, [], [true, false, false, false, true])
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['fieldGrab'],
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['fieldGrab_descr'],
    Admin::selectFill('fieldGrab[]', $allField, [], [true, true, false, false, true])
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['fromPage'],
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['fromPage_descr'],
    Admin::input(['fromPages', 'number', 1, false, false, 1, 9999])
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['toPage'],
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['toPage_descr'],
    Admin::input(['toPages', 'number', 10, false, false, 1, 9999])
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['licensed'],
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['licensed_descr'],
    Admin::checkBox('licensed', false, 'licensed')
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['approve'],
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['approve_descr'],
	Admin::checkBox('approve', false, 'approve')
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['allow_main'],
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['allow_main_descr'],
    Admin::checkBox('allow_main', false, 'allow_main')
);
Admin::row(
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['allow_comm'],
    $CDNVideoHubModule[$modName]['lang']['admin']['grab']['allow_comm_descr'],
    Admin::checkBox('allow_comm', false, 'allow_comm')
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
			Количество страниц: <span id="totalpagescount">0</span><br>
			Пройдено страниц: <span id="steppagescount">0</span><br>
			Количество новостей: <span id="newscount">0</span><br>
			Добавлено новостей: <span id="addnewscount">0</span><br>
			Пропущено новостей: <span id="skipnewscount">0</span>
		</div>
		<div class="panel-footer">
			<button id="saveFieldsButt" type="submit" class="btn bg-teal btn-sm"><i class="fa fa-floppy-o position-left"></i>{$CDNVideoHubModule[$modName]['lang']['admin']['fill']['start']}</button>
		</div>
		<input type="hidden" id="setOk" name="setOk" value="0">
	</div>
</form>
HTML;

$jsAdminScript[] = <<<HTML
let fromPages = 1;
let toPages = 1;
let totalPages = 1;

$(function() {
    $('body').on('click', '#saveFieldsButt', function(e) {
		e.preventDefault();
		fromPages = $('[name=fromPages]').val();
		toPages = $('[name=toPages]').val();
		totalPages = toPages - fromPages; 
		totalPages = totalPages == 0 ? 1 : totalPages + 1;
		$('#totalpagescount').text(totalPages);
		let data = $('form#fieldSettings').serialize();
        setNews(fromPages, 0, 0, 0);
		
		return false;
    });
});

function setNews(fromPages, addNews, notAdded, pageStep) {
	let data = $('form#fill').serialize();
	$.post('engine/' + coreAdmin.mod + '/admin/ajax/ajax.php', {fromPages: fromPages, pageStep: pageStep, added: addNews, notAdded: notAdded, action: 'grabNews', dle_hash: dle_login_hash, data: data}, function(data) {
		if (data) {
			if (data.status == 'ok') {
                $('#steppagescount').text(data.page);
                $('#addnewscount').text(data.added);
                $('#skipnewscount').text(data.notAdded);
                $('#newscount').text(data.added+data.notAdded);
                
				let proc = data.page == 0 ? 100 : Math.round((100 * data.page) / totalPages);
				if (proc > 100) {
					proc = 100;
				}
				
				$('#progressbar').css('width', proc + '%');

				if (data.page == 0 || data.page >= totalPages) {
					Growl.info({
						title: 'Успех',
						text: '{$CDNVideoHubModule[$modName]['lang']['admin']['fill']['newsComplete']}'
					});
					$('#setOk').val('0');
					$('#saveFieldsButt').attr('disabled', false);
				} else {
					setTimeout(setNews, 300, data.nextPage, data.added, data.notAdded, data.page);
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