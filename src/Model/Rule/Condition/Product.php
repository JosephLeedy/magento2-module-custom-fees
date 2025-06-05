<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Rule\Condition;

use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote\Item;
use Magento\Rule\Model\Condition\Product\AbstractProduct;

class Product extends AbstractProduct
{
    protected $_isUsedForRuleProperty = 'is_used_for_custom_fee_conditions';

    public function validate(AbstractModel $model): bool
    {
        if (!$model instanceof Item) {
            return true;
        }

        return parent::validate($model->getProduct());
    }
}
