<?php

namespace ereminmdev\yii2\crud\components;

use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Yii;
use yii\base\BaseObject;
use yii\db\ActiveRecord;
use yii\db\IntegrityException;
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

        $fields = array_shift($rows);
        array_shift($rows); // remove unused 2nd row

        foreach ($rows as $idx => $row) {
            $this->importRow($fields, $row, $idx + 3);
        }

        return empty($this->_errors);
    }

    /**
     * @return bool
     * @throws PhpSpreadsheetException
     */
    public function importCsv()
    {
        $handle = fopen($this->fileName, 'r');

        if ($handle === false) {
            $this->_errors[] = Yii::t('yii', 'File upload failed.');
            return false;
        }

        $fields = fgetcsv($handle);
        $fields2 = fgetcsv($handle);  // remove unused 2nd row
        if (($fields === false) || ($fields2 === false)) {
            $this->_errors[] = Yii::t('crud', 'No data to import. Need more then 2 strings in file.');
            return false;
        }
        unset($fields2);

        $rowIdx = 3;
        while (($row = fgetcsv($handle)) !== FALSE) {
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
        $values = array_combine($fields, $row);
        foreach ($values as $key => $value) {
            if ($value === null) unset($values[$key]);
        }

        $this->prepareData($values);

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
        } catch (IntegrityException $e) {
            $this->_errors[] = Yii::t('crud', 'String') . ' ' . $rowIdx . ': ' . $e->getMessage();
        }
    }

    /**
     * @param $values array
     */
    public function prepareData(&$values)
    {
        foreach ($values as $field => &$value) {
            if (isset($this->columnsSchema[$field]['type'])) {
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
