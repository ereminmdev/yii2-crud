<?php

namespace ereminmdev\yii2\crud\components;

use Yii;

/**
 * Class Pagination
 * @package ereminmdev\yii2\crud\components
 */
class Pagination extends \yii\data\Pagination
{
    /**
     * @var int the default page size
     */
    public $defaultPageSize = 30;
    /**
     * @var array|false the page size limits
     */
    public $pageSizeLimit = [0, 1000];
    /**
     * @var string key to store page size
     */
    public $storeKey = 'per-page';

    /**
     * {@inheritdoc}
     */
    public function getPageSize()
    {
        $pageSize = $this->getQueryParam($this->pageSizeParam) ?? $this->restorePageSize();

        if ($pageSize !== null) {
            $this->setPageSize($pageSize, true);
            $this->storePageSize();
        }

        return parent::getPageSize();
    }

    /**
     * @return int|null
     */
    protected function restorePageSize()
    {
        return Yii::$app->session->get($this->storeKey);
    }

    protected function storePageSize()
    {
        $pageSize = parent::getPageSize();

        if ($pageSize != $this->defaultPageSize) {
            Yii::$app->session->set($this->storeKey, $pageSize);
        } else {
            Yii::$app->session->remove($this->storeKey);
        }
    }
}
