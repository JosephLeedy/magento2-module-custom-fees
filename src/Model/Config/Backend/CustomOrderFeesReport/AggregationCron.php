<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Config\Backend\CustomOrderFeesReport;

use Exception;
use Magento\Cron\Model\Config\Source\Frequency;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

use function __;
use function sprintf;

class AggregationCron extends Value
{
    public const CRON_EXPRESSION_PATH
        = 'crontab/default/jobs/aggregate_custom_order_fees_report/schedule/cron_expr';
    private const CONFIG_PATH_ENABLE_AGGREGATION
        = 'groups/custom_order_fees/groups/report/fields/enable_aggregation/value';
    private const CONFIG_PATH_AGGREGATION_TIME = 'groups/custom_order_fees/groups/report/fields/aggregation_time/value';
    private const CONFIG_PATH_AGGREGATION_FREQUENCY
        = 'groups/custom_order_fees/groups/report/fields/aggregation_frequency/value';

    /**
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly ValueFactory $configValueFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [],
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @throws LocalizedException
     */
    public function afterSave(): self
    {
        $isEnabled = (bool) ($this->getData(self::CONFIG_PATH_ENABLE_AGGREGATION) ?? '0');
        /** @var string[] $time */
        $time = $this->getData(self::CONFIG_PATH_AGGREGATION_TIME) ?? ['00', '00', '00'];
        /** @var string $frequency */
        $frequency = $this->getData(self::CONFIG_PATH_AGGREGATION_FREQUENCY) ?? 'D';
        $cronExpression = sprintf(
            '%d %d %s * %s',
            (int) $time[1],
            (int) $time[0],
            $frequency === Frequency::CRON_MONTHLY ? '1' : '*',
            $frequency === Frequency::CRON_WEEKLY ? '1' : '*',
        );

        if (!$isEnabled) {
            return parent::afterSave();
        }

        try {
            /** @var Value $cronExpressionConfigValue */
            $cronExpressionConfigValue = $this->configValueFactory->create();

            $cronExpressionConfigValue
                ->load(self::CRON_EXPRESSION_PATH, 'path')
                ->setPath(self::CRON_EXPRESSION_PATH)
                ->setValue($cronExpression)
                ->save();
        } catch (Exception $exception) {
            throw new LocalizedException(
                __('Could not save Cron expression for custom order fees report aggregation.'),
                $exception,
            );
        }

        return parent::afterSave();
    }
}
