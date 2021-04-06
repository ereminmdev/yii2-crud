<?php

namespace ereminmdev\yii2\crud\components;

use Exception;
use Yii;
use yii\base\Component;
use yii\bootstrap\ButtonDropdown;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/**
 * Class PerPage
 * @package ereminmdev\yii2\crud\components
 *
 * @property array $menuItems
 */
class PerPage extends Component
{
    /**
     * @var array of perPage values
     */
    public $variants = [1, 5, 10, 20, 30, 50];
    /**
     * @var int default perPage value
     */
    public $default = 30;
    /**
     * @var int current perPage value
     */
    public $current;
    /**
     * @var string perPage $_GET param name
     */
    public $perPageParam = 'per-page';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        $this->current = $this->current ?: Yii::$app->request->getQueryParam($this->perPageParam, $this->default);
    }

    /**
     * @return array of menu items for bootstrap ButtonDropdown widget
     */
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

        $items[] = [
            'label' => Yii::t('crud', 'Show all'),
            'url' => $this->urlCreate(0),
            'options' => 0 == $this->current ? ['class' => 'active'] : [],
        ];

        return $items;
    }

    /**
     * @param array $options for bootstrap ButtonDropdown widget
     * @return string
     * @throws Exception
     */
    public function buttonDropdown($options = [])
    {
        return ButtonDropdown::widget(ArrayHelper::merge([
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
        ], $options));
    }

    /**
     * @param int $perPage
     * @return string
     */
    public function urlCreate($perPage)
    {
        $perPage = $perPage != $this->default ? $perPage : null;
        return Url::current([$this->perPageParam => $perPage]);
    }
}
