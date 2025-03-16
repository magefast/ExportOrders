<?php

namespace Dragonfly\ExportOrders\Controller\Adminhtml\Order;

use Dragonfly\ExportOrders\Service\SyncOrder;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class MassSync extends AbstractMassAction implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Dragonfly_ExportOrders::management';

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;
    private SyncOrder $syncOrder;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param SyncOrder $syncOrder
     */
    public function __construct(
        Context           $context,
        Filter            $filter,
        CollectionFactory $collectionFactory,
        SyncOrder         $syncOrder
    )
    {

        $this->collectionFactory = $collectionFactory;
        $this->syncOrder = $syncOrder;
        parent::__construct($context, $filter);
    }

    /**
     * @param AbstractCollection $collection
     * @return ResultInterface
     */
    protected function massAction(AbstractCollection $collection)
    {
        $count = 0;
        foreach ($collection->getItems() as $order) {
            $this->syncOrder->exportOneOrder($order->getId());
            $count++;
        }

        if ($count) {
            $this->messageManager->addSuccessMessage(__('1C Synced: %1 order(s)', $count));
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($this->getComponentRefererUrl());
        return $resultRedirect;
    }
}
