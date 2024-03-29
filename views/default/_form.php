<?php

use ereminmdev\yii2\crud\components\Crud;
use ereminmdev\yii2\crud\controllers\DefaultController;
use yii\bootstrap\ActiveForm;
use yii\db\ActiveRecord;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $crud Crud */
/* @var $model ActiveRecord */

/** @var DefaultController $controller */
$controller = $this->context;

?>
<div class="cms-crud-form">

    <?php $form = ActiveForm::begin([
        'options' => [
            'id' => 'crudFormData',
            'enctype' => 'multipart/form-data',
        ],
    ]); ?>

    <?= $form->errorSummary($model); ?>

    <?= $controller->crud->renderFormFields($form, $model) ?>

    <div class="form-group form-buttons">
        <?php if ($crud->getConfig('access.save', true)): ?>
            <?= Html::submitButton('<span class="glyphicon glyphicon-ok"></span> ' . ($model->isNewRecord ? Yii::t('crud', 'Create') : Yii::t('crud', 'Save')), ['class' => 'btn btn-primary']) ?>
             
            <?= Html::submitButton(Yii::t('crud', 'Apply'), ['class' => 'btn btn-default', 'name' => 'submit-apply', 'value' => 1]) ?>
             
        <?php endif; ?>
        <?= Html::a(Yii::t('crud', 'Cancel'), $controller->getReturnUrl(), ['class' => 'btn btn-link']) ?>

        <?php if (!$model->isNewRecord && $crud->getConfig('access.delete', true)): ?>
            <div class="pull-right">
                <?= Html::a('<span class="glyphicon glyphicon-remove"></span> ' . Yii::t('crud', 'Delete'), $controller->urlCreate(['delete', 'id' => $model->getPrimaryKey()]),
                    ['class' => 'btn btn-danger', 'data-confirm' => Yii::t('yii', 'Are you sure you want to delete this item?'), 'data-form' => 'delete record']) ?>
            </div>
        <?php endif; ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
