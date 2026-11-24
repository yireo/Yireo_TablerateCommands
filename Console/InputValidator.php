<?php declare(strict_types=1);

namespace Yireo\TablerateCommands\Console;

use InvalidArgumentException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Input\InputInterface;

class InputValidator
{
    private const TRIM_CHARACTERS = " \f\n\r\t\v\x00";
    private const FORBIDDEN_WRITE_FOLDERS = ['pub', 'app/etc'];

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly DirectoryList $directoryList
    ) {
    }

    public function getWebsiteId(InputInterface $input, string $name = 'website_id'): int
    {
        $websiteId = trim((string)$input->getArgument($name), self::TRIM_CHARACTERS);
        if (false === ctype_digit($websiteId)) {
            throw new InvalidArgumentException((string)__('Website ID must be a positive number'));
        }

        try {
            return (int)$this->storeManager->getWebsite((int)$websiteId)->getId();
        } catch (NoSuchEntityException) {
            throw new InvalidArgumentException((string)__('Website with ID %1 does not exist', $websiteId));
        }
    }

    public function isOverwrite(InputInterface $input, string $name = 'overwrite'): bool
    {
        $value = $input->getOption($name);
        if ($value === false) {
            return false;
        }

        if ($value === null || $value === true) {
            return true;
        }

        $overwrite = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($overwrite === null) {
            throw new InvalidArgumentException((string)__('Invalid value for option "%1"', $name));
        }

        return $overwrite;
    }

    public function getReadableCsvFile(InputInterface $input, string $name = 'csv_file'): string
    {
        $csvFile = $this->getCsvFile($input, $name);
        if (false === is_file($csvFile) || false === is_readable($csvFile)) {
            throw new InvalidArgumentException((string)__('No such file'));
        }

        return $csvFile;
    }

    public function getWritableCsvFile(InputInterface $input, bool $overwrite, string $name = 'csv_file'): string
    {
        $csvFile = $this->getCsvFile($input, $name);
        if (is_link($csvFile)) {
            throw new InvalidArgumentException((string)__('Refusing to write to a symbolic link'));
        }

        if (false === $overwrite && file_exists($csvFile)) {
            throw new InvalidArgumentException((string)__('File already exists'));
        }

        if (file_exists($csvFile) && false === is_file($csvFile)) {
            throw new InvalidArgumentException((string)__('Not a regular file'));
        }

        $folder = realpath(dirname($csvFile));
        if ($folder === false || false === is_dir($folder) || false === is_writable($folder)) {
            throw new InvalidArgumentException((string)__('Folder does not exist or is not writable'));
        }

        $root = rtrim((string)realpath($this->directoryList->getRoot()), '/');
        foreach (self::FORBIDDEN_WRITE_FOLDERS as $forbiddenFolder) {
            $forbiddenPath = $root.'/'.$forbiddenFolder;
            if ($folder === $forbiddenPath || str_starts_with($folder.'/', $forbiddenPath.'/')) {
                throw new InvalidArgumentException((string)__('Refusing to write to folder "%1"', $forbiddenFolder));
            }
        }

        return $folder.'/'.basename($csvFile);
    }

    private function getCsvFile(InputInterface $input, string $name): string
    {
        $csvFile = trim((string)$input->getArgument($name), self::TRIM_CHARACTERS);
        if ($csvFile === '' || strtolower((string)pathinfo($csvFile, PATHINFO_EXTENSION)) !== 'csv') {
            throw new InvalidArgumentException((string)__('CSV file must have the extension ".csv"'));
        }

        return $csvFile;
    }
}
