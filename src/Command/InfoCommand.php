<?php

declare(strict_types=1);

namespace Bolt\Command;

use Bolt\Version;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InfoCommand extends Command
{
    use ImageTrait;

    protected static $defaultName = 'bolt:info';

    /** @var \Bolt\Doctrine\Version */
    private $doctrineVersion;

    public function __construct(\Bolt\Doctrine\Version $doctrineVersion)
    {
        $this->doctrineVersion = $doctrineVersion;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Info about this Bolt Installation')
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command shows some information about this installation of Bolt.
HELP
            );
    }

    /**
     * This method is executed after initialize(). It usually contains the logic
     * to execute to complete this command task.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $this->outputImage($io);

        $message = sprintf('Bolt version: <comment>%s</comment>', Version::VERSION);

        $io->text([$message, '']);

        $platform = $this->doctrineVersion->getPlatform();

        $io->listing([
            sprintf('Install type: <info>%s</info>', Version::installType()),
            sprintf('Database: <info>%s %s</info> - <comment>%s</comment>', $platform['driver_name'], $platform['server_version'], $platform['connection_status']),
        ]);

        $io->text('');

        return null;
    }
}
