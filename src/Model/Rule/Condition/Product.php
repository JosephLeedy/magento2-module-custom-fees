<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Rule\Condition;

use Magento\Rule\Model\Condition\Product\AbstractProduct;

class Product extends AbstractProduct
{
    protected $_isUsedForRuleProperty = 'is_used_for_custom_fee_rules';
}
