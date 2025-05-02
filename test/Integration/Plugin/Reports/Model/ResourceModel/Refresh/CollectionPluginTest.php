<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Plugin\Reports\Model\ResourceModel\Refresh;

use Magento\Framework\App\Area;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Reports\Model\Flag;
use Magento\Reports\Model\ResourceModel\Refresh\Collection;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function __;

#[AppArea(Area::AREA_ADMINHTML)]
final class CollectionPluginTest extends TestCase
{
    public function testAddsCustomOrderFeesReportToCollection(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var DateTime $dateTime */
        $dateTime = $objectManager->get(DateTime::class);
        /** @var Flag $flag */
        $flag = $objectManager->get(Flag::class);
        /** @var Collection $collection */
        $collection = $objectManager->create(Collection::class);

        $flag->setReportFlagCode('report_custom_order_fees_aggregated')->loadSelf();
        $flag->setLastUpdate($dateTime->gmtDate())->save();

        $collection->load();

        $customOrderFeesReport = $collection->getItemById('custom_order_fees');
        $expectedData = [
            'id' => 'custom_order_fees',
            'report' => __('Custom Order Fees'),
            'comment' => __('Total Custom Order Fees Report'),
            'updated_at' => $flag->getLastUpdate(),
        ];

        self::assertNotNull($customOrderFeesReport);
        self::assertEquals($expectedData, $customOrderFeesReport->getData());
    }
}
