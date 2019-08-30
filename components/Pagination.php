<?php

namespace ereminmdev\yii2\crud\components;

use Yii;
use yii\web\Cookie;

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
    public $pageSizeLimit = [0, 100];
    /**
     * @var string cookie name to store page size
     */
    public $cookieName = 'per-page';

    /**
     * @inheritDoc
     */
    public function getPageSize()
    {
        if (($pageSize = $this->getQueryParam($this->pageSizeParam)) !== null) {
            $this->setPageSize($pageSize, true);
            $this->storePageSize($pageSize);
        } elseif (($pageSize = $this->restorePageSize()) !== null) {
            $this->setPageSize($pageSize, true);
        }
        return parent::getPageSize();
    }

    /**
     * @return int|null
     */
    protected function restorePageSize()
    {
        return Yii::$app->request->cookies->getValue($this->cookieName);
    }

    /**
     * @param int $pageSize
     */
    protected function storePageSize($pageSize)
    {
        $cookie = new Cookie([
            'name' => $this->cookieName,
            'value' => $pageSize,
            'expire' => time() + 60 * 60 * 24 * 365,
        ]);

        if ($pageSize != $this->defaultPageSize) {
            Yii::$app->response->cookies->add($cookie);
        } else {
            Yii::$app->response->cookies->remove($cookie);
        }
    }
}
