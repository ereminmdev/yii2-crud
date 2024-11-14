<?php

namespace ereminmdev\yii2\crud\components;

use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
use yii\web\Response;

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
            'csv' => Yii::t('crud', 'CSV (delimiter - comma)') . ' (*.csv)',
            'xlsx' => Yii::t('crud', 'Excel') . ' (*.xlsx)',
            'xls' => Yii::t('crud', 'Microsoft Excel 5.0/95') . ' (*.xls)',
            'ods' => Yii::t('crud', 'Open Document Format') . ' (*.ods)',
            'html' => Yii::t('crud', 'Web page') . ' (*.html)',
        ];
    }

    /**
     * @return Response
     * @throws PhpSpreadsheetException
     * @throws RangeNotSatisfiableHttpException
     */
    public function export()
    {
        if ($this->format == 'csv') {
            return $this->exportCsv();
        } elseif ($this->format == 'html') {
            return $this->exportHtml();
        }

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
            $sheet->setCellValue([$colI, $rowI], $column->attribute);
            $headerValue = $this->needRenderData ? $this->renderCell($column->renderHeaderCell()) : $this->valueToString($model->getAttributeLabel($column->attribute));
            $sheet->setCellValue([$colI, $rowI + 1], $headerValue);
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
                $value = $this->needRenderData ? $this->renderCell($column->renderDataCell($model, $key, $index)) : $this->valueToString($model->getAttribute($column->attribute));
                $sheet->setCellValue([$colI, $rowI], $value);
                $colI++;
            }
        }

        $fileName = $this->fileName ?: 'Export_' . $this->model->formName() . '_' . date('d.m.Y');

        switch ($this->format) {
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
            default:
                $writer = new Xlsx($spreadsheet);
                $fileName .= '.xlsx';
                $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
        }

        ob_start();
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        $content = ob_get_contents();
        ob_end_clean();

        return Yii::$app->response->sendContentAsFile($content, $fileName, ['mimeType' => $mimeType]);
    }

    /**
     * @return Response
     */
    public function exportCsv()
    {
        $gridView = new GridView([
            'dataProvider' => $this->dataProvider,
            'filterModel' => $this->model,
            'columns' => $this->columns,
            'emptyCell' => '',
        ]);

        /** @var DataColumn[] $columns */
        $columns = $gridView->columns;

        ob_start();
        $stream = fopen('php://output', 'w');

        $model = $this->model;
        $values = [];
        $values2 = [];
        foreach ($columns as $column) {
            $values[] = $column->attribute;
            $values2[] = $this->needRenderData ? $this->renderCell($column->renderHeaderCell()) : $this->valueToString($model->getAttributeLabel($column->attribute));
        }
        fputcsv($stream, $values);
        fputcsv($stream, $values2);

        /** @var ActiveRecord[] $models */
        $models = $gridView->dataProvider->getModels();
        $keys = $gridView->dataProvider->getKeys();
        foreach ($models as $index => $model) {
            $key = $keys[$index];
            $values = [];
            foreach ($columns as $column) {
                $value = $this->needRenderData ? $this->renderCell($column->renderDataCell($model, $key, $index)) : $this->valueToString($model->getAttribute($column->attribute));
                $values[] = $value;
            }
            fputcsv($stream, $values);
        }

        fclose($stream);
        $content = ob_get_contents();
        ob_end_clean();

        $fileName = $this->fileName ?: 'Export_' . $this->model->formName() . '_' . date('d.m.Y') . '.csv';

        return Yii::$app->response->sendContentAsFile($content, $fileName, ['mimeType' => 'text/csv']);
    }

    /**
     * @return Response
     */
    public function exportHtml()
    {
        $gridView = new GridView([
            'dataProvider' => $this->dataProvider,
            'filterModel' => $this->model,
            'columns' => $this->columns,
            'emptyCell' => '',
        ]);

        /** @var DataColumn[] $columns */
        $columns = $gridView->columns;

        ob_start();
        $stream = fopen('php://output', 'w');

        echo '<table border="1" cellpadding="5" cellspacing="0">';

        $model = $this->model;
        echo '<tr>';
        foreach ($columns as $column) {
            $value = $this->needRenderData ? $this->renderCell($column->renderHeaderCell()) : $this->valueToString($model->getAttributeLabel($column->attribute));
            echo '<th align="left" valign="top">' . $value . '</th>';
        }
        echo '</tr>';

        /** @var ActiveRecord[] $models */
        $models = $gridView->dataProvider->getModels();
        $keys = $gridView->dataProvider->getKeys();
        foreach ($models as $index => $model) {
            $key = $keys[$index];
            $values = [];
            echo '<tr>';
            foreach ($columns as $column) {
                $value = $this->needRenderData ? $this->renderCell($column->renderDataCell($model, $key, $index)) : $this->valueToString($model->getAttribute($column->attribute));
                echo '<td align="left" valign="top">' . $value . '</td>';
            }
            echo '</tr>';
        }

        echo '</table>';

        fclose($stream);
        $content = ob_get_contents();
        ob_end_clean();

        $fileName = $this->fileName ?: 'Export_' . $this->model->formName() . '_' . date('d.m.Y') . '.html';

        return Yii::$app->response->sendContentAsFile($content, $fileName, ['mimeType' => 'text/html']);
    }

    /**
     * @param string $value
     * @return string
     */
    protected function renderCell($value)
    {
        return trim(strip_tags($value));
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function valueToString($value)
    {
        if (is_array($value)) {
            return implode(',', $value);
        }
        return (string)$value;
    }
}
