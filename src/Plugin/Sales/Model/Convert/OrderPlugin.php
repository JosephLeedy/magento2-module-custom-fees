<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Sales\Model\Convert;

use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\Data\CreditmemoExtensionInterface;
use Magento\Sales\Model\Convert\Order;
use Magento\Sales\Model\Order\Creditmemo;

class OrderPlugin
{
    public function __construct(private readonly RequestInterface $request) {}

    /**
     * Initialize custom fee data from request
     *
     * **Note:** the `toCreditmemo` method is intercepted here because the extension attributes need to be set when the
     * credit memo's totals are collected in the `createByOrder()` and `createByInvoice()` methods of
     * `\Magento\Sales\Model\Order\CreditmemoFactory`.
     */
    public function afterToCreditmemo(Order $subject, Creditmemo $result): Creditmemo
    {
        $this->initCustomFeeData($result);

        return $result;
    }

    private function initCustomFeeData(Creditmemo $creditmemo): void
    {
        /**
         * @var array{
         *     custom_fees?: array<string, float|array<string, array{
         *         code: string,
         *         title: string,
         *         type: value-of<FeeType>,
         *         percent: float|null,
         *         show_percentage: bool,
         *         base_value: float,
         *         value: float,
         *     }>>
         * } $data
         */
        $data = $this->request->getParam('creditmemo', []);
        /** @var CreditmemoExtensionInterface $extensionAttributes */
        $extensionAttributes = $creditmemo->getExtensionAttributes();

        $extensionAttributes->setRefundedCustomFees($data['custom_fees'] ?? []);
    }
}
