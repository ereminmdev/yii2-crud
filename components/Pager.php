<?php

namespace ereminmdev\yii2\crud\components;

use Yii;
use yii\base\InvalidConfigException;
use yii\bootstrap\ButtonDropdown;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

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
    public $pageSizes = [1, 5, 10, 20, 30, 50, 100, 500, 1000];

    /**
     * {@inheritdoc}
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();
        $this->pageSize = $this->pagination->pageSize;
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        parent::run();
        $this->renderPageSize();
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
                'options' => ['class' => $this->pageSize == $value ? 'active' : null],
            ];
        }
        $items = array_merge($items, [
            '<li role="separator" class="divider"></li>',
            [
                'label' => Yii::t('crud', 'All items'),
                'url' => Url::current([$requestParam => 0, $pageParam => null]),
                'options' => ['class' => !$this->pageSize ? 'active' : null],
            ],
        ]);

        echo '<ul class="pagination per-page-variants">' .
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
