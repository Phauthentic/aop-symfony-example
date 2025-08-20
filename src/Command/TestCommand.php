<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\UserService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'test:app',
    description: 'AOP Test command',
)]
class TestCommand extends Command
{
    public function __construct(
        private UserService $userService
    )
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Testing UserService::tellName() with AOP:');

        echo $this->userService->tellName('TEST ME');

        return Command::SUCCESS;
    }
}