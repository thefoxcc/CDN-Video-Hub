<?php
/**
 * Модуль CDN Video Hub
 *
 * Файл отвечает за данные вывода плеера
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

$playerCDNInclude = <<<HTML
<video-player id="cdnvideohubvideoplayer" data-publisher-id="{partner}" is-show-banner="{banner}" is-show-voice-only="{voice}" data-title-id="{id}" data-aggregator="{aggregator}"></video-player>
<script async src="https://player.cdnvideohub.com/s2/stable/video-player.umd.js"></script>
HTML;

?>