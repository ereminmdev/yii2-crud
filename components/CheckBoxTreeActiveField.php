<?php

namespace ereminmdev\yii2\crud\components;

use Yii;
use yii\base\InvalidConfigException;
use yii\bootstrap\ActiveField;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

class CheckBoxTreeActiveField extends ActiveField
{
    public $modelClass = null;

    public $idColumn = 'id';

    public $titleColumn = 'title';

    public $prefix = '      ';

    public $maxDepth = 0;

    private $_tree = null;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if ($this->modelClass === null) {
            throw new InvalidConfigException('Property "modelClass" must be set.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function render($content = null)
    {
        $this->_tree = $this->findTree();
        $items = $this->getItemsForParent();

        $options = ['class' => 'form-group js-tree-field', 'encode' => false];

        $this->checkboxList($items, $options);

        $this->registerJs();

        return parent::render($content);
    }

    /**
     * @param int $parent_id
     * @param int $level
     * @return array
     */
    protected function getItemsForParent($parent_id = 0, $level = 1)
    {
        $rows = (array)($this->_tree[$parent_id] ?? []);

        if (empty($rows)) {
            return [];
        }

        $items = [];
        $idField = $this->idColumn;
        $titleField = $this->titleColumn;
        $prefix = $level > 1 ? str_repeat((string)$this->prefix, $level - 1) : '';

        foreach ($rows as $row) {
            $id = $row[$idField];

            $hasChildren = isset($this->_tree[$id]) && !empty($this->_tree[$id]);
            $hasChildren = !$this->maxDepth || ($level < $this->maxDepth) ? $hasChildren : false;

            $items[$id] = '<span class="js-tree-field-block" data-id="' . $id . '" data-parent="' . $parent_id . '">' .
                $prefix . ' ' .
                ($hasChildren ? '<i class="glyphicon glyphicon-chevron-right js-tree-field-opener"></i> ' : '') .
                $row[$titleField] .
                '</span>';

            if ($hasChildren) {
                $items = ArrayHelper::merge($items, $this->getItemsForParent($id, $level + 1));
            }
        }

        return $items;
    }

    /**
     * @return array
     */
    protected function findTree()
    {
        $parents = [];

        if (is_subclass_of($this->modelClass, ActiveRecord::class)) {
            $query = ($this->modelClass)::find()->select(['id', 'title', 'parent_id'])->asArray();
            foreach ($query->each() as $model) {
                $parents[$model['parent_id']][] = $model;
            }
        }

        return $parents;
    }

    protected function registerJs()
    {
        Yii::$app->view->registerJs(<<<JS
$('.js-tree-field')
    .find('.js-tree-field-block[data-parent!="0"]').each(function(idx, el) {
        $(el).closest('.checkbox').addClass('hidden');
    }).end()
    .find('input[type="checkbox"]:checked').each(function(idx, el) {
        const block = $(el).closest('.checkbox').find('.js-tree-field-block');
        const parent = block.closest('.js-tree-field').find('.js-tree-field-block[data-id="' + block.data('parent') + '"]');
        if (parent.length) {
            jsTreeOpen(parent);
        }
    });

$('.js-tree-field-opener').on('click', function() {
    const block = $(this).closest('.js-tree-field-block');
    if ($(this).hasClass('glyphicon-chevron-down')) {
        jsTreeClose(block);
    } else {
        jsTreeOpen(block);
    }
    return false;
});

function jsTreeOpen(block) {
    block.closest('.js-tree-field').find('.js-tree-field-block[data-parent="' + block.data('id') + '"]').each(function(idx, block) {
        $(block).closest('.checkbox').removeClass('hidden');
    });
    const parent = block.closest('.js-tree-field').find('.js-tree-field-block[data-id="' + block.data('parent') + '"]');
    if (parent.length) {
        jsTreeOpen(parent);
    }
    block.find('.js-tree-field-opener').addClass('glyphicon-chevron-down');
}

function jsTreeClose(block) {
    block.closest('.js-tree-field').find('.js-tree-field-block[data-parent="' + block.data('id') + '"]').each(function(idx, el) {
        const block = $(el);
        block.closest('.checkbox').addClass('hidden');
        jsTreeClose(block);
    });
    block.find('.js-tree-field-opener').removeClass('glyphicon-chevron-down');
}
JS
        );
    }
}
