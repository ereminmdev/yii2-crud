<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $crud \ereminmdev\yii2\crud\components\Crud */
/* @var $model \yii\db\ActiveRecord */

$this->title = Yii::t('crud', 'Create');
$this->params['breadcrumbs'][] = ['label' => $this->context->pageTitle, 'url' => $this->context->urlCreate(['index'])];
$this->params['breadcrumbs'][] = Yii::t('crud', 'Create');

?>

<div class="cms-crud cms-crud-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render($crud->getConfig('views.form', '_form'), [
        'crud' => $crud,
        'model' => $model,
    ]) ?>

</div>
