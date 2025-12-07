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

$jsAdminScript = implode($jsAdminScript);
$additionalJsAdminScript = implode($additionalJsAdminScript);
$date = date('Y', time());
$deselect = $action == 'grab' ? 'false' : 'true';
echo <<<HTML
                        <div class="panel" style="margin-top: 20px;">
                            <div class="panel-content">
                                <div class="panel-body">
                                    &copy; <a href="https://lazydev.pro/" target="_blank">LazyDev</a> {$date} All rights reserved. {$CDNVideoHubModule[$modName]['lang']['name']} {$modVer}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="{$config['http_home_url']}engine/{$modName}/admin/template/assets/core.js"></script>
        <script>let coreAdmin = new Admin('{$modName}');</script>
        <script>
        	{$jsAdminScript}
		</script>
        <script>
        	let setDark = function() {
				$.post('engine/' + coreAdmin.mod + '/admin/ajax/ajax.php', {action: 'setDark', dle_hash: dle_login_hash}, function(info) {
					if (info) {
						window.location.reload();
					}
				});
				
				return false;
			}
            
            let clearCache = function(path) {
				DLEconfirm(langCache['clearCache'+path+'Info'], "{$CDNVideoHubModule[$modName]['lang']['admin']['other']['try']}", function() {
					coreAdmin.ajaxSendOld(path, 'clearCache', false);
				});
				return false;
			}
            
			let selectTag = tail.select(".selectTag", {
				search: true,
				multiSelectAll: true,
				classNames: "default white",
				multiContainer: true,
				multiShowCount: false,
				locale: '{$CDNVideoHubModule[$modName]['config']['lang']}',
				deselect: {$deselect}
			});
        </script>
        {$additionalJsAdminScript}
    </body>
</html>
HTML;

?>