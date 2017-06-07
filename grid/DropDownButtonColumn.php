<?php

namespace ereminmdev\yii2\crud\grid;

use yii\bootstrap\ButtonDropdown;
use yii\grid\DataColumn;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;


/**
 * Render data column as bootstrap dropdown menu
 */
class DropDownButtonColumn extends DataColumn
{
    /**
     * @var array|callable list of menu items in the dropdown. Use function ($model, $key, $index) for callable.
     * @see \yii\bootstrap\Dropdown widget for dataile
     */
    public $items = [];
    /**
     * @var array list of options
     * @see \yii\bootstrap\ButtonDropdown
     */
    public $buttonDropdownOptions = [];
    /**
     * @var null|array list of labels
     */
    public $labels;
    /**
     * @var boolean set true to hide dropdown caret icon
     */
    public $hideCaret = true;
    /**
     * @var boolean encode button and dropdown labels
     */
    public $encodeLabels = true;


    public function init()
    {
        parent::init();

        if ($this->hideCaret) {
            Html::addCssClass($this->buttonDropdownOptions['options'], 'crud-hide-caret');
        }

        $this->buttonDropdownOptions['encodeLabel'] = isset($this->buttonDropdownOptions['encodeLabel']) ?
            $this->buttonDropdownOptions['encodeLabel'] : $this->encodeLabels;

        $this->buttonDropdownOptions['dropdown']['encodeLabels'] = isset($this->buttonDropdownOptions['dropdown']['encodeLabels']) ?
            $this->buttonDropdownOptions['dropdown']['encodeLabels'] : $this->encodeLabels;
    }

    /**
     * @inheritdoc
     */
    protected function renderDataCellContent($model, $key, $index)
    {
        if ($this->content === null) {
            $items = $this->getItems($model, $key, $index);;
            $value = $this->getDataCellValue($model, $key, $index);

            return ButtonDropdown::widget(ArrayHelper::merge([
                'label' => $this->labels ? ArrayHelper::getValue($this->labels, $value, '') : ArrayHelper::getValue($items, $value . '.label', ''),
                'dropdown' => ['items' => $items],
            ], $this->buttonDropdownOptions));

        } else {
            return parent::renderDataCellContent($model, $key, $index);
        }
    }

    /**
     * @param mixed $model the data model
     * @param mixed $key the key associated with the data model
     * @param integer $index the zero-based index of the data model among the models array returned by [[GridView::dataProvider]].
     * @return array
     */
    protected function getItems($model, $key, $index)
    {
        return is_callable($this->items) ? call_user_func_array($this->items, [$model, $key, $index]) : $this->items;
    }
}
