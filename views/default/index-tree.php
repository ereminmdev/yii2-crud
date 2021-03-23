<?php

use ereminmdev\yii2\crud\components\Crud;
use ereminmdev\yii2\crud\controllers\DefaultController;
use yii\bootstrap\Alert;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $crud Crud */
/* @var $models array */

/** @var DefaultController $controller */
$controller = $this->context;

$this->title = $controller->pageTitle;
$this->params['breadcrumbs'][] = $this->title;

$this->registerJs('
window.treeOpenUrl = "' . $controller->urlCreate(['tree-open', 'id' => '_id_']) . '";
window.treeCloseUrl = "' . $controller->urlCreate(['tree-close', 'ids' => '_ids_']) . '";
');

?>
<div class="cms-crud cms-crud-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <?php if (Yii::$app->session->hasFlash('cms-crud')): ?>
        <?= Alert::widget([
            'options' => ['class' => 'alert-success'],
            'body' => Yii::$app->session->getFlash('cms-crud'),
        ]) ?>
    <?php endif; ?>

    <?= ($view = $crud->getConfig('views.index.filter')) !== null ? $this->render($view, ['crud' => $crud]) : '' ?>

    <?= $this->render('_tree-toolbar', ['crud' => $crud]) ?>

    <br>

    <div class="cms-crud-tree">
        <?= $this->render('_tree-items', ['crud' => $crud, 'parentId' => 0, 'models' => $models]) ?>
    </div>

    <?= ($view = $crud->getConfig('views.index.footer')) !== null ? $this->render($view, ['crud' => $crud]) : '' ?>

</div>
