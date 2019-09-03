<?php

namespace ereminmdev\yii2\crud\components;

use Yii;
use yii\base\InvalidConfigException;
use yii\bootstrap\ButtonDropdown;
use yii\bootstrap\Dropdown;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;
use yii\widgets\Menu;

/**
 * Class Pager
 * @package ereminmdev\yii2\crud\components
 */
class Pager extends LinkPager
{
    /**
     * @var int current page size
     */
    public $pageSize;
    /**
     * @var array[int] page sizes to show
     */
    public $pageSizes = [1, 5, 10, 20, 30, 50];

    /**
     * @inheritDoc
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();
        $this->pageSize = $this->pagination->pageSize;
    }

    /**
     * @inheritDoc
     */
    public function run()
    {
        echo Html::beginTag('div', ['class' => 'clearfix']);
        parent::run();
        $this->renderPageSize();
        echo Html::endTag('div');
    }

    public function renderPageSize()
    {
        $requestParam = $this->pagination->pageSizeParam;
        $pageParam = $this->pagination->pageParam;

        $items = [];
        foreach ($this->pageSizes as $value) {
            $items[] = [
                'label' => Yii::t('crud', '{variant, number} {variant, plural, one{item} other{items}} per page', ['variant' => $value]),
                'url' => Url::current([$requestParam => $value, $pageParam => null]),
                'active' => $this->pageSize == $value,
            ];
        }
        $items = array_merge($items, [
            [
                'label' => Yii::t('crud', 'All items'),
                'url' => Url::current([$requestParam => 0, $pageParam => null]),
                'active' => !$this->pageSize,
            ],
        ]);

        echo '<ul class="pagination pull-right">' .
            ButtonDropdown::widget([
                'label' => $this->pageSize != 0 ? Yii::t('crud', '{variant, number} {variant, plural, one{item} other{items}} per page', ['variant' => $this->pageSize]) : Yii::t('crud', 'All items'),
                'tagName' => 'a',
                'dropdown' => [
                    'items' => $items,
                ],
                'containerOptions' => [
                    'tag' => 'li',
                    'style' => ['display' => 'inline-block'],
                ],
            ]) .
            '</ul>';
    }
}
