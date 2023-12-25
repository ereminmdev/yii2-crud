<?php

use ereminmdev\yii2\crud\controllers\DefaultController;
use yii\base\DynamicModel;
use yii\bootstrap\ActiveForm;
use yii\db\ActiveRecord;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model ActiveRecord */
/* @var $columns array */
/* @var $onlyColumns array */

/** @var DefaultController $controller */
$controller = $this->context;

$this->title = $controller->pageTitle . ' › ' . Yii::t('crud', 'Set columns');
$this->params['breadcrumbs'][] = ['label' => $controller->pageTitle, 'url' => $controller->urlCreate(['index'])];
$this->params['breadcrumbs'][] = Yii::t('crud', 'Set columns');

?>
<div class="cms-crud cms-crud-set-columns">

    <h1><?= Html::encode($this->title) ?></h1>

    <div class="cms-crud-form">

        <?php $form = ActiveForm::begin(['id' => 'cms-crud-set-columns-form']); ?>

        <?php foreach ($columns as $attribute): ?>
            <div class="checkbox">
                <label>
                    <?= Html::checkbox('columns[]', in_array($attribute, $onlyColumns), ['value' => $attribute]) ?>
                    <?= $model->getAttributeLabel($attribute) ?>
                </label>
            </div>
        <?php endforeach; ?>

        <br>

        <div class="form-group">
            <?= Html::submitButton('<span class="glyphicon glyphicon-ok"></span> ' . Yii::t('crud', 'Save'), ['class' => 'btn btn-primary']) ?>
             
            <?= Html::submitButton(Yii::t('crud', 'Reset settings'), ['class' => 'btn btn-default', 'name' => 'columns', 'value' => '']) ?>
             
            <?= Html::a(Yii::t('crud', 'Cancel'), $controller->urlCreate(['index']), ['class' => 'btn btn-link', 'onclick' => 'window.history.back(); return false']) ?>
        </div>

        <?php ActiveForm::end(); ?>

    </div>

</div>
