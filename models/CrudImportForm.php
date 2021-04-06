<?php

namespace ereminmdev\yii2\crud\models;

use ereminmdev\yii2\crud\components\CrudImport;
use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

/**
 * Class CrudImportForm
 * @package ereminmdev\yii2\crud\models
 */
class CrudImportForm extends Model
{
    /**
     * @var UploadedFile
     */
    public $file;
    /**
     * @var int
     */
    public $count = 0;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['file'], 'required'],
            [['file'], 'file'], // 'extensions' => 'csv'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'file' => Yii::t('crud', 'File'),
        ];
    }

    /**
     * @return array of pairs $format => $title
     */
    public static function fileFormats()
    {
        return CrudImport::fileFormats();
    }
}
