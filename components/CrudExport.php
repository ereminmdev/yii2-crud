<?php

namespace ereminmdev\yii2\crud\components;

use Yii;
use yii\base\Object;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\grid\GridView;
use yii\helpers\Inflector;
use yii\web\Response;


/**
 * Class CrudExport
 * @package ereminmdev\yii2\crud\components
 */
class CrudExport extends Object
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
            'csv' => Yii::t('crud', 'CSV (delimiter - comma)') . ' (*.csv)',
            'htm' => Yii::t('crud', 'Web page') . ' (*.htm)',
            //'pdf' => Yii::t('crud', 'Adobe acrobat PDF') . ' (*.pdf)',
        ];
    }

    /**
     * @return Response
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

        $this->fileName = $this->fileName ?: 'Export_' . Inflector::camelize($this->model->className()) . '_' . date('d.m.Y');

        //require_once Yii::getAlias('@vendor/phpoffice/phpexcel/Classes/PHPExcel.php');
        \PHPExcel_Settings::setLocale(Yii::$app->language);

        $objPHPExcel = new \PHPExcel();
        //$objPHPExcel->getProperties()->setCreator(Yii::$app->params['appName']);
        $objWorksheet = $objPHPExcel->setActiveSheetIndex(0);

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

        $colI = 0;
        foreach ($columns as $column) {
            $objWorksheet->setCellValueByColumnAndRow($colI, $rowI, $column->attribute);
            $headerValue = $this->renderData ? getHeaderValue($column->renderHeaderCell()) :
                $model->getAttributeLabel($column->attribute);
            $objWorksheet->setCellValueByColumnAndRow($colI, $rowI + 1, $headerValue);
            $colI++;
        }
        $rowI = 2;

        foreach ($models as $index => $model) {
            $colI = 0;
            $rowI++;
            $key = $keys[$index];
            foreach ($columns as $column) {
                $attribute = $column->attribute;
                $value = $this->renderData ? getValue($column->renderDataCell($model, $key, $index)) : $model->getAttribute($attribute);
                $objWorksheet->setCellValueByColumnAndRow($colI, $rowI, $value);
                $colI++;
            }
        }

        switch ($this->format) {
            case 'xlsx':
                $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
                $fileName = $this->fileName . '.xlsx';
                $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'xls':
                $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
                $fileName = $this->fileName . '.xls';
                $mimeType = 'application/vnd.ms-excel';
                break;
            case 'htm':
                $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'HTML');
                $fileName = $this->fileName . '.htm';
                $mimeType = 'text/html';
                break;
            /*case 'pdf':
                $this->initPdfWriter();
                $objWriter = new \PHPExcel_Writer_PDF($objPHPExcel);
                $fileName = $this->fileName . '.pdf';
                $mimeType = 'application/pdf';
                break;*/
            default:
                $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'CSV');
                $objWriter->setDelimiter(';');//->setExcelCompatibility(true);
                $fileName = $this->fileName . '.csv';
                $mimeType = 'text/csv';
        }

        ob_start();
        $objWriter->save('php://output');
        $objPHPExcel->disconnectWorksheets();
        unset($objPHPExcel);
        $content = ob_get_contents();
        ob_end_clean();

        if ($this->format == 'csv') {
            $content = iconv('UTF-8', 'CP1251//TRANSLIT', $content);
        }

        return Yii::$app->response->sendContentAsFile($content, $fileName, ['mimeType' => $mimeType]);
    }

    /*public function initPdfWriter()
    {
        // First, install library: composer require tecnickcom/tcpdf
        $rendererName = \PHPExcel_Settings::PDF_RENDERER_TCPDF;
        $rendererLibraryPath = Yii::getAlias('@vendor/tecnickcom/tcpdf');

        if (!\PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
            die('Please set the $rendererName and $rendererLibraryPath values as appropriate for your directory structure');
        }
    }*/
}
