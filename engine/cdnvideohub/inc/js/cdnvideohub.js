/**
 * Модуль CDN Video Hub
 *
 * @link https://lazydev.pro/
 * @author LazyDev <email@lazydev.pro>
 **/

const idPostFieldsCDN = {
    'p.title': 'title',
    'p.shortStory': 'short_story',
    'p.fullStory': 'full_story',
};

const getXField = key => $(`[name="xfield[${key}]"]`);

// Получаем доступные ID
const collectFields = () => {
    return ['idKinopoisk', 'idImdb', 'idMyAnimeList', 'idMyDramaList', 'idApi'].reduce((acc, key) => {
        const cfg = configCDNField[key];
        const el = cfg && $(`#xf_${cfg}`);
        const val = el?.val()?.trim();
        if (val) acc[key] = val;
        return acc;
    }, {});
};

// Обработка данных для получения информации о сезоне
const getSeason = () => {
    const cfg = configCDNField['season'];
    const el = cfg && $(`#xf_${cfg}`);
    return el?.val()?.trim() || 'max';
};

const showError = message => {
    Growl.error({ title: 'Ошибка', text: message });
};

// Обновление полей новости
const updatePostField = (key, value, editorMode) => {
    const fieldId = idPostFieldsCDN[key];
    if (!fieldId) {
        return;
    }

    const $el = $(`#${fieldId}`);
    const isEmpty = !$el.val();
    const shouldUpdate = !onlyEmptyCDN || isEmpty || forcedRowsCDN.includes(key); // Обработка опции Заполнять только пустые или форсить обновление в указанных полях
    if (!shouldUpdate) {
        return;
    }
    console.log(typeof editorMode);
    if (['short_story', 'full_story'].includes(fieldId)) {
        switch (editorMode) {
            case '1': return $el.froalaEditor('html.set', value);
            case '0': return $el.val(value);
            case '2':
            default: return tinymce.get(fieldId).setContent(value);
        }
    }

    $el.val(value);
};

const updateXFieldValue = (key, value, editorMode) => {
    const $el = getXField(key);
    if (!$el.length) {
        return;
    }

    const tag = $el.prop('tagName');
    const type = $el.prop('type');

    const isEmpty = !$el.val();
    const shouldUpdate = !onlyEmptyCDN || isEmpty || forcedRowsCDN.includes(key); // Обработка опции Заполнять только пустые или форсить обновление в указанных полях
    if (!shouldUpdate) {
        return;
    }

    if (tag === 'SELECT') {
        const opt = $el.find('option').filter((i, o) => $(o).text() === value);
        if (opt.length) {
            opt.prop('selected', true);
            $el.selectpicker('refresh');
        }
    } else if (tag === 'INPUT') {
        if (type === 'checkbox') {
            if (value) $el.prop('checked', true).trigger('click');
        } else if ($el.data('rel') === 'links') {
            $el.tokenfield('setTokens', value);
        } else {
            $el.val(value);
        }
    } else if (tag === 'TEXTAREA') {
        if ($el.hasClass('wysiwygeditor')) {
            if (editorMode == 1) {
                $el.froalaEditor('html.set', value);
            } else {
                tinymce.get(key).setContent(value);
            }
        } else {
            $el.val(value);
        }
    }
};

async function parseVideoCDN(type, hash, newsidCDN) {
    const fields = collectFields();
    if (!Object.keys(fields).length) {
        showError('Нет данных для поиска');
        return false;
    }

    const season = getSeason();
    ShowLoading('');

    try {
        const response = await $.ajax({
            method: 'POST',
            url: '/engine/cdnvideohub/inc/ajax/ajax.php',
            data: { fields, user_hash: hash, action: 'inputVideo', type, newsid: newsidCDN, season }
        });

        const msg = typeof response === 'string' ? JSON.parse(response) : response;

        if (msg.error) {
            showError(msg.error);
            return;
        }

        Object.entries(msg.api || {}).forEach(([key, value]) => {
            updatePostField(key, value, msg.config.editor);
            updateXFieldValue(key, value, msg.config.editor);
        });

        if (msg.cat) {
            msg.cat.forEach(cat => {
                $('#category option').filter((i, o) => $(o).val() == cat).prop('selected', true);
            });
            $('#category').trigger('chosen:updated');
        }
    } catch (err) {
        console.error('parseVideoCDN error:', err);
        showError('Сетевая ошибка');
    } finally {
        HideLoading();
    }
}