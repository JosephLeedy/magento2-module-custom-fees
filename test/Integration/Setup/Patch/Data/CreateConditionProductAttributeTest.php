<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Test\Integration\Setup\Patch\Data;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use JosephLeedy\CustomFees\Setup\Patch\Data\CreateConditionProductAttribute;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\AttributeRepository;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function __;

final class CreateConditionProductAttributeTest extends TestCase
{
    use ArraySubsetAsserts;

    public function testCreatesConditionProductAttribute(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var AttributeRepository $attributeRepository */
        $attributeRepository = $objectManager->create(AttributeRepository::class);

        /* We don't need to apply the patch because it should be applied automatically. We just need to test that it
           creates the attribute. */

        $expectedAttributeData = [
            'attribute_code' => 'apply_custom_fee',
            'backend_type' => 'int',
            'frontend_label' => 'Apply Custom Fee',
            'frontend_input' => 'boolean',
            'source_model' => Boolean::class,
            'is_global' => '1',
            'is_visible' => '1',
            'is_visible_on_front' => '0',
            'is_required' => '0',
            'is_user_defined' => '1',
            'is_used_for_custom_fee_conditions' => '1',
        ];
        $actualAttributeData = $attributeRepository->get(Product::ENTITY, 'apply_custom_fee')->getData();

        unset($actualAttributeData['entity_type']); // Fixes a crash while performing the assertion

        self::assertArraySubset($expectedAttributeData, $actualAttributeData);
    }

    public function testRemovesConditionProductAttribute(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $noSuchEntityException = $objectManager->create(
            NoSuchEntityException::class,
            [
                'phrase' => __(
                    'The attribute with a "%1" attributeCode doesn\'t exist. Verify the attribute and try again.',
                    'apply_custom_fee',
                ),
            ],
        );
        /** @var CreateConditionProductAttribute $createConditionProductAttributePatch */
        $createConditionProductAttributePatch = $objectManager->create(CreateConditionProductAttribute::class);

        $this->expectExceptionObject($noSuchEntityException);

        $createConditionProductAttributePatch->revert();

        /** @var EavConfig $eavConfig */
        $eavConfig = $objectManager->create(EavConfig::class);
        /** @var AttributeRepository $attributeRepository */
        $attributeRepository = $objectManager->create(
            AttributeRepository::class,
            [
                'eavConfig' => $eavConfig,
            ],
        );

        $attributeRepository->get(Product::ENTITY, 'apply_custom_fee');
    }
}
