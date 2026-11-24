<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Console\Command;

use InvalidArgumentException;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\OfflineShipping\Model\ResourceModel\Carrier\Tablerate\CollectionFactory as TablerateCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Yireo\TablerateCommands\Console\InputValidator;
use Yireo\TablerateCommands\Model\TablerateRecordConverter;

class TablerateImportCommand extends Command
{
    private const INSERT_BATCH_SIZE = 500;

    public function __construct(
        private readonly TablerateCollectionFactory $tablerateCollectionFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ResourceConnection $resourceConnection,
        private readonly InputValidator $inputValidator,
        private readonly TablerateRecordConverter $recordConverter,
        private readonly LoggerInterface $logger,
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
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_OPTIONAL,
            'Replace the existing table rates of this website',
            false
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $websiteId = $this->inputValidator->getWebsiteId($input);
            $overwrite = $this->inputValidator->isOverwrite($input);
            $csvFile = $this->inputValidator->getReadableCsvFile($input);
        } catch (InvalidArgumentException $exception) {
            return $this->error($output, $exception->getMessage());
        }

        $existingCount = $this->getExistingCount($websiteId);
        if (false === $overwrite && $existingCount > 0) {
            return $this->error(
                $output,
                (string)__('Found %1 tablerates without allowing overwrites', $existingCount)
            );
        }

        $conditionName = (string)$this->scopeConfig->getValue(
            'carriers/tablerate/condition_name',
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
        if ($conditionName === '') {
            return $this->error($output, (string)__('No table rate condition name configured'));
        }

        try {
            [$rows, $errors] = $this->getRows($csvFile, $websiteId, $conditionName);
        } catch (CsvException) {
            return $this->error($output, (string)__('Unable to read CSV file'));
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->error($output, $error);
            }

            return $this->error($output, (string)__('Import aborted, no table rates were changed'));
        }

        try {
            $this->replaceRows($websiteId, $rows);
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);

            return $this->error($output, (string)__('Import failed, no table rates were changed'));
        }

        $output->writeln((string)__('Imported %1 tablerates', count($rows)));

        return Command::SUCCESS;
    }

    private function getRows(string $csvFile, int $websiteId, string $conditionName): array
    {
        $errors = [];
        $rows = [];

        $reader = Reader::from($csvFile, 'r');
        $reader->setHeaderOffset(0);

        foreach ($reader->getRecords() as $offset => $record) {
            try {
                $rows[] = $this->recordConverter->convert($record, $websiteId, $conditionName);
            } catch (InvalidArgumentException $exception) {
                $errors[] = (string)__('Line %1: %2', (int)$offset + 1, $exception->getMessage());
            }
        }

        return [$rows, $errors];
    }

    private function replaceRows(int $websiteId, array $rows): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('shipping_tablerate');

        $connection->beginTransaction();
        try {
            $connection->delete($table, ['website_id = ?' => $websiteId]);
            foreach (array_chunk($rows, self::INSERT_BATCH_SIZE) as $batch) {
                $connection->insertArray($table, TablerateRecordConverter::COLUMNS, $batch);
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function getExistingCount(int $websiteId): int
    {
        $collection = $this->tablerateCollectionFactory->create();
        $collection->addFieldToFilter('website_id', ['eq' => $websiteId]);

        return (int)$collection->getSize();
    }

    private function error(OutputInterface $output, string $message): int
    {
        $output->writeln('<error>'.OutputFormatter::escape($message).'</error>');

        return Command::INVALID;
    }
}
