<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\SalesRule\Model\Rule\Condition;

use Magento\Framework\Model\AbstractModel;
use Magento\SalesRule\Model\Rule\Condition\Product;

class ProductPlugin
{
    /**
     * @param callable(AbstractModel): bool $proceed
     */
    public function aroundValidate(Product $subject, callable $proceed, AbstractModel $model): bool
    {
        if ($model->hasCustomFee()) {
            return true;
        }

        return $proceed($model);
    }
}
