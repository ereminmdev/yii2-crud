<?php

namespace ereminmdev\yii2\crud;

use Yii;


class Crud extends \yii\base\Module
{
    public $controllerNamespace = 'ereminmdev\yii2\crud\controllers';


    public function init()
    {
        parent::init();

        Yii::$app->view->registerAssetBundle('ereminmdev\yii2\crud\assets\CrudAsset');

        $this->registerTranslations();
    }

    public function registerTranslations()
    {
        Yii::$app->i18n->translations['crud*'] = [
            'class' => 'yii\i18n\PhpMessageSource',
            'sourceLanguage' => 'en-US',
            'basePath' => '@vendor/ereminmdev/yii2-crud/messages',
        ];
    }
}
