<?php

namespace ereminmdev\yii2\crud\components;

use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Yii;
use yii\base\BaseObject;
use yii\db\Schema;
use yii\helpers\ArrayHelper;

/**
 * Class CrudImport
 * @package ereminmdev\yii2\crud\components
 *
 * @property array $errors
 */
class CrudImport extends BaseObject
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
            'ods' => Yii::t('crud', 'Open Document Format') . ' (*.ods)',
            'csv' => Yii::t('crud', 'CSV (delimiter - comma)') . ' (*.csv)',
        ];
    }

    /**
     * @return bool
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function import()
    {
        switch ($this->format) {
            case 'xlsx':
                $reader = new Xlsx();
                break;
            case 'xls':
                $reader = new Xls();
                break;
            case 'ods':
                $reader = new Ods();
                break;
            case 'csv':
                $reader = new Csv();
                break;
            default:
                $this->_errors[] = Yii::t('crud', 'Not support file format "{format}".', ['format' => $this->format]);
                return false;
        }

        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($this->fileName);
        $rows = $spreadsheet->getActiveSheet()->toArray();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (count($rows) < 3) {
            $this->_errors[] = Yii::t('crud', 'No data to import. Need more then 2 strings in file.');
            return false;
        }

        $fields = array_shift($rows);
        array_shift($rows); // extract 2nd row

        foreach ($rows as $idx => $row) {
            $values = array_combine($fields, $row);
            foreach ($values as $key => $value) {
                if ($value === null) unset($values[$key]);
            }

            $this->prepareData($values);

            /* @var $model \yii\db\ActiveRecord */
            $model = new $this->modelClass;
            if ($id = ArrayHelper::getValue($values, 'id')) {
                $model->id = $id;
                $model->setIsNewRecord(false);
            }
            $model->setAttributes($values);

            if ($model->save()) {
                $this->insertCount++;
            } else {
                $errors = array_values($model->getFirstErrors());
                $this->_errors[] = Yii::t('crud', 'String') . ' ' . ($idx + 3) . ': ' . $errors[0];
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
