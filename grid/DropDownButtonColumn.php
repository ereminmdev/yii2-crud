<?php

namespace ereminmdev\yii2\crud\grid;

use Closure;
use Exception;
use yii\bootstrap\ButtonDropdown;
use yii\grid\DataColumn;
use yii\helpers\ArrayHelper;

/**
 * Render bootstrap ButtonDropdown widget as grid column
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
    public $showCaret = false;
    /**
     * @var boolean encode button and dropdown labels
     */
    public $encodeLabels = true;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->buttonDropdownOptions = ArrayHelper::merge([
            'tagName' => 'a',
            'options' => [
                'class' => ['btn btn-xs', !$this->showCaret ? 'crud-hide-caret' : null],
            ],
            'encodeLabel' => $this->encodeLabels,
            'dropdown' => [
                'encodeLabels' => $this->encodeLabels,
            ]
        ], $this->buttonDropdownOptions);
    }

    /**
     * @inheritdoc
     * @throws Exception
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
        return $this->items instanceof Closure ? call_user_func_array($this->items, [$model, $key, $index]) : $this->items;
    }
}
