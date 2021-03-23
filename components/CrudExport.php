<?php

namespace ereminmdev\yii2\crud\components;

use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
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
use yii\grid\DataColumn;
use yii\grid\GridView;
use yii\web\RangeNotSatisfiableHttpException;

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
    public $needRenderData = false;
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
     * @throws PhpSpreadsheetException
     * @throws RangeNotSatisfiableHttpException
     */
    public function export()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $gridView = new GridView([
            'dataProvider' => $this->dataProvider,
            'filterModel' => $this->model,
            'columns' => $this->columns,
            'emptyCell' => '',
        ]);

        /** @var DataColumn[] $columns */
        $columns = $gridView->columns;

        $rowI = 1;

        $colI = 1;
        $model = $this->model;
        foreach ($columns as $column) {
            $sheet->setCellValueByColumnAndRow($colI, $rowI, $column->attribute);
            $headerValue = $this->needRenderData ? strip_tags($column->renderHeaderCell()) : (string)$model->getAttributeLabel($column->attribute);
            $sheet->setCellValueByColumnAndRow($colI, $rowI + 1, $headerValue);
            $colI++;
        }
        $rowI = 2;

        /** @var ActiveRecord[] $models */
        $models = $gridView->dataProvider->getModels();
        $keys = $gridView->dataProvider->getKeys();
        foreach ($models as $index => $model) {
            $colI = 1;
            $rowI++;
            $key = $keys[$index];
            foreach ($columns as $column) {
                $attribute = $column->attribute;
                $value = $this->needRenderData ? strip_tags($column->renderDataCell($model, $key, $index)) : (string)$model->getAttribute($attribute);
                $sheet->setCellValueByColumnAndRow($colI, $rowI, $value);
                $colI++;
            }
        }

        $fileName = $this->fileName ?: 'Export_' . basename(get_class($this->model)) . '_' . date('d.m.Y');

        switch ($this->format) {
            case 'xlsx':
                $writer = new Xlsx($spreadsheet);
                $fileName .= '.xlsx';
                $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'xls':
                $writer = new Xls($spreadsheet);
                $fileName .= '.xls';
                $mimeType = 'application/vnd.ms-excel';
                break;
            case 'ods':
                $writer = new Ods($spreadsheet);
                $fileName .= '.ods';
                $mimeType = 'application/vnd.oasis.opendocument.spreadsheet';
                break;
            case 'htm':
                $writer = new Html($spreadsheet);
                $fileName .= '.htm';
                $mimeType = 'text/html';
                break;
            /*case 'pdf':
                $writer = \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf($spreadsheet);
                $fileName .= '.pdf';
                $mimeType = 'application/pdf';
                break;*/
            default:
                $writer = new Csv($spreadsheet);
                $writer->setUseBOM(true); // writing UTF-8 CSV file
                $fileName .= '.csv';
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
