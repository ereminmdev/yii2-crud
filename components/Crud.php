<?php

namespace ereminmdev\yii2\crud\components;

use Closure;
use ereminmdev\yii2\crud\controllers\DefaultController;
use ereminmdev\yii2\crud\grid\DropDownButtonColumn;
use ereminmdev\yii2\crud\models\CrudExportForm;
use ereminmdev\yii2\crud\models\CrudImportForm;
use ereminmdev\yii2\elfinder\Elfinder;
use ereminmdev\yii2\tinymce\TinyMce;
use Error;
use Exception;
use Yii;
use yii\base\BaseObject;
use yii\base\DynamicModel;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\bootstrap\ButtonDropdown;
use yii\bootstrap\Tabs;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Schema;
use yii\grid\CheckboxColumn;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use yii\web\RangeNotSatisfiableHttpException;
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
 *
 * @property string $treeParentField
 * @property string $treeChildrenRelation
 * @property string $treeSortField
 */
class Crud extends BaseObject
{
    const VIEW_AS_GRID = 'grid';
    const VIEW_AS_TREE = 'tree';

    /**
     * @var ActiveRecord|string model class name
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
     * @var bool
     */
    public $sortableJs = false;
    /**
     * @var bool
     */
    public $jsEditPrompt = false;

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
     * @return ActiveRecord[]
     * @throws InvalidConfigException
     */
    public function getModels($filterParams = true, $pagination = false, $relations = false, $limitById = true)
    {
        return $this->getDataProvider($filterParams, $pagination, $relations, $limitById)->getModels();
    }

    /**
     * @param bool $filterParams
     * @return null|ActiveRecord
     * @throws InvalidConfigException
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
     * @throws InvalidConfigException
     */
    public function getDataProvider($filterParams = true, $pagination = false, $relations = false, $limitById = true)
    {
        $modelClass = $this->modelClass;
        $model = $this->getModel('search');

        $dataProvider = new ActiveDataProvider([
            'query' => $modelClass::find(),
            'pagination' => [
                'class' => 'ereminmdev\yii2\crud\components\Pagination',
                'storeKey' => $model->formName() . '-per-page',
            ],
        ]);

        if (!empty($dataProvider->sort->getAttributeOrders())) {
            $dataProvider->query->orderBy([]);
        }

        /** @var ActiveQuery $query */
        $query = $dataProvider->query;

        if ($pagination !== true) {
            $dataProvider->setPagination($pagination);
        }

        $configDataProvider = $this->getConfig('dataProvider');
        if ($configDataProvider instanceof Closure) {
            call_user_func($configDataProvider, $dataProvider, $this);
        }

        $filterParams = ($filterParams === true) ? Yii::$app->request->queryParams : ($filterParams !== false ? $filterParams : []);

        if ($limitById) {
            $filterId = Yii::$app->request->get('id', 'all');
            if ($filterId != 'all') {
                $filterId = explode(',', $filterId);
                $query->andWhere([$model->tableName() . '.[[id]]' => $filterId]);
            }
        }

        if ($model->load($filterParams)) {
            $columnsSchema = $this->columnsSchema();
            $formName = $model->formName();
            $tableName = $modelClass::tableName();
            foreach ($filterParams[$formName] as $attribute => $value) {
                if (($value == '') || isset($columnsSchema[$attribute]['unsafeOnSearch']) || (!in_array($attribute, $model->attributes()) && !isset($columnsSchema[$attribute]['relatedAttribute']))) {
                    continue;
                }
                $attributeFullName = $tableName . '.[[' . $attribute . ']]';
                if (isset($columnsSchema[$attribute]['type'])) {
                    switch ($columnsSchema[$attribute]['type']) {
                        case Schema::TYPE_INTEGER:
                        case Schema::TYPE_BOOLEAN:
                        case Schema::TYPE_TIME:
                        case 'array':
                            $query->andWhere([$attributeFullName => $value]);
                            break;
                        case Schema::TYPE_DATE:
                        case Schema::TYPE_DATETIME:
                            $value = ($value && !is_numeric($value)) ? strtotime($value) : $value;
                            $attributeFullName = ($model->getTableSchema()->getColumn($attribute)->type == 'integer') ? 'FROM_UNIXTIME(' . $attributeFullName . ')' : $attributeFullName;
                            $query->andWhere(['DATE(' . $attributeFullName . ')' => date('Y-m-d', $value)]);
                            break;
                        case 'relation':
                            if (isset($columnsSchema[$attribute]['relatedAttribute'])) {
                                $query->andWhere([$columnsSchema[$attribute]['relatedAttribute'] => $value]);
                                $query->joinWith($columnsSchema[$attribute]['relation']);
                                break;
                            } else {
                                $query->andWhere([$attributeFullName => $value]);
                                break;
                            }
                        case 'list':
                            if (!empty($value)) {
                                $attributeFullName = isset($columnsSchema[$attribute]['relatedAttribute']) ? $tableName . '.[[' . $columnsSchema[$attribute]['relatedAttribute'] . ']]' : $attributeFullName;
                                $query->andWhere('FIND_IN_SET(:value,' . $attributeFullName . ')', [':value' => $value]);
                            }
                            break;
                        default:
                            $query->andWhere(['like', $attributeFullName, $value]);
                    }
                } else {
                    $query->andWhere([$attributeFullName => $value]);
                }
            }
        }

        if ($relations) {
            $columnsSchema = $this->columnsSchema();
            foreach ($columnsSchema as $column => $schema) {
                if (isset($schema['type']) && ($schema['type'] == 'relation')) {
                    $relation = $schema['relation'];

                    if ($schema['rtype'] == 'hasOne') {
                        if ($schema['queryWith'] ?? false) {
                            $query->with($relation);
                        } elseif (in_array($schema['titleField'], $model->attributes())) {
                            $linkAttribute = array_keys($model->getRelation($relation)->link)[0];
                            $query->with([
                                $relation => function (ActiveQuery $query) use ($schema, $linkAttribute) {
                                    $query->select([$linkAttribute, $schema['titleField']]);
                                },
                            ]);
                        } else {
                            $query->with($relation);
                        }
                    } elseif ($schema['rtype'] == 'hasMany') {
                        if ($schema['queryWith'] ?? true) {
                            $query->with([
                                $relation => function (ActiveQuery $query) use ($schema, $relation, $model) {
                                    $linkAttribute = array_keys($model->getRelation($relation)->link)[0];
                                    $query->select($linkAttribute);
                                },
                            ]);
                        }
                    } elseif ($schema['rtype'] == 'manyMany') {
                        $query->with($relation);
                        // select only `id` for count()
                        /*$query->with([
                            $relation => function (ActiveQuery $query) use ($schema, $relation, $model) {
                                $linkAttribute = array_keys($model->getRelation($relation)->link)[0];
                                $query->select($linkAttribute);
                            },
                        ]);*/
                    }
                }
            }
        }

        return $dataProvider;
    }

    /**
     * @return mixed
     * @throws InvalidConfigException
     */
    public function gridColumns()
    {
        $columns = $this->guessColumns();

        if ($this->getConfig('gridActionColumn', true)) {
            // actions column
            array_unshift($columns, [
                'class' => DropDownButtonColumn::class,
                'buttonDropdownOptions' => [
                    'label' => '<i class="glyphicon glyphicon-option-vertical"></i>',
                    'encodeLabel' => false,
                    'options' => [
                        'class' => $this->sortableJs ? ['crud-grid__sort-handle'] : [],
                    ],
                ],
                'showCaret' => false,
                'options' => ['class' => 'col-width-sm'],
                'items' => function ($model, $key) {
                    $template = $this->getConfig('gridActionsTemplate', "{custom}\n{update}\n{--}\n{delete}");

                    $actions = [
                        '{custom}' => '',
                        '{--}' => '<li role="presentation" class="divider"></li>',
                        '{update}' => [
                            'label' => Yii::t('crud', 'Edit'),
                            'url' => $this->columnUrlCreator('update', $model, $key),
                            'linkOptions' => ['class' => 'js-store-page-scroll'],
                        ],
                        '{delete}' => [
                            'label' => Yii::t('crud', 'Delete'),
                            'url' => $this->columnUrlCreator('delete', $model, $key),
                            'linkOptions' => [
                                'class' => 'js-store-page-scroll',
                                'data-confirm' => Yii::t('yii', 'Are you sure you want to delete this item?'),
                            ],
                            'visible' => $this->getConfig('access.delete', true),
                        ],
                    ];

                    $customActions = $this->getConfig('gridActions', []);
                    foreach ($customActions as $key => $customAction) {
                        $actions[$key] = $customAction instanceof Closure ? call_user_func_array($customAction, [$model, $key, $this]) : $customAction;
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
        }

        if ($this->getConfig('gridCheckboxColumn', true)) {
            array_unshift($columns, [
                'class' => CheckboxColumn::class,
                'options' => ['class' => 'col-width-sm'],
                'checkboxOptions' => ['class' => 'js-check-action'],
            ]);
        }

        return $columns;
    }

    /**
     * @param array $fields
     * @return mixed
     * @throws InvalidConfigException
     */
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

        $columns = array_diff($columns, $this->getConfig('excludeColumns', []));

        foreach ($columns as $key => $field) {
            if ($field instanceof Closure) {
                $columns[$key] = call_user_func($field);
                continue;
            } elseif (!is_string($field)) {
                continue;
            }

            if (isset($paramColumns[$field]) && ($paramColumns[$field] !== true)) {
                if ($paramColumns[$field] instanceof Closure) {
                    $columns[$key] = call_user_func($paramColumns[$field], $this, $model, $field);
                } elseif ($paramColumns[$field] === false) {
                    unset($columns[$key]);
                } else {
                    $columns[$key] = $paramColumns[$field];
                }
                continue;
            }

            if (isset($columnsSchema[$field])) {
                $schema = $columnsSchema[$field];

                if ($schema === false) {
                    unset($columns[$key]);
                    continue;
                }

                switch ($schema['type']) {
                    case false:
                        unset($columns[$key]);
                        break;
                    case Schema::TYPE_TEXT:
                        $columns[$key] = [
                            'attribute' => $field,
                            'format' => 'ntext',
                        ];
                        break;
                    case 'html':
                        $columns[$key] = [
                            'attribute' => $field,
                            'format' => 'html',
                            'value' => function (ActiveRecord $model, $key, $index, $column) {
                                $field = $column->attribute;
                                $value = $model->$field;
                                return StringHelper::truncateWords($value, 10, '...', true);
                            },
                        ];
                        break;
                    case Schema::TYPE_BOOLEAN:
                        $itemList = array_reverse(Yii::$app->formatter->booleanFormat, true);
                        $columns[$key] = [
                            'attribute' => $field,
                            'format' => 'boolean',
                            'filter' => $itemList,
                        ];
                        if ($schema['gridDropButton'] ?? true) {
                            $columns[$key] = ArrayHelper::merge($columns[$key], [
                                'class' => DropDownButtonColumn::class,
                                'items' => function ($model) use ($field, $itemList) {
                                    $items = [];
                                    foreach ($itemList as $itemKey => $itemValue) {
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
                            ]);
                        }
                        break;
                    case Schema::TYPE_DATE:
                        $columns[$key] = [
                            'attribute' => $field,
                            //'format' => 'date',
                            'filterInputOptions' => ['type' => 'date', 'class' => 'form-control', 'id' => null],
                            'value' => function (ActiveRecord $model) use ($field) {
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
                            'value' => function (ActiveRecord $model) use ($field) {
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
                            'filterInputOptions' => ['type' => 'date', 'class' => 'form-control', 'id' => null],
                            'value' => function (ActiveRecord $model) use ($field) {
                                $value = $model->$field;
                                $value = ($value && !is_numeric($value)) ? strtotime($value) : $value;
                                return $value ? date('d.m.Y H:i:s', $value) : $value;
                            },
                        ];
                        break;
                    case 'url':
                        //$columns[$key] = $field . ':url';
                        $columns[$key] = [
                            'attribute' => $field,
                            'content' => function (ActiveRecord $model) use ($field) {
                                $url = $model->$field;
                                return Html::a($url, $url, ['target' => '_blank']);
                            },
                        ];
                        break;
                    case 'file':
                        $columns[$key] = [
                            'attribute' => $field,
                            'format' => 'html',
                            'filter' => false,
                            'content' => function (ActiveRecord $model) use ($field) {
                                $behavior = $model->getBehavior($field) ?? $model;
                                $url = $behavior->getUploadUrl($field);
                                return Html::a(basename($url), $url, ['target' => '_blank']);
                            },
                        ];
                        break;
                    case 'email':
                        $columns[$key] = $field . ':email';
                        break;
                    case 'tel':
                        $columns[$key] = [
                            'attribute' => $field,
                            'format' => 'html',
                            'value' => function (ActiveRecord $model) use ($field) {
                                return Html::a($model->$field, 'tel:' . preg_replace('/[^+0-9]/', '', $model->$field));
                            },
                        ];
                        break;
                    case 'image':
                        $columns[$key] = [
                            'attribute' => $field,
                            'format' => 'html',
                            'value' => function (ActiveRecord $model) use ($field) {
                                return Html::img($model->$field, ['class' => 'img-responsive crud-field-img']);
                            },
                        ];
                        break;
                    case 'upload-image':
                    case 'crop-image-upload':
                    case 'croppie-image-upload':
                    case 'cropper-image-upload':
                        $columns[$key] = [
                            'attribute' => $field,
                            'format' => 'html',
                            'filter' => false,
                            'content' => function (ActiveRecord $model) use ($field, $schema) {
                                $thumb = isset($schema['thumb']) ? $schema['thumb'] : 'thumb';
                                $thumb2 = isset($schema['thumb2']) ? $schema['thumb2'] : null;
                                $url = $model->getImageUrl($field, $thumb);
                                $url2 = $thumb2 ? $model->getImageUrl($field, $thumb2) : $model->getUploadUrl($field);
                                return Html::a(Html::img($url, ['class' => 'img-responsive crud-field-img']), $url2, ['target' => '_blank']);
                            },
                        ];
                        break;
                    case 'sort':
                        $this->sortableJs = true;
                        if (!isset($paramColumns[$field])) {
                            unset($columns[$key]);
                        }
                        break;
                    case 'array':
                        $itemList = $schema['itemList'] instanceof Closure ? call_user_func($schema['itemList']) : $schema['itemList'];
                        if ($schema['gridDropButton'] ?? false) {
                            $dropList = $schema['gridDropButtonList'] ?? $itemList;
                            $dropOptions = $schema['gridDropButtonOptions'] ?? [];
                            $columns[$key] = ArrayHelper::merge([
                                'class' => DropDownButtonColumn::class,
                                'attribute' => $field,
                                'filter' => $itemList,
                                'items' => function ($model) use ($field, $dropList) {
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
                            ], $dropOptions);
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
                                $value = (array)$model->{$column->attribute};
                                $value = array_intersect_key($list, array_flip($value));
                                $value = implode(', ', $value);
                                return str_replace(array_keys($list), array_values($list), $value);
                            },
                        ];
                        break;
                    case 'relation':
                        $relation = $schema['relation'];

                        if ($schema['rtype'] == 'hasOne') {
                            $model = $this->getModel('getfields');
                            $relatedClass = $model->getRelation($relation)->modelClass;
                            $list = isset($schema['getList']) ? call_user_func($schema['getList']) : (isset($schema['listAsTree']) && $schema['listAsTree'] ? static::getTreeList($relatedClass, $schema['titleField']) : static::getList($relatedClass, $schema['titleField']));
                            $columns[$key] = [
                                'attribute' => $field,
                                'filter' => $filter ?? $list,
                                'content' => function (ActiveRecord $model) use ($field, $schema, $relation, $list) {
                                    $relatedClass = $model->getRelation($relation)->modelClass;
                                    $relatedPureClass = StringHelper::basename($relatedClass);
                                    $text = $list[$model->$field] ?? '';
                                    return $model->$relation ? Html::a($text, ['index', 'model' => $relatedClass, $relatedPureClass . '[id]' => $model->$field]) : '';
                                },
                            ];
                            if (array_key_exists('select2', $schema)) {
                                //$title = isset($schema['relativeTitle']) ? $relModel->{$schema['relativeTitle']} : ($relModel && $relModel->hasAttribute('title') ? $relModel->getAttribute('title') : $model->$field);
                                $title = $list[$model->$field] ?? '';
                                $items = isset($schema['getList']) ? ArrayHelper::merge(['' => ''], $list) : [];
                                $columns[$key]['filter'] = \conquer\select2\Select2Widget::widget(ArrayHelper::merge(
                                    $this->getSelect2Options($model, $field, $title, $items),
                                    is_array($schema['select2']) ? $schema['select2'] : []
                                ));
                            }
                        } elseif ($schema['rtype'] == 'hasMany') {
                            $schema['title'] = array_key_exists('title', $schema) ? $schema['title'] : $model->getAttributeLabel($field);
                            $columns[$key] = [
                                //'label' => $schema['title'],
                                'content' => function (ActiveRecord $model) use ($field, $schema, $relation) {
                                    $relatedClass = $model->getRelation($relation)->modelClass;
                                    $relatedPureClass = StringHelper::basename($relatedClass);

                                    $link = $model->getRelation($relation)->link;
                                    $linkKey = array_keys($link)[0];
                                    $options = $schema['gridElOptions'] ?? [];

                                    return Html::a($schema['title'] . ' <small>(' . count($model->$relation) . ')</small>',
                                        ['index', 'model' => $relatedClass, $relatedPureClass . '[' . $linkKey . ']' => $model->getPrimaryKey()], $options);
                                },
                            ];
                        } elseif ($schema['rtype'] == 'manyMany') {
                            $columns[$key] = [
                                'content' => function (ActiveRecord $model) use ($field, $schema, $relation) {
                                    return $model->getAttributeLabel($field) . ' (' . count($model->$relation) . ')';
                                },
                            ];
                            if (isset($schema['getList']) || isset($schema['itemList'])) {
                                $itemList = isset($schema['getList']) ? $schema['getList'] : $schema['itemList'];
                                $itemList = $itemList instanceof Closure ? call_user_func($itemList) : $itemList;
                                $columns[$key] = ArrayHelper::merge($columns[$key], [
                                    'filter' => $itemList,
                                    'attribute' => $field,
                                ]);
                                if (!isset($schema['gridContentAsList']) || $schema['gridContentAsList']) {
                                    $columns[$key]['content'] = function (ActiveRecord $model) use ($field, $schema, $relation, $itemList) {
                                        $ids = array_column($model->$relation, 'id');
                                        $list = array_intersect_key($itemList, array_flip($ids));
                                        return implode(', ', $list);
                                    };
                                }
                            }
                        }
                        break;
                    case 'files':
                        $columns[$key] = [
                            'class' => 'yii\grid\Column',
                            'header' => $model->getAttributeLabel($field),
                            'content' => function (ActiveRecord $model) use ($field) {
                                $html = '';
                                try {
                                    $basePath = $model->getFilesPath($field);
                                    $baseUrl = $model->getFilesUrl($field);
                                    $files = FileHelper::findFiles($basePath);
                                    foreach ($files as $file) {
                                        $filename = mb_basename($file);
                                        $html .= Html::a('<i class="fa fa-file-o"></i> ' . $filename,
                                            $baseUrl . '/' . $filename,
                                            ['class' => 'btn btn-link btn-sm text-ellipsis', 'target' => '_blank']);
                                    }
                                } catch (InvalidArgumentException $e) {
                                    Yii::error($e, __METHOD__);
                                }
                                return $html;
                            },
                        ];
                        break;
                    default:
                        $columns[$key] = [
                            'attribute' => $field,
                        ];
                }

                if (isset($columns[$key]) && is_array($columns[$key])) {
                    if (isset($schema['gridColumnOptions'])) {
                        $columns[$key] = ArrayHelper::merge($columns[$key], (array)$schema['gridColumnOptions']);
                    }
                    if (isset($schema['jsEditPrompt'])) {
                        Html::addCssClass($columns[$key]['contentOptions'], 'js-edit-prompt');
                        $columns[$key]['contentOptions']['data']['message'] = $model->getAttributeLabel($field);
                        $columns[$key]['contentOptions']['data']['column'] = $field;
                        $this->jsEditPrompt = true;
                    }
                }
            }
        }

        return $columns;
    }

    /**
     * @param string $action
     * @param ActiveRecord $model
     * @param string $key
     * @param array $urlParams
     * @return string
     */
    public function columnUrlCreator($action, $model, $key, $urlParams = [])
    {
        $params = is_array($key) ? $key : ['id' => (string)$key];
        $params[0] = $this->context->id ? $this->context->id . '/' . $action : $action;
        $params = ArrayHelper::merge($params, $urlParams);
        return $this->context->urlCreate($params);
    }

    /**
     * @param ActiveForm $form
     * @param ActiveRecord $model
     * @param string $content
     * @return string
     * @throws Exception|InvalidConfigException
     */
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
                        foreach ($formTabs as $fields) $oFields = array_merge($oFields, $fields);
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

        return '<div class="well">' . $content . '</div>';
    }

    /**
     * @param ActiveForm $form
     * @param ActiveRecord $model
     * @param string $field
     * @param mixed $param
     * @param array $schema
     * @param string $content
     * @return string
     * @throws Exception
     */
    public function renderFormField(ActiveForm $form, ActiveRecord $model, $field, $param, $schema, $content = '')
    {
        $formField = '';
        if (($param === false) || ($schema === false)) {
            return '';
        }
        if (isset($param) && ($param instanceof Closure)) {
            $formField = call_user_func($param, $form, $model);
            if ($formField === false) {
                return '';
            } elseif ($formField !== true) {
                return $formField;
            }
        }
        $inputOptions = $schema['formFieldInputOptions'] ?? [];
        if (isset($schema['type'])) {
            switch ($schema['type']) {
                case false:
                    break;
                case Schema::TYPE_SMALLINT:
                case Schema::TYPE_INTEGER:
                case Schema::TYPE_BIGINT:
                    $formField = $form->field($model, $field)->textInput(array_merge($inputOptions, ['type' => 'number']));
                    break;
                case Schema::TYPE_TEXT:
                    $formField = $form->field($model, $field)->textarea(array_merge($inputOptions, ['class' => 'form-control input-auto-height', 'rows' => 1]));
                    break;
                case 'html':
                    $widgetOptions = isset($schema['widgetOptions']) ? $schema['widgetOptions'] : [];
                    $formField = $form->field($model, $field)->widget(TinyMce::class, $widgetOptions);
                    break;
                case Schema::TYPE_BOOLEAN:
                    $formField = $form->field($model, $field)->checkbox($inputOptions);
                    break;
                case Schema::TYPE_DATE:
                    $formField = $form->field($model, $field)->input('date', $inputOptions);
                    break;
                case Schema::TYPE_TIME:
                    $formField = $form->field($model, $field)->input('time', $inputOptions);
                    break;
                case Schema::TYPE_DATETIME:
                    $value = $model->$field;
                    $value = ($value && !is_numeric($value)) ? strtotime($value) : $value;
                    $value = $value ? date('Y-m-d\TH:i:s', $value) : '';
                    $formField = $form->field($model, $field)->input('datetime-local', array_merge($inputOptions, ['value' => $value]));
                    break;
                case 'email':
                    $formField = $form->field($model, $field)->input('email', $inputOptions);
                    break;
                case 'tel':
                    $formField = $form->field($model, $field)->input('tel', $inputOptions);
                    break;
                case 'file':
                    $value = $model->$field;
                    $behavior = $model->getBehavior($field) ?? $model;
                    $formField = $form->field($model, $field, ['template' => "{label}\n{file}\n{input}\n{hint}\n{error}"])->fileInput($inputOptions);
                    $formField->parts['{file}'] = $value;
                    if ($value) {
                        $formField->parts['{file}'] = Html::tag('p', Html::a('<span class="glyphicon glyphicon-file"></span> ' . $value, $behavior->getUploadUrl($field), ['class' => 'btn btn-link', 'target' => '_blank']) .
                            '   ' . Html::a('<span class="glyphicon glyphicon-remove"></span> ' . Yii::t('crud', 'Delete'), $this->columnUrlCreator('delete-upload-file', $model, $model->id, ['field' => $field, 'returnUrl' => Url::current()]),
                                ['class' => 'btn js-delete-file', 'data-message' => Yii::t('crud', 'Are you sure you want to delete this file?')]), ['class' => 'help-block']);
                    }
                    break;
                case 'image':
                    $formField = $form->field($model, $field, ['template' => "{label}\n{image}\n{input}\n{hint}\n{error}"])->fileInput(array_merge($inputOptions, ['accept' => 'image/*']));
                    if ($model->$field) {
                        $formField->parts['{image}'] = '<div class="form-group field-' . Html::getInputId($model, $field) . '">' .
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
                    $file = Html::tag('p', Html::img($url, ['class' => 'img-responsive crud-field-img img-result', 'data-src' => $model->getUploadUrl($field)]), ['class' => 'help-block']);
                    if ($model->$field) {
                        $file .= '<div class="help-block">' .
                            Html::a('<span class="glyphicon glyphicon-download-alt"></span> ' . Yii::t('crud', 'Download'), $model->getUploadUrl($field), ['class' => 'btn btn-default btn-xs', 'download' => true]) .
                            ' ' .
                            Html::a('<span class="glyphicon glyphicon-download-alt"></span> ' . Yii::t('crud', 'Download') . ' JPEG', $this->columnUrlCreator('download-jpg', $model, $model->id, ['path' => $model->getUploadPath($field), 'returnUrl' => Url::current()]), ['class' => 'btn btn-default btn-xs', 'download' => true]) .
                            '   ' .
                            Html::a('<span class="glyphicon glyphicon-remove"></span> ' . Yii::t('crud', 'Delete'), $this->columnUrlCreator('delete-upload-image', $model, $model->id, ['field' => $field, 'returnUrl' => Url::current()]),
                                ['class' => 'btn btn-danger btn-xs js-delete-file pull-right', 'data-message' => Yii::t('crud', 'Are you sure you want to delete this image?')]) .
                            '</div>';
                    }
                    $formField = $form->field($model, $field, ['template' => "{label}\n{file}\n{input}\n{hint}\n{error}"])->fileInput(array_merge($inputOptions, ['accept' => 'image/*']));
                    $formField->parts['{file}'] = $file;
                    $widgetOptions = isset($schema['widgetOptions']) ? $schema['widgetOptions'] : [];
                    if ($schema['type'] == 'crop-image-upload') {
                        $formField->widget(\ereminmdev\yii2\cropimageupload\CropImageUploadWidget::class, $widgetOptions);
                    } elseif ($schema['type'] == 'croppie-image-upload') {
                        $formField->widget(\ereminmdev\yii2\croppieimageupload\CroppieImageUploadWidget::class, $widgetOptions);
                    } elseif ($schema['type'] == 'cropper-image-upload') {
                        $formField->widget(\ereminmdev\yii2\cropperimageupload\CropperImageUploadWidget::class, $widgetOptions);
                    }
                    break;
                case 'array':
                    $itemList = $schema['itemList'] instanceof Closure ? call_user_func($schema['itemList']) : $schema['itemList'];
                    $formField = $form->field($model, $field)->dropDownList($itemList, $inputOptions);
                    break;
                case 'list':
                    $items = call_user_func($schema['getList']);
                    $formField = $form->field($model, $field, ['inline' => true])->checkboxList($items, $inputOptions);
                    break;
                case 'relation':
                    $relation = $schema['relation'];

                    if ($schema['rtype'] == 'hasOne') {
                        $relatedClass = $model->getRelation($relation)->modelClass;
                        $list = isset($schema['getList']) ? call_user_func($schema['getList']) : (isset($schema['listAsTree']) && $schema['listAsTree'] ? static::getTreeList($relatedClass, $schema['titleField']) : static::getList($relatedClass, $schema['titleField']));
                        $options = [];
                        if (array_key_exists('allowNull', $schema) && $schema['allowNull']) {
                            $options = ['prompt' => ''];
                        }
                        if (array_key_exists('select2', $schema)) {
                            $title = $list[$model->$field] ?? '';
                            $items = isset($schema['getList']) ? $list : [];
                            $formField = $form->field($model, $field)->widget(
                                \conquer\select2\Select2Widget::class,
                                ArrayHelper::merge($this->getSelect2Options($model, $field, $title, $items), is_array($schema['select2']) ? $schema['select2'] : [])
                            );
                        } else {
                            $formField = $form->field($model, $field)->dropDownList($list, array_merge($inputOptions, $options));
                        }
                    } elseif ($schema['rtype'] == 'hasMany') {
                        if ($this->scenario != 'create') {
                            $relatedClass = $model->getRelation($relation)->modelClass;
                            $relatedPureClass = StringHelper::basename($relatedClass);
                            $schema['title'] = array_key_exists('title', $schema) ? $schema['title'] : $model->getAttributeLabel($field);
                            $link = $model->getRelation($relation)->link;
                            $linkKey = array_keys($link)[0];

                            if (array_key_exists('select2', $schema)) {
                                $list = isset($schema['getList']) ? call_user_func($schema['getList']) : (isset($schema['listAsTree']) && $schema['listAsTree'] ? static::getTreeList($relatedClass, $schema['titleField']) : static::getList($relatedClass, $schema['titleField']));
                                $schema['select2'] = is_array($schema['select2']) ? $schema['select2'] : [];
                                $schema['select2']['multiple'] = true;
                                $formField = $form->field($model, $field)->widget(
                                    \conquer\select2\Select2Widget::class,
                                    ArrayHelper::merge(['items' => $list], $schema['select2'])
                                );
                            } else {
                                $formField = $form->field($model, $field, [
                                    'template' => "{input}\n",
                                    'parts' => ['{input}' => Html::a(
                                        $schema['title'] . ' <small>(' . count($model->$relation) . ')</small>',
                                        ['index', 'model' => $relatedClass, $relatedPureClass . '[' . $linkKey . ']' => $model->id])],
                                ]);
                            }
                        }
                    } elseif ($schema['rtype'] == 'manyMany') {
                        $relatedClass = $model->getRelation($relation)->modelClass;
                        $list = isset($schema['getList']) ? call_user_func($schema['getList']) : (isset($schema['listAsTree']) && $schema['listAsTree'] ? static::getTreeList($relatedClass, $schema['titleField']) : static::getList($relatedClass, $schema['titleField']));

                        if (array_key_exists('select2', $schema)) {
                            $schema['select2'] = is_array($schema['select2']) ? $schema['select2'] : [];
                            $schema['select2']['multiple'] = true;
                            $schema['select2']['placeholder'] = '';
                            $formField = $form->field($model, $field)->widget(
                                \conquer\select2\Select2Widget::class,
                                ArrayHelper::merge(['items' => $list], $schema['select2'])
                            );
                        } else {
                            $formField = $form->field($model, $field, ['inline' => true])->checkboxList($list, $inputOptions);
                        }
                    }
                    break;
                case 'files':
                    return $content . '<div class="form-group field-' . Html::getInputId($model, $field) . '">' .
                        '<label class="control-label">' . $model->getAttributeLabel($field) . '</label>' .
                        Elfinder::widget([
                            'clientOptions' => [
                                'height' => 250,
                                'url' => $this->columnUrlCreator('files-connector', $model, $model->id, ['field' => $field]),
                                'ui' => ['toolbar'],
                                'uiOptions' => ['toolbar' => [['upload']]],
                            ],
                        ]) .
                        '</div>';
                    break;
                default:
                    $formField = $form->field($model, $field)->textInput($inputOptions);
            }
        } else {
            $formField = $form->field($model, $field)->textInput($inputOptions);
        }
        if (isset($schema['hint']) && ($formField instanceof ActiveField)) {
            $formField->hint($schema['hint']);
        }
        if (isset($schema['formFieldCallback']) && (is_callable($schema['formFieldCallback'])) && ($formField instanceof ActiveField)) {
            call_user_func($schema['formFieldCallback'], $formField);
        }

        return $content . $formField;
    }

    /**
     * @return array|mixed
     */
    public function formFields()
    {
        return ($onlyFields = $this->getConfig('formFieldsOnly')) != null ? $onlyFields : $this->getFields();
    }

    private $_fields;

    /**
     * @return array
     */
    public function getFields()
    {
        if ($this->_fields === null) {
            if (($fields = $this->getConfig('fieldsOnly')) === null) {
                $model = $this->getModel('getfields');
                $fields = array_keys($model->attributeLabels());
                $fields = array_diff($fields, $this->getConfig('excludeColumns', []));
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
     * @param int $id
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

    /**
     * @param ActiveRecord $model
     * @param string $scenario
     */
    public function setModelScenario(ActiveRecord $model, $scenario)
    {
        if (($scenario !== false) && in_array($scenario, array_keys($model->scenarios()))) {
            $model->setScenario($scenario);
        }
    }

    private $_columnsSchema;

    /**
     * @return array
     * @throws InvalidConfigException
     */
    public function columnsSchema()
    {
        if ($this->_columnsSchema === null) {
            $columnsSchema = [];

            $tableSchema = $this->getModel()->getTableSchema()->columns;
            foreach ($tableSchema as $field => $columnSchema) {
                $columnsSchema[$field]['type'] = $columnSchema->type;
                $columnsSchema[$field]['size'] = $columnSchema->size;
                $columnsSchema[$field]['allowNull'] = $columnSchema->allowNull;

                if ((in_array($columnsSchema[$field]['type'], [Schema::TYPE_TINYINT, Schema::TYPE_SMALLINT])) && ($columnsSchema[$field]['size'] == 1)) {
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
                $this->sortableJs = true;
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

    /**
     * @param string $key
     * @param string $default
     * @return mixed
     */
    public function getConfig($key, $default = null)
    {
        try {
            return ArrayHelper::getValue($this->config, $key, $default);
        } catch (Error $e) {
            return $default;
        }
    }

    /**
     * @return bool
     */
    public function canViewAsTree()
    {
        return ($this->getConfig('viewAs') == self::VIEW_AS_TREE) || ($this->getConfig('tree', false) !== false);
    }

    /**
     * @return bool
     */
    public function isViewAsTree()
    {
        return $this->getViewAs() == self::VIEW_AS_TREE;
    }

    /**
     * @return string
     */
    public function getViewAs()
    {
        return Yii::$app->session->get($this->getViewAsKey()) ?? $this->getConfig('viewAs') ??
            ($this->canViewAsTree() ? self::VIEW_AS_TREE : self::VIEW_AS_GRID);
    }

    /**
     * @param string $view
     */
    public function setViewAs($view)
    {
        if (in_array($view, [self::VIEW_AS_GRID, self::VIEW_AS_TREE])) {
            Yii::$app->session->set($this->getViewAsKey(), $view);
        }
    }

    /**
     * @return string
     */
    public function getViewAsKey()
    {
        return 'crud-' . $this->modelClass . '-view-as';
    }

    /**
     * @return array
     */
    public function checkedActions()
    {
        $template = $this->getConfig('gridCheckedActionsTemplate',
            "{custom}\n{setvals}\n{duplicate}\n{export}\n{--}\n{delete}");

        $actions = [
            '{custom}' => '',
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
            $actions[$key] = $customAction instanceof Closure ? call_user_func_array($customAction, [$this]) : $customAction;
        }

        $items = explode("\n", $template);
        foreach ($actions as $key => $action) {
            foreach (array_keys($items, $key) as $pos) {
                $items[$pos] = $action;
            }
        }

        return $items;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function renderCheckedActions()
    {
        $checked = $this->checkedActions();
        foreach ($checked as $key => $check) {
            if (is_array($check)) {
                Html::addCssClass($checked[$key]['linkOptions'], 'js-checked-action');
            }
        }

        $this->context->view->registerJs('
$(".js-checked-action").on("click", function () {
    let keys = [];
    $(".js-check-action").each(function() {
        if (this.checked) {
            keys.push(parseInt($(this).val()));
        }
    });
    if (keys.length === 0) {
        alert("' . Yii::t('crud', 'Please select a one entry at least.') . '");
        return false;
    } else {
        let url = $(this).attr("href");
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
                'title' => Yii::t('crud', 'Checked data actions'),
                'class' => 'btn btn-default',
            ],
        ]);
    }

    /**
     * @param ActiveForm $form
     * @param ActiveRecord $model
     * @param ActiveRecord $setModel
     * @param string $content
     * @return string
     */
    public function renderFormSetvals($form, $model, $setModel, $content = '')
    {
        // js/crud.js
        foreach ($setModel->attributes() as $field) {
            $content .= $form->field($setModel, $field)->checkbox([
                'label' => $model->getAttributeLabel($field),
                'class' => 'js-toggle-block',
                'data-destination' => 'field-' . Html::getInputId($model, $field),
            ]);
        }
        return $content;
    }

    /**
     * @return DynamicModel
     */
    public function getSetvalsModel()
    {
        $formFields = $this->formFields();
        $setModel = new DynamicModel(array_fill_keys($formFields, 0));
        $setModel->addRule($formFields, 'boolean');
        $setModel->load(Yii::$app->request->post());
        return $setModel;
    }

    /**
     * @param ActiveRecord $model
     * @param DefaultController $controller
     * @return string[]
     */
    public function getTreeActions($model, $controller)
    {
        $template = $this->getConfig('tree.itemActionsTemplate', "{custom}\n{update}\n{--}\n{create1}\n{create2}\n{--}\n{delete}");

        $returnUrl = Url::current();
        $parentField = $this->treeParentField;

        $actions = [
            '{custom}' => '',
            '{--}' => '<li role="presentation" class="divider"></li>',
            '{update}' => [
                'label' => Yii::t('crud', 'Edit'),
                'url' => $controller->urlCreate(['update', 'id' => $model->id]),
                'linkOptions' => ['class' => 'js-store-page-scroll'],
            ],
            '{create1}' => [
                'label' => Yii::t('crud', 'Create nearby'),
                'url' => $controller->urlCreate(['create', $model->formName() => [$parentField => $model->$parentField], 'returnUrl' => $returnUrl]),
                'linkOptions' => ['class' => 'js-store-page-scroll'],
            ],
            '{create2}' => [
                'label' => Yii::t('crud', 'Create as sublevel') . ' →',
                'url' => $controller->urlCreate(['create', $model->formName() => [$parentField => $model->id], 'returnUrl' => $returnUrl]),
                'linkOptions' => ['class' => 'js-store-page-scroll'],
            ],
            '{delete}' => [
                'label' => Yii::t('crud', 'Delete'),
                'url' => $controller->urlCreate(['delete', 'id' => $model->id]),
                'linkOptions' => [
                    'class' => 'js-store-page-scroll',
                    'data-confirm' => Yii::t('yii', 'Are you sure you want to delete this item?'),
                ],
                'visible' => $this->getConfig('access.delete', true),
            ],
        ];

        $customActions = $this->getConfig('tree.itemActions', []);
        foreach ($customActions as $key => $customAction) {
            $actions[$key] = $customAction instanceof Closure ? call_user_func_array($customAction, [$model, $controller, $this]) : $customAction;
        }

        $items = explode("\n", $template);
        foreach ($actions as $key => $action) {
            foreach (array_keys($items, $key) as $pos) {
                $items[$pos] = $action;
            }
        }

        return $items;
    }

    /**
     * @param ActiveRecord $model
     * @param DefaultController $controller
     * @return string
     */
    public function getTreeTitleBlock($model, $controller)
    {
        $block = $this->getConfig('tree.titleBlock', function ($model, $controller, $crud) {
            $text = $this->getConfig('tree.titleBlock_text', $model->title);
            $text = $text instanceof Closure ? call_user_func_array($text, [$model, $controller, $this]) : $text;

            $options = $this->getConfig('tree.titleBlock_options', []);
            $options = $options instanceof Closure ? call_user_func_array($options, [$model, $controller, $this]) : $options;
            $options = (array)$options;

            $hover = $this->getConfig('tree.titleBlock_onHover', '');
            $hover = $hover instanceof Closure ? call_user_func_array($hover, [$model, $controller, $this]) : $hover;
            $hover = !empty($hover) ? '<span class="tree-item--on-hover">       ' . $hover . '</span>' : '';

            return Html::a($text, $controller->urlCreate(['update', 'id' => $model->id]), $options) . $hover;
        });
        return $block instanceof Closure ? call_user_func_array($block, [$model, $controller, $this]) : $block;
    }

    /**
     * @param ActiveRecord $model
     * @param DefaultController $controller
     * @return string
     */
    public function getTreeRightBlock($model, $controller)
    {
        $block = $this->getConfig('tree.rightBlock', '');
        return $block instanceof Closure ? call_user_func_array($block, [$model, $controller, $this]) : $block;
    }

    /**
     * @return string
     */
    public function getTreeParentField()
    {
        return $block = $this->getConfig('tree.parentField', 'parent_id');
    }

    /**
     * @return string
     */
    public function getTreeChildrenRelation()
    {
        return $block = $this->getConfig('tree.childrenRelation', 'children');
    }

    /**
     * @return string
     */
    public function getTreeSortField()
    {
        return $block = $this->getConfig('tree.sortField', 'position');
    }

    /**
     * @param CrudExportForm $model
     * @return $this|mixed
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws InvalidConfigException|RangeNotSatisfiableHttpException
     */
    public function export(CrudExportForm $model)
    {
        $fields = $this->getConfig('exportColumns', $this->getModel()->attributes());
        $exporter = new CrudExport([
            'format' => $model->fileFormat,
            'needRenderData' => (bool)$model->needRenderData,
            'model' => $this->getModel('search'),
            'dataProvider' => $this->getDataProvider(),
            'columns' => $model->needRenderData ? $this->guessColumns($fields) : $fields,
        ]);
        return $exporter->export();
    }

    /**
     * @param CrudImportForm $model
     * @param UploadedFile $file
     * @return bool
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws InvalidConfigException
     */
    public function import(CrudImportForm $model, UploadedFile $file)
    {
        $filename = $file->tempName . '.' . $file->extension;

        $importer = new CrudImport([
            'format' => $file->extension,
            'fileName' => $filename,
            'modelClass' => $this->modelClass,
            'columnsSchema' => $this->columnsSchema(),
        ]);

        @copy($file->tempName, $filename);
        try {
            $result = $importer->import();
        } finally {
            @unlink($filename);
        }

        $model->count = $importer->insertCount;

        if (!$result) {
            $errors = $importer->getErrors();
            foreach ($errors as $key => $error) $model->addError('summary' . $key, $error);
        }

        return $result;
    }

    /**
     * @param ActiveRecord $model
     * @param string $field
     * @param string $title
     * @param array $items
     * @return array
     */
    public function getSelect2Options($model, $field, $title, $items = [])
    {
        return !empty($items) ?
            [
                'model' => $model,
                'attribute' => $field,
                'items' => $items,
                'placeholder' => ['id' => '', 'text' => ''],
                'settings' => ['width' => '100%', 'allowClear' => true, 'dropdownAutoWidth' => true],
            ] :
            [
                'model' => $model,
                'attribute' => $field,
                'ajax' => $this->context->urlCreate(['select2filter', 'field' => $field]),
                'placeholder' => ['id' => '', 'text' => ''],
                'settings' => ['width' => '100%', 'allowClear' => true, 'dropdownAutoWidth' => true],
                'data' => [
                    ['id' => '', 'text' => '', 'search' => '', 'hidden' => true],
                    ['id' => $model->$field ?? '', 'text' => $title ?? '', 'selected' => 'selected'],
                ],
            ];
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
            ->indexBy($index);

        if ($query->orderBy === null) {
            $query->orderBy(['crud_title_field' => SORT_ASC]);
        }

        if ($queryFunc instanceof Closure) {
            call_user_func($queryFunc, $query);
        }

        return $query->column();
    }

    /**
     * Возвращает массив из $title, индексированный по $index
     * Рекомендация: если в базе установить ключ на $title, выборка идет по этому индексу
     * @param ActiveRecord|string $class класс модели для выборки
     * @param string $title поле для значений массива (sql-запрос)
     * @param string $index поля для индекса массива
     * @param callable (ActiveQuery $query) $queryFunc функция для настройки ActiveQuery
     * @param mixed $parentId id родителя
     * @return array
     */
    public static function getTreeList($class, $title = 'title', $index = 'id', $queryFunc = null, $parentId = 0, $prefix = '', $items = null)
    {
        if ($items === null) {
            $query = $class::find()
                ->select(['crud_title_field' => $title, 'crud_id_field' => $index, 'parent_id'])
                ->asArray();

            if ($query->orderBy === null) {
                $query->orderBy(['crud_title_field' => SORT_ASC]);
            }

            if ($queryFunc instanceof Closure) {
                call_user_func($queryFunc, $query);
            }

            $items = $query->all();
        }

        $list = [];
        foreach ($items as $item) {
            if ($item['parent_id'] != $parentId) continue;
            $list[$item['crud_id_field']] = $prefix . $item['crud_title_field'];
            $list = ArrayHelper::merge($list, self::getTreeList($class, $title, $index, $queryFunc, $item['crud_id_field'], $prefix . '   ', $items));
        }

        return $list;
    }
}
