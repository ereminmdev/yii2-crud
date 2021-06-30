<?php

use ereminmdev\yii2\crud\components\Crud;
use ereminmdev\yii2\crud\controllers\DefaultController;
use yii\db\ActiveRecord;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $crud Crud */
/* @var $model ActiveRecord */

/** @var DefaultController $controller */
$controller = $this->context;

$this->title = $controller->pageTitle . ' › ' . Yii::t('crud', 'Update');
$this->params['breadcrumbs'][] = ['label' => $controller->pageTitle, 'url' => $controller->urlCreate(['index'])];
$this->params['breadcrumbs'][] = Yii::t('crud', 'Update');

?>
<div class="cms-crud cms-crud-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render($crud->getConfig('views.form', '_form'), [
        'crud' => $crud,
        'model' => $model,
    ]) ?>

</div>
