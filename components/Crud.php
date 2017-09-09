<?php

namespace ereminmdev\yii2\crud\components;

use ereminmdev\yii2\crud\controllers\DefaultController;
use ereminmdev\yii2\crud\grid\DropDownButtonColumn;
use ereminmdev\yii2\crud\models\CrudExportForm;
use ereminmdev\yii2\crud\models\CrudImportForm;
use ereminmdev\yii2\tinymce\TinyMce;
use Yii;
use yii\base\DynamicModel;
use yii\base\Object;
use yii\bootstrap\ButtonDropdown;
use yii\bootstrap\Tabs;
use yii\data\ActiveDataProvider;
use yii\db\ActiveRecord;
use yii\db\Schema;
use yii\grid\CheckboxColumn;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;
use yii\widgets\ActiveField;
use yii\widgets\ActiveForm;


/**
 * Class Crud
 * @package ereminmdev\yii2\crud\components
 *
 * @property ActiveRecord $modelScenario
 * @property ActiveRecord|null $searchModel
 * @property mixed $fields
 * @property DynamicModel $setvalsModel
 */
class Crud extends Object
{
    /**
     * @var ActiveRecord model class name
     */
    public $modelClass;
    /**
     * @var DefaultController
     */
    public $context;
    /**
     * @var array $config getting from $model->crudConfig()
     */
    public $config;
    /**
     * @var string crud scenario, like model scenario
     */
    public $scenario = 'default';


    /**
     * @param string $scenario
     */
    public function setScenario($scenario)
    {
        $this->scenario = $scenario;
    }

    /**
     * @return null|ActiveRecord
     */
    public function getSearchModel()
    {
        return $this->getModel('search');
    }

    /**
     * @param bool $filterParams
     * @param bool $pagination
     * @param bool $relations
     * @param bool $limitById
     * @return array
     */
    public function getModels($filterParams = true, $pagination = false, $relations = false, $limitById = true)
    {
        return $this->getDataProvider($filterParams, $pagination, $relations, $limitById)->getModels();
    }

    /**
     * @param bool $filterParams
     * @return null|ActiveRecord
     */
    public function getFirstModel($filterParams = true)
    {
        $dataProvider = $this->getDataProvider($filterParams);
        $dataProvider->query->limit(1);
        $models = $dataProvider->getModels();
        return isset($models[0]) ? $models[0] : null;
    }

    /**
     * @param bool $filterParams
     * @param bool $pagination
     * @param bool $relations
     * @param bool $limitById
     * @return ActiveDataProvider
     */
    public function getDataProvider($filterParams = true, $pagination = false, $relations = false, $limitById = true)
    {
        $modelClass = $this->modelClass;
        $model = $this->getModel('search');

        $dataProvider = new ActiveDataProvider([
            'query' => $modelClass::find(),
            'pagination' => [
                'pageSizeLimit' => [0, 50],
                'defaultPageSize' => 30,
            ],
            'sort' => [
                'defaultOrder' => [
                    'id' => SORT_DESC,
                ],
            ],
        ]);

        if ($pagination !== true) {
            $dataProvider->setPagination($pagination);
        }

        $configDataProvider = $this->getConfig('dataProvider');
        if ($configDataProvider instanceof \Closure) {
            call_user_func($configDataProvider, $dataProvider);
        }

        $filterParams = $filterParams === true ? Yii::$app->request->queryParams :
            ($filterParams !== false ? $filterParams : []);

        if ($limitById) {
            $filterId = Yii::$app->request->get('id', 'all');
            if ($filterId != 'all') {
                $filterId = explode(',', $filterId);
                $dataProvider->query->andWhere([$model->tableName() . '.[[id]]' => $filterId]);
            }
        }

        if ($model->load($filterParams)) {
            $columnsSchema = $this->columnsSchema();
            $formName = $model->formName();
            //foreach ($model->attributes() as $attribute) {
            foreach ($filterParams[$formName] as $attribute => $value) {
                if (isset($columnsSchema[$attribute]['type'])) {
                    switch ($columnsSchema[$attribute]['type']) {
                        case Schema::TYPE_INTEGER:
                        case Schema::TYPE_BOOLEAN:
                        case Schema::TYPE_DATE:
                        case Schema::TYPE_TIME:
                        case Schema::TYPE_DATETIME:
                        case 'array':
                        case 'url':
                            $dataProvider->query->andFilterWhere([$attribute => $value]);
                            break;
                        case 'relation':
                            if (isset($columnsSchema[$attribute]['relatedAttribute'])) {
                                $dataProvider->query->andFilterWhere([$columnsSchema[$attribute]['relatedAttribute'] => $value]);
                                $dataProvider->query->joinWith($columnsSchema[$attribute]['relation']);
                                break;
                            } else {
                                $dataProvider->query->andFilterWhere([$attribute => $value]);
                                break;
                            }
                        case 'list':
                            if (!empty($value)) {
                                $dataProvider->query->andWhere('FIND_IN_SET(:value,[[' . $attribute . ']])', [':value' => $value]);
                            }
                            break;
                        default:
                            $dataProvider->query->andFilterWhere(['like', $attribute, $value]);
                    }
                } else {
                    $dataProvider->query->andFilterWhere([$attribute => $value]);
                }
            }
        }

        if ($relations) {
            $columnsSchema = $this->columnsSchema();
            foreach ($columnsSchema as $column => $schema) {
                if ($schema['type'] == 'relation') {
                    $relation = $schema['relation'];

                    if ($schema['rtype'] == 'hasOne') {
                        if (in_array($schema['titleField'], $model->attributes())) {
                            $dataProvider->query->with([
                                $relation => function ($query) use ($schema, $relation) {
                                    $query->select(['id', $schema['titleField']]);
                                },
                            ]);
                        } else {
                            $dataProvider->query->with($relation);
                        }
                    } elseif ($schema['rtype'] == 'hasMany') {
                        $dataProvider->query->with([
                            $relation => function ($query) use ($schema, $relation, $model) {
                                $linkAttribute = array_keys($model->{'get' . $relation}()->link)[0];
                                $query->select($linkAttribute);
                            },
                        ]);
                    } elseif ($schema['rtype'] == 'manyMany') {
                        $dataProvider->query->with($relation);
                        // select only `id` for count()
                        /*$dataProvider->query->with([
                            $relation => function ($query) use ($schema, $relation, $model) {
                                $linkAttribute = array_keys($model->{'get' . $relation}()->link)[0];
                                $query->select($linkAttribute);
                            },
                        ]);*/
                    }
                }
            }
        }

        return $dataProvider;
    }

    public function gridColumns()
    {
        $columns = $this->guessColumns();

        // actions column
        array_unshift($columns, [
            'class' => DropDownButtonColumn::className(),
            'buttonDropdownOptions' => [
                'label' => '<i class="glyphicon glyphicon-menu-hamburger"></i>',
                'encodeLabel' => false,
            ],
            'items' => function ($model, $key) {
                $template = $this->getConfig('gridActionsTemplate', "{update}\n{--}\n{delete}");

                $actions = [
                    '{--}' => '<li role="presentation" class="divider"></li>',
                    '{update}' => [
                        'label' => Yii::t('crud', 'Edit'),
                        'url' => $this->columnUrlCreator('update', $model, $key),
                    ],
                    '{delete}' => [
                        'label' => Yii::t('crud', 'Delete'),
                        'url' => $this->columnUrlCreator('delete', $model, $key),
                        'linkOptions' => [
                            'data-confirm' => Yii::t('yii', 'Are you sure you want to delete this item?'),
                        ],
                        'visible' => $this->getConfig('access.delete', true),
                    ],
                ];

                $customActions = $this->getConfig('gridActions', []);
                foreach ($customActions as $key => $customAction) {
                    $actions[$key] = is_callable($customAction) ? call_user_func_array($customAction, [$model, $key, $this]) : $customAction;
                }

                $items = explode("\n", $template);
                foreach ($actions as $key => $action) {
                    foreach (array_keys($items, $key) as $pos) {
                        $items[$pos] = $action;
                    }
                }

                return $items;
            }
        ]);

        array_unshift($columns, ['class' => CheckboxColumn::className()]);

        return $columns;
    }

    public function guessColumns($fields = null)
    {
        $model = $this->getModel('getfields');

        $columns = $fields ?: (($onlyColumns = $this->getConfig('gridColumnsOnly')) != null ?
            $onlyColumns : array_keys($model->attributeLabels()));

        $paramColumns = $this->getConfig('gridColumns', []);
        $columnsSchema = $this->columnsSchema();

        $prepend = $this->getConfig('gridColumnsPrepend', []);
        $columns = ArrayHelper::merge($prepend, $columns);

        $append = $this->getConfig('gridColumnsAppend', []);
        $columns = ArrayHelper::merge($columns, $append);

        foreach ($columns as $key => $field) {
            if ($field instanceof \Closure) {
                $columns[$key] = call_user_func($field);
                continue;
            } elseif (!is_string($field)) {
                continue;
            }

            if (isset($paramColumns[$field]) && ($paramColumns[$field] !== true)) {
                if ($paramColumns[$field] instanceof \Closure) {
                    $columns[$key] = call_user_func($paramColumns[$field]);
                } elseif ($paramColumns[$field] === false) {
                    unset($columns[$key]);
                } else {
                    $columns[$key] = $paramColumns[$field];
                }
                continue;
            }

            if (isset($columnsSchema[$field])) {
                $schema = $columnsSchema[$field];

                switch ($schema['type']) {
                    case false:
                        unset($columns[$key]);
                        break;
                    case Schema::TYPE_TEXT:
                        $columns[$key] = $field . ':ntext';
                        break;
                    case 'html':
                        $columns[$key] = [
                            'attribute' => $field,
                            'format' => 'html',
                            'value' => function ($model, $key, $index, $column) {
                                $field = $column->attribute;
                                $value = $model->$field;
                                return StringHelper::truncateWords($value, 10, '...', true);
                            },
                        ];
                        break;
                    case Schema::TYPE_BOOLEAN:
                        $columns[$key] = [
                            'attribute' => $field,
                            'format' => 'boolean',
                            'filter' => Yii::$app->formatter->booleanFormat,
                        ];
                        break;
                    case Schema::TYPE_DATE:
                        $columns[$key] = [
                            'attribute' => $field,
                            //'format' => 'date',
                            'filterInputOptions' => ['type' => 'date', 'class' => 'form-control', 'id' => null],
                            'value' => function ($model, $key, $index, $column) use ($field) {
                                $value = $model->$field;
                                $value = ($value && !is_numeric($value)) ? strtotime($value) : $value;
                                return $value ? date('d.m.Y', $value) : $value;
                            },
                        ];
                        break;
                    case Schema::TYPE_TIME:
                        $columns[$key] = [
                            'attribute' => $field,
                            //'format' => 'time',
                            'filterInputOptions' => ['type' => 'time', 'class' => 'form-control', 'id' => null],
                            'value' => function ($model, $key, $index, $column) use ($field) {
                                $value = $model->$field;
                                $value = ($value && !is_numeric($value)) ? strtotime($value) : $value;
                                return $value ? date('H:i:s', $value) : $value;
                            },
                        ];
                        break;
                    case Schema::TYPE_DATETIME:
                        $columns[$key] = [
                            'attribute' => $field,
                            //'format' => 'datetime',
                            'filterInputOptions' => ['type' => 'datetime-local', 'class' => 'form-control', 'id' => null],
                            'value' => function ($model, $key, $index, $column) use ($field) {
                                $value = $model->$field;
                                $value = ($value && !is_numeric($value)) ? strtotime($value) : $value;
                                return $value ? date('d.m.Y H:i:s', $value) : $value;
                            },
                        ];
                        break;
                    case 'url':
                        $columns[$key] = $field . ':url';
                        break;
                    case 'email':
                        $columns[$key] = $field . ':email';
                        break;
                    case 'tel':
                        $columns[$key] = [
                            'attribute' => $field,
                            'format' => 'html',
                            'value' => function ($model, $key, $index, $column) use ($field) {
                                return Html::a($model->$field, 'tel:' . preg_replace('/[^\+\d]/', '', $model->$field));
                            },
                        ];
                        break;
                    case 'file':
                        $columns[$key] = $field . ':url';
                        break;
                    case 'image':
                        $columns[$key] = $field . ':image';
                        break;
                    case 'upload-image':
                    case 'crop-image-upload':
                    case 'croppie-image-upload':
                    case 'cropper-image-upload':
                        $columns[$key] = [
                            'attribute' => $field,
                            'format' => 'html',
                            'content' => function ($model, $key, $index, $column) use ($field, $schema) {
                                $thumb = isset($schema['thumb']) ? $schema['thumb'] : 'thumb';
                                $url = $model->getImageUrl($field, $thumb);
                                return Html::a(Html::img($url, ['class' => 'img-responsive crud-column-img']), $url);
                            },
                        ];
                        break;
                    case 'sort':
                        $this->useSortableJs = true;
                        $columns[$key] = [
                            'attribute' => $field,
                            'filter' => false,
                            'content' => function ($model, $key, $index, $column) use ($field, $schema) {
                                return Html::tag('div', '<span class="glyphicon glyphicon-move"></span>', ['class' => 'crud-grid__sort-handle']);
                            },
                        ];
                        break;
                    case 'array':
                        $itemList = is_callable($schema['itemList']) ? call_user_func($schema['itemList']) : $schema['itemList'];

                        if (ArrayHelper::getValue($schema, 'gridDropButton', false)) {
                            $dropList = ArrayHelper::getValue($schema, 'gridDropButtonList', $itemList);
                            $columns[$key] = [
                                'class' => DropDownButtonColumn::className(),
                                'attribute' => $field,
                                'filter' => $itemList,
                                'items' => function ($model, $key, $index) use ($field, $dropList) {
                                    $items = [];
                                    foreach ($dropList as $itemKey => $itemValue) {
                                        $items[$itemKey] = [
                                            'label' => $itemValue,
                                            'url' => $this->columnUrlCreator('update', $model, $model->id, ['useReturnUrl' => 0]),
                                            'linkOptions' => [
                                                'class' => 'js-crud-post-refresh',
                                                'data-params' => [
                                                    Html::getInputName($model, $field) => $itemKey,
                                                ],
                                            ],
                                        ];
                                    }
                                    return $items;
                                },
                            ];
                        } else {
                            $columns[$key] = [
                                'attribute' => $field,
                                'filter' => $itemList,
                                'value' => function ($model, $key, $index, $column) use ($itemList) {
                                    $key = $model->{$column->attribute};
                                    return array_key_exists($key, $itemList) ? $itemList[$key] : '';
                                },
                            ];
                        }
                        break;
                    case 'list':
                        $list = call_user_func($schema['getList']);
                        $columns[$key] = [
                            'attribute' => $field,
                            'filter' => $list,
                            'value' => function ($model, $key, $index, $column) use ($list) {
                                $value = $model->{$column->attribute};
                                $value = implode(', ', $value);
                                return str_replace(array_keys($list), array_values($list), $value);
                            },
                        ];
                        break;
                    case 'relation':
                        $relation = $schema['relation'];

                        if ($schema['rtype'] == 'hasOne') {
                            $model = $this->getModel('getfields');
                            $relatedClass = $model->{'get' . $relation}()->modelClass;
                            $list = isset($schema['getList']) ? call_user_func($schema['getList']) : static::getList($relatedClass, $columnsSchema[$field]['titleField']);
                            $columns[$key] = [
                                'attribute' => $field,
                                'filter' => $list,
                                'content' => function ($model, $key, $index, $column) use ($field, $schema, $relation, $list) {
                                    $relatedClass = $model->{'get' . $relation}()->modelClass;
                                    $relatedPureClass = StringHelper::basename($relatedClass);
                                    $id = $model->$field;
                                    $text = array_key_exists($id, $list) ? $list[$id] : '';
                                    return $model->$relation ? Html::a($text, ['index', 'model' => $relatedClass, $relatedPureClass . '[id]' => $model->$field]) : '';
                                },
                            ];
                        } elseif ($schema['rtype'] == 'hasMany') {
                            $schema['title'] = array_key_exists('title', $schema) ? $schema['title'] : $model->getAttributeLabel($field);
                            $columns[$key] = [
                                //'label' => $schema['title'],
                                'content' => function ($model, $key, $index, $column) use ($field, $schema, $relation) {
                                    /** @var ActiveRecord $model */
                                    $relatedClass = $model->{'get' . $relation}()->modelClass;
                                    $relatedPureClass = StringHelper::basename($relatedClass);

                                    $link = $model->{'get' . $relation}()->link;
                                    $linkKey = array_keys($link)[0];

                                    return Html::a($schema['title'] . '&nbsp;<small>(' . count($model->$relation) . ')</small>',
                                        ['index', 'model' => $relatedClass, $relatedPureClass . '[' . $linkKey . ']' => $model->id]);
                                },
                            ];
                        } elseif ($schema['rtype'] == 'manyMany') {
                            if (isset($schema['relatedAttribute']) && isset($schema['itemList'])) {
                                $itemList = is_callable($schema['itemList']) ? call_user_func($schema['itemList']) : $schema['itemList'];
                                $columns[$key] = [
                                    'filter' => $itemList,
                                    'attribute' => $field,
                                    'content' => function ($model, $key, $index, $column) use ($field, $schema, $relation) {
                                        return $model->getAttributeLabel($field) . ' (' . count($model->$relation) . ')';
                                    },
                                ];
                            } else {
                                $columns[$key] = [
                                    'content' => function ($model, $key, $index, $column) use ($field, $schema, $relation) {
                                        return $model->getAttributeLabel($field) . ' (' . count($model->$relation) . ')';
                                    },
                                ];
                            }
                        }
                        break;
                }

                if (isset($columns[$key]) && is_array($columns[$key]) && isset($schema['gridColumnOptions'])) {
                    $columns[$key] = ArrayHelper::merge($columns[$key], (array)$schema['gridColumnOptions']);
                }
            }
        }

        return $columns;
    }

    public function columnUrlCreator($action, $model, $key, $urlParams = [])
    {
        $params = is_array($key) ? $key : ['id' => (string)$key];
        $params[0] = $this->context->id ? $this->context->id . '/' . $action : $action;
        $params = ArrayHelper::merge($params, $urlParams);
        return $this->context->urlCreate($params);
    }

    public function renderFormFields(ActiveForm $form, ActiveRecord $model, $content = '')
    {
        $formFields = $this->formFields();
        $paramFields = $this->getConfig('formFields', []);
        $columnsSchema = $this->columnsSchema();

        $formTabs = $this->getConfig('formTabs');
        if ($formTabs) {
            $tabItems = [];
            foreach ($formTabs as $title => $fields) {
                $tabContent = '';
                $tabErrors = false;
                $oldFields = [];
                foreach ($fields as $field) {
                    if ($field == '*') {
                        $oFields = [];
                        foreach ($formTabs as $title => $fields) $oFields = array_merge($oFields, $fields);
                        $oFields = array_diff($formFields, $oFields);
                        foreach ($oFields as $oField) {
                            $param = isset($paramFields[$oField]) ? $paramFields[$oField] : null;
                            $schema = isset($columnsSchema[$oField]) ? $columnsSchema[$oField] : null;
                            $tabContent .= $this->renderFormField($form, $model, $oField, $param, $schema);
                            $oldFields[] = $oField;
                        }
                    } else {
                        $param = isset($paramFields[$field]) ? $paramFields[$field] : null;
                        $schema = isset($columnsSchema[$field]) ? $columnsSchema[$field] : null;
                        $tabContent .= $this->renderFormField($form, $model, $field, $param, $schema);
                        $oldFields[] = $field;
                    }
                    $tabErrors = $model->hasErrors($field) ? true : $tabErrors;
                }
                $tabItems[] = [
                    'label' => $title,
                    'content' => $tabContent,
                    'linkOptions' => $tabErrors ? ['class' => 'text-danger'] : [],
                ];
            }
            $content = Tabs::widget(['items' => $tabItems]);
        } else {
            foreach ($formFields as $field) {
                $param = isset($paramFields[$field]) ? $paramFields[$field] : null;
                $schema = isset($columnsSchema[$field]) ? $columnsSchema[$field] : null;
                $content .= $this->renderFormField($form, $model, $field, $param, $schema);
            }
        }

        return $content;
    }

    public function renderFormField(ActiveForm $form, ActiveRecord $model, $field, $param, $schema, $content = '')
    {
        $formField = '';
        if (isset($param) && ($param !== true)) {
            if ($param instanceof \Closure) {
                $formField = call_user_func($param, $form, $model);
            }
        } elseif ($schema) {
            switch ($schema['type']) {
                case false:
                    break;
                case Schema::TYPE_SMALLINT:
                case Schema::TYPE_INTEGER:
                case Schema::TYPE_BIGINT:
                    $formField = $form->field($model, $field)->textInput(['type' => 'number']);
                    break;
                case Schema::TYPE_TEXT:
                    $formField = $form->field($model, $field)->textarea(['rows' => 6]);
                    break;
                case 'html':
                    $formField = $form->field($model, $field)->widget(TinyMce::className());
                    break;
                case Schema::TYPE_BOOLEAN:
                    $formField = $form->field($model, $field)->checkbox();
                    break;
                case Schema::TYPE_DATE:
                    $formField = $form->field($model, $field)->input('date');
                    break;
                case Schema::TYPE_TIME:
                    $formField = $form->field($model, $field)->input('time');
                    break;
                case Schema::TYPE_DATETIME:
                    $value = $model->$field ? date('Y-m-d\TH:i:s', strtotime($model->$field)) : '';
                    $formField = $form->field($model, $field)->input('datetime-local', ['value' => $value]);
                    break;
                case 'url':
                    $url = $model->$field;
                    $formField = $form->field($model, $field, ['parts' => ['{input}' => Html::a($url, $url)]]);
                    break;
                case 'email':
                    $formField = $form->field($model, $field)->input('email');
                    break;
                case 'tel':
                    $formField = $form->field($model, $field)->input('tel');
                    break;
                case 'file':
                    $formField = $form->field($model, $field)->fileInput();
                    break;
                case 'image':
                    $formField = $form->field($model, $field)->fileInput(['accept' => 'image/*']);
                    if ($model->$field) {
                        $formField .= '<div class="form-group field-' . Html::getInputId($model, $field) . '">' .
                            '<div>' . Html::checkbox($field . '__delete', false, ['label' => Yii::t('crud', 'delete')]) . '</div>' .
                            '<div>' . Html::img($model->$field, ['class' => 'img-responsive crud-field-img']) . '</div>' .
                            '</div>';
                    }
                    break;
                case 'upload-image':
                case 'crop-image-upload':
                case 'croppie-image-upload':
                case 'cropper-image-upload':
                    $thumb = isset($schema['thumb']) ? $schema['thumb'] : 'thumb';
                    $url = $model->getImageUrl($field, $thumb);
                    $hint = Html::img($url, ['class' => 'img-responsive crud-field-img img-result']);
                    $hint .= $model->$field ? '<p class="help-block">' .
                        Html::a('<i class="fa fa-remove"></i> ' . Yii::t('crud', 'Delete image'), $this->columnUrlCreator('delete-upload-image', $model, $model->id, ['field' => $field, 'returnUrl' => Url::current()]),
                            ['class' => 'btn btn-default btn-xs js-delete-image', 'data-message' => Yii::t('crud', 'Are you sure you want to delete this image?')]) . '</p>' : '';
                    $formField = $form->field($model, $field)->fileInput(['accept' => 'image/*'])->hint($hint);
                    if ($schema['type'] == 'crop-image-upload') {
                        $formField->widget(\ereminmdev\yii2\cropimageupload\CropImageUploadWidget::className());
                    } elseif ($schema['type'] == 'croppie-image-upload') {
                        $formField->widget(\ereminmdev\yii2\croppieimageupload\CroppieImageUploadWidget::className());
                    } elseif ($schema['type'] == 'cropper-image-upload') {
                        $formField->widget(\ereminmdev\yii2\cropperimageupload\CropperImageUploadWidget::className());
                    }
                    break;
                case 'array':
                    $itemList = is_callable($schema['itemList']) ? call_user_func($schema['itemList']) : $schema['itemList'];
                    $formField = $form->field($model, $field)->dropDownList($itemList);
                    break;
                case 'list':
                    $items = call_user_func($schema['getList']);
                    $formField = $form->field($model, $field)->listBox($items, ['multiple' => true, 'size' => min(10, count($items))])
                        ->hint(Yii::t('crud', 'Use Ctrl or Shift button to select multiple values.'));
                    break;
                case 'relation':
                    $relation = $schema['relation'];

                    if ($schema['rtype'] == 'hasOne') {
                        $relatedClass = $model->{'get' . $relation}()->modelClass;
                        $list = isset($schema['getList']) ? call_user_func($schema['getList']) : static::getList($relatedClass, $schema['titleField']);
                        $options = [];
                        if (array_key_exists('allowNull', $schema) && $schema['allowNull']) {
                            $options = ['prompt' => ''];
                        }

                        if (array_key_exists('select2', $schema)) {
                            $schema['select2'] = is_array($schema['select2']) ? $schema['select2'] : [];
                            $formField = $form->field($model, $field)->widget(
                                \conquer\select2\Select2Widget::className(),
                                ArrayHelper::merge(['items' => $list], $schema['select2'])
                            );
                        } else {
                            $formField = $form->field($model, $field)->dropDownList($list, $options);
                        }
                    } elseif ($schema['rtype'] == 'hasMany') {
                        if ($this->scenario != 'create') {
                            $relatedClass = $model->{'get' . $relation}()->modelClass;
                            $relatedPureClass = StringHelper::basename($relatedClass);
                            $schema['title'] = array_key_exists('title', $schema) ? $schema['title'] : $model->getAttributeLabel($field);
                            $link = $model->{'get' . $relation}()->link;
                            $linkKey = array_keys($link)[0];

                            if (array_key_exists('select2', $schema)) {
                                $list = isset($schema['getList']) ? call_user_func($schema['getList']) : static::getList($relatedClass, $schema['titleField']);
                                $schema['select2'] = is_array($schema['select2']) ? $schema['select2'] : [];
                                $schema['select2']['multiple'] = true;
                                $formField = $form->field($model, $field)->widget(
                                    \conquer\select2\Select2Widget::className(),
                                    ArrayHelper::merge(['items' => $list], $schema['select2'])
                                );
                            } else {
                                $formField = $form->field($model, $field, [
                                    'template' => "{input}\n",
                                    'parts' => ['{input}' => Html::a(
                                        $schema['title'] . '&nbsp;<small>(' . count($model->$relation) . ')</small>',
                                        ['index', 'model' => $relatedClass, $relatedPureClass . '[' . $linkKey . ']' => $model->id])],
                                ]);
                            }
                        }
                    } elseif ($schema['rtype'] == 'manyMany') {
                        $relatedClass = $model->{'get' . $relation}()->modelClass;
                        $list = isset($schema['getList']) ? call_user_func($schema['getList']) : static::getList($relatedClass, $schema['titleField']);

                        if (array_key_exists('select2', $schema)) {
                            $schema['select2'] = is_array($schema['select2']) ? $schema['select2'] : [];
                            $schema['select2']['multiple'] = true;
                            $schema['select2']['placeholder'] = '';
                            $formField = $form->field($model, $field)->widget(
                                \conquer\select2\Select2Widget::className(),
                                ArrayHelper::merge(['items' => $list], $schema['select2'])
                            );
                        } else {
                            $formField = $form->field($model, $field)->dropDownList($list, ['multiple' => true, 'size' => 10])
                                ->hint(Yii::t('crud', 'Use Ctrl or Shift button to select multiple values.'));
                        }
                    }
                    break;
                default:
                    $formField = $form->field($model, $field)->textInput();
            }
            if (isset($schema['hint']) && ($formField instanceof ActiveField)) {
                $formField->hint($schema['hint']);
            }
        } elseif ($schema !== false) {
            $formField = $form->field($model, $field)->textInput();
        }

        return $content . $formField;
    }

    public function formFields()
    {
        return ($onlyFields = $this->getConfig('formFieldsOnly')) != null ? $onlyFields : $this->getFields();
    }

    private $_fields;

    public function getFields()
    {
        if ($this->_fields === null) {
            if (($fields = $this->getConfig('fieldsOnly')) === null) {
                $model = $this->getModel('getfields');
                $fields = array_keys($model->attributeLabels());
                //$safeFields = $model->safeAttributes();
                //$fields = !empty($fields) ? array_intersect($fields, $safeFields) : $safeFields;
            }

            $append = $this->getConfig('formFieldsAppend', []);
            $fields = ArrayHelper::merge($fields, $append);

            $this->_fields = $fields;
        }

        return $this->_fields;
    }

    private $_model;

    /**
     * @param string $scenario
     * @return ActiveRecord
     */
    public function getModel($scenario = 'crud')
    {
        if ($this->_model === null) {
            $modelClass = $this->modelClass;
            $model = new $modelClass();
            $this->setModelScenario($model, $scenario);
            $this->_model = $model;
        }
        return $this->_model;
    }

    /**
     * @param $id
     * @param string $scenario
     * @return ActiveRecord
     * @throws NotFoundHttpException
     */
    public function findModel($id, $scenario = 'crud')
    {
        $modelClass = $this->modelClass;

        if (($model = $modelClass::findOne($id)) !== null) {
            $this->setModelScenario($model, $scenario);
            return $model;
        } else {
            throw new NotFoundHttpException('The requested model does not exist.');
        }
    }

    public function setModelScenario(ActiveRecord $model, $scenario)
    {
        if (($scenario !== false) && in_array($scenario, array_keys($model->scenarios()))) {
            $model->setScenario($scenario);
        }
    }

    private $_columnsSchema;

    public function columnsSchema()
    {
        if ($this->_columnsSchema === null) {
            $columnsSchema = [];

            $tableSchema = $this->getModel()->getTableSchema()->columns;
            foreach ($tableSchema as $field => $columnSchema) {
                $columnsSchema[$field]['type'] = $columnSchema->type;
                $columnsSchema[$field]['size'] = $columnSchema->size;
                $columnsSchema[$field]['allowNull'] = $columnSchema->allowNull;

                if (($columnsSchema[$field]['type'] == Schema::TYPE_SMALLINT) && ($columnsSchema[$field]['size'] == 1)) {
                    $columnsSchema[$field]['type'] = Schema::TYPE_BOOLEAN;
                }
            }

            if (isset($columnsSchema['created_at'])) {
                $columnsSchema['created_at']['type'] = 'datetime';
            }
            if (isset($columnsSchema['updated_at'])) {
                $columnsSchema['created_at']['type'] = 'datetime';
            }

            if (isset($columnsSchema['email'])) {
                $columnsSchema['email']['type'] = 'email';
            }

            if (isset($columnsSchema['position'])) {
                $columnsSchema['position']['type'] = 'sort';
            }

            $columnsSchema = ArrayHelper::merge($columnsSchema, $this->getConfig('columnsSchema', []));

            foreach ($columnsSchema as $key => $schema) {
                if (isset($schema['type']) && ($schema['type'] == 'relation') && !isset($schema['titleField'])) {
                    $columnsSchema[$key]['titleField'] = 'title';
                }
            }

            $this->_columnsSchema = $columnsSchema;
        }
        return $this->_columnsSchema;
    }

    public function getConfig($key, $default = null)
    {
        return ArrayHelper::getValue($this->config, $key, $default);
    }

    public function checkedActions()
    {
        $template = $this->getConfig('gridCheckedActionsTemplate',
            "{setvals}\n{duplicate}\n{export}\n{--}\n{delete}");

        $actions = [
            '{--}' => '<li role="presentation" class="divider"></li>',
            '{setvals}' => [
                'label' => Yii::t('crud', 'Set values'),
                'url' => $this->context->urlCreate(['setvals'], true),
            ],
            '{duplicate}' => [
                'label' => Yii::t('crud', 'Duplicate'),
                'url' => $this->context->urlCreate(['duplicate'], true),
            ],
            '{export}' => [
                'label' => Yii::t('crud', 'Export'),
                'url' => $this->context->urlCreate(['export'], true),
            ],
            '{delete}' => [
                'label' => Yii::t('crud', 'Delete'),
                'url' => $this->context->urlCreate(['delete'], true),
                'linkOptions' => [
                    'data-confirm' => Yii::t('crud', 'Are you sure you want to delete this items?'),
                ],
            ],
        ];

        $customActions = $this->getConfig('gridCheckedActions', []);
        foreach ($customActions as $key => $customAction) {
            $actions[$key] = is_callable($customAction) ? call_user_func_array($customAction, [$this]) : $customAction;
        }

        $items = explode("\n", $template);
        foreach ($actions as $key => $action) {
            foreach (array_keys($items, $key) as $pos) {
                $items[$pos] = $action;
            }
        }

        return $items;
    }

    public function renderCheckedActions($gridId)
    {
        $checked = $this->checkedActions();
        foreach ($checked as $key => $check) {
            if (is_array($check)) {
                Html::addCssClass($checked[$key]['linkOptions'], 'js-checked-action');
            }
        }

        $this->context->view->registerJs('
$(".js-checked-action").on("click", function () {
    var keys = $("#' . $gridId . '").yiiGridView("getSelectedRows");
    if (keys.length === 0) {
        alert("' . Yii::t('crud', 'Please select a one entry at least.') . '");
        return false;
    } else {
        var url = $(this).attr("href");
        url += url.indexOf("?") === -1 ? "?" : "&";
        $(this).attr("href", url + "id=" + keys.toString());
    }
});
        ');

        return ButtonDropdown::widget([
            'label' => '<span class="glyphicon glyphicon-check"></span>',
            'encodeLabel' => false,
            'dropdown' => [
                'items' => $checked,
            ],
            'options' => [
                'class' => 'btn-default',
                'title' => Yii::t('crud', 'Checked data actions'),
            ],
        ]);
    }

    public function renderFormSetvals($form, $model, $setvalsModel, $content = '')
    {
        // js/crud.js
        foreach ($setvalsModel->attributes() as $field) {
            $content .= $form->field($setvalsModel, $field)->checkbox([
                'label' => $model->getAttributeLabel($field),
                'class' => 'js-toggle-block',
                'data-destination' => 'field-' . Html::getInputId($model, $field),
            ]);
        }
        return $content;
    }

    public function getSetvalsModel()
    {
        $formFields = $this->formFields();
        $setModel = new DynamicModel(array_fill_keys($formFields, 0));
        $setModel->addRule($formFields, 'boolean');
        $setModel->load(Yii::$app->request->post());
        return $setModel;
    }

    public function export(CrudExportForm $model)
    {
        $exporter = new CrudExport([
            'format' => $model->fileFormat,
            'renderData' => (bool)$model->renderData,
            'model' => $this->getModel('search'),
            'dataProvider' => $this->getDataProvider(),
            'columns' => $this->guessColumns($this->getFields()),
        ]);
        return $exporter->export();
    }

    public function import(CrudImportForm $model, UploadedFile $file)
    {
        $importer = new CrudImport([
            'format' => $file->extension,
            'fileName' => $file->tempName,
            'modelClass' => $this->modelClass,
            'columnsSchema' => $this->columnsSchema(),
        ]);

        $result = $importer->import();
        $model->count = $importer->insertCount;

        if (!$result) {
            $errors = $importer->getErrors();
            foreach ($errors as $key => $error) $model->addError('summary' . $key, $error);
        }

        return $result;
    }

    /**
     * Возвращает массив из $title, индексированный по $index
     * Рекомендация: если в базе установить ключ на $title, выборка идет по этому индексу
     * @param ActiveRecord|string $class класс модели для выборки
     * @param string $title поле для значений массива (sql-запрос)
     * @param string $index поля для индекса массива
     * @param callable (ActiveQuery $query) $queryFunc функция для настройки ActiveQuery
     * @return array
     */
    public static function getList($class, $title = 'title', $index = 'id', $queryFunc = null)
    {
        $query = $class::find()
            ->select(['crud_title_field' => $title, $index])
            ->orderBy(['crud_title_field' => SORT_ASC])
            ->indexBy($index);

        if (is_callable($queryFunc)) {
            call_user_func($queryFunc, $query);
        }

        return $query->column();
    }

    /**
     * @var bool
     */
    protected $useSortableJs = false;

    /**
     * @return bool
     */
    public function isUseSortableJs()
    {
        return $this->useSortableJs;
    }
}
