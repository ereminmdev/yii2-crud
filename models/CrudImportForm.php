<?php

namespace ereminmdev\yii2\crud\models;

use ereminmdev\yii2\crud\components\CrudImport;
use Yii;
use yii\base\Model;


class CrudImportForm extends Model
{
    public $file;

    public $count = 0;


    public function rules()
    {
        return [
            [['file'], 'required'],
            [['file'], 'file'], // 'extensions' => 'csv'
        ];
    }

    public function attributeLabels()
    {
        return [
            'file' => Yii::t('crud', 'File'),
        ];
    }

    public static function fileFormats()
    {
        return CrudImport::fileFormats();
    }
}
