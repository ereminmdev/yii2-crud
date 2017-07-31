# yii2-crud

Crud module for Yii framework.

## Install

``composer require ereminmdev/yii2-crud``

## Use

1) Add module to config file:

```
'modules' => [
    'crud' => [
        'class' => 'ereminmdev\yii2\crud\Crud',
    ],
],
```

2) Add crudConfig() function to ActiveRecord model class:

```
public static function crudConfig()
{
    return [
        'title' => Yii::t('app', 'Products'),
    ],
}
```

or with some customizations:

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
            'colors3' => false,
        ],
    ];
}
```

3) Add link in view file:

```
<?= Url::toRoute(['/crud', 'model' => Product::className()]); ?>
```
