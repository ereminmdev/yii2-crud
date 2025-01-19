<?php

namespace common\models;

use common\behaviors\TagCacheBehavior;
use common\helpers\MainHelper;
use Yii;
use yii\caching\TagDependency;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * This is the model class for table "{{%order}}".
 *
 * @property int $id
 * @property string $global_id
 * @property int $user_id
 * @property int $employee_id
 * @property string $order_code
 * @property string $contract_code
 * @property string $date_time
 * @property string $date_time_change
 * @property string $date_score
 * @property int $full_col
 * @property float $full_price
 * @property float $full_price_discount
 * @property string $status
 * @property string $comment
 * @property string $stroke_code
 * @property string $user_phone
 * @property string $user_email
 * @property string $user_address
 * @property string $user_comments
 * @property string $comments_1c
 * @property string $user_payment
 * @property string $invoice_payment
 * @property string $date_payment
 * @property string $weight
 * @property string $capacity
 * @property float $discount_1c
 * @property float $discount_sconto_1c
 * @property int $can_edit
 * @property float $sum_nds
 * @property float $sum_not_nds
 * @property float $sum_not_discount
 * @property string $city_code
 * @property string $city_name
 * @property string $fio
 * @property string $adds
 * @property string $order_txt
 * @property string $order_items_json
 * @property string $order_items_1c
 * @property string $xml_to_1c
 *
 * @property array[] $items
 *
 * @property User $user
 * @property Employee $employee
 * @property Contract $contract
 * @property Rnk[] $rnks
 * @property City $city
 * @property OrderHistory[] $orderHistory
 */
class Order extends ActiveRecord
{
    public const CACHE_TAG = 'model-Order-changed';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%order}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            [
                'class' => TagCacheBehavior::class,
                'tags' => self::CACHE_TAG,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['global_id', 'order_code', 'contract_code', 'status', 'comment', 'stroke_code', 'user_phone', 'user_email', 'user_payment', 'invoice_payment', 'date_payment', 'weight', 'capacity', 'city_code', 'city_name', 'user_address', 'fio', 'order_txt', 'order_items_json', 'order_items_1c', 'user_comments', 'comments_1c', 'adds', 'xml_to_1c'], 'trim'],

            [['full_price', 'full_price_discount', 'discount_1c', 'discount_sconto_1c', 'sum_nds', 'sum_not_nds', 'sum_not_discount'], function ($attribute) {
                $this->$attribute = is_string($this->$attribute) ? (float)str_replace(',', '.', $this->$attribute) : $this->$attribute;
            }],

            [['global_id', 'order_code', 'contract_code', 'status', 'comment', 'stroke_code', 'user_phone', 'user_email', 'user_payment', 'invoice_payment', 'date_payment', 'weight', 'capacity', 'city_code', 'city_name'], 'string', 'max' => 255],
            [['user_address'], 'string', 'max' => 1000],
            [['fio', 'order_txt', 'order_items_json', 'order_items_1c', 'user_comments', 'comments_1c', 'adds', 'xml_to_1c'], 'string'],
            [['user_id', 'employee_id', 'full_col'], 'integer'],
            [['full_price', 'full_price_discount', 'discount_1c', 'discount_sconto_1c', 'sum_nds', 'sum_not_nds', 'sum_not_discount'], 'number'],
            [['can_edit'], 'boolean'],
            [['date_time', 'date_time_change', 'date_score'], 'safe'],

            [['user_id', 'employee_id', 'full_col', 'full_price', 'full_price_discount', 'discount_1c', 'discount_sconto_1c', 'sum_nds', 'sum_not_nds', 'sum_not_discount'], 'default', 'value' => 0],
            [['status'], 'default', 'value' => 'Заявка'],

            [['order_txt', 'order_items_json', 'order_items_1c', 'comments_1c'], 'sanitizeFrom1c'],

            [['id'], 'safe', 'on' => 'search'],

            [['date_time'], 'required'],
        ];
    }

    /**
     * @param string $attribute the attribute currently being validated
     */
    public function sanitizeFrom1c($attribute)
    {
        if (!$this->hasErrors()) {
            $this->$attribute = trim((string)$this->$attribute, '\' ');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '№',
            'global_id' => 'Глобальный №',
            'date_time' => 'Дата время',
            'order_code' => 'Код заказа',
            'contract_code' => 'Договор',
            'fio' => 'Заказчик',
            'order_txt' => 'Заказ',
            'full_col' => 'Общее кол-во',
            'full_price' => 'Общая стоимость',
            'full_price_discount' => 'Общая стоимость с учетом скидки',
            'user_id' => 'Пользователь',
            'employee_id' => 'Сотрудник',
            'adds' => 'Дополнительно',
            'city_code' => 'Код города',
            'city_name' => 'Город',
            'can_edit' => 'Можно редактировать (не заблокирован по API)',
            'status' => 'Статус',
            'comment' => 'Комментарий (заметка)',
            'date_time_change' => 'Дата время изменения',
            'date_score' => 'Дата заказа',
            'stroke_code' => 'Штрих-код',
            'user_phone' => 'Телефон покупателя',
            'user_email' => 'E-mail покупателя',
            'user_address' => 'Адрес покупателя',
            'user_comments' => 'Комментарий покупателя',
            'comments_1c' => 'Комментарий (1C)',
            'user_payment' => 'Оплата',
            'invoice_payment' => 'Счет на оплату',
            'date_payment' => 'Дата платежа',
            'weight' => 'Общий вес (1C)',
            'capacity' => 'Общий объем (1C)',
            'discount_1c' => 'Скидка (1C)',
            'discount_sconto_1c' => 'Скидка сконто (1C)',
            'sum_nds' => 'Сумма с НДС (1C)',
            'sum_not_nds' => 'Сумма без НДС (1C)',
            'sum_not_discount' => 'Сумма без скидок (1C)',
            'order_items_1c' => 'Товары (1C)',
            'order_items_json' => 'Товары (Json)',
            'xml_to_1c' => 'XML для переотправки в 1С',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function find()
    {
        return parent::find()
            ->cache(0, new TagDependency(['tags' => self::CACHE_TAG]))
            ->orderBy(['order.date_score' => SORT_DESC, 'order.id' => SORT_DESC]);
    }

    /**
     * @return ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id'])->inverseOf('orders');
    }

    /**
     * @return ActiveQuery
     */
    public function getEmployee()
    {
        return $this->hasOne(Employee::class, ['id' => 'employee_id'])->inverseOf('orders');
    }

    /**
     * @return ActiveQuery
     */
    public function getContract()
    {
        return $this->hasOne(Contract::class, ['code' => 'contract_code'])->inverseOf('orders');
    }

    /**
     * @return ActiveQuery
     */
    public function getRnks()
    {
        return $this->hasMany(Rnk::class, ['order_global_id' => 'global_id'])->inverseOf('order');
    }

    /**
     * @return ActiveQuery
     */
    public function getCity()
    {
        return $this->hasOne(City::class, ['code' => 'city_code']);
    }

    /**
     * @return ActiveQuery
     */
    public function getOrderHistory()
    {
        return $this->hasMany(OrderHistory::class, ['order_id' => 'id'])->inverseOf('order');
    }

    /**
     * @param int $user_id
     * @return int
     */
    public static function countForUser($user_id)
    {
        return static::find()->andWhere(['user_id' => $user_id])->count() ?: 0;
    }

    /**
     * @return float
     */
    public function getFullPrice()
    {
        return ($this->sum_nds ?: $this->full_price_discount) ?: $this->full_price;
    }

    /**
     * @return bool
     */
    public function canPaid()
    {
        return !$this->isPaid() && !in_array($this->status, ['Черновик', 'Заявка', 'Отменен']);
    }

    /**
     * @return bool
     */
    public function isPaid()
    {
        return (bool)$this->invoice_payment;
    }

    /**
     * @return bool
     */
    public function isFrom1c()
    {
        return (bool)preg_match('/^[a-z]{2}/i', $this->global_id);
    }

    /**
     * @return bool
     */
    public function isDraft()
    {
        return $this->status == 'Черновик';
    }

    /**
     * @return bool
     */
    public function canEdit()
    {
        return $this->can_edit && in_array($this->status, ['', 'Заявка', 'Черновик', 'Отправлен']) && !$this->isPaid() && !$this->isFrom1c();
    }

    /**
     * @return array
     */
    public function getItems()
    {
        $items = [];
        if (!empty($this->order_items_1c)) {
            $items = unserialize($this->order_items_1c);
        } elseif (!empty($this->order_items_json)) {
            $items = json_decode($this->order_items_json, true);
        }
        $items = is_array($items) ? $items : [];

        foreach ($items as $idx => $item) {
            $items[$idx]['id'] ??= '';
            $items[$idx]['code'] ??= '';
            $items[$idx]['articul'] ??= '';
            $items[$idx]['nomer_old'] ??= '';
            $items[$idx]['txt'] ??= '';
            $items[$idx]['group_id'] ??= 0;
            $items[$idx]['price'] ??= 0;
            $items[$idx]['price_fullopt'] ??= $items[$idx]['price'];
            $items[$idx]['price_event'] ??= 0;
            $items[$idx]['real_price'] ??= $items[$idx]['price'];
            $items[$idx]['moc_price'] ??= 0;
            $items[$idx]['col'] ??= 0;
            $items[$idx]['sum'] ??= $items[$idx]['price'] * $items[$idx]['col'];
            $items[$idx]['step'] ??= 1;
            $items[$idx]['count_bu'] ??= 0;
            $items[$idx]['vendor_name'] ??= '';
            $items[$idx]['date_delivery'] ??= '';
        }

        return $items;
    }

    /**
     * @return array
     */
    public function getItemsWithModels()
    {
        $items = $this->getItems();

        $models = self::getProductModels($items);
        $idsToCodes = array_column($models, 'code', 'id');

        foreach ($items as $idx => $item) {
            $code = $item['code'] ?? null;
            $id = $item['id'] ?? null;

            if (empty($code) && !empty($id) && isset($idsToCodes[$id])) {
                $code = $idsToCodes[$id];
            }

            if (!empty($code)) {
                $items[$idx]['code'] = $code;

                if (isset($models[$code])) {
                    $model = $models[$code];
                    $items[$idx]['id'] = $model->id;
                    $items[$idx]['articul'] = $model->articul;
                }
            }
        }

        return $items;
    }

    /**
     * @param array $items
     * @return Product[]
     */
    public static function getProductModels($items)
    {
        if (empty($items)) {
            return [];
        }

        $ids = array_filter(array_unique(array_column($items, 'id')));
        $codes = array_filter(array_unique(array_column($items, 'code')));

        return Product::find()->andFilterWhere(['id' => $ids, 'code' => $codes])->indexBy('code')->all();
    }

    /**
     * @param array $items
     */
    public function saveItems($items)
    {
        $full_col = 0;
        $full_price = 0;
        $order_txt = '';
        $orderItems = [];

        /** @var Product[] $products */
        $products = Product::find()
            ->andWhere(['id' => array_column($items, 'id')])
            ->with(['parent', 'vendor'])
            ->indexBy('id')
            ->all();

        foreach ($items as $key => $item) {
            $product = $products[$item['id']] ?? null;

            if ($product === null) {
                continue;
            }

            $col = (float)$item['quantity'];
            $price = (float)$item['price'];
            $full_col += $col;
            $full_price += $col * $price;
            $order_txt .= ($key + 1) . ') ' . $item['name'] . ' (' . MainHelper::price($price) . ' * ' . $col . 'шт. = ' . MainHelper::price($col * $price) . ')' . "\n";

            $orderItems[] = [
                'id' => $product->id,
                'code' => $product->code,
                'nomer_old' => $product->number_old,
                'txt' => $item['name'],
                'group_id' => $product->parent->id_1c ?? 0,
                'price' => $price,
                'price_event' => $product->price_event,
                'real_price' => $product->price,
                'moc_price' => $product->price_moc,
                'step' => $product->getMinOrderCountValue(),
                'vendor_name' => $product->vendor->title ?? '',
                'col' => $col,
                //'price_fullopt' => $product->price_full_opt,
                //'count_bu' => $product->count_bu,
                //'bonus' => 0,
                //'url' => Yii::$app->urlManagerFrontend->createAbsoluteUrl($product->getSiteUrl()),
                //'adds' => '',
            ];
        }

        $this->full_col = $full_col;
        $this->full_price = floatval($full_price);
        $this->full_price_discount = floatval($full_price);
        $this->order_txt = $order_txt;
        $this->order_items_json = Json::encode($orderItems);
        $this->order_items_1c = '';

        if ($this->save()) {
            Yii::$app->bitrix1c->exportOrder($this);
        }
    }

    /**
     * @return string
     */
    public function renderNumber()
    {
        return ($this->order_code ?: $this->id) . $this->renderDateScore(' от ');
    }

    /**
     * @param string $prepend
     * @return string
     */
    public function renderDateScore($prepend = '')
    {
        return !empty($this->date_score) && ($this->date_score != '0000-00-00') ? $prepend . date('d.m.Y', strtotime($this->date_score)) : '';
    }

    /**
     * @return string
     */
    public function renderDateTimeChange()
    {
        return !empty($this->date_time_change) && ($this->date_time_change != '2015-01-01 00:00:00') ? date('d.m.Y', strtotime($this->date_time_change)) : '';
    }

    /**
     * Get configuration for ereminmdev\yii2_crud\components\Crud module
     * @return array
     */
    public static function crudConfig()
    {
        $title = 'Заказы';
        $breadcrumbs = [];

        $parent_id = Yii::$app->request->get('Order')['user_id'] ?? null;
        if ($parent_id && ($parent = User::findOne($parent_id))) {
            $breadcrumbs[] = ['label' => 'Заказы', 'url' => ['/crud/default/index', 'model' => self::class]];
            $title = 'Заказы пользователя "' . $parent->username . '"';
        }

        return [
            'title' => $title,
            'breadcrumbs' => $breadcrumbs,
            'gridShowColumns' => ['id', 'global_id', 'date_score', 'order_code', 'city_name', 'user_id', 'fio', 'order_txt', 'adds', 'full_col', 'full_price', 'full_price_discount', 'can_edit'],
            'gridEditLinkField' => 'id',
            'columnsSchema' => [
                'user_id' => [
                    'type' => 'relation',
                    'rtype' => 'hasOne',
                    'relation' => 'user',
                    'titleField' => 'username',
                    'select2' => true,
                ],
                'employee_id' => [
                    'type' => 'relation',
                    'rtype' => 'hasOne',
                    'relation' => 'employee',
                    'titleField' => 'name',
                    'select2' => true,
                ],
                'contract_code' => [
                    'type' => 'relation',
                    'rtype' => 'hasOne',
                    'relation' => 'contract',
                    'titleField' => 'code',
                    'select2' => true,
                ],
            ],
            'gridActionsTemplate' => "{update}\n{--}\n{sendTo1c}\n{--}\n{delete}",
            'gridActions' => [
                '{sendTo1c}' => fn(self $model) => $model->xml_to_1c ?
                    [
                        'label' => 'Отправить заказ в 1С',
                        'url' => ['/bitrix1c/send-order', 'id' => $model->id],
                        'linkOptions' => ['class' => 'js-load-in-modal'],
                    ] : ''
            ],
            'gridViewOptions' => [
                'layout' => "<div class=\"cms-crud-pager\">{pager}{summary}</div>\n{items}\n<div class=\"cms-crud-pager\">{pager}{summary}</div>",
            ],
            'access.save' => !Yii::$app->user->is('viewer'),
            'access.delete' => !Yii::$app->user->is('viewer'),
        ];
    }
}
