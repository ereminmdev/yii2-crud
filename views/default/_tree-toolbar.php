<?php

use ereminmdev\yii2\crud\components\Crud;
use ereminmdev\yii2\crud\controllers\DefaultController;
use yii\bootstrap\ButtonDropdown;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $crud Crud */

/** @var DefaultController $controller */
$controller = $this->context;

?>
<div class="btn-toolbar" role="toolbar">
    <?php

    $template = $crud->getConfig('treeToolbarTemplate', "{create}\n{custom}\n{full}");

    $actions = [];

    $actions['{create}'] = Html::a('<span class="glyphicon glyphicon-plus"></span> ' . Yii::t('crud', 'Create'), $controller->urlCreate(['create']), ['class' => 'btn btn-success']);

    $actions['{custom}'] = '';

    $actions['{full}'] = ButtonDropdown::widget([
        'label' => '<span class="glyphicon glyphicon-asterisk"></span>',
        'encodeLabel' => false,
        'dropdown' => [
            'items' => [
                ['label' => Yii::t('crud', 'Export'), 'url' => $controller->urlCreate(['export'])],
                ['label' => Yii::t('crud', 'Import'), 'url' => $controller->urlCreate(['import'])],
                '<li role="presentation" class="divider"></li>',
                ['label' => Yii::t('crud', 'Reset filter'), 'url' => $controller->urlCreate([], false, false)],
                '<li role="presentation" class="divider"></li>',
                [
                    'label' => Yii::t('crud', 'Delete all'),
                    'url' => $controller->urlCreate(['delete', 'id' => 'all']),
                    'linkOptions' => [
                        'data-confirm' => Yii::t('crud', 'Are you sure you want to delete all items?'),
                    ],
                ],
            ],
        ],
        'options' => [
            'title' => Yii::t('crud', 'Common actions'),
            'class' => 'btn btn-default',
        ],
        'containerOptions' => ['class' => 'pull-right'],
    ]);

    if (!$crud->getConfig('access.delete', true)) {
        $actions['{checks}'] = $actions['{full}'] = '';
    }

    $customActions = $crud->getConfig('treeToolbarActions', []);
    foreach ($customActions as $key => $customAction) {
        $actions[$key] = $customAction instanceof Closure ? call_user_func_array($customAction, [$crud, $this]) : $customAction;
    }

    echo strtr($template, $actions);

    ?>
</div>