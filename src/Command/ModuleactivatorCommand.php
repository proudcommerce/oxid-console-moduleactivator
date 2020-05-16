<?php declare(strict_types=1);

namespace ProudCommerce\OxidConsoleModuleactivator\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ModuleactivatorCommand extends Command
{

    protected function configure()
    {
        $this->setName('pc:module:activator')
            ->setDescription('');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Go go go ...');
    }
}
