<?php

namespace ereminmdev\yii2\crud\components;

use Yii;
use yii\base\Object;
use yii\db\Schema;


/**
 * Class CrudImport
 * @package ereminmdev\yii2\crud\components
 *
 * @property array $errors
 */
class CrudImport extends Object
{
    /**
     * @var string file name
     */
    public $fileName;
    /**
     * @var string file format
     */
    public $format;
    /**
     * @var string model class name
     */
    public $modelClass;
    /**
     * @var array of columns schema
     */
    public $columnsSchema;
    /**
     * @var int inserted count
     */
    public $insertCount = 0;

    /**
     * @var array of errors during import process
     */
    private $_errors = [];


    /**
     * @return array of file formats
     */
    public static function fileFormats()
    {
        return [
            'xlsx' => Yii::t('crud', 'Excel') . ' (*.xlsx)',
            'xls' => Yii::t('crud', 'Microsoft Excel 5.0/95') . ' (*.xls)',
            'csv' => Yii::t('crud', 'CSV (delimiter - comma)') . ' (*.csv)',
        ];
    }

    /**
     * @return bool to has errors during import process
     */
    public function import()
    {
        \PHPExcel_Settings::setLocale(Yii::$app->language);

        switch ($this->format) {
            case 'xlsx':
                $objReader = new \PHPExcel_Reader_Excel2007();
                break;
            case 'xls':
                $objReader = new \PHPExcel_Reader_Excel5();
                break;
            case 'csv':
                $objReader = new \PHPExcel_Reader_CSV();
                $objReader->setDelimiter(';')
                    ->setInputEncoding('CP1251');
                break;
            default:
                $this->_errors[] = Yii::t('crud', 'Not support file format "{format}".', ['format' => $this->format]);
                return false;
        }

        $objReader->setReadDataOnly(true);
        $objPHPExcel = $objReader->load($this->fileName);
        $fileData = $objPHPExcel->getActiveSheet()->toArray();
        $objPHPExcel->disconnectWorksheets();
        unset($objPHPExcel);

        $dataCount = count($fileData);
        if ($dataCount < 3) {
            $this->_errors[] = Yii::t('crud', 'No data to import. Need more then 2 strings in file.');
            return false;
        }

        $fields = $fileData[0];

        for ($rowI = 2, $rowCount = $dataCount; $rowI < $rowCount; $rowI++) {
            $data = $fileData[$rowI];

            $values = array_combine($fields, $data);
            foreach ($values as $key => $value) {
                if ($value === null) unset($values[$key]);
            }

            $this->prepareData($values);

            /* @var $model \yii\db\ActiveRecord */
            $model = new $this->modelClass;
            $model->setAttributes($values);

            if ($model->save()) {
                $this->insertCount++;
            } else {
                $errors = array_values($model->getFirstErrors());
                $this->_errors[] = 'Строка ' . ($rowI + 1) . ': ' . $errors[0];
            }
        }

        return empty($this->_errors);
    }

    /**
     * @param $values array
     */
    public function prepareData(&$values)
    {
        foreach ($values as $field => &$value) {
            if (isset($this->columnsSchema[$field])) {
                switch ($this->columnsSchema[$field]['type']) {
                    case Schema::TYPE_INTEGER:
                        $value = (integer)$value;
                        break;
                    case Schema::TYPE_BOOLEAN:
                        $value = in_array(mb_strtolower($value), ['', 'нет', 'ложь', false]) ? false : (boolean)$value;
                        break;
                    default:
                        $value = (string)$value;
                }
            }
        }
    }

    /**
     * @return array of errors during import process
     */
    public function getErrors()
    {
        return $this->_errors;
    }
}
