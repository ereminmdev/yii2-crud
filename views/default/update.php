<?php
/* @var $this yii\web\View */
/* @var $crud \ereminmdev\yii2\crud\components\Crud */
/* @var $model \yii\db\ActiveRecord */

use yii\helpers\Html;

$this->title = Yii::t('crud', 'Update');
$this->params['breadcrumbs'][] = ['label' => $this->context->pageTitle, 'url' => $this->context->urlCreate(['index'])];
$this->params['breadcrumbs'][] = Yii::t('crud', 'Update');

?>
<div class="cms-crud cms-crud-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render($crud->getConfig('views.form', '_form'), [
        'crud' => $crud,
        'model' => $model,
    ]) ?>

</div>
