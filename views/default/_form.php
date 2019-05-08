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

    <hr>

    <div class="form-group form-buttons">
        <?= Html::submitButton($model->isNewRecord ? Yii::t('crud', 'Create') : Yii::t('crud', 'Update'),
            ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
        &nbsp;
        <?= Html::a(Yii::t('crud', 'Cancel'), $controller->getReturnUrl(), ['class' => 'btn btn-link', 'onclick' => 'window.history.back(); return false']) ?>

        <?php if (!$model->isNewRecord && $crud->getConfig('access.delete', true)): ?>
            <div class="pull-right">
                <?= Html::a(Yii::t('crud', 'Delete'), $controller->urlCreate(['delete', 'id' => $model->getPrimaryKey()]),
                    ['class' => 'btn btn-default', 'data-confirm' => Yii::t('yii', 'Are you sure you want to delete this item?'), 'data-form' => 'delete record']) ?>
            </div>
        <?php endif; ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
