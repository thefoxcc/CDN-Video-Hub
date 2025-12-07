<?php
/**
* Класс для работы с админ панелью
*
* @link https://lazydev.pro/
* @author LazyDev <email@lazydev.pro>
**/

namespace LazyDev\CDNVideoHub;

class Admin
{

    /**
     * Данные таблицы
     *
     * @param    string    $title
     * @param    string    $description
     * @param    string    $field
	 * @param	 string	   $helper
     **/
    static function row($title = '', $description = '', $field = '', $helper = '')
    {
        $description = $description ? '<span class="text-muted text-size-small hidden-xs">' . $description . '</span>' : '';
		$helper = $helper ? '<i class="color-warning help-button visible-lg-inline-block text-primary-600 fa fa-question-circle position-right" data-rel="popover" data-html="true" data-trigger="hover" data-placement="right" data-content="' . $helper . '" data-original-title="" title=""></i>' : '';
        echo '<tr>
            <td class="col-xs-6 col-sm-6 col-md-7">
                <h6 class="media-heading text-semibold">' . $title .  $helper . '</h6>
                ' . $description . '
            </td>
            <td class="col-xs-6 col-sm-6 col-md-5">' . $field . '</td>
        </tr>';
    }

	/**
	 * Данные таблицы
	 *
	 * @param    string    $title
	 * @param    string    $description
	 * @param    string    $field
	 * @param	 string	   $helper
	 **/
	static function rowInline($title = '', $description = '', $field = '', $helper = '')
	{
		$description = $description ? '<span class="text-muted text-size-small hidden-xs">' . $description . '</span>' : '';
		$helper = $helper ? '<i class="color-warning help-button visible-lg-inline-block text-primary-600 fa fa-question-circle position-right" data-rel="popover" data-html="true" data-trigger="hover" data-placement="right" data-content="' . $helper . '" data-original-title="" title=""></i>' : '';
		echo '<tr>
            <td colspan="2" class="col-xs-12 col-sm-12 col-md-12">
                <label style="float:left;" class="form-label">' . $title .  $helper . '</label>
                ' . $description  . $field . '
            </td>
        </tr>';
	}

    /**
     * Параметры input
     *
     * @param    array    $data
     * @return   string
     **/
    static function input($data)
    {
		$inputElement = $data[3] ? ' placeholder="' . $data[3] . '"' : '';
		$inputElement .= $data[4] ? ' disabled' : '';
		$class = 'form-control';
		$style = $divStart = '';
        if ($data[1] == 'range') {
            $class = ' custom-range';
			$inputElement .= $data[5] ? ' step="' . $data[5] . '"' : '';
			$inputElement .= $data[6] ? ' min="' . $data[6] . '"' : '';
			$inputElement .= $data[7] ? ' max="' . $data[7] . '"' : '';
        } elseif ($data[1] == 'number') {
			$class = '';
			$divStart = '<div class="quantity">';
			$inputElement .= $data[5] >=0 ? ' min="' . $data[5] . '"' : '';
			$inputElement .= $data[6] ? ' max="' . $data[6] . '"' : '';
			$inputElement .= $data[7] ? ' step="' . $data[7] . '"' : '';
        } else {
			$style = $data[5];
		}

		return $divStart . '<input type="' . $data[1] . '" autocomplete="off" style="' . $style . '" value="' . $data[2]. '" class="' . $class . '" name="' . $data[0] . '" ' . $inputElement . '>' . ($divStart != '' ? '</div>' : '');
    }

	/**
	 * Параметры checkbox
	 *
	 * @param string $name
	 * @param bool $checked
	 * @param string $id
	 * @param bool $disabled
	 * @param bool|array $connect
	 * @param bool|array $lang
	 *
	 * @return string
	 */
	static function checkBox($name, $checked, $id, $disabled = false, $connect = false, $lang = false, $key = '')
	{
		global $CDNVideoHubModule;

		$checked = $checked ? 'checked' : '';
		$disabled = $disabled ? 'disabled' : '';
		$data = '';
		if ($connect) {
			$data = 'data-dis="' . implode(',', $connect[$id]) . '"';
		}

		if ($key) {
			$key = 'data-key="' . $key . '"';
		}

		if (!$lang) {
			$lang = [$CDNVideoHubModule[Base::$modName]['lang']['admin']['other']['turn_on'], $CDNVideoHubModule[Base::$modName]['lang']['admin']['other']['turn_off']];
		}

return <<<HTML
<div class="can-toggle can-toggle--size-small">
	<input id="{$id}" {$data} name="{$name}" {$key} value="1" type="checkbox" {$checked} {$disabled}>
	<label for="{$id}">
		<div class="can-toggle__switch" data-checked="{$lang[0]}" data-unchecked="{$lang[1]}"></div>
	</label>
</div>
HTML;
	}

    /**
     * Параметры select
     *
     * @param    string    $name
     * @param    string    $select
     * @param    string    $placeholder
     * @return   string
     **/
    static function selectTag($name, $select, $placeholder = '')
    {
        return '<select name="' . $name . '" class="selectTag" data-placeholder="' . $placeholder . '" multiple>' . $select . '</select>';
    }

    /**
     * Параметры select
     *
     * @param    string 			$name
	 * @param 	 array 				$select
	 * @param 	 array|string 		$valueSelect
	 * @param 	 array				$settings // 0 - $keyArray, 1 = $multiple, 2 = $disabled, 3 = $placeholder, 4 = $optgroup
	 *
     * @return   string
     **/
	static function select($name, $select, $valueSelect, $settings = [])
	{
		global $CDNVideoHubModule, $modName;

		$inputElement = implode(' ', array_filter([
			$settings[1] ? 'multiple' : '',
			$settings[2] ? 'disabled' : '',
			$settings[3] ? "data-placeholder=\"{$settings[3]}\"" : ''
		]));

		$options = '';
		foreach ($select as $key => $val) {

			if ($settings[4] && $key == 'p.date') {
				$options .= "<optgroup label=\"{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['standard']}\">";
			}

			$optionValue = $settings[0] ? $key : $val;
			$selected = (is_array($valueSelect) && in_array($optionValue, $valueSelect)) || $valueSelect == $optionValue ? ' selected' : '';
			$val = stripslashes($val);
			$options .= "<option value=\"{$optionValue}\"{$selected}>{$val}</option>";

			if ($settings[4] && $key == 'e.editdate') {
				$options .= "</optgroup><optgroup label=\"{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['xfieldField']}\">";
			}

		}

		if ($settings[4]) {
			$options .= '</optgroup>';
		}
        
		return "<select name=\"{$name}\" class=\"selectTag\" {$inputElement}>{$options}</select>";
	}

	/**
	 * Параметры select
	 *
	 * @param    string 			$name
	 * @param 	 array 				$select
	 * @param 	 array|string 		$valueSelect
	 * @param 	 array				$settings // 0 - $keyArray, 1 = $multiple, 2 = $disabled, 3 = $placeholder, 4 = $optgroup
	 *
	 * @return   string
	 **/
	static function selectFill($name, $select, $valueSelect, $settings = [])
	{
		global $CDNVideoHubModule, $modName;

		$inputElement = implode(' ', array_filter([
			$settings[1] ? 'multiple' : '',
			$settings[2] ? 'disabled' : '',
			$settings[3] ? "data-placeholder=\"{$settings[3]}\"" : ''
		]));

		$options = '';
		foreach ($select as $key => $val) {
			if ($settings[4] && $key == 'p.title') {
				$options .= "<optgroup label=\"{$CDNVideoHubModule[$modName]['lang']['admin']['fill']['standard']}\">";
			}
			if ($settings[4] && !in_array($key, ['p.title', 'p.cat', 'p.shortStory', 'p.fullStory', 'p.metaTitle', 'p.metaDescr', 'p.altName'])) {
				$options .= "</optgroup><optgroup label=\"{$CDNVideoHubModule[$modName]['lang']['admin']['fill']['xfieldField']}\">";
			}

			$optionValue = $settings[0] ? $key : $val;
			$selected = (is_array($valueSelect) && in_array($optionValue, $valueSelect)) || $valueSelect == $optionValue ? ' selected' : '';
			$val = stripslashes($val);
			$options .= "<option value=\"{$optionValue}\"{$selected}>{$val}</option>\n";
		}

		if ($settings[4]) {
			$options .= '</optgroup>';
		}

		return "<select name=\"{$name}\" class=\"selectTag\" {$inputElement}>{$options}</select>";
	}

	/**
	 * Параметры select
	 *
	 * @param    string 			$name
	 * @param 	 array 				$select
	 * @param 	 array|string 		$valueSelect
	 * @param 	 array				$settings // 0 - $keyArray, 1 = $multiple, 2 = $disabled, 3 = $placeholder
	 *
	 * @return   string
	 **/
	static function selectGenres($name, $select, $valueSelect, $settings = [])
	{
		global $CDNVideoHubModule, $modName;

		$inputElement = implode(' ', array_filter([
			$settings[1] ? 'multiple' : '',
			$settings[2] ? 'disabled' : '',
			$settings[3] ? "data-placeholder=\"{$settings[3]}\"" : ''
		]));

		$options = "";
		foreach ($select as $key => $val) {
			if ($val === '__genres__') {
				$options .= "<optgroup label=\"{$CDNVideoHubModule[$modName]['lang']['admin']['settings']['__genres__']}\">";
				continue;
			}
			if (in_array($val, ['__country__', '__years__', '__type__', '__others__'])) {
				$options .= "</optgroup><optgroup label=\"{$CDNVideoHubModule[$modName]['lang']['admin']['settings'][$val]}\">";
				continue;
			}

			$selected = in_array($key, $valueSelect) ? ' selected' : '';
			$val = stripslashes($val);
			$options .= "<option value=\"{$key}\"{$selected}>{$val}</option>\n";
		}

		$options .= '</optgroup>';
		return "<select name=\"{$name}\" class=\"selectTag\" {$inputElement}>{$options}</select>";
	}

    /**
     * Параметры textarea
     *
     * @param    array    $data
     * @return   string
     **/
    static function textarea($data)
    {
		$input_elemet = $data[2] ? ' placeholder="' . $data[2] . '"' : '';
		$input_elemet .= $data[3] ? ' disabled' : '';

        return '<textarea style="min-height:150px;max-height:150px;min-width:333px;max-width:100%;border:1px solid #ddd;padding:5px;" autocomplete="off" class="form-control" name="' . $data[0] . '" ' . $input_elemet . '>' . $data[1] . '</textarea>';
    }

    /**
     * Menu
     *
     * @param    array    $items
     * @return   string
     **/
    static function menu($items)
    {
        if (empty($items)) {
            return '';
        }

        $total = count($items);
        $perRow = $total <= 2 ? 2 : 3;
        $colSize = 12 / $perRow;

        $html = '';
        foreach (array_chunk($items, $perRow) as $row) {
            $html .= '<div class="row box-section">';
            foreach ($row as $menu) {
                $link  = htmlspecialchars($menu['link'],  ENT_QUOTES, 'UTF-8');
                $icon  = htmlspecialchars($menu['icon'],  ENT_QUOTES, 'UTF-8');
                $title = htmlspecialchars($menu['title'], ENT_QUOTES, 'UTF-8');
                $desc  = htmlspecialchars($menu['descr'], ENT_QUOTES, 'UTF-8');

$html .= <<<HTML
<div class="col-sm-{$colSize} media-list media-list-linked">
    <a class="media-link" href="{$link}">
        <div class="media-left">
            <img src="{$icon}" alt="{$title}" class="img-lg section_icon">
        </div>
        <div class="media-body">
            <h6 class="media-heading text-semibold">{$title}</h6>
            <span class="text-muted text-size-small">{$desc}</span>
        </div>
    </a>
</div>
HTML;
            }
            $html .= '</div>';
        }

        return $html;
	}
}

?>