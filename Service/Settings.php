<?php

namespace Dragonfly\ExportOrders\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Settings
{
    public const PARAM_ORDER_ID = 'entity_id';
    public const STATUSES_SYNC = ['processing', 'pending',   'workingready'];

    public const SYNCED_MESSAGE = "<strong>Exported to 1C</strong>";

    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return string
     */
    public function getExportDir(): string
    {
        return $this->scopeConfig->getValue('sales/dragonfly_export_orders/export_dir');
    }

    /**
     * @param string $value
     * @return string
     */
    public function preparePhoneNumber(string $value = ''): string
    {
        $value = trim($value);
        $value = preg_replace('/[^0-9]/', '', $value);

        if ($value == '') {
            return '';
        }

        $count = iconv_strlen($value);

        if (9 > $count) {
            return '';
        }

        if (9 == $count) {
            return '380' . $value;
        }

        if (10 == $count) {
            $firstSymbol = substr($value, 0, 1);
            if ($firstSymbol == '0') {
                return '38' . $value;
            }
        }

        if (11 == $count) {
            $firstSymbol = substr($value, 0, 1);
            if ($firstSymbol == '8') {
                return '3' . $value;
            }
        }

        return $value;
    }

    /**
     * @return string[]
     */
    public function getListOrderAreaCreated(): array
    {
        $array = [
            'user' => 'Site',
            'Rozetka' => 'Rozetka',
            'Prom' => 'Prom',
            'Allo' => 'Allo',
            'Epicentr' => 'Epicentr',
            'Rozetka2' => 'Rozetka2',
        ];

//        $admins = Mage::getResourceModel('admin/user_collection')->getItems();
//        foreach ($admins as $a) {
//            $array[$a->getData('username')] = $a->getData('username');
//        }
//        unset($admins);

        return $array;
    }

    /**
     * @return string[]
     */
    public function getListOrderAreaCreatedCode(): array
    {
        $array = [
            'Rozetka' => '1',
            'Prom' => '2',
            'user' => '3',
            'Rozetka2' => '4',
            'Allo' => '5',
            'Epicentr' => '6',
        ];

//        $admins = Mage::getResourceModel('admin/user_collection')->getItems();
//        foreach ($admins as $a) {
//            $array[$a->getData('username')] = '3';
//        }
//        unset($admins);

        return $array;
    }

    /**
     * @param $orderStatus
     * @return bool
     */
    public function canExportTo1C($orderStatus): bool
    {
        if (in_array($orderStatus, self::STATUSES_SYNC)) {
            return true;
        }
        return false;
    }

    /**
     * @param $order
     * @return array|mixed|string|string[]
     */
    public function getShipppingMethodCode($order): mixed
    {
        /**
         * dostavka Kiev
         */
        $arrayData[] = array(
            'shipping_method' => 'tablerate_bestway',
            'price' => '30.0000',
            'code' => '001'
        );

        /**
         * dostavka po Ukraine v ruki
         */
        $arrayData[] = array(
            'shipping_method' => 'novaposhtashippingadresnaya_novaposhtashippingadresnaya',
            'price' => '35.0000',
            'code' => '002'
        );

        /**
         * dostavka meestshipping
         */
        $arrayData[] = array(
            'shipping_method' => 'meestshipping_meestshipping',
            'price' => $order->getShippingAmount(),
            'code' => '003'
        );

        $shippingMethod = $order->getData('shipping_method');

        foreach ($arrayData as $a) {
            if ($a['shipping_method'] == $shippingMethod) {
                return $a;
            }
        }

        return '';
    }
}
