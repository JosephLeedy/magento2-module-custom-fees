<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Controller\Adminhtml\System\Config\CustomFees\Advanced;

use Magento\CatalogRule\Model\Rule;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\ConditionInterface;

use function class_exists;
use function class_implements;
use function explode;
use function str_replace;

class NewConditionHtml implements HttpGetActionInterface, HttpPostActionInterface
{
    private RequestInterface $request;
    private ResponseInterface $response;
    private ObjectManagerInterface $objectManager;

    public function __construct(Context $context)
    {
        $this->request = $context->getRequest();
        $this->response = $context->getResponse();
        $this->objectManager = $context->getObjectManager();
    }

    public function execute(): void
    {
        $objectId = $this->request->getParam('id');
        $formNamespace = $this->request->getParam('form_namespace');
        $types = explode('|', str_replace('-', '/', $this->request->getParam('type', '')));
        $objectType = $types[0];
        $responseBody = '';

        /*if (class_exists($objectType) && !in_array(ConditionInterface::class, class_implements($objectType))) {
            $this->response->setBody($responseBody);

            return;
        }

        $conditionModel = $this->objectManager
            ->create($objectType)
            ->setId($objectId)
            ->setType($objectType)
            ->setRule($this->objectManager->create(Rule::class))
            ->setPrefix('conditions');

        if (!empty($types[1])) {
            $conditionModel->setAttribute($types[1]);
        }

        if ($conditionModel instanceof AbstractCondition) {
            $conditionModel->setJsFormObject($this->request->getParam('form'));
            $conditionModel->setFormName($formNamespace);
            $responseBody = $conditionModel->asHtmlRecursive();
        }*/

        $this->response->setBody($responseBody);
    }
}
