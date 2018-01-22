<?php

namespace ereminmdev\yii2\crud\controllers;

use ereminmdev\yii2\crud\components\Crud;
use ereminmdev\yii2\crud\models\CrudExportForm;
use ereminmdev\yii2\crud\models\CrudImportForm;
use Yii;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * Class DefaultController
 * @package ereminmdev\yii2\crud\controllers
 *
 * @property null|Crud $crud
 */
class DefaultController extends Controller
{
    /**
     * @var string view page title
     */
    public $pageTitle = 'Crud';
    /**
     * @var string $_get param for model class
     */
    public $modelUrlParam = 'model';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->setCrud();

        $crud = $this->getCrud();
        $this->pageTitle = $crud->getConfig('title') ?: Yii::t('app', basename($crud->modelClass));
        $this->view->params['breadcrumbs'] = $crud->getConfig('breadcrumbs', []);
        $this->attachBehaviors($crud->getConfig('controller.behaviors', []));
    }

    public function actionIndex()
    {
        $crud = $this->getCrud();
        $view = $this->getCrud()->getConfig('views.index.view', 'index');
        $params = [
            'crud' => $crud,
            'searchModel' => $crud->getSearchModel(),
            'dataProvider' => $crud->getDataProvider(true, true, true),
            'columns' => $crud->gridColumns(),
        ];
        if (Yii::$app->request->isAjax) {
            return $this->renderPartial($view, $params);
        } else {
            return $this->render($view, $params);
        }
    }

    public function actionCreate()
    {
        $crud = $this->getCrud();

        $model = $crud->getModel('insert');
        $model->loadDefaultValues();
        $model->load(Yii::$app->request->get());

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect($this->getReturnUrl());
        } else {
            return $this->render($crud->getConfig('views.create.view', 'create'), [
                'crud' => $crud,
                'model' => $model,
            ]);
        }
    }

    public function actionUpdate($id)
    {
        $crud = $this->getCrud();

        $model = $crud->findModel($id, 'update');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            if (Yii::$app->request->isAjax) {
                return Json::encode($model->getAttributes());
            } else {
                return $this->redirect($this->getReturnUrl());
            }
        } else {
            return $this->render($crud->getConfig('views.update.view', 'update'), [
                'crud' => $crud,
                'model' => $model,
            ]);
        }
    }

    public function actionDelete($id)
    {
        $models = $this->getCrud()->getModels();

        foreach ($models as $model) {
            $model->delete();
        }

        return $this->redirect($this->getReturnUrl());
    }

    public function actionDuplicate($id)
    {
        $modelClass = $this->getCrud()->modelClass;
        $models = $this->getCrud()->getModels();

        foreach ($models as $model) {
            /* @var $cloneModel ActiveRecord */
            $cloneModel = new $modelClass;
            $cloneModel->setAttributes($model->getAttributes());

            if ($cloneModel->hasAttribute('slug')) {
                $cloneModel->setAttribute('slug', '');
            }

            $beforeDuplicate = $this->getCrud()->getConfig('onBeforeDuplicate');
            if (is_callable($beforeDuplicate)) {
                call_user_func($beforeDuplicate, $cloneModel);
            }

            if ($cloneModel->save() === false) {
                $errors = array_values($cloneModel->getFirstErrors());
                Yii::$app->session->setFlash('cms-crud', $errors[0]);
            } else {
                if ($cloneModel->getBehavior('ImageBehavior') !== null) {
                    $cloneModel->recreateImagesFromModel($model);
                }

                if ($cloneModel->hasAttribute('position')) {
                    $cloneModel->setAttribute('position', $cloneModel->getAttribute('position') + $cloneModel->getAttribute('id'));
                }

                $afterDuplicate = $this->getCrud()->getConfig('onAfterDuplicate');
                if (is_callable($afterDuplicate)) {
                    call_user_func($afterDuplicate, $cloneModel);
                }

                $cloneModel->save();
            }
        }

        return $this->redirect($this->getReturnUrl());
    }

    public function actionSetvals($id)
    {
        $model = $this->getCrud()->getFirstModel();
        $setModel = $this->getCrud()->getSetvalsModel();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $setAttributes = array_keys(array_filter($setModel->attributes, function ($val) {
                return (bool)$val;
            }));
            if (empty($setAttributes)) {
                $setModel->addError('summary', Yii::t('crud', 'Please choose a field(s)'));
            } else {
                $setVals = $model->getAttributes($setAttributes);
                $models = $this->getCrud()->getModels();
                foreach ($models as $model) {
                    $model->setAttributes($setVals);
                    $model->save();
                }
                return $this->redirect($this->getReturnUrl());
            }
        }

        return $this->render($this->getCrud()->getConfig('views.setvals.view', 'setvals'), [
            'model' => $model,
            'setModel' => $setModel,
        ]);
    }

    public function actionSortable()
    {
        $class = Yii::$app->request->get('model', $this->crud->modelClass);

        $order = Yii::$app->request->post('order', []);
        if (count($order) < 2) return;

        $positions = $this->getCrud()->getDataProvider(true, true, true)->getModels();

        foreach ($order as $id) {
            $position = array_shift($positions);
            if ($position !== null) {
                $model = $class::findOne(['id' => $id]);
                if ($model !== null) {
                    $model->position = $position->position;
                    $model->save(false);
                }
            } else {
                break;
            }
        }
    }

    public function actionExport()
    {
        $model = new CrudExportForm;

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            return $this->getCrud()->export($model);
        } else {
            return $this->render($this->getCrud()->getConfig('views.export.view', 'export'), [
                'model' => $model,
            ]);
        }
    }

    public function actionImport()
    {
        $model = new CrudImportForm;

        if ($model->load(Yii::$app->request->post())) {
            $model->file = UploadedFile::getInstance($model, 'file');

            if ($model->validate()) {
                $this->getCrud()->import($model, $model->file);
                Yii::$app->session->addFlash('info', Yii::t('crud', 'Count of imported items') . ': ' . $model->count);
                if (!$model->hasErrors()) {
                    return $this->redirect($this->getReturnUrl());
                }
            }
        }

        return $this->render($this->getCrud()->getConfig('views.import.view', 'import'), [
            'model' => $model,
        ]);
    }

    /**
     * Delete image for mongosoft/yii2-upload-behavior behavior
     * @param integer $id
     * @param string $field attribute name
     * @return \yii\console\Response|\yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionDeleteUploadImage($id, $field)
    {
        $crud = $this->getCrud();
        $model = $crud->findModel($id, 'update');

        if (in_array($field, array_keys($model->getBehaviors()), true) && ($behavior = $model->getBehavior($field))) {
            $behavior->beforeDelete();
        }

        $model->updateAttributes([$field => '']);

        if (Yii::$app->request->isAjax) {
            Yii::$app->response->content = true;
            return Yii::$app->response;
        } else {
            return $this->redirect($this->getReturnUrl());
        }
    }

    /**
     * Create url for crud
     * @param array $params
     * @param bool $scheme
     * @param bool $includeGet
     * @return string
     */
    public function urlCreate($params = [], $scheme = false, $includeGet = true)
    {
        $params = (array)$params;

        if (!isset($params[0]) || ($params[0] === true)) {
            $params[0] = '/' . Yii::$app->controller->getRoute();
        }

        $params[$this->modelUrlParam] = $this->getCrud()->modelClass;

        if ($includeGet) {
            $queryParams = Yii::$app->request->queryParams;
            unset($queryParams['id']);
            unset($queryParams['useReturnUrl']);
            $params = ArrayHelper::merge($queryParams, $params);
        }

        return Url::toRoute($params, $scheme);
    }

    /**
     * @var Crud component
     */
    private $_crud;

    /**
     * Get Crud component
     * @return Crud
     */
    public function getCrud()
    {
        if ($this->action !== null) {
            $this->_crud->setScenario($this->action->id);
        }

        return $this->_crud;
    }

    /**
     * Set Crud component
     * @param null $modelClass
     * @throws NotFoundHttpException
     */
    public function setCrud($modelClass = null)
    {
        if ($modelClass === null) {
            if (($modelClass = Yii::$app->request->get($this->modelUrlParam)) === null) {
                throw new NotFoundHttpException('There\'s no parameter "' . $this->modelUrlParam . '" in request query.');
            }
        }

        //$config = is_callable([$modelClass, 'crudConfig']) ? call_user_func([$modelClass, 'crudConfig']) : [];
        $config = call_user_func([$modelClass, 'crudConfig']);

        $this->_crud = new Crud([
            'modelClass' => $modelClass,
            'context' => $this,
            'config' => $config,
        ]);
    }

    /**
     * Get return url
     * @param array $defRoute default route
     * @return string
     */
    public function getReturnUrl($defRoute = ['index'])
    {
        return (Yii::$app->request->get('useReturnUrl', 1) && ($url = Yii::$app->request->get('returnUrl'))) ? $url : $this->urlCreate($defRoute);
    }
}
