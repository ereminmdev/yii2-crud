<?php

namespace ereminmdev\yii2\crud\components;

use avadim\FastExcelWriter\Excel;
use Yii;
use yii\base\BaseObject;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\grid\DataColumn;
use yii\grid\GridView;
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
            'xlsx' => Yii::t('crud', 'Excel') . ' (*.xlsx)',
            'csv' => Yii::t('crud', 'CSV (delimiter - comma)') . ' (*.csv)',
            'html' => Yii::t('crud', 'Web page') . ' (*.html)',
        ];
    }

    /**
     * @return Response
     */
    public function export()
    {
        set_time_limit(0);

        if ($this->format == 'csv') {
            return $this->exportCsv();
        } elseif ($this->format == 'html') {
            return $this->exportHtml();
        } else {
            return $this->exportXlsx();
        }
    }

    /**
     * @return Response
     */
    public function exportXlsx()
    {
        $excel = Excel::create();
        $sheet = $excel->sheet();

        $gridView = new GridView([
            'dataProvider' => $this->dataProvider,
            'filterModel' => $this->model,
            'columns' => $this->columns,
            'emptyCell' => '',
        ]);

        /** @var DataColumn[] $columns */
        $columns = $gridView->columns;

        $model = $this->model;
        $rowValues = [];
        foreach ($columns as $column) {
            $headerValue = $this->needRenderData ? $this->renderCell($column->renderHeaderCell()) : $this->valueToString($model->getAttributeLabel($column->attribute));
            $rowValues[] = $headerValue;
        }
        $sheet->writeRow($rowValues)->applyFontStyleBold();

        $rowValues = [];
        foreach ($columns as $column) {
            $rowValues[] = $column->attribute;
        }
        $sheet->writeHeader($rowValues)->applyFontStyleBold();

        /** @var ActiveRecord[] $models */
        $models = $gridView->dataProvider->getModels();
        $keys = $gridView->dataProvider->getKeys();

        foreach ($models as $index => $model) {
            $rowValues = [];
            foreach ($columns as $column) {
                $rowValues[] = $this->needRenderData ?
                    $this->renderCell($column->renderDataCell($model, $keys[$index], $index)) :
                    $this->valueToString($model->getAttribute($column->attribute));
            }
            $sheet->writeRow($rowValues);
        }

        $fileName = $this->fileName ?: 'Export_' . $this->model->formName() . '_' . date('d.m.Y') . '.xlsx';
        $tempName = tempnam(sys_get_temp_dir(), 'crud-export-xlsx-');
        $excel->save($tempName);

        return Yii::$app->response->sendFile($tempName, $fileName, ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * @param string $separator
     * @return Response
     */
    public function exportCsv($separator = ';')
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
            $values[] = $this->needRenderData ? $this->renderCell($column->renderHeaderCell()) : $this->valueToString($model->getAttributeLabel($column->attribute));
            $values2[] = $column->attribute;
        }
        fputcsv($stream, $values, $separator);
        fputcsv($stream, $values2, $separator);

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
            fputcsv($stream, $values, $separator);
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
