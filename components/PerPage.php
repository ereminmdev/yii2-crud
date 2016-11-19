<?php

namespace ereminmdev\yii2\crud\components;

use Yii;
use yii\base\Component;
use yii\bootstrap\ButtonDropdown;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;


class PerPage extends Component
{
    public $variants = [1, 5, 10, 20, 30, 50];

    public $default = 30;

    public $current;

    public $perPageParam = 'per-page';

    public $pageParam = 'page';

    public $urlManager;


    public function init()
    {
        parent::init();

        $this->current = $this->current ?: Yii::$app->request->getQueryParam('per-page', $this->default);
    }

    public function getMenuItems()
    {
        $items = [];

        //$items = [['label' => Yii::t('crud', 'Items per page')]];

        foreach ($this->variants as $variant) {
            $item = [
                'label' => Yii::t('crud', '{variant, number} {variant, plural, one{item} other{items}}', ['variant' => $variant]),
                'url' => $this->urlCreate($variant),
            ];
            if ($variant == $this->current) {
                $item['options'] = ['class' => 'active'];
            }
            $items[] = $item;
        }

        $items[] = '<li role="presentation" class="divider"></li>';

        $items[] = [
            'label' => Yii::t('crud', 'Show all'),
            'url' => $this->urlCreate(0),
            'options' => 0 == $this->current ? ['class' => 'active'] : [],
        ];

        return $items;
    }

    public function buttonDropdown($options = [])
    {
        $options = ArrayHelper::merge([
            'label' => '<span class="glyphicon glyphicon-filter"></span>',
            'encodeLabel' => false,
            'dropdown' => [
                'items' => $this->getMenuItems(),
            ],
            'options' => [
                'class' => 'btn-default',
                'title' => Yii::t('crud', 'Items per page'),
            ],
            'containerOptions' => [
                'class' => 'btn-default pull-right',
            ],
        ], $options);

        return ButtonDropdown::widget($options);
    }

    public function urlCreate($perPage)
    {
        $perPage = $perPage != $this->default ? $perPage : null;
        return Url::current([$this->perPageParam => $perPage]);
    }
}
