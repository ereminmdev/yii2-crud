<?php

namespace ereminmdev\yii2\crud\behaviors;

use Exception;
use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\helpers\FileHelper;

/**
 * Attach this behavior when model has columns with `files` scheme
 */
class FilesBehavior extends Behavior
{
    /**
     * @var string
     */
    public $attribute;
    /**
     * @var string|callable($model, $behavior)
     */
    public $url;
    /**
     * @var string|callable($model, $behavior)
     */
    public $path;

    /**
     * {@inheritdoc}
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    public function beforeSave()
    {
        $oldPath = $this->getFilesPath();
    }

    public function afterSave()
    {
        $oldPath = $this->getFilesPath();

        if (is_dir($oldPath)) {
            $this->_path = null;
            $path = $this->getFilesPath();

            if ($path !== $oldPath) {
                try {
                    FileHelper::copyDirectory($oldPath, $path);
                    FileHelper::removeDirectory($oldPath);
                } catch (Exception $e) {
                    Yii::error([$e, __FILE__ . ':' . __LINE__]);
                }
            }
        }
    }

    public function afterDelete()
    {
        $path = $this->getFilesPath();

        try {
            FileHelper::removeDirectory($path);
        } catch (Exception $e) {
            Yii::error([$e, __FILE__ . ':' . __LINE__]);
        }
    }

    /**
     * @var string
     */
    protected $_path;

    /**
     * @param null|string $attribute
     * @return string
     */
    public function getFilesPath($attribute = null)
    {
        $behavior = ($attribute === null) ? $this : $this->findBehavior($attribute);

        if ($behavior->_path === null) {
            $behavior->_path = is_callable($behavior->path) ? call_user_func($behavior->path, $behavior->owner, $behavior) : $behavior->path;
        }

        return $behavior->_path;
    }

    /**
     * @var string
     */
    protected $_url;

    /**
     * @param null|string $attribute
     * @return string
     */
    public function getFilesUrl($attribute = null)
    {
        $behavior = ($attribute === null) ? $this : $this->findBehavior($attribute);

        if ($behavior->_url === null) {
            $behavior->_url = is_callable($behavior->url) ? call_user_func($behavior->url, $behavior->owner, $behavior) : $behavior->url;
        }

        return $behavior->_url;
    }

    /**
     * @param string $attribute
     * @return self|null
     */
    protected function findBehavior($attribute)
    {
        if ($this->attribute == $attribute) {
            return $this;

        } else {
            $owner = $this->owner;

            foreach ($owner->getBehaviors() as $behavior) {
                if (($behavior instanceof self) && ($behavior->attribute == $attribute)) {
                    return $behavior;
                }
            }
        }

        return null;
    }
}
