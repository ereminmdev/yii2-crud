<?php

use ereminmdev\yii2\crud\controllers\DefaultController;
use ereminmdev\yii2\crud\models\CrudImportForm;
use yii\bootstrap\ActiveForm;
use yii\helpers\Html;
use yii\web\JsExpression;

/* @var $this yii\web\View */
/* @var $model CrudImportForm */

/** @var DefaultController $controller */
$controller = $this->context;

$this->title = $controller->pageTitle . ' › ' . Yii::t('crud', 'Import');
$this->params['breadcrumbs'][] = ['label' => $controller->pageTitle, 'url' => $controller->urlCreate(['index'])];
$this->params['breadcrumbs'][] = Yii::t('crud', 'Import');

?>
<div class="cms-crud cms-crud-import">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin([
        'id' => 'crud_import_form',
        'options' => [
            'onSubmit' => new JsExpression('$(this).find("[type=submit]").button("loading")'),
        ],
    ]); ?>

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
        <?php if ($controller->crud->getConfig('access.save', true) && $controller->crud->getConfig('access.delete', true)): ?>
            <?= Html::submitButton('<span class="glyphicon glyphicon-ok"></span> ' . Yii::t('crud', 'Import'), ['class' => 'btn btn-primary', 'data-loading-text' => Yii::t('crud', 'Import') . '…']) ?>
             
        <?php endif; ?>
        <?= Html::a(Yii::t('crud', 'Cancel'), $controller->urlCreate(['index']), ['class' => 'btn btn-link', 'onclick' => 'window.history.back(); return false']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
