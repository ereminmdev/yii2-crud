<?php

namespace ereminmdev\yii2\crud\components;

use avadim\FastExcelReader\Excel;
use Yii;
use yii\base\BaseObject;
use yii\db\ActiveRecord;
use yii\db\Schema;

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
            'csv' => Yii::t('crud', 'CSV (delimiter - comma)') . ' (*.csv)',
        ];
    }

    /**
     * @return bool
     */
    public function import()
    {
        set_time_limit(0);

        if ($this->format == 'xlsx') {
            return $this->importXlsx();
        } elseif ($this->format == 'csv') {
            return $this->importCsv();
        }

        $this->_errors[] = Yii::t('crud', 'Not support file format "{format}".', ['format' => $this->format]);
        return false;
    }

    /**
     * @return bool
     */
    public function importXlsx()
    {
        $sheet = Excel::open($this->fileName)->getSheet();
        $fields = [];

        foreach ($sheet->nextRow() as $rowIdx => $row) {
            if ($rowIdx == 1) {
                continue;
            } elseif ($rowIdx == 2) {
                $fields = $row;
                continue;
            }
            $values = $this->arrayCombineByIndex($fields, $row);
            $this->importRow(array_keys($values), $values, $rowIdx);
        }

        return empty($this->_errors);
    }

    /**
     * @param string $separator
     * @return bool
     */
    public function importCsv($separator = ';')
    {
        $handle = fopen($this->fileName, 'r');

        if ($handle === false) {
            $this->_errors[] = Yii::t('yii', 'File upload failed.');
            return false;
        }

        fgetcsv($handle, null, $separator);
        $fields = fgetcsv($handle, null, $separator);
        if ($fields === false) {
            $this->_errors[] = Yii::t('crud', 'No data to import. Need more then 2 strings in file.');
            return false;
        }

        $rowIdx = 3;
        while (($row = fgetcsv($handle, null, $separator)) !== false) {
            $this->importRow($fields, $row, $rowIdx);
            $rowIdx++;
        }

        fclose($handle);

        return empty($this->_errors);
    }

    /**
     * @param array $fields
     * @param array $row
     * @param int $rowIdx
     */
    public function importRow($fields, $row, $rowIdx)
    {
        $fieldsCount = count($fields);
        $rowCount = count($row);

        if ($fieldsCount > $rowCount) {
            return;
        } elseif ($fieldsCount < $rowCount) {
            $row = array_slice($row, 0, $fieldsCount);
        }

        $values = array_combine($fields, $row);

        $this->prepareData($values);

        foreach ($values as $key => $value) {
            if ($value === null) unset($values[$key]);
        }

        /* @var $modelClass ActiveRecord */
        $modelClass = $this->modelClass;

        $model = new $modelClass;

        if ($id = ($values['id'] ?? null)) {
            $model = $modelClass::findOne($id);
            if ($model === null) {
                $model = new $modelClass;
                $model->setAttribute('id', $id);
            }
        }
        $model->setAttributes($values, false);

        try {
            if ($model->save()) {
                $this->insertCount++;
            } else {
                $errors = array_values($model->getFirstErrors());
                $this->_errors[] = Yii::t('crud', 'String') . ' ' . $rowIdx . ': ' . $errors[0];
            }
        } catch (\Exception $e) {
            $this->_errors[] = Yii::t('crud', 'String') . ' ' . $rowIdx . ': ' . $e->getMessage();
        }
    }

    /**
     * @param array $keys
     * @param array $values
     * @return array
     */
    protected function arrayCombineByIndex($keys, $values)
    {
        $result = [];

        foreach ($keys as $idx => $key) {
            if (array_key_exists($idx, $values)) {
                $result[$key] = $values[$idx];
            }
        }

        return $result;
    }

    /**
     * @param $values array
     */
    public function prepareData(&$values)
    {
        foreach ($values as $field => &$value) {
            if (!is_null($value) && isset($this->columnsSchema[$field]['type'])) {
                switch ($this->columnsSchema[$field]['type']) {
                    case Schema::TYPE_INTEGER:
                        $value = (integer)$value;
                        break;
                    case Schema::TYPE_FLOAT:
                    case Schema::TYPE_DOUBLE:
                    case Schema::TYPE_DECIMAL:
                        $value = str_replace(',', '.', (float)$value);
                        break;
                    case Schema::TYPE_BOOLEAN:
                        $value = !in_array(mb_strtolower($value), ['', 'нет', 'ложь', false]) && $value;
                        break;
                    case Schema::TYPE_DATE:
                        $value = date('Y-m-d', is_numeric($value) ? $value : strtotime($value));
                        break;
                    case Schema::TYPE_DATETIME:
                        $value = date('Y-m-d H:i:s', is_numeric($value) ? $value : strtotime($value));
                        break;
                    case Schema::TYPE_TIME:
                        $value = date('H:i:s', is_numeric($value) ? $value : strtotime($value));
                        break;
                    case 'upload-image':
                    case 'crop-image-upload':
                    case 'croppie-image-upload':
                    case 'cropper-image-upload':
                        $value = null;
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
