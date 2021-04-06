<?php

namespace ereminmdev\yii2\crud\models;

use ereminmdev\yii2\crud\components\CrudExport;
use Yii;
use yii\base\Model;

/**
 * Class CrudExportForm
 * @package ereminmdev\yii2\crud\models
 */
class CrudExportForm extends Model
{
    /**
     * @var string
     */
    public $fileFormat;
    /**
     * @var bool
     */
    public $needRenderData = false;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['fileFormat', 'needRenderData'], 'required'],
            [['needRenderData'], 'boolean'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'fileFormat' => Yii::t('crud', 'File type'),
            'needRenderData' => Yii::t('crud', 'Render relative data'),
        ];
    }

    /**
     * @return array of pairs $format => $title
     */
    public static function fileFormats()
    {
        return CrudExport::fileFormats();
    }
}
