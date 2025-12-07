<?php
/**
 * CDN Video Hub — Licensed Sync (admin)
 * @link https://lazydev.pro/
 * @author LazyDev
 */

if (!defined('DATALIFEENGINE')) {
    header("HTTP/1.1 403 Forbidden");
    header('Location: ../../../');
    die("Hacking attempt!");
}

include_once __DIR__ . '/../loader.php';

use LazyDev\CDNVideoHub\Admin;
use LazyDev\CDNVideoHub\Base;
use LazyDev\CDNVideoHub\Cache;
use LazyDev\CDNVideoHub\License;

$action2 = isset($_REQUEST['action2']) ? $_REQUEST['action2'] : '';
$langAdmin = $CDNVideoHubModule[Base::$modName]['lang']['admin'] ?? [];
$langLicense = $langAdmin['license'] ?? [];
$textHide = $langLicense['hide_player'] ?? 'Скрыть плеер';
$textShow = $langLicense['show_player'] ?? 'Показать плеер';
$textHidden = $langLicense['player_hidden'] ?? 'Плеер скрыт';
$textVisible = $langLicense['player_visible'] ?? 'Плеер активен';
$textHideAll = $langLicense['hide_all'] ?? 'Скрыть плеер у всех';
$textHideSuccess = $langLicense['hide_success'] ?? 'Плеер скрыт для новости.';
$textShowSuccess = $langLicense['show_success'] ?? 'Плеер включен для новости.';
$textHideAllSuccess = $langLicense['hide_all_success'] ?? 'Плеер скрыт во всех новостях со совпадениями.';
$textPlayerColumn = $langLicense['player_column'] ?? 'Плеер';

License::ensureHidePlayerColumn($db);

if ($action2 == 'approve') {
    $id = (int)($_REQUEST['id'] ?? -1);
    if ($id > 0) {
        $db->query("UPDATE " . PREFIX . "_post SET approve=0 WHERE id=" . $id);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'ok', 'approve' => $id], JSON_UNESCAPED_UNICODE);
        die();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'bad', 'approve' => $id], JSON_UNESCAPED_UNICODE);
    die();
}

if ($action2 === 'hideplayer') {
    $id = (int)($_REQUEST['id'] ?? -1);
    $hide = (int)($_REQUEST['hide'] ?? 1) === 1 ? 1 : 0;
    $table = PREFIX . "_cdnvideohub_license";
    $time = time();

    if ($id > 0) {
        $exists = $db->super_query("SELECT id FROM {$table} WHERE news_id=" . $id . " LIMIT 1");
        if ($exists && isset($exists['id'])) {
            $db->query("UPDATE {$table} SET hide_player={$hide}, updated_at={$time} WHERE id=" . (int)$exists['id']);
        } else {
            $db->query("INSERT INTO {$table} (news_id, aggregator, aggregator_external_id, title, hide_player, created_at, updated_at) VALUES (" .
                $id . ", '', '', '', {$hide}, {$time}, {$time})");
        }

        Cache::setFile((string)$hide, $id, '/player_hide');

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'ok', 'hide' => $hide, 'id' => $id], JSON_UNESCAPED_UNICODE);
        die();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'bad', 'hide' => $id], JSON_UNESCAPED_UNICODE);
    die();
}

if ($action2 == 'closeall') {
    $db->query("UPDATE " . PREFIX . "_post p LEFT JOIN " . PREFIX . "_cdnvideohub_license e ON(p.id=e.news_id) SET approve=0 WHERE p.id=e.news_id");
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'approve' => 'all'], JSON_UNESCAPED_UNICODE);
    die();
}

if ($action2 === 'hideall') {
    $hideCacheDir = ENGINE_DIR . '/' . Base::$modName . '/cache/player_hide';
    if (!is_dir($hideCacheDir)) {
        @mkdir($hideCacheDir, 0755, true);
    }
    $db->query("UPDATE " . PREFIX . "_cdnvideohub_license SET hide_player=1");
    Cache::clear('/player_hide');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'hide' => 'all'], JSON_UNESCAPED_UNICODE);
    die();
}

if ($action2 === 'start') {
    $job = 'licensed_' . time();
    $state = [
        'page' => 1,
        'processed' => 0,
        'inserted' => 0,
        'matched' => 0,
        'logs' => [],
        'finished' => false,
    ];
    License::saveJob($job, $state);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'ok', 'job' => $job], JSON_UNESCAPED_UNICODE);
    die();
}

if ($action2 === 'step') {
    $job = (string)($_REQUEST['job'] ?? '');

    if (!$job) {
        http_response_code(400);
        die(json_encode(['status'=>'error','message'=>'No job']));
    }

    $state = License::loadJob($job);

    $page = (int)$state['page'];
    try {
        $data = License::fetchPage($apiLink, $page);
    } catch (\Throwable $e) {
        License::log($state, 'Ошибка API на странице ' . $page . ': ' . $e->getMessage());
        License::saveJob($job, $state);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'ok',
            'job' => $job,
            'page' => $page,
            'processed' => (int)$state['processed'],
            'inserted' => (int)$state['inserted'],
            'matched' => (int)$state['matched'],
            'logs' => $state['logs'],
            'empty' => false,
            'error' => true,
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
        die();
    }

    $count = is_array($data) ? count($data) : 0;
    if ($count === 0) {
        $state['finished'] = true;
        License::log($state, 'Синхронизация завершена.');
        License::saveJob($job, $state);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'ok', 'job' => $job, 'finished' => true,
            'processed' => (int)$state['processed'],
            'inserted'  => (int)$state['inserted'],
            'matched'   => (int)$state['matched'],
            'logs' => $state['logs'],
        ], JSON_UNESCAPED_UNICODE);
        die();
    }

    $res = License::matchAndStore($db, $data);
    $state['processed'] += $count;
    $state['matched']   += (int)$res['matched'];
    $state['inserted']  += (int)$res['inserted'];

    License::log($state, 'Стр. ' . $page . ': элементов=' . $count . ", совпадений=" . (int)$res['matched'] . ", вставок=" . (int)$res['inserted']);

    $state['page'] = $page + 1;
    License::saveJob($job, $state);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'ok',
        'job' => $job,
        'page' => $page,
        'nextPage' => $state['page'],
        'processed' => (int)$state['processed'],
        'inserted' => (int)$state['inserted'],
        'matched' => (int)$state['matched'],
        'logs' => $state['logs'],
        'finished' => false,
    ], JSON_UNESCAPED_UNICODE);

    die();
}

$startFrom = 0;
if (isset($_GET['start_from']) && $_GET['start_from']) {
    $startFrom = intval($_GET['start_from']);
}

$dataPerPage = 50;
$i = $startFrom;

$admin_url = $PHP_SELF . '?mod=cdnvideohub&action=license';
$q = $db->query("SELECT m.id, m.news_id, m.title as t2, m.aggregator, m.aggregator_external_id, m.hide_player, p.date, p.title, p.approve, p.alt_name, p.category FROM " . PREFIX . "_cdnvideohub_license m LEFT JOIN " . PREFIX . "_post p ON (p.id=m.news_id) ORDER BY m.id ASC LIMIT {$startFrom}, {$dataPerPage}");

echo <<<HTML
<style>
    .cvh-flex { display:flex; gap:12px; align-items:center; }
    .cvh-progress { height: 10px; background: #e5e7eb; border-radius: 6px; overflow: hidden; }
    .cvh-progress__bar { height: 10px; width:0%; background:#22c55e; transition: width .2s; }
    .cvh-logs { height: 220px; overflow: auto; background:#0b1020; color:#d1d5db; padding:10px; border-radius:8px; font: 12px/1.45 Consolas,Monaco,monospace; }
    
    .badge { display:inline-block; padding:2px 6px; border-radius:6px; background:#eef2ff; color:#3730a3; font-size:12px; }
    .btn2 { display:inline-block; padding:8px 14px; border-radius:8px; border:0; background:#3b82f6; color:#fff; cursor:pointer; }
    .btn2:disabled { opacity:.6; cursor:not-allowed; }
    .stats { color:#374151; font-size:13px; }
</style>

<div class="box">
    <div class="panel panel-flat">
        <div class="panel-body" style="font-size:20px; font-weight:bold;border-bottom: 1px solid #ddd;">Синхронизация лицензированного контента</div>
        <div class="panel-body">

            <div class="cvh-sync">
                <div class="cvh-flex" style="margin-bottom:10px;">
                    <button id="cvhStart" class="btn2">Синхронизировать</button>
                    <div class="stats" id="cvhStats">Обработано: 0 • Совпадений: 0 • Вставок: 0</div>
                </div>
                <div class="cvh-progress" title="Прогресс">
                    <div class="cvh-progress__bar" id="cvhBar"></div>
                </div>
                <div id="cvhLogs" class="cvh-logs" style="margin-top:10px;" aria-live="polite"></div>
            </div>
        </div>
    </div>
    <div class="panel panel-flat">
        <div class="panel panel-default">
            <div class="panel-heading">
                Лицензированный контент на сайте
                <div style="margin-top: -1.028rem;"  class="heading-elements">
                    <button class="btn bg-success btn-sm btn-raised" type="button" data-closeall="1">Снять все новости с публикации</button>
                    <button class="btn bg-slate-600 btn-sm btn-raised" type="button" data-hideall="1">{$textHideAll}</button>
                </div>
                    </div>
        </div>
        <div class="table-responsive">
            <table class="table table-xs table-hover">
                <thead>
                    <tr>
                        <th>ID новости</th>
                        <th>Название</th>
                        <th>Название в CDNVideoHub</th>
                        <th>Агрегатор</th>
                        <th>ID</th>
                        <th>Статус</th>
                        <th>{$textPlayerColumn}</th>
                        <th class="text-center"><i class="fa fa-cog"></i></th>
                    </tr>
                </thead>
                <tbody>
HTML;
if (!$q->num_rows) {
echo <<<HTML
    <tr><td colspan="8">Пока нет данных.</td></tr>
HTML;
} else {
    $countNews = $db->super_query("SELECT COUNT(*) as count FROM " . PREFIX . "_cdnvideohub_license m LEFT JOIN " . PREFIX . "_post p ON (p.id=m.news_id)")['count'];

    while ($row = $db->get_row($q)) {
        $i++;
        if ($config['allow_alt_url']) {
            if ($config['seo_type'] == 1 || $config['seo_type'] == 2) {
                if ($row['category'] && $config['seo_type'] == 2) {
                    $cats_url = get_url($row['category']);
                    if ($cats_url) {
                        $full_link = $config['http_home_url'] . $cats_url . '/' . $row['news_id'] . '-' . $row['alt_name'] . '.html';
                    } else {
                        $full_link = $config['http_home_url'] . $row['news_id'] . '-' . $row['alt_name'] . '.html';
                    }
                } else {
                    $full_link = $config['http_home_url'] . $row['news_id'] . '-' . $row['alt_name'] . '.html';
                }
            } else {
                $full_link = $config['http_home_url'] . date('Y/m/d/', strtotime($row['date'])) . $row['alt_name'] . '.html';
            }
        } else {
            $full_link = $config['http_home_url'] . 'index.php?newsid=' . $row['news_id'];
        }
        $row['title'] = htmlspecialchars(stripslashes($row['title']));
        $row['t2'] = htmlspecialchars(stripslashes($row['t2']));

        $isApproved = (int)$row['approve'] == 1;
        $approveData = [
            'html'  => $isApproved
                ? "<span style='color:red;font-weight:bold;'>Опубликовано</span>"
                : "<span style='color:green;font-weight:bold;'>Снято с публикации</span>",
            'btn'   => $isApproved ? 'bg-danger-600' : 'bg-success-600',
            'icon'  => $isApproved ? 'fa-close' : 'fa-check',
            'text'  => $isApproved ? 'Снять с публикации' : 'Снято с публикации',
            'id'    => $isApproved ? (int)$row['news_id'] : -1,
        ];
        $isHidden = (int)$row['hide_player'] === 1;
        $hideData = [
            'label' => $isHidden
                ? "<span class='badge' style='background:#fee2e2;color:#991b1b;'>{$textHidden}</span>"
                : "<span class='badge' style='background:#ecfdf3;color:#166534;'>{$textVisible}</span>",
            'btn'   => $isHidden ? 'bg-success-600' : 'bg-warning-600',
            'icon'  => $isHidden ? 'fa-eye' : 'fa-eye-slash',
            'text'  => $isHidden ? $textShow : $textHide,
            'hide'  => $isHidden ? 0 : 1,
        ];
echo <<<HTML
<tr>
        <td><a href="{$PHP_SELF}?mod=editnews&action=editnews&id={$row['news_id']}" target="_blank">{$row['news_id']}</a></td>
        <td><a href="{$full_link}" target="_blank">{$row['title']}</a></td>
        <td>{$row['t2']}</td>
        <td><span class="badge">{$row['aggregator']}</span></td>
        <td>{$row['aggregator_external_id']}</td>
        <td>{$approveData['html']}</td>
        <td>{$hideData['label']}</td>
        <td class="text-center" style="display:flex; gap:6px; justify-content:center;">
            <button data-approve="{$approveData['id']}" class="btn {$approveData['btn']} btn-sm btn-raised legitRipple"><i class="fa {$approveData['icon']}"></i> {$approveData['text']}</button>
            <button data-hideplayer="{$row['news_id']}" data-hide="{$hideData['hide']}" class="btn {$hideData['btn']} btn-sm btn-raised legitRipple"><i class="fa {$hideData['icon']}"></i> {$hideData['text']}</button>
        </td>
    </tr>
HTML;
    }
    $db->free($q);
}
echo <<<HTML
                </tbody>
            </table>
        </div>
    </div>
</div>
HTML;
$npp_nav = '';
if ($countNews > $dataPerPage) {
    if ($startFrom > 0) {
        $previous = $startFrom - $dataPerPage;
        $npp_nav .= "<li class=\"page-item\"><a class=\"page-link\" href=\"{$PHP_SELF}?mod=cdnvideohub&action=license&start_from={$previous}\"> &lt;&lt; </a></li>";
    }

    $enpages_count = @ceil($countNews / $dataPerPage);
    $enpages_start_from = 0;
    $enpages = '';

    if ($enpages_count <= 10) {
        for ($j = 1; $j <= $enpages_count; $j++) {
            if ($enpages_start_from != $startFrom) {
                $enpages .= "<li class=\"page-item\"><a class=\"page-link\" href=\"{$PHP_SELF}?mod=cdnvideohub&action=license&start_from={$enpages_start_from}\">$j</a></li>";
            } else {
                $enpages .= "<li class=\"page-item active\"><span class=\"page-link\">$j</span></li>";
            }
            $enpages_start_from += $dataPerPage;
        }
        $npp_nav .= $enpages;
    } else {
        $start = 1;
        $end = 10;
        if ($startFrom > 0) {
            if (($startFrom / $dataPerPage) > 4) {
                $start = @ceil($startFrom / $dataPerPage) - 3;
                $end = $start + 9;
                if ($end > $enpages_count) {
                    $start = $enpages_count - 10;
                    $end = $enpages_count - 1;
                }
                $enpages_start_from = ($start - 1) * $dataPerPage;
            }
        }

        if ($start > 2) {
            $enpages .= "<li><a href=\"#\">1</a></li> <li><span>...</span></li>";
        }

        for ($j = $start; $j <= $end; $j++) {
            if ($enpages_start_from != $startFrom) {
                $enpages .= "<li class=\"page-item\"><a class=\"page-link\" href=\"{$PHP_SELF}?mod=cdnvideohub&action=license&start_from={$enpages_start_from}\">$j</a></li>";
            } else {
                $enpages .= "<li class=\"page-item active\"><span class=\"page-link\">$j</span></li>";
            }
            $enpages_start_from += $dataPerPage;
        }
        $enpages_start_from = ($enpages_count - 1) * $dataPerPage;
        $enpages .= "<li><span>...</span></li><li><a href=\"{$PHP_SELF}?mod=cdnvideohub&action=license&start_from={$enpages_start_from}\">$enpages_count</a></li>";
        $npp_nav .= $enpages;
    }

    if ($countNews > $i) {
        $npp_nav .= "<li class=\"page-item\"><a class=\"page-link\" href=\"$PHP_SELF?mod=cdnvideohub&action=license&start_from={$i}\"> &gt;&gt; </a></li>";
    }

    echo "<nav aria-label=\"Page navigation\"><ul class=\"pagination justify-content-center\">" . $npp_nav . "</ul></nav>";
}

echo <<<HTML
<script>
    (function() {
        var \$start = document.getElementById('cvhStart');
        var \$bar   = document.getElementById('cvhBar');
        var \$logs  = document.getElementById('cvhLogs');
        var \$stats = document.getElementById('cvhStats');

        var job = null, page = 0, processed = 0, matched = 0, inserted = 0;

        function setProgress(p) {
            \$bar.style.width = Math.max(0, Math.min(100, p)) + '%';
        }
        
        function setStats() {
            \$stats.textContent = 'Обработано: ' + processed + ' • Совпадений: ' + matched + ' • Вставок: ' + inserted;
        }
        
        function pushLogs(lines) {
            if (!lines) {
                return;
            }
            
            \$logs.innerHTML = lines.join('<br>'); 
            \$logs.scrollTop = \$logs.scrollHeight;
        }

        function ajax(url, data) {
            return fetch(url, { method: 'POST', headers:{ 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' }, body: new URLSearchParams(data) })
                .then(function(r){ return r.json(); });
        }

        function step() {
            if (!job) {
                return;
            }

            ajax('{$admin_url}', { action2:'step', job: job }).then(function(res) {
                processed = res.processed || processed;
                matched   = res.matched   || matched;
                inserted  = res.inserted  || inserted;
                setStats();
                
                if (res.logs) {
                    pushLogs(res.logs);
                }

                if (res.finished) {
                    setProgress(100);
                    \$start.disabled = false;
                    return;
                } else {
                    page = res.nextPage || (page+1);
                    var pseudo = Math.min(95, Math.round((page) * 3));
                    setProgress(pseudo);
                    setTimeout(step, 150);
                }
            }).catch(function(e) {
                pushLogs([e && e.message ? e.message : String(e)]);
                \$start.disabled = false;
            });
        }

        \$start.addEventListener('click', function(){
            \$start.disabled = true;
            setProgress(0);
            \$logs.textContent = '';
            processed = matched = inserted = 0;
            setStats();
            
            ajax('{$admin_url}', { action2:'start' }).then(function(res) {
                job = res.job;
                page = 1;
                step();
            }).catch(function(e) {
                \$start.disabled = false;
                pushLogs([String(e)]);
            });
        });
        
        $('body').on('click', '[data-approve]', function () {
           let id = $(this).data('approve');
           if (id == -1) {
               Growl.info({
                    title: 'Информация',
                    text: 'Новость уже снята с публикации.'
                });
               return false;
           }
           
           ShowLoading('');
            $.post('{$admin_url}', {action2: 'approve', id: id, dle_hash: dle_login_hash}, function(data) {
                if (data.status == 'bad') {
                    Growl.error({
                        title: 'Информация',
                        text: 'Произошла ошибка.'
                    });
                } else {
                    Growl.info({
                        title: 'Информация',
                        text: 'Новость успешно снята с публикации.'
                    });
                }
            });
            HideLoading('');
       });
        
        $('body').on('click', '[data-closeall]', function () {
           ShowLoading('');
            $.post('{$admin_url}', {action2: 'closeall', dle_hash: dle_login_hash}, function(data) {
                
                Growl.info({
                    title: 'Информация',
                    text: 'Все новости успешно сняты с публикации.'
                });
            });
            HideLoading('');
       });

        $('body').on('click', '[data-hideplayer]', function () {
            let id = $(this).data('hideplayer');
            let hide = $(this).data('hide');

            ShowLoading('');
            $.post('{$admin_url}', {action2: 'hideplayer', id: id, hide: hide, dle_hash: dle_login_hash}, function(data) {
                if (data.status == 'bad') {
                    Growl.error({
                        title: 'Информация',
                        text: 'Произошла ошибка.'
                    });
                } else {
                    Growl.info({
                        title: 'Информация',
                        text: hide ? '{$textHideSuccess}' : '{$textShowSuccess}'
                    });
                    setTimeout(function(){ location.reload(); }, 300);
                }
            });
            HideLoading('');
        });

        $('body').on('click', '[data-hideall]', function () {
            ShowLoading('');
            $.post('{$admin_url}', {action2: 'hideall', dle_hash: dle_login_hash}, function(data) {
                Growl.info({
                    title: 'Информация',
                    text: '{$textHideAllSuccess}'
                });
                setTimeout(function(){ location.reload(); }, 300);
            });
            HideLoading('');
        });

    })();
</script>
HTML;

?>