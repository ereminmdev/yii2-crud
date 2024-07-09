<?php

namespace ereminmdev\yii2\crud\controllers;

use Closure;
use elFinder;
use elFinderConnector;
use ereminmdev\yii2\crud\components\Crud;
use ereminmdev\yii2\crud\models\CrudExportForm;
use ereminmdev\yii2\crud\models\CrudImportForm;
use ereminmdev\yii2\elfinder\ElfinderBaseAsset;
use Exception;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use Throwable;
use Yii;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\StaleObjectException;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Cookie;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\RangeNotSatisfiableHttpException;
use yii\web\Response;
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
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        if (in_array($action->id, ['sortable', 'tree-sortable', 'columns-sortable'])) {
            $this->enableCsrfValidation = false;
        }

        $crud = $this->getCrud();
        if (in_array($action->id, ['create', 'update', 'duplicate', 'setvals', 'js-edit-prompt', 'import']) && !$crud->getConfig('access.save', true)) {
            throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
        }
        if (in_array($action->id, ['delete', 'delete-upload-file', 'delete-upload-image']) && !$crud->getConfig('access.delete', true)) {
            throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
        }

        return parent::beforeAction($action);
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        $this->setCrud();

        $crud = $this->getCrud();
        $this->pageTitle = $crud->getConfig('title') ?: Yii::t('app', StringHelper::basename($crud->modelClass));
        $this->view->params['breadcrumbs'] = $crud->getConfig('breadcrumbs', []);
        $this->attachBehaviors($crud->getConfig('controller.behaviors', []));
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function actionIndex()
    {
        if ($this->getCrud()->isViewAsTree()) {
            return $this->actionTree();
        } else {
            return $this->actionGrid();
        }
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function actionGrid()
    {
        $crud = $this->getCrud();
        $crud->setViewAs(Crud::VIEW_AS_GRID);

        $view = $crud->getConfig('views.index.view', 'index');

        $params = [
            'crud' => $crud,
            'searchModel' => $crud->getSearchModel(),
            'dataProvider' => $crud->getDataProvider(true, true, true),
            'columns' => $crud->gridColumns(),
        ];

        if (Yii::$app->request->isAjax) {
            return $this->renderAjax($view, $params);
        } else {
            return $this->render($view, $params);
        }
    }

    /**
     * @return string
     */
    public function actionTree()
    {
        $crud = $this->getCrud();
        $crud->setViewAs(Crud::VIEW_AS_TREE);

        $openIds = $this->getTreeOpenIds();
        $models = $this->findTreeModelsByParentId($openIds);

        $view = $crud->getConfig('views.index.view', 'index-tree');

        return $this->render($view, [
            'crud' => $crud,
            'models' => $models,
        ]);
    }

    /**
     * @return string|Response
     */
    public function actionCreate()
    {
        $crud = $this->getCrud();

        $model = $crud->getModel('insert');
        $model->load(Yii::$app->request->get());
        $model->loadDefaultValues(true);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            if (Yii::$app->request->post('submit-apply') && !Yii::$app->request->isAjax) {
                return $this->redirect($this->urlCreate(['update', 'id' => $model->id]));
            }
            $url = $this->getActionSuccessUrl('create', [
                'model' => $model,
            ]);
            return $this->redirect($url);
        } else {
            return $this->render($crud->getConfig('views.create.view', 'create'), [
                'crud' => $crud,
                'model' => $model,
            ]);
        }
    }

    /**
     * @param int $id
     * @return string|Response
     * @throws NotFoundHttpException
     */
    public function actionUpdate($id)
    {
        $crud = $this->getCrud();

        $model = $crud->findModel($id, 'update');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            if (Yii::$app->request->post('submit-apply') && !Yii::$app->request->isAjax) {
                return $this->refresh();
            } elseif (Yii::$app->request->isAjax) {
                return Json::encode($model->getAttributes());
            }
            $url = $this->getActionSuccessUrl('update', [
                'model' => $model,
            ]);
            return $this->redirect($url);
        } else {
            return $this->render($crud->getConfig('views.update.view', 'update'), [
                'crud' => $crud,
                'model' => $model,
            ]);
        }
    }

    /**
     * @return Response
     * @throws InvalidConfigException
     * @throws StaleObjectException
     * @throws Exception|Throwable
     */
    public function actionDelete()
    {
        $crud = $this->getCrud();

        $models = $this->getCrud()->getModels();

        foreach ($models as $model) {
            if ($crud->isViewAsTree()) {
                $this->deleteTreeChildModels($model);
            }
            if (($model->delete() === false) && $model->hasErrors()) {
                Yii::$app->session->addFlash('error', implode('<br>', $model->getErrorSummary(false)));
            }
        }

        $url = $this->getActionSuccessUrl('delete', [
            'models' => $models,
        ]);
        return $this->redirect($url);
    }

    /**
     * @param int $id
     * @return string|Response
     */
    public function actionTreeOpen($id)
    {
        $ids = $this->getTreeOpenIds();
        $ids[] = $id;
        $this->setTreeOpenIds($ids);

        if (Yii::$app->request->isAjax) {
            $models = $this->findTreeModelsByParentId($id);

            return $this->renderAjax('_tree-items', [
                'crud' => $this->getCrud(),
                'parentId' => $id,
                'models' => $models,
            ]);
        } else {
            return $this->redirect($this->urlCreate(['index']));
        }
    }

    /**
     * @param string $ids
     * @return string|Response
     */
    public function actionTreeClose($ids)
    {
        $ids = explode(',', $ids);
        $this->removeTreeOpenIds($ids);

        if (Yii::$app->request->isAjax) {
            return '';
        } else {
            return $this->redirect($this->urlCreate(['index']));
        }
    }

    /**
     * @throws InvalidConfigException
     */
    public function actionTreeSortable()
    {
        $order = Yii::$app->request->post('order', []);
        $oldOrder = Yii::$app->request->post('oldOrder', []);
        $newOrder = array_diff($order, $oldOrder);
        if ((count($order) < 2) || (count($order) < count($oldOrder))) {
            return;
        }

        $crud = $this->getCrud();
        $modelClass = $crud->modelClass;
        $sortField = $crud->treeSortField;
        $positions = $modelClass::find()->andWhere(['id' => $order])->all();

        $parent_id = 0;
        foreach ($positions as $position) {
            if (!in_array($position->id, $newOrder)) {
                $parent_id = $position->{$crud->treeParentField};
                break;
            }
        }

        foreach ($order as $id) {
            $position = array_shift($positions);
            if ($position !== null) {
                $model = $modelClass::findOne($id);
                if ($model !== null) {
                    if (in_array($model->id, $newOrder)) {
                        $model->{$crud->treeParentField} = $parent_id;
                    }
                    $model->$sortField = $position->$sortField;
                    $model->save(false);
                }
            } else {
                break;
            }
        }
    }

    /**
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionDuplicate()
    {
        $columnsSchema = $this->getCrud()->columnsSchema();
        $modelClass = $this->getCrud()->modelClass;
        $models = $this->getCrud()->getModels();

        foreach ($models as $model) {
            /* @var $cloneModel ActiveRecord */
            $cloneModel = new $modelClass;
            $cloneModel->setAttributes($model->getAttributes());

            if ($cloneModel->hasAttribute('slug')) {
                $cloneModel->setAttribute('slug', null);
            }
            if ($cloneModel->hasAttribute('position')) {
                $cloneModel->setAttribute('position', null);
            }

            $beforeDuplicate = $this->getCrud()->getConfig('onBeforeDuplicate');
            if ($beforeDuplicate instanceof Closure) {
                call_user_func($beforeDuplicate, $cloneModel, $model);
            }

            if ($cloneModel->save() === false) {
                $errors = array_values($cloneModel->getFirstErrors());
                Yii::$app->session->setFlash('cms-crud', $errors[0]);
            } else {
                foreach ($columnsSchema as $attribute => $scheme) {
                    if (isset($scheme['type']) && ($scheme['type'] == 'cropper-image-upload') && ($filename = $model->getAttribute($attribute))) {
                        $cloneModel->createFromUrl($model->getUploadPath($attribute));
                    }
                }

                $afterDuplicate = $this->getCrud()->getConfig('onAfterDuplicate');
                if ($afterDuplicate instanceof Closure) {
                    call_user_func($afterDuplicate, $cloneModel, $model);
                }

                $cloneModel->save();
            }
        }

        $url = $this->getActionSuccessUrl('duplicate', [
            'models' => $models,
        ]);
        return $this->redirect($url);
    }

    /**
     * @return string|Response
     * @throws InvalidConfigException
     */
    public function actionSetvals()
    {
        $model = $this->getCrud()->getFirstModel();
        $setModel = $this->getCrud()->getSetvalsModel();

        if (!$model || !$setModel) {
            Yii::$app->session->addFlash('info', Yii::t('yii', 'No results found.'));
            return $this->redirect($this->getReturnUrl());
        }

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $setAttributes = array_keys(array_filter($setModel->attributes, fn($val) => (bool)$val));
            if (empty($setAttributes)) {
                $setModel->addError('summary', Yii::t('crud', 'Please choose a field(s)'));
            } else {
                $setVals = $model->getAttributes($setAttributes);
                $columnsSchema = $this->getCrud()->columnsSchema();
                $models = $this->getCrud()->getModels();

                foreach ($models as $saveModel) {
                    $saveModel->setAttributes($setVals);

                    foreach ($setAttributes as $setAttribute) {
                        if (isset($columnsSchema[$setAttribute]['type']) && ($columnsSchema[$setAttribute]['type'] == 'cropper-image-upload')) {
                            $saveModel->createFromUrl($model->getUploadPath($setAttribute));
                        }
                    }

                    $saveModel->save();
                }

                $url = $this->getActionSuccessUrl('setvals', [
                    'models' => $models,
                ]);
                return $this->redirect($url);
            }
        }

        return $this->render($this->getCrud()->getConfig('views.setvals.view', 'setvals'), [
            'id' => $this->getCrud()->getRequestModelIds(),
            'model' => $model,
            'setModel' => $setModel,
        ]);
    }

    /**
     * @return string|Response
     * @throws InvalidConfigException
     */
    public function actionSetColumns()
    {
        $crud = $this->getCrud();
        $model = $crud->getModel();

        $columns = array_diff(array_keys($model->attributeLabels()), array_keys(array_filter($crud->getConfig('gridColumns', []), function ($value) {
            return $value === false;
        })));
        $onlyColumns = $crud->getGridColumnsOnly($columns);

        $order = Yii::$app->request->cookies->getValue($crud->getGridColumnsOnlyStoreKey() . '-order', []);
        $columns = $order ? array_intersect($order, $columns) : $columns;

        if (Yii::$app->request->isPost) {
            $newColumns = Yii::$app->request->post('columns');

            if ($newColumns !== null) {
                $crud->setGridColumnsOnly($newColumns);
            }

            $url = $this->getActionSuccessUrl('set-columns');
            return $this->redirect($url);
        }

        return $this->render($this->getCrud()->getConfig('views.set-columns.view', 'set-columns'), [
            'model' => $model,
            'columns' => $columns,
            'onlyColumns' => $onlyColumns,
        ]);
    }

    /**
     * @throws InvalidConfigException
     */
    public function actionColumnsSortable()
    {
        $order = (array)Yii::$app->request->post('order');
        $oldOrder = (array)Yii::$app->request->post('oldOrder');

        if ((count($order) < 2) || (count($order) !== count($oldOrder))) {
            return;
        }

        Yii::$app->response->cookies->add(new Cookie([
            'name' => $this->crud->getGridColumnsOnlyStoreKey() . '-order',
            'value' => $order,
            'expire' => strtotime('+30 days'),
        ]));
    }

    public function actionJsEditPrompt()
    {
        $this->response->format = Response::FORMAT_RAW;
        Yii::$app->response->statusCode = 400;

        if (Yii::$app->request->isPost) {
            try {
                $id = (int)Yii::$app->request->post('id', 0);
                $column = Yii::$app->request->post('column', '');
                $value = Yii::$app->request->post('value', '');

                $crud = $this->getCrud();

                $model = $crud->findModel($id, 'update');
                $model->setAttribute($column, $value);

                if ($model->save(true, [$column])) {
                    Yii::$app->response->statusCode = 200;
                    return $value;
                } elseif ($model->hasErrors($column)) {
                    return $model->getFirstError($column);
                }

            } catch (Exception $e) {
                return $e->getMessage();
            }
        }

        return 'Bad Request';
    }

    /**
     * @throws InvalidConfigException
     */
    public function actionSortable()
    {
        $order = (array)Yii::$app->request->post('order');
        $oldOrder = (array)Yii::$app->request->post('oldOrder');

        if ((count($order) < 2) || (count($order) !== count($oldOrder))) {
            return;
        }

        $crud = $this->getCrud();
        $modelClass = $crud->modelClass;
        $models = $modelClass::find()->andWhere(['id' => $order])->indexBy('id')->all();

        $positions = [];
        foreach ($models as $model) {
            $positions[$model->id] = $model->position;
        }

        foreach ($order as $idx => $id) {
            $model = $models[$id] ?? null;
            $oldId = $oldOrder[$idx] ?? null;
            $position = $positions[$oldId] ?? null;

            if (!$model || !$oldId || !$position || ($id == $oldId)) continue;

            $model->position = $position;
            $model->save(false, ['position']);
        }
    }

    /**
     * @return mixed|string
     * @throws PhpSpreadsheetException
     * @throws InvalidConfigException
     * @throws RangeNotSatisfiableHttpException
     */
    public function actionExport()
    {
        $model = new CrudExportForm;
        $model->load(Yii::$app->request->get());

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            return $this->getCrud()->export($model);
        }

        return $this->render($this->getCrud()->getConfig('views.export.view', 'export'), [
            'id' => $this->getCrud()->getRequestModelIds(),
            'model' => $model,
        ]);
    }

    /**
     * @return string|Response
     * @throws PhpSpreadsheetException
     * @throws InvalidConfigException
     */
    public function actionImport()
    {
        $model = new CrudImportForm;
        $model->load(Yii::$app->request->get());

        if ($model->load(Yii::$app->request->post())) {
            $model->file = UploadedFile::getInstance($model, 'file');

            if ($model->validate()) {
                $this->getCrud()->import($model, $model->file);
                Yii::$app->session->addFlash('info', Yii::t('crud', 'Count of imported items') . ': ' . $model->count);
                if (!$model->hasErrors()) {
                    $url = $this->getActionSuccessUrl('import', [
                        'model' => $model,
                    ]);
                    return $this->redirect($url);
                }
            }
        }

        return $this->render($this->getCrud()->getConfig('views.import.view', 'import'), [
            'model' => $model,
        ]);
    }

    /**
     * Delete file for mohorev/yii2-upload-behavior behavior
     *
     * @param integer $id
     * @param string $field attribute name
     * @return \yii\console\Response|Response
     * @throws NotFoundHttpException
     */
    public function actionDeleteUploadFile($id, $field)
    {
        $crud = $this->getCrud();
        $model = $crud->findModel($id, 'update');

        $behavior = $model->getBehavior($field) ?? $model;

        $path = $behavior->hasMethod('getUploadPath') ? $behavior->getUploadPath($field) : $behavior->getAttribute($field);
        if (is_file($path)) {
            @unlink($path);
        }

        $model->updateAttributes([$field => '']);

        if (Yii::$app->request->isAjax) {
            Yii::$app->response->content = true;
            return Yii::$app->response;
        } else {
            $url = $this->getActionSuccessUrl('delete-upload-file', [
                'model' => $model,
            ]);
            return $this->redirect($url);
        }
    }

    /**
     * Delete image for mohorev/yii2-upload-behavior behavior
     *
     * @param integer $id
     * @param string $field attribute name
     * @return \yii\console\Response|Response
     * @throws NotFoundHttpException
     */
    public function actionDeleteUploadImage($id, $field)
    {
        $crud = $this->getCrud();
        $model = $crud->findModel($id, 'update');

        if (in_array($field, array_keys($model->getBehaviors()), true) && ($behavior = $model->getBehavior($field)) && $behavior->hasMethod('removeImage')) {
            $behavior->removeImage($field);
        }

        $model->updateAttributes([$field => '']);

        if (Yii::$app->request->isAjax) {
            Yii::$app->response->content = true;
            return Yii::$app->response;
        } else {
            $url = $this->getActionSuccessUrl('delete-upload-image', [
                'model' => $model,
            ]);
            return $this->redirect($url);
        }
    }

    /**
     * Output image in jpeg format
     *
     * @param string $path
     */
    public function actionDownloadJpg($path)
    {
        if (file_exists($path)) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $im = null;

            switch ($ext) {
                case 'webp':
                    $im = @imagecreatefromwebp($path);
                    break;
                case 'png':
                    $im = @imagecreatefrompng($path);
                    break;
                case 'jpg':
                    $im = @imagecreatefromjpeg($path);
                    break;
            }

            if ($im) {
                header('Content-Type: image/jpeg');
                header('Content-Disposition: inline; filename="' . pathinfo($path, PATHINFO_FILENAME) . '.jpg"');
                imagejpeg($im, null, 80);
                imagedestroy($im);
            }

            exit(0);
        }
    }

    /**
     * Find data for select2 widget
     *
     * @param string $field
     * @return array
     * @throws InvalidConfigException
     */
    public function actionSelect2filter($field)
    {
        $q = Yii::$app->request->get('q', '');

        $crud = $this->getCrud();
        $columnsSchema = $crud->columnsSchema();
        $schema = $columnsSchema[$field] ?? [];
        $model = $crud->getModel('getfields');
        $relatedClass = $model->getRelation($schema['relation'])->modelClass;
        $relatedField = array_key_first($model->getRelation($schema['relation'])->link);
        $titleField = $schema['select2TitleField'] ?? ($schema['titleField'] ?? $field);

        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->formatters[Response::FORMAT_JSON] = [
            'class' => 'yii\web\JsonResponseFormatter',
            'encodeOptions' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ];

        $query = $relatedClass::find();

        if (isset($schema['select2Query']) && is_callable($schema['select2Query'])) {
            call_user_func_array($schema['select2Query'], [$query, $q, $crud]);
        } else {
            $query->select(['id' => $relatedField, 'text' => $titleField])
                ->andWhere(['like', $titleField, $q]);
        }

        $query->asArray()->limit(50);

        return ['results' => $query->all()];
    }

    /**
     * @param string $field
     * @param int $id
     */
    public function actionFilesConnector($field, $id)
    {
        $crud = $this->getCrud();
        $modelClass = $crud->modelClass;

        $id = Yii::$app->request->get('id');
        $model = $id ? $crud->findModel($id) : new $modelClass;

        $modelPath = $model->getFilesPath($field);
        $modelUrl = $model->getFilesUrl($field);

        $bundle = ElfinderBaseAsset::register(Yii::$app->view);
        $iconUrl = $bundle->baseUrl . '/img/volume_icon_local.png';

        $dir = Yii::getAlias('@vendor/studio-42/elfinder/php/');
        include_once $dir . 'elFinderConnector.class.php';
        include_once $dir . 'elFinder.class.php';
        include_once $dir . 'elFinderVolumeDriver.class.php';
        include_once $dir . 'elFinderVolumeLocalFileSystem.class.php';

        $basePath = Yii::getAlias('@frontend/web');
        $baseUrl = Yii::$app->urlManagerFrontend->getBaseUrl();

        $opts = ['debug' => YII_ENV_DEV, 'roots' => [
            [
                'driver' => 'LocalFileSystem',
                'path' => $modelPath,
                'URL' => $modelUrl,
                'alias' => $model->getAttributeLabel($field),
                'icon' => $iconUrl,
                'tmbPath' => $basePath . '/files/temp/elfinder/tmb',
                'tmbURL' => $baseUrl . '/files/temp/elfinder/tmb',
                'tmpPath' => '',
                'quarantine' => $basePath . '/files/temp/elfinder/quarantine',
                'uploadOverwrite' => false,
            ],
        ]];

        @mkdir($modelPath, 0777, true);
        @mkdir($basePath . '/files/temp/elfinder', 0777, true);

        $connector = new elFinderConnector(new elFinder($opts));
        $connector->run();
    }

    /**
     * Create url for crud
     *
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
     *
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
     *
     * @param null $modelClass
     * @throws InvalidArgumentException
     */
    public function setCrud($modelClass = null)
    {
        if ($modelClass === null) {
            if (($modelClass = Yii::$app->request->get($this->modelUrlParam)) === null) {
                throw new InvalidArgumentException('There\'s no parameter "' . $this->modelUrlParam . '" in request query.');
            }
        }

        $config = call_user_func([$modelClass, 'crudConfig']);

        if (!is_array($config)) {
            throw new InvalidArgumentException('Config parameter "' . $this->modelUrlParam . '" type not array.');
        }

        $this->_crud = new Crud([
            'modelClass' => $modelClass,
            'context' => $this,
            'config' => $config,
        ]);
    }

    /**
     * Get return url
     *
     * @param array $defRoute default route
     * @return string
     */
    public function getReturnUrl($defRoute = ['index'])
    {
        return (Yii::$app->request->get('useReturnUrl', 1) && ($url = Yii::$app->request->get('returnUrl'))) ? $url : $this->urlCreate($defRoute);
    }

    /**
     * @param string $action
     * @param array $params to be passed to the call_user_func_array function, as an indexed array.
     * @return mixed
     */
    public function getActionSuccessUrl($action, $params = [])
    {
        $url = $this->getCrud()->getConfig('actionSuccessUrl.' . $action, $this->getReturnUrl());
        $url = $url instanceof Closure ? call_user_func_array($url, $params) : $url;
        return $url;
    }

    /**
     * @param int|array $parent_id
     * @return ActiveRecord[]
     */
    public function findTreeModelsByParentId($parent_id)
    {
        $crud = $this->getCrud();
        return ($crud->modelClass)::find()
            ->andWhere([$crud->treeParentField => $parent_id])
            ->with([$crud->treeChildrenRelation])
            ->all();
    }

    /**
     * @param ActiveRecord $model
     */
    public function deleteTreeChildModels($model)
    {
        $children = $model->{$this->getCrud()->treeChildrenRelation};
        foreach ($children as $child) {
            $this->deleteTreeChildModels($child);
            $child->delete();
        }
    }

    /**
     * @return array
     */
    public function getTreeOpenIds()
    {
        return Yii::$app->session->get($this->getTreeStoreKey(), [0 => 0]);
    }

    /**
     * @param array $ids
     */
    public function setTreeOpenIds($ids)
    {
        $ids[] = 0;
        $ids = array_unique($ids);
        $ids = array_map('intval', $ids);
        Yii::$app->session->set($this->getTreeStoreKey(), $ids);
    }

    /**
     * @param array $removeIds
     */
    public function removeTreeOpenIds($removeIds)
    {
        $ids = $this->getTreeOpenIds();
        $ids = array_diff($ids, $removeIds);
        $this->setTreeOpenIds($ids);
    }

    /**
     * @return string
     */
    public function getTreeStoreKey()
    {
        return 'crud-tree-' . $this->getCrud()->modelClass;
    }
}
