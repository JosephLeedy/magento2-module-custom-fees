<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Controller\Adminhtml\Config\Export;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Filesystem;

use function microtime;

class ExampleCsv extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly Filesystem $filesystem,
        private readonly FileFactory $fileFactory,
    ) {
        parent::__construct($context);
    }

    /**
     * @throws Exception
     */
    public function execute(): ResponseInterface
    {
        $content = [
            'type' => 'filename',
            'value' => $this->createTemporaryCsvFile(),
            'rm' => true,
        ];

        return $this->fileFactory->create('custom-fees-import.csv', $content, DirectoryList::VAR_DIR);
    }

    private function createTemporaryCsvFile(): string
    {
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);

        $directory->create('export');

        $filePath = 'export/custom_fees_' . microtime() . '.csv';
        $stream = $directory->openFile($filePath, 'w+');

        $stream->lock();
        $stream->write('code,title,type,status,value');
        $stream->unlock();
        $stream->close();

        return $filePath;
    }
}
