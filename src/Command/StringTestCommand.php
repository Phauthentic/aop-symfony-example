<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\StringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'test:string',
    description: 'Test StringService with AOP string modification',
)]
class StringTestCommand extends Command
{
    public function __construct(
        private StringService $stringService
    )
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🧪 Testing StringService with AOP string modification:');
        $output->writeln('');

        // Test 1: processString
        $output->writeln('📝 Test 1: processString()');
        $result1 = $this->stringService->processString('Hello World');
        $output->writeln("   Final result: $result1");
        $output->writeln('');

        // Test 2: reverseString
        $output->writeln('📝 Test 2: reverseString()');
        $result2 = $this->stringService->reverseString('Hello World');
        $output->writeln("   Final result: $result2");
        $output->writeln('');

        // Test 3: uppercaseString
        $output->writeln('📝 Test 3: uppercaseString()');
        $result3 = $this->stringService->uppercaseString('Hello World');
        $output->writeln("   Final result: $result3");
        $output->writeln('');

        $output->writeln('✅ All tests completed!');
        return Command::SUCCESS;
    }
}

