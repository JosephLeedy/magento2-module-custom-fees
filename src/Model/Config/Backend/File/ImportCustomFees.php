<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Model\Config\Backend\File;

use DateTimeImmutableFactory;
use DateTimeInterface;
use Exception;
use JosephLeedy\CustomFees\Api\ConfigInterface;
use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Config\Model\Config\Backend\File;
use Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\Writer;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

use function array_combine;
use function array_filter;
use function array_intersect;
use function array_merge;
use function array_slice;
use function array_walk;
use function count;
use function pathinfo;
use function preg_replace;
use function strtolower;
use function usleep;

use const PATHINFO_EXTENSION;

/**
 * @method void unsValue()
 */
class ImportCustomFees extends File
{
    /**
     * @var string[]
     */
    private array $requiredFields = [
        'code',
        'title',
        'type',
        'value',
    ];
    /**
     * @var array{
     *     code: string,
     *     title: string,
     *     type: value-of<FeeType>,
     *     show_percentage?: string,
     *     value: float,
     *     advanced?: string
     * }[]
     */
    private array $customFees = [];

    /**
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        UploaderFactory $uploaderFactory,
        RequestDataInterface $requestData,
        Filesystem $filesystem,
        private readonly Csv $csv,
        private readonly DateTimeImmutableFactory $dateTimeFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly SerializerInterface $serializer,
        private readonly RequestInterface $request,
        private readonly Writer $configWriter,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $uploaderFactory,
            $requestData,
            $filesystem,
            $resource,
            $resourceCollection,
            $data,
        );
    }

    public function beforeSave(): self
    {
        $this->_dataSaveAllowed = false;

        /** @var array{name: string, tmp_name: string} $file */
        $file = $this->getFileData();

        $this->unsValue();

        if (count($file) === 0) {
            return $this;
        }

        $this->validateCustomFeesFile($file);
        $this->replaceCustomFeeKeys();
        $this->fixCustomFees();

        /**
         * @var array{
         *     custom_order_fees?: array{
         *         fields?: array{
         *             import_custom_fees?: array{
         *                 replace_existing?: int
         *             }
         *         }
         *     }
         * } $configGroups
         */
        $configGroups = $this->request->getParam('groups') ?? [];
        $replaceExistingCustomFees =
            (bool) ($configGroups['custom_order_fees']['fields']['import_custom_fees']['replace_existing'] ?? false);
        $originalCustomFees = [];

        if (!$replaceExistingCustomFees) {
            /**
             * @var array<string, array{
             *     code: string,
             *     title: string,
             *     value: float,
             *     advanced?: string
             * }> $originalCustomFees
             */
            $originalCustomFees = $this->serializer->unserialize(
                (string) (
                    $this->_config->getValue(
                        ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
                        $this->getScope() ?? ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                        $this->getScopeId() ?? 0,
                    ) ?? '[]'
                ),
            );
            $originalCustomFees = array_filter(
                $originalCustomFees,
                static fn(array $customFee): bool => $customFee['code'] !== 'example_fee',
            );
        }

        $this->configWriter->save(
            ConfigInterface::CONFIG_PATH_CUSTOM_FEES,
            (string) ($this->serializer->serialize(array_merge($originalCustomFees, $this->customFees)) ?: ''),
            $this->getScope() ?? ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $this->getScopeId() ?? 0,
        );

        return $this;
    }

    /**
     * @param array{name: string, tmp_name: string} $file
     * @throws LocalizedException
     */
    private function validateCustomFeesFile(array $file): void
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.DiscouragedWithAlternative
        if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
            throw new LocalizedException(__('Custom Fees spreadsheet must be a CSV file.'));
        }

        try {
            /** @var array<int, array<int, string|float>> $rawCustomFees */
            $rawCustomFees = $this->csv->getData($file['tmp_name']);
        } catch (Exception $exception) {
            throw new LocalizedException(__('Could not read Custom Fees spreadsheet.'), $exception);
        }

        if (
            count($rawCustomFees) === 0
            || count(array_intersect($rawCustomFees[0], $this->requiredFields)) !== count($this->requiredFields)
        ) {
            throw new LocalizedException(__('Invalid Custom Fees spreadsheet.'));
        }

        foreach (array_slice($rawCustomFees, 1) as $customFee) {
            /** @var string[] $headerFields */
            $headerFields = $rawCustomFees[0];
            /**
             * @var array{
             *     code: string,
             *     title: string,
             *     type: value-of<FeeType>,
             *     show_percentage?: string,
             *     value: float
             * } $customFee
             */
            $customFee = array_combine($headerFields, $customFee);

            if (FeeType::tryFrom($customFee['type']) === null) {
                throw new LocalizedException(__('Invalid custom fee type "%1".', $customFee['type']));
            }

            $this->customFees[] = $customFee;
        }
    }

    /**
     * Rekeys custom fees data to follow the same format used by the array config field serialization process
     */
    private function replaceCustomFeeKeys(): void
    {
        $customFees = [];

        foreach ($this->customFees as $customFee) {
            /** @var DateTimeInterface $dateTime */
            $dateTime = $this->dateTimeFactory->create();
            $key = $dateTime->format('_Uv_v'); // see Magento_Config::system/config/form/field/array.phtml
            $customFees[$key] = $customFee;

            usleep(1000); // fix timing issue causing key collisions
        }

        $this->customFees = $customFees;
    }

    private function fixCustomFees(): void
    {
        try {
            $store = match ($this->getScope()) {
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT => $this->storeManager->getStore(),
                ScopeInterface::SCOPE_WEBSITES => $this
                    ->storeManager
                    ->getWebsite($this->getScopeId())
                    ->getDefaultStore(),
                ScopeInterface::SCOPE_STORES => $this->storeManager->getStore($this->getScopeId()),
                default => null,
            };
        } catch (LocalizedException) {
            $store = null;
        }

        array_walk(
            $this->customFees,
            static function (array &$customFee) use ($store): void {
                $customFee['code'] = preg_replace('/[^A-z0-9_]+/', '_', $customFee['code']);
                $customFee['advanced'] = '{"show_percentage":"'
                    . match (strtolower($customFee['show_percentage'] ?? '')) {
                        '0', 'n', 'no', 'false' => '0',
                        '1', 'y', 'yes', 'true' => '1',
                        default => '0',
                    } . '"}';

                if (FeeType::Fixed->equals($customFee['type'])) {
                    $customFee['value'] = $store
                        ?->getBaseCurrency()
                        ->format(
                            $customFee['value'],
                            ['display' => 1],
                            false,
                        ) ?? $customFee['value'];
                }

                unset($customFee['show_percentage']);
            },
        );
    }
}
