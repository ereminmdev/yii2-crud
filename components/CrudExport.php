<?php

namespace ereminmdev\yii2\crud\components;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yii;
use yii\base\BaseObject;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\grid\GridView;
use yii\helpers\Inflector;

/**
 * Class CrudExport
 * @package ereminmdev\yii2\crud\components
 */
class CrudExport extends BaseObject
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
     * @var bool
     */
    public $renderData = false;
    /**
     * @var ActiveDataProvider
     */
    public $dataProvider;
    /**
     * @var ActiveRecord
     */
    public $model;
    /**
     * @var array of columns
     */
    public $columns;

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
            'htm' => Yii::t('crud', 'Web page') . ' (*.htm)',
        ];
    }

    /**
     * @return $this|mixed
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \yii\web\RangeNotSatisfiableHttpException
     */
    public function export()
    {
        function getHeaderValue($value)
        {
            return strip_tags($value);
        }

        function getValue($value)
        {
            return strip_tags($value);
        }

        $this->fileName = $this->fileName ?: 'Export_' . Inflector::camelize(get_class($this->model)) . '_' . date('d.m.Y');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $gridView = new GridView([
            'dataProvider' => $this->dataProvider,
            'filterModel' => $this->model,
            'columns' => $this->columns,
            'emptyCell' => '',
        ]);
        $columns = $gridView->columns;
        $models = $gridView->dataProvider->getModels();
        $model = $this->model;
        $keys = $this->dataProvider->getKeys();

        $rowI = 1;

        $colI = 1;
        foreach ($columns as $column) {
            $sheet->setCellValueByColumnAndRow($colI, $rowI, $column->attribute);
            $headerValue = $this->renderData ? getHeaderValue($column->renderHeaderCell()) : $model->getAttributeLabel($column->attribute);
            $sheet->setCellValueByColumnAndRow($colI, $rowI + 1, $headerValue);
            $colI++;
        }
        $rowI = 2;

        foreach ($models as $index => $model) {
            $colI = 1;
            $rowI++;
            $key = $keys[$index];
            foreach ($columns as $column) {
                $attribute = $column->attribute;
                $value = $this->renderData ? getValue($column->renderDataCell($model, $key, $index)) : $model->getAttribute($attribute);
                $sheet->setCellValueByColumnAndRow($colI, $rowI, $value);
                $colI++;
            }
        }

        switch ($this->format) {
            case 'xlsx':
                $writer = new Xlsx($spreadsheet);
                $fileName = $this->fileName . '.xlsx';
                $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'xls':
                $writer = new Xls($spreadsheet);
                $fileName = $this->fileName . '.xls';
                $mimeType = 'application/vnd.ms-excel';
                break;
            case 'ods':
                $writer = new Ods($spreadsheet);
                $fileName = $this->fileName . '.ods';
                $mimeType = 'application/vnd.oasis.opendocument.spreadsheet';
                break;
            case 'htm':
                $writer = new Html($spreadsheet);
                $fileName = $this->fileName . '.htm';
                $mimeType = 'text/html';
                break;
            /*case 'pdf':
                $writer = \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf($spreadsheet);
                $fileName = $this->fileName . '.pdf';
                $mimeType = 'application/pdf';
                break;*/
            default:
                $writer = new Csv($spreadsheet);
                $writer->setUseBOM(true); // writing UTF-8 CSV file
                $fileName = $this->fileName . '.csv';
                $mimeType = 'text/csv';
        }

        ob_start();
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        $content = ob_get_contents();
        ob_end_clean();

        return Yii::$app->response->sendContentAsFile($content, $fileName, ['mimeType' => $mimeType]);
    }
}
