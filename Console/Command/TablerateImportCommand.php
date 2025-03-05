<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Console\Command;

use Exception;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\Collection as TablerateCollection;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\CollectionFactory as TablerateCollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use League\Csv\Reader;

class TablerateImportCommand extends Command
{
    public function __construct(
        private TablerateCollectionFactory $tablerateCollectionFactory,
        private ScopeConfigInterface $scopeConfig,
        private ResourceConnection $resourceConnection,
        private CountryFactory $countryFactory,
        private CollectionFactory $regionCollectionFactory,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('yireo_tablerates:import');
        $this->setDescription('Import table rates from CSV');
        $this->addArgument('website_id', InputArgument::REQUIRED, 'Website ID');
        $this->addArgument('csv_file', InputArgument::REQUIRED, 'CSV file');
        $this->addOption('overwrite', null, InputOption::VALUE_OPTIONAL, 'Overwrite CSV file if exists', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $websiteId = trim((string)$input->getArgument('website_id'));
        if (empty($websiteId)) {
            $output->writeln('<error>Website ID is required</error>');

            return Command::INVALID;
        }

        $overwrite = (bool)$input->getOption('overwrite');
        $collection = $this->getCollection($websiteId);
        if (false === $overwrite && $collection->getSize() > 0) {
            $output->writeln(
                '<error>'.(string)__(
                    'Found %1 tablerates without allowing overwrites',
                    $collection->getSize()
                ).'</error>'
            );

            return Command::INVALID;
        }

        $csvFile = (string)$input->getArgument('csv_file');
        if (false === file_exists($csvFile)) {
            $output->writeln('<error>No such file</error>');

            return Command::INVALID;
        }

        $this->cleanData($websiteId);

        $conditionName = $this->scopeConfig->getValue('carriers/tablerate/condition_name', 'website', $websiteId);

        $reader = Reader::createFromPath($csvFile, 'r');
        $reader->setHeaderOffset(0);
        $records = $reader->getRecords();

        $columns = [
            'website_id',
            'dest_country_id',
            'dest_region_id',
            'dest_zip',
            'condition_name',
            'condition_value',
            'price',
            'cost',
        ];

        foreach ($records as $record) {

            $record = array_values($record);

            $values = [];
            $values['website_id'] = $websiteId;
            $values['dest_country_id'] = $this->getTwoLetterCountryId($record[0]);
            $values['dest_region_id'] = $this->getRegionId($values['dest_country_id'], $record[1]);
            $values['dest_zip'] = $record[2];
            $values['condition_name'] = $conditionName;

            if (isset($record[3])) {
                $values['condition_value'] = $record[3];
            }

            if (isset($record[4])) {
                $values['price'] = $record[4];
            }

            if (isset($record[5])) {
                $values['cost'] = $record[5];
            }

            $columns = array_slice($columns, 0, count($values));

            print_r($values);

            $this->importData($columns, $values);
        }

        return Command::SUCCESS;
    }

    private function getCollection(string $websiteId): TablerateCollection
    {
        $collection = $this->tablerateCollectionFactory->create();
        $collection->addFieldToFilter('website_id', ['eq' => $websiteId]);

        return $collection;
    }

    private function cleanData(string $websiteId)
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $connection->getTableName('shipping_tablerate');
        $connection->beginTransaction();
        $connection->query('DELETE FROM `'.$table.'` WHERE `website_id` = "'.$websiteId.'"');
        $connection->commit();
    }

    private function importData(array $columns, array $values)
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        $table = $connection->getTableName('shipping_tablerate');

        try {
            $connection->insertArray($table, $columns, [$values]);
        } catch (Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        $connection->commit();
    }

    private function getRegionId(string $countryId, string $regionId): int
    {
        if ($regionId === '*') {
            return 0;
        }

        $regionCollection = $this->regionCollectionFactory->create();
        $regionCollection->addFilter('country_id', $countryId);
        $regionCollection->addFilter('code', $regionId);
        $region = $regionCollection->getFirstItem();
        return (int)$region->getRegionId();
    }

    private function getTwoLetterCountryId(string $countryId): string
    {
        if (strlen($countryId) === 2) {
            return $countryId;
        }

        $country = $this->countryFactory->create();
        $country->load($countryId, 'iso3_code');
        return $country->getCountryId();
    }
}
