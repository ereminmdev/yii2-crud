<?php
/* @var $this yii\web\View */
/* @var $crud \ereminmdev\yii2\crud\components\Crud */
/* @var $model \yii\db\ActiveRecord */

use yii\bootstrap\ActiveForm;
use yii\helpers\Html;

// js/crud.js

?>
<div class="cms-crud-form">

    <?php $form = ActiveForm::begin(['options' => ['id' => 'crudFormData', 'enctype' => 'multipart/form-data']]); ?>

    <?= $form->errorSummary($model); ?>

    <?= $this->context->crud->renderFormFields($form, $model) ?>

    <div class="form-group form-buttons">
        <?= Html::submitButton($model->isNewRecord ? Yii::t('crud', 'Create') : Yii::t('crud', 'Update'),
            ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
        &nbsp;
        <?= Html::a(Yii::t('crud', 'Cancel'), $this->context->getReturnUrl(), ['class' => 'btn btn-link']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
