<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Console\Command;

use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\Collection as TablerateCollection;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\CollectionFactory as TablerateCollectionFactory;
use SplTempFileObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use League\Csv\Writer;
use Magento\Framework\Filesystem\File\WriteFactory;

class TablerateExportCommand extends Command
{
    public function __construct(
        private TablerateCollectionFactory $tablerateCollectionFactory,
        private WriteFactory $writeFactory,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('yireo_tablerates:export');
        $this->setDescription('Export table rates to CSV');
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

        $collection = $this->getCollection($websiteId);
        if ($collection->getSize() === 0) {
            $output->writeln('<error>No tablerates found</error>');

            return Command::INVALID;
        }

        $overwrite = (bool)$input->getOption('overwrite');
        $csvFile = (string)$input->getArgument('csv_file');
        if (false === $overwrite && file_exists($csvFile)) {
            $output->writeln('File already exists');

            return Command::INVALID;
        }

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setEscape('');
        $csv->insertOne([
            'Country',
            'Region/State',
            'ZIP/Postal Code',
            'Order Subtotal (and above)',
            'Shipping Price',
            'Shipping Cost',
        ]);

        foreach ($collection as $tablerate) {
            $destRegionId = (int)$tablerate->getDestRegionId();
            if ($destRegionId === 0) {
                $destRegionId = '*';
            }

            $csv->insertOne([
                $tablerate->getDestCountryId(),
                $destRegionId,
                $tablerate->getDestZip(),
                $tablerate->getConditionValue(),
                $tablerate->getPrice(),
                $tablerate->getCost(),
            ]);
        }

        $csvOutput = $csv->toString();
        $debug = false;
        if ($debug) {
            $output->writeln($csvOutput);
        }

        $write = $this->writeFactory->create($csvFile, 'file', 'w');
        $write->write($csvOutput);

        return Command::SUCCESS;
    }

    private function getCollection(string $websiteId): TablerateCollection
    {
        $collection = $this->tablerateCollectionFactory->create();
        $collection->addFieldToFilter('website_id', ['eq' => $websiteId]);

        return $collection;
    }
}
