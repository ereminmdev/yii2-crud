<?php

namespace ereminmdev\yii2\crud\behaviors;

use Yii;
use yii\base\Behavior;
use yii\base\ErrorException;
use yii\base\Event;
use yii\caching\TagDependency;
use yii\db\ActiveRecord;
use yii\db\AfterSaveEvent;
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
        $this->_path = null;
        $path = $this->getFilesPath();
        if ($path !== $oldPath) {
            @FileHelper::copyDirectory($oldPath, $path);
            @FileHelper::removeDirectory($oldPath);
        }
    }

    public function afterDelete()
    {
        $path = $this->getFilesPath();
        @FileHelper::removeDirectory($path);
    }

    /**
     * @var string
     */
    protected $_path;

    /**
     * @param null|self $attribute
     * @return string
     */
    public function getFilesPath($attribute = null)
    {
        $bahavior = ($attribute === null) ? $this : $this->findBehavior($attribute);

        if ($bahavior->_path === null) {
            $bahavior->_path = is_callable($bahavior->path) ? call_user_func($bahavior->path, $bahavior->owner, $bahavior) : $bahavior->path;
        }

        return $bahavior->_path;
    }

    /**
     * @var string
     */
    protected $_url;

    /**
     * @param null|self $attribute
     * @return string
     */
    public function getFilesUrl($attribute = null)
    {
        $bahavior = ($attribute === null) ? $this : $this->findBehavior($attribute);

        if ($bahavior->_url === null) {
            $bahavior->_url = is_callable($bahavior->url) ? call_user_func($bahavior->url, $bahavior->owner, $bahavior) : $bahavior->url;
        }

        return $bahavior->_url;
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
