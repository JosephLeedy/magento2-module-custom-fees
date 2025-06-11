<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Controller\Adminhtml\System\Config\CustomFees\Advanced;

use JosephLeedy\CustomFees\Model\Rule\Condition\Combine;
use JosephLeedy\CustomFees\Model\Rule\CustomFees;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\ConditionInterface;

use function class_exists;
use function class_implements;
use function explode;
use function str_replace;

class NewConditionHtml extends Action implements HttpPostActionInterface
{
    public function __construct(Context $context, private readonly RawFactory $rawResultFactory)
    {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $objectId = $this->getRequest()->getParam('id');
        $formNamespace = $this->getRequest()->getParam('form_namespace');
        /** @var string $type */
        $type = $this->getRequest()->getParam('type', '');
        $types = explode('|', str_replace('-', '/', $type));
        $objectType = $types[0];
        $responseBody = '';
        /** @var Raw $rawResult */
        $rawResult = $this->rawResultFactory->create();

        if (class_exists($objectType) && !in_array(ConditionInterface::class, class_implements($objectType))) {
            $rawResult->setContents($responseBody);

            return $rawResult;
        }

        /** @var ConditionInterface|AbstractCondition $conditionModel */
        $conditionModel = $this->_objectManager
            ->create($objectType)
            ->setId($objectId)
            ->setType($objectType)
            ->setRule($this->_objectManager->create(CustomFees::class))
            ->setPrefix('conditions');

        if (!empty($types[1])) {
            $conditionModel->setAttribute($types[1]);
        }

        if (!$conditionModel instanceof AbstractCondition) {
            $rawResult->setContents($responseBody);

            return $rawResult;
        }

        if ($conditionModel instanceof Combine) {
            $conditionModel->setConditions([]);
        }

        $conditionModel->setJsFormObject($this->_request->getParam('form'));
        $conditionModel->setFormName($formNamespace);

        $responseBody = $conditionModel->asHtmlRecursive();

        $rawResult->setContents($responseBody);

        return $rawResult;
    }
}
