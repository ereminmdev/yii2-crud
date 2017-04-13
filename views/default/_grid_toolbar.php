<?php
use ereminmdev\yii2\crud\components\PerPage;
use yii\bootstrap\ButtonDropdown;
use yii\helpers\Html;

/* @var $this yii\web\View */

/* @var $crud \ereminmdev\yii2\crud\components\Crud */
$crud = $this->context->crud;

?>

<div class="btn-toolbar" role="toolbar">
    <?php if ($crud->getConfig('access.delete', true)): ?>
        <?= $crud->renderCheckedActions($gridViewWidget->id) ?>

        <?= ButtonDropdown::widget([
            'label' => '<span class="glyphicon glyphicon-asterisk"></span>',
            'encodeLabel' => false,
            'dropdown' => [
                'items' => [
                    ['label' => Yii::t('crud', 'Export'), 'url' => $this->context->urlCreate(['export'])],
                    ['label' => Yii::t('crud', 'Import'), 'url' => $this->context->urlCreate(['import'])],
                    '<li role="presentation" class="divider"></li>',
                    [
                        'label' => Yii::t('crud', 'Delete all'),
                        'url' => $this->context->urlCreate(['delete', 'id' => 'all']),
                        'linkOptions' => [
                            'data-confirm' => Yii::t('crud', 'Are you sure you want to delete all items?'),
                        ],
                    ],
                ],
            ],
            'options' => [
                'class' => 'btn-default',
                'title' => Yii::t('crud', 'Common actions'),
            ],
            'containerOptions' => ['class' => 'btn-default pull-right'],
        ]); ?>
    <?php endif; ?>

    <div class="btn-group" role="group">
        <?= Html::a(Yii::t('crud', 'Create'), $this->context->urlCreate(['create']), ['class' => 'btn btn-success']) ?>
    </div>

    <?php
    $items = (new PerPage())->getMenuItems();
    $items[] = '<li role="presentation" class="divider"></li>';
    $items[] = [
        'label' => Yii::t('crud', 'Reset filter'),
        'url' => $this->context->urlCreate([], false, false),
    ];
    ?>
    <?= ButtonDropdown::widget([
        'label' => '<span class="glyphicon glyphicon-filter"></span>',
        'encodeLabel' => false,
        'split' => true,
        'dropdown' => ['items' => $items],
        'options' => [
            'title' => Yii::t('crud', 'Items per page'),
            'class' => 'btn btn-default toggle-filters',
        ],
        'containerOptions' => ['class' => 'btn-default pull-right'],
    ]); ?>
    <?php // js/crud.js ?>
</div>
