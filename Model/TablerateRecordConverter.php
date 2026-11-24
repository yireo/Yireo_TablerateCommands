<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Model;

use InvalidArgumentException;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;

class TablerateRecordConverter
{
    public const COLUMNS = [
        'website_id',
        'dest_country_id',
        'dest_region_id',
        'dest_zip',
        'condition_name',
        'condition_value',
        'price',
        'cost',
    ];

    private const TRIM_CHARACTERS = " \f\n\r\t\v\x00";
    private const MAX_ZIP_LENGTH = 10;
    private const MAX_DECIMAL = 99999999.9999;

    private ?array $countryIds = null;
    private array $regionIds = [];

    public function __construct(
        private readonly CountryCollectionFactory $countryCollectionFactory,
        private readonly RegionCollectionFactory $regionCollectionFactory
    ) {
    }

    public function convert(array $record, int $websiteId, string $conditionName): array
    {
        $record = array_values($record);
        $countryId = $this->getCountryId($this->getField($record, 0));

        return [
            $websiteId,
            $countryId,
            $this->getRegionId($countryId, $this->getField($record, 1)),
            $this->getZip($this->getField($record, 2)),
            $conditionName,
            $this->getDecimal($this->getField($record, 3), 'Order Subtotal (and above)'),
            $this->getDecimal($this->getField($record, 4), 'Shipping Price'),
            $this->getDecimal($this->getField($record, 5), 'Shipping Cost'),
        ];
    }

    private function getField(array $record, int $index): string
    {
        $value = $record[$index] ?? '';
        if (!is_scalar($value)) {
            return '';
        }

        return trim((string)$value, self::TRIM_CHARACTERS);
    }

    private function getCountryId(string $countryId): string
    {
        if ($countryId === '*' || $countryId === '0') {
            return '0';
        }

        $countryIds = $this->getCountryIds();
        $countryId = strtoupper($countryId);
        if (!isset($countryIds[$countryId])) {
            throw new InvalidArgumentException((string)__('Unknown country "%1"', $countryId));
        }

        return $countryIds[$countryId];
    }

    private function getCountryIds(): array
    {
        if ($this->countryIds !== null) {
            return $this->countryIds;
        }

        $this->countryIds = [];
        foreach ($this->countryCollectionFactory->create() as $country) {
            $countryId = (string)$country->getData('country_id');
            $this->countryIds[$countryId] = $countryId;
            $iso3Code = (string)$country->getData('iso3_code');
            if ($iso3Code !== '') {
                $this->countryIds[$iso3Code] = $countryId;
            }
        }

        return $this->countryIds;
    }

    private function getRegionId(string $countryId, string $region): int
    {
        if ($region === '*' || $region === '' || $region === '0') {
            return 0;
        }

        $regionIds = $this->getRegionIds($countryId);
        if (!isset($regionIds[$region])) {
            throw new InvalidArgumentException(
                (string)__('Unknown region "%1" for country "%2"', $region, $countryId)
            );
        }

        return $regionIds[$region];
    }

    private function getRegionIds(string $countryId): array
    {
        if (isset($this->regionIds[$countryId])) {
            return $this->regionIds[$countryId];
        }

        $this->regionIds[$countryId] = [];
        if ($countryId === '0') {
            return $this->regionIds[$countryId];
        }

        $regionCollection = $this->regionCollectionFactory->create();
        $regionCollection->addCountryFilter($countryId);
        foreach ($regionCollection as $region) {
            $regionId = (int)$region->getData('region_id');
            $this->regionIds[$countryId][(string)$regionId] = $regionId;
            $this->regionIds[$countryId][(string)$region->getData('code')] = $regionId;
        }

        return $this->regionIds[$countryId];
    }

    private function getZip(string $zip): string
    {
        if ($zip === '') {
            return '*';
        }

        if (mb_strlen($zip) > self::MAX_ZIP_LENGTH) {
            throw new InvalidArgumentException(
                (string)__('ZIP/Postal Code "%1" is longer than %2 characters', $zip, self::MAX_ZIP_LENGTH)
            );
        }

        return $zip;
    }

    private function getDecimal(string $value, string $label): string
    {
        if ($value === '') {
            return '0';
        }

        if (!is_numeric($value) || (float)$value < 0 || (float)$value > self::MAX_DECIMAL) {
            throw new InvalidArgumentException((string)__('Invalid value "%1" for "%2"', $value, $label));
        }

        return $value;
    }
}
