<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Reports\Model\ResourceModel\Refresh;

use Exception;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Reports\Model\FlagFactory;
use Magento\Reports\Model\ResourceModel\Refresh\Collection;

use function __;

class CollectionPlugin
{
    private bool $isAdded = false;

    public function __construct(
        private readonly FlagFactory $reportsFlagFactory,
        private readonly DataObjectFactory $dataObjectFactory,
    ) {}

    /**
     * @throws LocalizedException
     * @throws Exception
     */
    public function afterLoadData(Collection $subject, Collection $result): Collection
    {
        if ($this->isAdded !== false) {
            return $result;
        }

        $flag = $this
            ->reportsFlagFactory
            ->create()
            ->setReportFlagCode('report_custom_order_fees_aggregated')
            ->loadSelf();
        $item = $this->dataObjectFactory->create();

        $item->setData(
            [
                'id' => 'custom_order_fees',
                'report' => __('Custom Order Fees'),
                'comment' => __('Total Custom Order Fees Report'),
                'updated_at' => $flag->hasData() ? $flag->getLastUpdate() : '',
            ],
        );

        $result->addItem($item);

        $this->isAdded = true;

        return $result;
    }
}
