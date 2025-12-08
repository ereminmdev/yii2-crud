<?php

use ereminmdev\yii2\crud\components\Crud;
use ereminmdev\yii2\crud\controllers\DefaultController;
use yii\bootstrap\ButtonDropdown;
use yii\grid\GridView;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $crud Crud */
/* @var $gridViewWidget GridView|null */

/** @var DefaultController $controller */
$controller = $this->context;

?>
<div class="btn-toolbar" role="toolbar" style="margin-bottom: 1em">
    <?php

    if ($crud->isViewAsTree()) {
        $template = $crud->getConfig('treeToolbarTemplate', "{checks}\n{create}\n{custom}\n{full}");
    } else {
        $template = $crud->getConfig('gridToolbarTemplate', "{checks}\n{create}\n{custom}\n{full}\n{filter}");
    }
    if (!$crud->getConfig('access.save', true)) {
        $template = str_replace('{create}', '', $template);
    }

    $actions = [];

    $actions['{checks}'] = $crud->renderCheckedActions();

    $actions['{create}'] = Html::a('<span class="glyphicon glyphicon-plus"></span> ' . Yii::t('crud', 'Create'), $controller->urlCreate(['create']), ['class' => 'btn btn-success']);

    $actions['{custom}'] = '';
    $actions['{filter}'] = '';

    $items = [
        [
            'label' => Yii::t('crud', 'Set values'),
            'url' => $controller->urlCreate(['set-values', 'id' => 'all']),
            'visible' => $crud->getConfig('access.save', true),
        ],
        '<li role="presentation" class="divider"></li>',
        ['label' => Yii::t('crud', 'Export'), 'url' => $controller->urlCreate(['export'])],
        [
            'label' => Yii::t('crud', 'Import'),
            'url' => $controller->urlCreate(['import']),
            'visible' => $crud->getConfig('access.save', true) && $crud->getConfig('access.delete', true),
        ],
        '<li role="presentation" class="divider"></li>',
        ['label' => Yii::t('crud', 'Customize columns'), 'url' => $controller->urlCreate(['set-columns'])],
        '<li role="presentation" class="divider"></li>',
        ['label' => Yii::t('crud', 'Reset filters'), 'url' => $controller->urlCreate([], false, false)],
        '<li role="presentation" class="divider"></li>',
        [
            'label' => Yii::t('crud', 'Delete all'),
            'url' => $controller->urlCreate(['delete', 'id' => 'all']),
            'linkOptions' => [
                'data-confirm' => Yii::t('crud', 'Are you sure you want to delete all items?'),
            ],
            'visible' => $crud->getConfig('access.delete', true),
        ],
    ];

    if ($crud->canViewAsTree()) {
        $items = array_merge($items, [
            '<li role="presentation" class="divider"></li>',
            ['label' => Yii::t('crud', 'Grid view'), 'url' => $controller->urlCreate(['grid'], false, false)],
            ['label' => Yii::t('crud', 'Tree view'), 'url' => $controller->urlCreate(['tree'], false, false)],
        ]);
    }

    $actions['{full}'] = ButtonDropdown::widget([
        'label' => '<span class="glyphicon glyphicon-cog"></span>',
        'encodeLabel' => false,
        'dropdown' => ['items' => $items],
        'options' => [
            'title' => Yii::t('crud', 'All data actions'),
            'class' => 'btn btn-default',
        ],
        'containerOptions' => ['class' => 'pull-right'],
    ]);

    if ($crud->isViewAsTree()) {
        $customActions = $crud->getConfig('treeToolbarActions', []);
        foreach ($customActions as $key => $customAction) {
            $actions[$key] = $customAction instanceof Closure ? call_user_func_array($customAction, [$crud, $this]) : $customAction;
        }
    } else {
        $customActions = $crud->getConfig('gridToolbarActions', []);
        foreach ($customActions as $key => $customAction) {
            $actions[$key] = $customAction instanceof Closure ? call_user_func_array($customAction, [$gridViewWidget, $crud, $this]) : $customAction;
        }
    }

    echo strtr($template, $actions);

    ?>
</div>
<?= Html::beginForm('', 'post', ['id' => 'crud_checked_form', 'style' => 'display: none']) ?>
<?= Html::hiddenInput('id', '') ?>
<?= Html::endForm() ?>
