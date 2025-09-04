<?php

namespace ereminmdev\yii2\crud\assets;

use yii\web\AssetBundle;

/**
 * Class Crud asset bundle
 * @package ereminmdev\yii2\crud\assets
 */
class CrudAsset extends AssetBundle
{
    public $sourcePath = '@vendor/ereminmdev/yii2-crud/assets/CrudAsset';

    public $css = [
        'css/crud.css',
    ];

    public $js = [
        'js/crud.js',
    ];

    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap\BootstrapAsset',
        'ereminmdev\yii2\crud\assets\AutosizeAsset',
    ];
}
