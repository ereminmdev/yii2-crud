<?php
use ereminmdev\yii2\crud\models\CrudImportForm;
use yii\bootstrap\ActiveForm;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model CrudImportForm */

$this->title = Yii::t('crud', 'Import');
$this->params['breadcrumbs'][] = ['label' => $this->context->pageTitle, 'url' => $this->context->urlCreate(['index'])];
$this->params['breadcrumbs'][] = Yii::t('crud', 'Import');

?>

<div class="cms-crud cms-crud-import">

    <h1><?= Html::encode($this->title) ?></h1>

    <p><?= Yii::t('crud', 'Import items from file') ?>.</p>

    <div class="well">
        Формат оформления таблицы можно посмотреть, вначале экспортировав данные.<br>
        Первые две строки таблицы необходимо оставить без изменения.<br>
        Количество и расположение колонок можно изменять.
    </div>

    <p>Поддерживаемые форматы файлов: <?= Html::dropDownList('format', null, CrudImportForm::fileFormats()) ?></p>

    <br>

    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

    <?= $form->errorSummary($model) ?>

    <?= $form->field($model, 'file')->fileInput() ?>

    <div class="form-group">
        <?= Html::submitButton(Yii::t('crud', 'Import'), ['class' => 'btn btn-primary']) ?>
        &nbsp; &nbsp;
        <?= Html::a(Yii::t('crud', 'Cancel'), $this->context->urlCreate(['index'])) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
