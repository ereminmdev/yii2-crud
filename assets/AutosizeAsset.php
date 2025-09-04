<?php

namespace ereminmdev\yii2\crud\assets;

use yii\web\AssetBundle;

class AutosizeAsset extends AssetBundle
{
    public $sourcePath = '@npm/autosize/dist';

    public $js = [
        YII_DEBUG ? 'autosize.js' : 'autosize.min.js',
    ];
}
