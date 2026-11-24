<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Console\Command;

use InvalidArgumentException;
use League\Csv\EscapeFormula;
use League\Csv\Writer;
use Magento\Framework\Filesystem\File\WriteFactory;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\Collection as TablerateCollection;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\CollectionFactory as TablerateCollectionFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Yireo\TablerateCommands\Console\InputValidator;

class TablerateExportCommand extends Command
{
    public function __construct(
        private readonly TablerateCollectionFactory $tablerateCollectionFactory,
        private readonly WriteFactory $writeFactory,
        private readonly InputValidator $inputValidator,
        private readonly LoggerInterface $logger,
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
        try {
            $websiteId = $this->inputValidator->getWebsiteId($input);
            $overwrite = $this->inputValidator->isOverwrite($input);
            $csvFile = $this->inputValidator->getWritableCsvFile($input, $overwrite);
        } catch (InvalidArgumentException $exception) {
            return $this->error($output, $exception->getMessage());
        }

        $collection = $this->getCollection($websiteId);
        if ($collection->getSize() === 0) {
            return $this->error($output, (string)__('No tablerates found'));
        }

        try {
            $write = $this->writeFactory->create($csvFile, 'file', $overwrite ? 'w' : 'x');
            $write->write($this->getCsvOutput($collection));
            $write->close();
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);

            return $this->error($output, (string)__('Unable to write CSV file'));
        }

        return Command::SUCCESS;
    }

    private function getCsvOutput(TablerateCollection $collection): string
    {
        $escapeFormula = new EscapeFormula();
        $csv = Writer::fromString();
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
            $textFields = $escapeFormula->escapeRecord([
                (string)$tablerate->getDestCountryId(),
                $destRegionId === 0 ? '*' : (string)$destRegionId,
                (string)$tablerate->getDestZip(),
            ]);

            $csv->insertOne([
                ...$textFields,
                $tablerate->getConditionValue(),
                $tablerate->getPrice(),
                $tablerate->getCost(),
            ]);
        }

        return $csv->toString();
    }

    private function getCollection(int $websiteId): TablerateCollection
    {
        $collection = $this->tablerateCollectionFactory->create();
        $collection->addFieldToFilter('website_id', ['eq' => $websiteId]);

        return $collection;
    }

    private function error(OutputInterface $output, string $message): int
    {
        $output->writeln('<error>'.OutputFormatter::escape($message).'</error>');

        return Command::INVALID;
    }
}
