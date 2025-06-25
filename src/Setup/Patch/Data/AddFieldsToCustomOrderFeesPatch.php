<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Setup\Patch\Data;

use Exception;
use JosephLeedy\CustomFees\Model\CustomOrderFees;
use JosephLeedy\CustomFees\Model\FeeType;
use JosephLeedy\CustomFees\Model\ResourceModel\CustomOrderFees\CollectionFactory as CustomOrderFeesCollectionFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;

use function array_key_exists;
use function array_walk;

class AddFieldsToCustomOrderFeesPatch implements DataPatchInterface
{
    public function __construct(private readonly CustomOrderFeesCollectionFactory $customOrderFeesCollectionFactory) {}

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply(): self
    {
        $customOrderFeesCollection = $this->customOrderFeesCollectionFactory->create();

        $customOrderFeesCollection->load();

        if ($customOrderFeesCollection->count() === 0) {
            return $this;
        }

        $customOrderFeesCollection->walk(
            /**
             * @throws Exception
             */
            static function (CustomOrderFees $customOrderFees): void {
                /**
                 * @var array{}|array<string, array{
                 *      code: string,
                 *      title: string,
                 *      type?: value-of<FeeType>,
                 *      percent?: float|null,
                 *      show_percentage?: bool,
                 *      base_value: float,
                 *      value: float
                 *  }> $customFees
                 */
                $customFees = $customOrderFees->getCustomFees();
                $hasChanges = false;

                array_walk(
                    $customFees,
                    static function (array &$customFee) use (&$hasChanges): void {
                        if (!array_key_exists('type', $customFee)) {
                            $customFee['type'] = 'fixed';
                            $hasChanges = true;
                        }

                        if (!array_key_exists('percent', $customFee)) {
                            $customFee['percent'] = null;
                            $hasChanges = true;
                        }

                        if (!array_key_exists('show_percentage', $customFee)) {
                            $customFee['show_percentage'] = !FeeType::Percent->equals($customFee['type']) ? '0' : '1';
                            $hasChanges = true;
                        }
                    },
                );

                /**
                 * @var array{}|array<string, array{
                 *      code: string,
                 *      title: string,
                 *      type: value-of<FeeType>,
                 *      percent: float|null,
                 *      show_percentage: bool,
                 *      base_value: float,
                 *      value: float
                 *  }> $customFees
                 */

                if ($hasChanges) {
                    $customOrderFees->setCustomFees($customFees);
                    $customOrderFees->save();
                }
            },
        );

        return $this;
    }
}
