<?php

use ereminmdev\yii2\crud\controllers\DefaultController;
use ereminmdev\yii2\crud\models\CrudImportForm;
use yii\bootstrap\ActiveForm;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model CrudImportForm */

/** @var DefaultController $controller */
$controller = $this->context;

$this->title = Yii::t('crud', 'Import');
$this->params['breadcrumbs'][] = ['label' => $controller->pageTitle, 'url' => $controller->urlCreate(['index'])];
$this->params['breadcrumbs'][] = Yii::t('crud', 'Import');

?>
<div class="cms-crud cms-crud-import">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

    <?= $form->errorSummary($model) ?>

    <div class="well">
        <p><?= Yii::t('crud', 'Import items from file') ?>.</p>

        <p>Формат оформления таблицы можно посмотреть, вначале экспортировав данные.<br>
            Первые две строки таблицы необходимо оставить без изменения.<br>
            Количество и расположение колонок можно изменять.</p>

        <p>Поддерживаемые форматы файлов: <?= implode(', ', CrudImportForm::fileFormats()) ?></p>

        <br>

        <?php $accept = '.' . implode(', .', array_keys(CrudImportForm::fileFormats())); ?>
        <?= $form->field($model, 'file')->fileInput(['accept' => $accept]) ?>
    </div>

    <div class="form-group">
        <?= Html::submitButton('<span class="glyphicon glyphicon-ok"></span> ' . Yii::t('crud', 'Import'), ['class' => 'btn btn-primary']) ?>
        &nbsp;
        <?= Html::a(Yii::t('crud', 'Cancel'), $controller->urlCreate(['index']), ['class' => 'btn btn-link', 'onclick' => 'window.history.back(); return false']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
