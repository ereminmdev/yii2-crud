<?php

namespace ereminmdev\yii2\crud\components;

use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as PhpSpreadsheetDate;
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
            'xls' => Yii::t('crud', 'Microsoft Excel 5.0/95') . ' (*.xls)',
            'ods' => Yii::t('crud', 'Open Document Format') . ' (*.ods)',
            'csv' => Yii::t('crud', 'CSV (delimiter - comma)') . ' (*.csv)',
        ];
    }

    /**
     * @return bool
     * @throws PhpSpreadsheetException
     */
    public function import()
    {
        set_time_limit(0);

        if ($this->format == 'csv') {
            return $this->importCsv();
        }

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

        array_shift($rows);
        $fields = array_shift($rows);

        $rowIdx = 3;
        foreach ($rows as $row) {
            $this->importRow($fields, $row, $rowIdx);
            $rowIdx++;
        }

        return empty($this->_errors);
    }

    /**
     * @param string $separator
     * @return bool
     * @throws PhpSpreadsheetException
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
                        $value = in_array(mb_strtolower($value), ['', 'нет', 'ложь', false]) ? false : (boolean)$value;
                        break;
                    case Schema::TYPE_DATE:
                        $time = ($this->format == 'csv') || !is_numeric($value) ? strtotime($value) : PhpSpreadsheetDate::excelToTimestamp($value);
                        $value = date('Y-m-d', $time);
                        break;
                    case Schema::TYPE_DATETIME:
                        $time = ($this->format == 'csv') || !is_numeric($value) ? strtotime($value) : PhpSpreadsheetDate::excelToTimestamp($value);
                        $value = date('Y-m-d H:i:s', $time);
                        break;
                    case Schema::TYPE_TIME:
                        $time = ($this->format == 'csv') || !is_numeric($value) ? strtotime($value) : PhpSpreadsheetDate::excelToTimestamp($value);
                        $value = date('H:i:s', $time);
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
