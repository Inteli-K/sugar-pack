<?php

namespace SugarPack\Commands;

use Ramsey\Uuid\Uuid;
use SugarPack\Utils\Manifest;
use SugarPack\Utils\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PackCommand extends Command
{
    protected static $defaultName = 'pack';

    protected function configure()
    {
        $this->setDescription('Creates a ZIP file with the package contents.');

        $this->addArgument(
            'package',
            InputArgument::REQUIRED,
            'The pacakage to pack.'
        );

        $this->addOption(
            'dir',
            'd',
            InputOption::VALUE_REQUIRED,
            'Specify the directory where the ZIP file will be created.'
        );

        $this->addOption(
            'output',
            'o',
            InputOption::VALUE_REQUIRED,
            'Specify the name of the created ZIP file.'
        );

        $this->addOption(
            'skip-upgrade',
            's',
            InputOption::VALUE_NONE,
            'Skips the manifest upgrade.'
        );

        $this->addOption(
            'upgrade-type',
            'u',
            InputOption::VALUE_REQUIRED,
            'Type of upgrade to apply to manifest: PATCH|MINOR|MAJOR',
            'PATCH'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $skipUpgrade = $input->getOption('skip-upgrade');
            $upgradeType = $input->getOption('upgrade-type');

            $path = getcwd() . '/' . rtrim($input->getArgument('package'), DIRECTORY_SEPARATOR);
            $this->packageName = basename($path);

            $pacakge = new Package($path);

            if (!$pacakge->isValid()) {
                $output->writeln("<error>{$this->packageName} is not a valid SugarCRM© Module Loadable Package </>");
                return 1;
            }

            $manifest = new Manifest($path . '/manifest.php');

            if (!$skipUpgrade) {
                if (!$this->isValidUpgradeType($upgradeType)) {
                    $output->writeln('<comment>Invalid upgrade type, Defaulting to PATCH.</>');
                    $upgradeType = 'PATCH';
                }

                $manifest->upgrade($upgradeType);
            } else {
                $output->writeln('Skipping manifest upgrade...');
            }

            $this->version = $manifest->getVersion();
            $outputFile = $this->buildOutputFileName($input);

            if (!$pacakge->compress($outputFile)) {
                $output->writeln('<error>Failed to compress the package</>');
            }

            $output->writeln("<info>Successfully packaged to $outputFile</>");
            return 0;
        } catch (\Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</>");
            return 1;
        }
    }

    private function isValidUpgradeType($type): bool
    {
        return in_array($type, ['PATCH', 'MINOR', 'MAJOR']);
    }

    private function buildOutputFileName(InputInterface $input) : string
    {
        $overwriteName = $input->getOption('output');
        $overwriteDir = $input->getOption('dir');

        $fileName = null;
        $directory = null;
        
        $namingStrategy = $GLOBALS['config']['package_naming'];
        $directory = $GLOBALS['config']['output_dir'];

        switch ($namingStrategy) {
            case 'GUID':
                $fileName = Uuid::uuid4()->toString();
                break;
            case 'Versioned':
            default:
                $fileName = $this->packageName . '_v' . $this->version;
                break;
        }

        if ($overwriteName) {
            $fileName = $overwriteName;
        }

        if ($overwriteDir) {
            $directory = $overwriteDir;
        }

        return $directory . '/' . $fileName . '.zip';
    }
}
