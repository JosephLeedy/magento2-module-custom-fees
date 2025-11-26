<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Ui\Component;

use InvalidArgumentException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Grid\Collection as SalesGridCollection;
use Magento\Ui\Component\AbstractComponent;

use function array_key_exists;
use function array_walk;

class AbstractComponentPlugin
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly SerializerInterface $serializer,
        private readonly UiComponentFactory $uiComponentFactory,
    ) {}

    public function afterPrepare(AbstractComponent $subject): void
    {
        if ($subject->getData('name') !== 'sales_order_columns' || $this->request->getParam('selected') !== null) {
            return;
        }

        /** @var SalesGridCollection $gridCollection */
        $gridCollection = $subject->getContext()->getDataProvider()->getSearchResult();
        /** @var array<string, string> $uniqueCustomFeesLabelsByCode */
        $uniqueCustomFeesLabelsByCode = [];

        /**
         * @var Order $order
         */
        foreach ($gridCollection->getItems() as $order) {
            /** @var string|null $customFeesOrderedJson */
            $customFeesOrderedJson = $order->getData('custom_fees_ordered');

            if ($customFeesOrderedJson === null) {
                continue;
            }

            try {
                /** @var array<string, CustomOrderFeeData> $customFees */
                $customFees = $this->serializer->unserialize($customFeesOrderedJson);
            } catch (InvalidArgumentException) {
                continue;
            }

            foreach ($customFees as $customFee) {
                if (array_key_exists($customFee['code'], $uniqueCustomFeesLabelsByCode)) {
                    continue;
                }

                $uniqueCustomFeesLabelsByCode[$customFee['code']] = $customFee['title'];
            }
        }

        $defaultArguments = [
            'data' => [
                'config' => [
                    'dataType' => 'text',
                    'component' => 'Magento_Ui/js/grid/columns/column',
                    'componentType' => 'column',
                    'visible' => false,
                    '__disableTmpl' => [
                        'label' => true,
                    ],
                ],
                'js_config' => [
                    'component' => 'Magento_Ui/js/form/element/text',
                    'extends' => 'sales_order_grid',
                ],
            ],
            'context' => $subject->getContext(),
        ];

        array_walk(
            $uniqueCustomFeesLabelsByCode,
            function (string $customFeeLabel, string $customFeeCode) use ($defaultArguments, $subject): void {
                $baseArguments = $defaultArguments;
                $baseArguments['data']['config']['label'] = __('%1 (Base)', $customFeeLabel);
                $baseArguments['data']['name'] = $customFeeCode . '_base';

                $arguments = $defaultArguments;
                $arguments['data']['config']['label'] = __('%1 (Purchased)', $customFeeLabel);
                $arguments['data']['name'] = $customFeeCode;

                $baseColumn = $this->uiComponentFactory->create($customFeeCode . '_base', 'column', $baseArguments);
                $column = $this->uiComponentFactory->create($customFeeCode, 'column', $arguments);

                $subject->addComponent($customFeeCode . '_base', $baseColumn);
                $subject->addComponent($customFeeCode, $column);
            },
        );
    }
}
