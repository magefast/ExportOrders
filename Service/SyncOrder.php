<?php

namespace Dragonfly\ExportOrders\Service;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Csv;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status\HistoryFactory;

class SyncOrder
{
    /**
     * @var array
     */
    private array $errors = [];
    private Settings $settings;
    private OrderRepositoryInterface $orderRepository;
    private Csv $csv;
    private HistoryFactory $historyFactory;

    /**
     * @param Settings $settings
     * @param OrderRepositoryInterface $orderRepository
     * @param Csv $csv
     * @param HistoryFactory $historyFactory
     */
    public function __construct(
        Settings                 $settings,
        OrderRepositoryInterface $orderRepository,
        Csv                      $csv,
        HistoryFactory           $historyFactory
    )
    {
        $this->settings = $settings;
        $this->orderRepository = $orderRepository;
        $this->csv = $csv;
        $this->historyFactory = $historyFactory;
    }

    /**
     * @param $id
     * @return bool
     */
    public function exportOneOrder($id): bool
    {
        return $this->runSyncOrder($id);
    }

    /**
     * @param $orderId
     * @return bool
     */
    private function runSyncOrder($orderId): bool
    {
        try {
            $order = $this->orderRepository->get($orderId);

            if (!$this->settings->canExportTo1C($order->getStatus())) {
                $this->errors[] = __('Cannot export order to 1C. Because order not processes.');
                return false;
            }

            if ($order->getId()) {
                $exportDir = $this->settings->getExportDir();

                if (!is_dir($exportDir)) {
                    mkdir($exportDir);
                }
                $fileName = date("Ymd") . '_' . $order->getData('increment_id') . ".csv";
                $filePath = $exportDir . '/' . $fileName;

                $customerPhone = '';
                $customerName = '';

                if ($order->getData('customer_firstname')) {
                    $customerName = $order->getData('customer_firstname');
                }

                if ($order->getData('customer_lastname')) {
                    $customerName = $order->getData('customer_lastname') . ' ' . $customerName;
                }

                if ($order->getShippingAddress()->getTelephone()) {
                    $customerPhone = $order->getShippingAddress()->getTelephone();
                    $customerPhone = $this->settings->preparePhoneNumber($customerPhone);
                }

                $products = $order->getAllVisibleItems();
                $productsAll = $order->getAllItems();

                $csvRow = [];
                $dataCsv = [];
                $dataCsv['sku'] = 'Артикул';
                $dataCsv['qty'] = 'количество';
                $dataCsv['price'] = 'цена';
                $dataCsv['cost'] = 'сума';
                $dataCsv['phone'] = 'номер клиента';
                $dataCsv['name'] = 'имя клиент';
                $dataCsv['area'] = 'подразделение';
                $dataCsv['comment'] = 'комментарий';
                $csvRow[] = $dataCsv;

                $orderComment = [];
                if ($order->getPayment()->getMethodInstance()->getCode() != 'liqpay') {
                    $orderComment = $this->getOrderComment($order);
                }
                $orderAreaCreated = $orderAreaCreated1 = $order->getExtensionAttributes()->getOrderAreaCreated($order) ?? '';

                $listOrderAreaCreated = $this->settings->getListOrderAreaCreated();
                if (isset($listOrderAreaCreated[$orderAreaCreated])) {
                    $orderAreaCreated = $listOrderAreaCreated[$orderAreaCreated];
                }

                if ($orderAreaCreated != '') {
                    $orderComment[] = $orderAreaCreated . $order->getData('increment_id');
                } else {
                    $orderComment[] = 'Site' . $order->getData('increment_id');
                }

                $listOrderAreaCreatedCode = $this->settings->getListOrderAreaCreatedCode();

                $orderAreaCreatedCode = '';
                if (isset($listOrderAreaCreatedCode[$orderAreaCreated1])) {
                    $orderAreaCreatedCode = $listOrderAreaCreatedCode[$orderAreaCreated1];
                }

                $orderComment = implode('#%*', $orderComment);

                //$i = 0;
                foreach ($products as $product) {

                    if ($product->getData('product_type') == 'bundle') {
                        foreach ($productsAll as $pa) {
                            if ($pa->getData('parent_item_id') == $product->getData('item_id')) {
                                $dataCsv = [];
                                $dataCsv['sku'] = $pa->getSku();
                                $dataCsv['qty'] = $pa->getQtyOrdered();
                                $dataCsv['price'] = $pa->getPrice();
                                $dataCsv['cost'] = $this->getRowTotal($product);
                                $dataCsv['phone'] = $customerPhone;
                                $dataCsv['name'] = $customerName;
                                $dataCsv['area'] = $orderAreaCreatedCode;
                                $dataCsv['comment'] = $orderComment;
                                $csvRow[] = $dataCsv;
                            }
                        }
                        continue;
                    }

                    $dataCsv = [];
                    $dataCsv['sku'] = $product->getSku();
                    $dataCsv['qty'] = $product->getQtyOrdered();
                    $dataCsv['price'] = $product->getPrice();
                    $dataCsv['cost'] = $this->getRowTotal($product);
                    $dataCsv['phone'] = $customerPhone;
                    $dataCsv['name'] = $customerName;
                    $dataCsv['area'] = $orderAreaCreatedCode;
                    $dataCsv['comment'] = $orderComment;
                    $csvRow[] = $dataCsv;
                    //$i++;
                }

                /**
                 * Add shipping as Product
                 */
                $shippingAsSku = $this->settings->getShipppingMethodCode($order);

                if ($shippingAsSku != '' && isset($shippingAsSku['price']) && $order->getData('shipping_amount') && intval($order->getData('shipping_amount')) != 0) {
                    $dataCsv = [];
                    $dataCsv['sku'] = $shippingAsSku['code'];
                    $dataCsv['qty'] = '1';
                    $dataCsv['price'] = $shippingAsSku['price'];
                    $dataCsv['cost'] = $shippingAsSku['price'];
                    $dataCsv['phone'] = $customerPhone;
                    $dataCsv['name'] = $customerName;
                    $dataCsv['area'] = $orderAreaCreatedCode;
                    $dataCsv['comment'] = $orderComment;
                    $csvRow[] = $dataCsv;
                }

                unset($products, $productsAll);

                $this->csv->setEnclosure('"');
                $this->csv->setDelimiter(',');
                $this->csv->appendData($filePath, $csvRow);

                $this->addCommentToOrder($order);
                // Mage::getModel('admin1c/admin1c')->markOrderAsSentTo1C($order->getId(), '');
                unset($order);

                return true;
            }

        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        return false;
    }

    /**
     * @param $order
     * @return array
     */
    public function getOrderComment($order): array
    {
        $array = [];

        foreach ($order->getStatusHistoryCollection(true) as $_item) {
            if ($_item->getComment()) {
                $array[] = strip_tags($_item->getComment());
            }
        }

        return $array;
    }

    /**
     * @param $product
     * @return float
     */
    private function getRowTotal($product): float
    {
        if ($product->getDiscountAmount() && $product->getDiscountAmount() > 0) {
            $rowTotal = $product->getRowTotal() + $product->getTaxAmount() + $product->getHiddenTaxAmount() + $product->getWeeeTaxAppliedRowAmount() - $product->getDiscountAmount();
        } else {
            $rowTotal = $product->getRowTotal();
        }
        return floatval($rowTotal);
    }

    /**
     * @param $order
     * @return void
     * @throws LocalizedException
     */
    private function addCommentToOrder($order): void
    {
        try {
            $statusHistory = $this->historyFactory->create();
            $statusHistory->setComment(__(Settings::SYNCED_MESSAGE));
            $statusHistory->setEntityName(Order::ENTITY);
            $statusHistory->setStatus($order->getStatus());
            $statusHistory->setIsCustomerNotified(false)->setIsVisibleOnFront(false);
            $order->addStatusHistory($statusHistory);
            $order->save();
        } catch (Exception $e) {
            throw new LocalizedException(__("Failed to add the comment to the order: %1", $e->getMessage()));
        }
    }

    /**
     * @return string
     */
    public function getErrors(): string
    {
        return implode('; ', $this->errors);
    }
}
