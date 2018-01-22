<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $crud \ereminmdev\yii2\crud\components\Crud */
/* @var $model \yii\db\ActiveRecord */

/** @var \ereminmdev\yii2\crud\controllers\DefaultController $controller */
$controller = $this->context;

$this->title = Yii::t('crud', 'Create');
$this->params['breadcrumbs'][] = ['label' => $controller->pageTitle, 'url' => $controller->urlCreate(['index'])];
$this->params['breadcrumbs'][] = Yii::t('crud', 'Create');

?>
<div class="cms-crud cms-crud-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render($crud->getConfig('views.form', '_form'), [
        'crud' => $crud,
        'model' => $model,
    ]) ?>

</div>
