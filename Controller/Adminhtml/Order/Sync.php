<?php

namespace Dragonfly\ExportOrders\Controller\Adminhtml\Order;

use Dragonfly\ExportOrders\Service\Settings;
use Dragonfly\ExportOrders\Service\SyncOrder;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Delete ApiCity controller.
 */
class Sync extends Action implements HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session.
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Dragonfly_ExportOrders::management';

    private SyncOrder $syncOrder;

    /**
     * @param Context $context
     * @param SyncOrder $syncOrder
     */
    public function __construct(
        Context   $context,
        SyncOrder $syncOrder
    )
    {
        parent::__construct($context);
        $this->syncOrder = $syncOrder;
    }

    public function execute()
    {
        /** @var ResultInterface $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('sales/order/');

        $entityId = (int)$this->getRequest()->getParam(Settings::PARAM_ORDER_ID);

        try {
            $result = $this->syncOrder->exportOneOrder($entityId);
            if ($result) {
                $this->messageManager->addSuccessMessage(__('Order synced order 1C'));
            } else {
                $errors = $this->syncOrder->getErrors();
                $this->messageManager->addErrorMessage($errors);
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $resultRedirect;
    }
}
