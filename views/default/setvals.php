<?php

use ereminmdev\yii2\crud\controllers\DefaultController;
use yii\base\DynamicModel;
use yii\bootstrap\ActiveForm;
use yii\db\ActiveRecord;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $id string */
/* @var $model ActiveRecord */
/* @var $setModel DynamicModel */
/* @var $form ActiveForm */

/** @var DefaultController $controller */
$controller = $this->context;

$this->title = $controller->pageTitle . ' › ' . Yii::t('crud', 'Set values');
$this->params['breadcrumbs'][] = ['label' => $controller->pageTitle, 'url' => $controller->urlCreate(['index'])];
$this->params['breadcrumbs'][] = Yii::t('crud', 'Set values');

?>
<div class="cms-crud cms-crud-setvals">

    <h1><?= Html::encode($this->title) ?></h1>

    <div class="cms-crud-form">

        <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
        <?= Html::hiddenInput('id', $id) ?>

        <div class="panel panel-default">
            <div class="panel-heading"><?= Yii::t('crud', 'Select the required fields') ?></div>
            <div class="panel-body">
                <?= $form->errorSummary($setModel) ?>
                <div class="setvals-columns">
                    <?= $controller->crud->renderFormSetvals($form, $model, $setModel) ?>
                </div>
            </div>
        </div>

        <?= $form->errorSummary($model) ?>

        <?= $controller->crud->renderFormFields($form, $model) ?>

        <div class="form-group">
            <?php if ($controller->crud->getConfig('access.save', true)): ?>
                <?= Html::submitButton('<span class="glyphicon glyphicon-ok"></span> ' . Yii::t('crud', 'Set values'), ['class' => 'btn btn-primary']) ?>
                 
            <?php endif; ?>
            <?= Html::a(Yii::t('crud', 'Cancel'), $controller->urlCreate(['index']), ['class' => 'btn btn-link', 'onclick' => 'window.history.back(); return false']) ?>
        </div>

        <?php ActiveForm::end(); ?>

    </div>

</div>
