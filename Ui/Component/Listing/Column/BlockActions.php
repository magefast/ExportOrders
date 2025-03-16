<?php

namespace Dragonfly\ExportOrders\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Class to build edit and delete link for each item.
 */
class BlockActions extends Column
{
    /**
     * Entity name.
     */
    private const ENTITY_NAME = 'ApiDepots';

    /**
     * Url paths.
     */
    private const EDIT_URL_PATH = 'expord/order/sync';

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface   $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface       $urlBuilder,
        array              $components = [],
        array              $data = []
    )
    {
        parent::__construct(
            $context,
            $uiComponentFactory,
            $components,
            $data
        );
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Prepare data source.
     *
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item['entity_id'])) {
                    $urlData = ['entity_id' => $item['entity_id']];
                    $editUrl = $this->urlBuilder->getUrl(static::EDIT_URL_PATH, $urlData);
                    $dialogTitle = __('Sync to 1C');
                    $dialogMessage = __('Confirm sync order # %1', $item['increment_id']);
                    $item[$this->getData('name')] = [
                        'edit' => $this->getActionData($editUrl, (string)__('1C'), $dialogTitle, $dialogMessage)
                    ];
                }
            }
        }

        return $dataSource;
    }

    /**
     * Get action link data array.
     *
     * @param string $url
     * @param string $label
     * @param string $dialogTitle
     * @param string $dialogMessage
     *
     * @return array
     */
    private function getActionData(
        string $url,
        string $label,
        string $dialogTitle,
        string $dialogMessage
    ): array
    {
        $data = [
            'href' => $url,
            'label' => $label,
            'post' => true,
            '__disableTmpl' => true,
            'confirm' => [
                'title' => $dialogTitle,
                'message' => $dialogMessage
            ]
        ];

        return $data;
    }
}
