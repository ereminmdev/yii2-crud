<?php

namespace ereminmdev\yii2\crud;

use Yii;
use yii\base\Module;


/**
 * Class Crud
 * @package ereminmdev\yii2\crud
 */
class Crud extends Module
{
    /**
     * @var string
     */
    public $controllerNamespace = 'ereminmdev\yii2\crud\controllers';


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        Yii::$app->view->registerAssetBundle('ereminmdev\yii2\crud\assets\CrudAsset');

        $this->registerTranslations();
    }

    /**
     * @inheritdoc
     */
    public function registerTranslations()
    {
        Yii::$app->i18n->translations['crud*'] = [
            'class' => 'yii\i18n\PhpMessageSource',
            'sourceLanguage' => 'en-US',
            'basePath' => '@vendor/ereminmdev/yii2-crud/messages',
        ];
    }
}
