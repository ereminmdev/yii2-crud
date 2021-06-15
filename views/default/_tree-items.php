<?php

use ereminmdev\yii2\crud\components\Crud;
use ereminmdev\yii2\crud\controllers\DefaultController;
use ereminmdev\yii2\sortablejs\SortableJs;
use yii\bootstrap\Dropdown;
use yii\bootstrap\Html;
use yii\db\ActiveRecord;

/* @var $this yii\web\View */
/* @var $crud Crud */
/* @var $parentId int */
/* @var $models ActiveRecord[] */

/** @var DefaultController $controller */
$controller = $this->context;

$items = array_filter($models, function ($model) use ($parentId, $crud) {
    return $model->{$crud->treeParentField} == $parentId;
});

if (count($items) == 0) {
    return;
}

if ($crud->treeSortField) {
    $this->registerJs('if (Sortable.active) Sortable.active.destroy();');
    echo SortableJs::widget([
        'elementSelector' => '.tree-items',
        'storeSetAction' => $controller->urlCreate(['tree-sortable']),
        'clientOptions' => [
            'group' => 'tree-items',
            'handle' => '.tree-item--sort-handle',
            'draggable' => '.tree-item',
        ],
    ]);
}

?>
<div class="tree-items" data-parent-id="<?= $parentId ?>">
    <?php foreach ($items as $model): ?>
        <?php
        $children = array_filter($models, function ($child) use ($model, $crud) {
            return $child->{$crud->treeParentField} == $model->id;
        });
        ?>
        <div class="tree-item<?= count($children) ? ' open' : '' ?>" data-id="<?= $model->id ?>">
            <div class="tree-item-row">
                <input type="checkbox" value="<?= $model->getPrimaryKey() ?>" class="tree-item--checkbox js-check-action">
                <div class="tree-item--menu">
                    <div class="dropdown">
                        <a href="#" data-toggle="dropdown"
                           class="dropdown-toggle<?= $crud->treeSortField ? ' tree-item--sort-handle' : '' ?>"><i
                                    class="glyphicon glyphicon-option-vertical"></i></a>
                        <?= Dropdown::widget(['items' => $crud->getTreeActions($model, $controller)]) ?>
                    </div>
                </div>
                <div class="tree-item--opener">
                    <?php if (count($model->{$crud->treeChildrenRelation})): ?>
                        <?= Html::a('<i class="glyphicon glyphicon-chevron-right"></i>', $controller->urlCreate(['tree-open', 'id' => $model->id]), ['class' => 'js-tree-open']) ?>
                        <?= Html::a('<i class="glyphicon glyphicon-chevron-down"></i>', $controller->urlCreate(['tree-close', 'ids' => $model->id]), ['class' => 'js-tree-close']) ?>
                    <?php endif; ?>
                </div>
                <div class="tree-item--title">
                    <?= $crud->getTreeTitleBlock($model, $controller) ?>
                </div>
                <div class="tree-item--right">
                    <?= $crud->getTreeRightBlock($model, $controller) ?>
                </div>
            </div>
            <?= count($children) ? $this->render('_tree-items', ['crud' => $crud, 'parentId' => $model->id, 'models' => $models]) : '' ?>
        </div>
    <?php endforeach; ?>
</div>
