<?php

use ereminmdev\yii2\crud\components\Crud;
use ereminmdev\yii2\crud\controllers\DefaultController;
use ereminmdev\yii2\sortablejs\SortableJs;
use yii\bootstrap\Alert;
use yii\db\ActiveRecord;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $crud Crud */
/* @var $searchModel ActiveRecord */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $columns array grid column configuration. Each array element represents the configuration */

/** @var DefaultController $controller */
$controller = $this->context;

$this->title = $controller->pageTitle;
$this->params['breadcrumbs'][] = $this->title;

if ($crud->sortableJs) {
    $this->registerJs('if (Sortable.active) Sortable.active.destroy();');
    echo SortableJs::widget([
        'elementSelector' => '.cms-crud-grid table > tbody',
        'storeSetAction' => $controller->urlCreate(['/crud/default/sortable']),
        'clientOptions' => [
            'dataIdAttr' => 'data-key',
            'handle' => '.crud-grid__sort-handle',
        ],
    ]);
}
if ($crud->jsEditPrompt) {
    $this->registerJs('window.jsEditPromptUrl = "' . $controller->urlCreate(['js-edit-prompt']) . '";');
}

$gridViewWidget = new GridView(ArrayHelper::merge([
    'dataProvider' => $dataProvider,
    'columns' => $columns,
    'layout' => "{items}\n<div class=\"cms-crud-pager\">{pager}{summary}</div>",
    'filterModel' => $searchModel,
    'filterPosition' => GridView::FILTER_POS_BODY,
    'tableOptions' => ['class' => 'table table-hover'],
    'pager' => ['class' => 'ereminmdev\yii2\crud\components\Pager'],
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

    <?= ($view = $crud->getConfig('views.index.filter')) ? $this->render($view, ['crud' => $crud]) : '' ?>

    <?= $crud->getConfig('viewIndexToolbar', true) ? $this->render('_index-toolbar', ['crud' => $crud, 'gridViewWidget' => $gridViewWidget]) : '' ?>

    <div class="cms-crud-grid">
        <?php $gridViewWidget->run() ?>
    </div>

    <?= ($view = $crud->getConfig('views.index.footer')) !== null ? $this->render($view, ['crud' => $crud]) : '' ?>

</div>
