<?php

use ereminmdev\yii2\crud\models\CrudExportForm;
use yii\bootstrap\ActiveForm;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model CrudExportForm */

/** @var \ereminmdev\yii2\crud\controllers\DefaultController $controller */
$controller = $this->context;

$this->title = Yii::t('crud', 'Export');
$this->params['breadcrumbs'][] = ['label' => $controller->pageTitle, 'url' => $controller->urlCreate(['index'])];
$this->params['breadcrumbs'][] = Yii::t('crud', 'Export');

?>
<div class="cms-crud cms-crud-export">

    <h1><?= Html::encode($this->title) ?></h1>

    <p><?= Yii::t('crud', 'Export items to file') ?>.</p>

    <div class="well"><?= Yii::t('crud', 'When unloading accounted applied to the filter table') ?>.</div>

    <p>&nbsp;</p>

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'fileFormat')->dropDownList(CrudExportForm::fileFormats()) ?>

    <?= $form->field($model, 'needRenderData')->checkbox() ?>

    <hr>

    <div class="form-group">
        <?= Html::submitButton(Yii::t('crud', 'Export'), ['class' => 'btn btn-primary']) ?>
        &nbsp; &nbsp;
        <?= Html::a(Yii::t('crud', 'Cancel'), $controller->urlCreate(['index'])) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
