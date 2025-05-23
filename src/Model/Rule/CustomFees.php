<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Rule;

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
use Magento\Rule\Model\Action\CollectionFactory;

class CustomFees extends AbstractModel
{
    /**
     * @var bool[]
     */
    private array $validatedAddresses = [];

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

    public function getConditionsInstance()
    {
        return $this->combineFactory->create();
    }

    public function getActionsInstance()
    {
        return $this->actionFactory->create();
    }

    public function validateData(DataObject $dataObject): bool
    {
        return true;
    }

    public function setAddressValidationResult(int|string $addressId, bool $validationResult): self
    {
        $this->validatedAddresses[$addressId] = $validationResult;

        return $this;
    }

    public function isValidForAddress(int|string $addressId): bool
    {
        return $this->isAddressValidated($addressId) ? $this->validatedAddresses[$addressId] : false;
    }

    public function isAddressValidated(int|string $addressId): bool
    {
        return array_key_exists($addressId, $this->validatedAddresses);
    }
}
