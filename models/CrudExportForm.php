<?php

namespace ereminmdev\yii2\crud\models;

use ereminmdev\yii2\crud\components\CrudExport;
use Yii;
use yii\base\Model;


class CrudExportForm extends Model
{
    public $fileFormat;

    public $renderData = false;


    public function rules()
    {
        return [
            [['fileFormat', 'renderData'], 'required'],
            [['renderData'], 'boolean'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'fileFormat' => Yii::t('crud', 'File type'),
            'renderData' => Yii::t('crud', 'Render relative data'),
        ];
    }

    public static function fileFormats()
    {
        return CrudExport::fileFormats();
    }
}
