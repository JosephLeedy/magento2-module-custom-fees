<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Rule;

use JosephLeedy\CustomFees\Model\Rule\Condition\Combine;
use JosephLeedy\CustomFees\Model\Rule\Condition\CombineFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Rule\Model\AbstractModel;
use Magento\Rule\Model\Action\Collection;
use Magento\Rule\Model\Action\CollectionFactory;

/**
 * @method self setFeeCode(string $feeCode)
 * @method string getFeeCode()
 */
class CustomFees extends AbstractModel
{
    /**
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        TimezoneInterface $localeDate,
        private readonly CombineFactory $combineFactory,
        private readonly CollectionFactory $actionFactory,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
        ?ExtensionAttributesFactory $extensionFactory = null,
        ?AttributeValueFactory $customAttributeFactory = null,
        ?Json $serializer = null,
    ) {
        parent::__construct(
            $context,
            $registry,
            $formFactory,
            $localeDate,
            $resource,
            $resourceCollection,
            $data,
            $extensionFactory,
            $customAttributeFactory,
            $serializer,
        );
    }

    public function getConditionsInstance(): Combine
    {
        return $this->combineFactory->create();
    }

    public function getActionsInstance(): Collection
    {
        return $this->actionFactory->create();
    }

    public function validateData(DataObject $dataObject): bool
    {
        return true;
    }
}
