<?php
/* @var $this yii\web\View */
/* @var $model \yii\db\ActiveRecord */
/* @var $setModel \yii\base\DynamicModel */
/* @var $form ActiveForm */

use yii\bootstrap\ActiveForm;
use yii\helpers\Html;

/** @var \ereminmdev\yii2\crud\controllers\DefaultController $controller */
$controller = $this->context;

$this->title = Yii::t('crud', 'Set values');
$this->params['breadcrumbs'][] = ['label' => $controller->pageTitle, 'url' => $controller->urlCreate(['index'])];
$this->params['breadcrumbs'][] = Yii::t('crud', 'Set values');

?>
<div class="cms-crud cms-crud-setvals">

    <h1><?= Html::encode($this->title) ?></h1>

    <div class="cms-crud-form">

        <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

        <div class="panel panel-default">
            <div class="panel-heading"><?= Yii::t('crud', 'Select the required fields') ?></div>
            <div class="panel-body">
                <?= $form->errorSummary($setModel) ?>
                <?= $controller->crud->renderFormSetvals($form, $model, $setModel) ?>
            </div>
        </div>

        <?= $form->errorSummary($model) ?>

        <?= $controller->crud->renderFormFields($form, $model) ?>

        <div class="form-group">
            <?= Html::submitButton(Yii::t('crud', 'Set values'),
                ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
            &nbsp; &nbsp;
            <?= Html::a(Yii::t('crud', 'Cancel'), $controller->urlCreate(['index'])) ?>
        </div>

        <?php ActiveForm::end(); ?>

    </div>

</div>
