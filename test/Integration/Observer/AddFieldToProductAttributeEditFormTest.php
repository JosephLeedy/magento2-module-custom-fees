<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Observer;

use JosephLeedy\CustomFees\Observer\AddFieldToProductAttributeEditForm;
use Magento\Catalog\Block\Adminhtml\Product\Attribute\Edit\Tab\Front;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\App\Area;
use Magento\Framework\Event\ConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea(Area::AREA_ADMINHTML)]
final class AddFieldToProductAttributeEditFormTest extends TestCase
{
    public function testIsConfiguredCorrectly(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var ConfigInterface $observerConfig */
        $observerConfig = $objectManager->create(ConfigInterface::class);
        /**
         * @var array{custom_fees_add_field_to_product_attribute_edit_form?: array{instance: class-string}} $observers
         */
        $observers = $observerConfig->getObservers('adminhtml_catalog_product_attribute_edit_frontend_prepare_form');

        self::assertArrayHasKey('custom_fees_add_field_to_product_attribute_edit_form', $observers);
        self::assertSame(
            ltrim(AddFieldToProductAttributeEditForm::class, '\\'),
            $observers['custom_fees_add_field_to_product_attribute_edit_form']['instance'] ?? null,
        );
    }

    public function testsAddsFieldToProductAttributeEditForm(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Attribute $entityAttribute */
        $entityAttribute = $objectManager->create(Attribute::class);
        /** @var Registry $registry */
        $registry = $objectManager->get(Registry::class);
        /** @var LayoutInterface $layout */
        $layout = $objectManager->get(LayoutInterface::class);
        /** @var Front $productAttributeEditFormBlock */
        $productAttributeEditFormBlock = $layout->createBlock(Front::class);

        $registry->register('entity_attribute', $entityAttribute);

        $productAttributeEditFormBlock->toHtml();

        $productAttributeEditForm = $productAttributeEditFormBlock->getForm();
        $isUsedForCustomFeesField = $productAttributeEditForm->getElement('is_used_for_custom_fee_rules');

        self::assertNotNull($isUsedForCustomFeesField);
    }
}
