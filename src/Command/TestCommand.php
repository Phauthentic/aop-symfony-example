<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\UserTestClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'test:aop',
    description: 'AOP Test command',
)]
class TestCommand extends Command
{
    public function __construct(
        private UserTestClass $userTestClass
    )
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Testing UserTestClass::test() with AOP:');

        $this->userTestClass->test('TEST ME');

        return Command::SUCCESS;
    }
}