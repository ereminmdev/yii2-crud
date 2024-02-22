# yii2-crud

Crud module for Yii framework.

## Install

``composer require --prefer-dist ereminmdev/yii2-crud``

## Use

1) Add Crud module to config file:

```
'modules' => [
    'crud' => [
        'class' => 'ereminmdev\yii2\crud\Crud',
    ],
],
```

2) Add crudConfig() static function to ActiveRecord model:

```
public static function crudConfig()
{
    return [
        'title' => Yii::t('app', 'Products'),
    ],
}
```

More options:

```
public static function crudConfig()
{
    return [
        'title' => Yii::t('app', 'Products'),
        'dataProvider' => function (ActiveDataProvider $dataProvider) {
            $dataProvider->sort = [
                'defaultOrder' => [
                    'title' => SORT_ASC,
                ],
            ];
        },
        'gridColumns' => [
            'title' => [
                'attribute' => 'title',
                'label' => 'Title',
                'value' => function (self $model) {
                    return $model->getBrandTitle() . ' ' . $model->title;
                },
            ],
            'colors1' => false,
        ],       
        'formFields' => [
            'colors' => function (ActiveForm $form, self $model) {
                return $form->field($model, 'sizes')->checkboxList(static::colors());
            },
            'colors2' => false,
        ],
        'columnsSchema' => [
            'shop_phone' => ['type' => 'tel'],
            'shop_email' => ['type' => 'email'],
            'shop_url' => ['type' => 'url'],
            'brand_id' => [
                'type' => 'relation',
                'rtype' => 'hasOne',
                'relation' => 'brand',
                'getList' => function () {
                    return Brand::find()
                        ->select(['title', 'id'])
                        ->orderBy(['title' => SORT_ASC])
                        ->indexBy('id')
                        ->column();
                },
            ],
            'sizes' => [
                'type' => 'relation',
                'rtype' => 'hasMany',
                'relation' => 'sizes',
            ],
            'status' => [
                'type' => 'array',
                'itemList' => function () {
                    return static::statuses();
                },
                'gridDropButton' => true,
            ],
            'comment' => ['jsEditPrompt' => true],
            'colors3' => false,
        ],
        'gridToolbarActions' => [
            '{custom}' => Html::a('Моя кнопка', '#', ['class' => 'btn btn-default']),
        ],
        'gridActions' => [
            '{custom}' => function(self $model, mixed $key, Crud $crud) {
                return [
                   'label' => 'Мое действие',
                   'url' => '#',
                ];
            },
        ],
        'controller.behaviors' => [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['create', 'update'],
                        'allow' => true,
                        'roles' => ['admin'],
                    ],
                ],
            ],
        ],
        'access.save' => false, // hide Create, Save and Update buttons
        'access.delete' => false, // hide Delete button
    ];
}
```

Set tree view:

```
public static function crudConfig()
{
    return [
    ...
        'viewAs' => 'tree', // enable tree as default index view
        'tree' => [
            'parentField' => 'parent_id',
            'childrenRelation' => 'children',
            'sortField' => 'position', // false - disable sorting
            'itemActionsTemplate' => "{custom}\n{--}\n{create1}\n{create2}\n{--}",
            'itemActions' => [
                '{custom}' => function (self $model, DefaultController $controller, Crud $crud) {
                    return [
                        'label' => 'Custom label',
                        'url' => ['#'],
                    ];
                },
            ],
            'titleBlock' => function (self $model, DefaultController $controller, Crud $crud) {
                return Html::encode($model->title);
            },
            'titleBlock_text' => function (self $model, DefaultController $controller, Crud $crud) {
                return Html::encode($model->name);
            },
            'titleBlock_options' => function (self $model, DefaultController $controller, Crud $crud) {
                return ['class' => 'red'];
            },
            'titleBlock_onHover' => function (self $model, DefaultController $controller, Crud $crud) {
                return Html::encode($model->statusText);
            },
            'rightBlock' => function (self $model, DefaultController $controller, Crud $crud) {
                return $model->status;
            },
        ],
    ];
}
```

3) Insert link into view:

```
<?= Url::toRoute(['/crud', 'model' => Product::class]); ?>
```
