<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Controller\Adminhtml\System\Config\CustomFees\Advanced;

use JosephLeedy\CustomFees\Block\System\Config\Form\Field\CustomFees\Advanced\Form as AdvancedFormBlock;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw as RawResult;
use Magento\Framework\Controller\Result\RawFactory as RawResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\LayoutInterface;

class Form extends Action implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawResultFactory $rawResultFactory,
        private readonly LayoutInterface $layout,
        Context $context,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        /** @var string|null $rowId */
        $rowId = $this->request->getParam('row_id');
        /** @var string $feeType */
        $feeType = $this->request->getParam('fee_type', FeeType::Fixed->value);
        /** @var string $advancedConfig */
        $advancedConfig = $this->request->getParam('advanced_config', '{}');

        if ($rowId === null) {
            throw new LocalizedException(__('Row ID is required.'));
        }

        $advancedFormBlock = $this->layout->createBlock(
            AdvancedFormBlock::class,
            arguments: [
                'data' => [
                    'row_id' => $rowId,
                    'fee_type' => $feeType,
                    'advanced_config' => $advancedConfig,
                ],
            ],
        );
        $modalContent = $advancedFormBlock->toHtml();
        /** @var RawResult $rawResult */
        $rawResult = $this->rawResultFactory->create();

        $rawResult->setContents($modalContent);

        return $rawResult;
    }
}
