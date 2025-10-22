<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Cron;

use DateInterval;
use Exception;
use InvalidArgumentException;
use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Model\ResourceModel\Report\CustomOrderFees as CustomOrderFeesReport;
use JosephLeedy\CustomFees\Model\ResourceModel\Report\CustomOrderFeesFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

use function __;

class AggregateCustomOrderFeesReport
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ResolverInterface $localeResolver,
        private readonly TimezoneInterface $localeDate,
        private readonly CustomOrderFeesFactory $customOrderFeesReportFactory,
    ) {}

    /**
     * @throws InvalidArgumentException
     * @throws LocalizedException
     */
    public function execute(): void
    {
        if (!$this->config->isCustomOrderFeesReportAggregationEnabled()) {
            return;
        }

        $this->localeResolver->emulate(0);

        $fromDate = $this->localeDate->date();
        $reportAggregationFrequency = $this->config->getCustomOrderFeesReportAggregationFrequency();
        $duration = match ($reportAggregationFrequency) {
            'D' => 'PT25H',
            'W' => 'P1WT1H',
            'M' => 'P1MT1H',
            default => throw new InvalidArgumentException(
                (string) __(
                    'Invalid custom order fees report aggregation frequency "%1".',
                    $reportAggregationFrequency,
                ),
            ),
        };
        /** @var CustomOrderFeesReport $customOrderFeesReport */
        $customOrderFeesReport = $this->customOrderFeesReportFactory->create();

        $fromDate->sub(new DateInterval($duration));

        try {
            $customOrderFeesReport->aggregate($fromDate);
        } catch (Exception $exception) {
            throw new LocalizedException(
                __('Could not aggregate custom order fees report. Error: "%1"', $exception->getMessage()),
                $exception,
            );
        }

        $this->localeResolver->revert();
    }
}
