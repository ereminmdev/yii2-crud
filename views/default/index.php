<?php
/* @var $this yii\web\View */
/* @var $crud \ereminmdev\yii2\crud\components\Crud */
/* @var $searchModel \yii\db\ActiveRecord */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $columns array grid column configuration. Each array element represents the configuration */

use yii\bootstrap\Alert;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var \ereminmdev\yii2\crud\controllers\DefaultController $controller */
$controller = $this->context;

$this->title = $controller->pageTitle;
$this->params['breadcrumbs'][] = $this->title;

$gridViewWidget = new GridView(ArrayHelper::merge([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'columns' => $columns,
    'layout' => "{items}\n{pager}\n{summary}",
    'filterPosition' => GridView::FILTER_POS_HEADER,
    'tableOptions' => ['class' => 'table table-hover'],
], $crud->getConfig('gridViewOptions', [])));

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

    <?= $this->render('_grid_toolbar', [
        'gridViewWidget' => $gridViewWidget,
        'crud' => $crud,
    ]) ?>

    <br>

    <div class="cms-crud-grid table-responsive">
        <?php $gridViewWidget->run() ?>
    </div>

</div>
