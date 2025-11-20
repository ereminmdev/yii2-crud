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
            if ($dataProvider->pagination) {
                $dataProvider->pagination->pageSize = 50;
            }
        },
        'gridShowColumns' => ['title', 'image'],
        'gridHideColumns' => ['content'],
        'gridEditLinkField' => 'id',
        'gridColumns' => [
            'title' => [
                'attribute' => 'title',
                'label' => 'Title',
                'value' => fn(self $model) => $model->getBrandTitle() . ' ' . $model->title,
            ],
            'colors1' => false,
        ],
        'formFields' => [
            'colors' => fn(ActiveForm $form, self $model) => $form->field($model, 'sizes')->checkboxList(static::colors()),
            'colors2' => false,
        ],
        'columnsSchema' => [
            'shop_phone' => ['type' => 'tel', 'labelHint' => 'User phone number'],
            'shop_email' => ['type' => 'email'],
            'shop_url' => ['type' => 'url'],
            'brand_id' => [
                'type' => 'relation',
                'rtype' => 'hasOne',
                'relation' => 'brand',
                'getList' => fn() => Brand::find()
                    ->select(['title', 'id'])
                    ->orderBy(['title' => SORT_ASC])
                    ->indexBy('id')
                    ->column(),
            ],
            'sizes' => [
                'type' => 'relation',
                'rtype' => 'hasMany',
                'relation' => 'sizes',
            ],
            'status' => [
                'type' => 'array',
                'itemList' => fn() => static::statuses(),
                'gridDropButton' => true,
                'formFieldInputType' => 'radioList',
            ],
            'comment' => ['jsEditPrompt' => true],
            'colors3' => false,
        ],
        'gridToolbarActions' => [
            '{custom}' => Html::a('Моя кнопка', '#', ['class' => 'btn btn-default']),
        ],
        'gridActions' => [
            '{custom}' => fn(self $model, mixed $key, Crud $crud) => [
                'label' => 'Мое действие',
                'url' => '#',
            ],
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
        'views.index.h1' => fn(Crud $crud, View $view) => '<h1>' . Html::encode($view->title) . '</h1>',
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
        // ...
        'viewAs' => 'tree', // enable tree as default index view
        'tree' => [
            'parentField' => 'parent_id',
            'childrenRelation' => 'children',
            'sortField' => 'position', // false - disable sorting
            'itemActionsTemplate' => "{custom}\n{--}\n{create1}\n{create2}\n{--}",
            'itemActions' => [
                '{custom}' => fn(self $model, DefaultController $controller, Crud $crud) => [
                    'label' => 'Custom label',
                    'url' => ['#'],
                ],
            ],
            'titleBlock' => fn(self $model, DefaultController $controller, Crud $crud) => Html::encode($model->title),
            'titleBlock_text' => fn(self $model, DefaultController $controller, Crud $crud) => Html::encode($model->name),
            'titleBlock_options' => fn(self $model, DefaultController $controller, Crud $crud) => ['class' => 'red'],
            'titleBlock_onHover' => fn(self $model, DefaultController $controller, Crud $crud) => Html::encode($model->statusText),
            'rightBlock' => fn(self $model, DefaultController $controller, Crud $crud) => $model->status,
        ],
    ];
}
```

3) Insert link into view:

```
<?= Url::toRoute(['/crud/default/index', 'model' => Product::class]); ?>
```
