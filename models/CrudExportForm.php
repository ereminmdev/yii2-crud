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
    public $renderData = false;


    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['fileFormat', 'renderData'], 'required'],
            [['renderData'], 'boolean'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'fileFormat' => Yii::t('crud', 'File type'),
            'renderData' => Yii::t('crud', 'Render relative data'),
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
