<?php

use ereminmdev\yii2\crud\controllers\DefaultController;
use ereminmdev\yii2\crud\models\CrudExportForm;
use yii\bootstrap\ActiveForm;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model CrudExportForm */

/** @var DefaultController $controller */
$controller = $this->context;

$this->title = $controller->pageTitle . ' › ' . Yii::t('crud', 'Export');
$this->params['breadcrumbs'][] = ['label' => $controller->pageTitle, 'url' => $controller->urlCreate(['index'])];
$this->params['breadcrumbs'][] = Yii::t('crud', 'Export');

?>
<div class="cms-crud cms-crud-export">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(); ?>

    <div class="well">
        <p><?= Yii::t('crud', 'Export items to file') ?>.</p>

        <p><?= Yii::t('crud', 'When unloading accounted applied to the filter table') ?>.</p>

        <p>&nbsp;</p>

        <?= $form->field($model, 'fileFormat')->dropDownList(CrudExportForm::fileFormats()) ?>

        <?= $form->field($model, 'needRenderData')->checkbox() ?>
    </div>

    <div class="form-group">
        <?= Html::submitButton('<span class="glyphicon glyphicon-ok"></span> ' . Yii::t('crud', 'Export'), ['class' => 'btn btn-primary']) ?>
        &nbsp;
        <?= Html::a(Yii::t('crud', 'Cancel'), $controller->urlCreate(['index']), ['class' => 'btn btn-link', 'onclick' => 'window.history.back(); return false']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
