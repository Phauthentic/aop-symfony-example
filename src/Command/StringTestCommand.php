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
        private object $stringService
    )
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🧪 Testing StringService with AOP string modification:');
        $output->writeln('');

        // Test 1: processString (with caching)
        $output->writeln('📝 Test 1: processString() - Cache demonstration');
        $result1 = $this->stringService->processString('Hello World');
        $output->writeln("   Final result: $result1");
        $output->writeln('');
        
        // Test 1b: processString with cached input
        $output->writeln('📝 Test 1b: processString() - Cached input');
        $result1b = $this->stringService->processString('cached_input');
        $output->writeln("   Final result: $result1b");
        $output->writeln('');

        // Test 2: reverseString (with rate limiting)
        $output->writeln('📝 Test 2: reverseString() - Rate limiting demonstration');
        $result2 = $this->stringService->reverseString('Hello World');
        $output->writeln("   Final result: $result2");
        $output->writeln('');
        
        // Test 2b: reverseString again (should be rate limited)
        $output->writeln('📝 Test 2b: reverseString() - Rate limited call');
        $result2b = $this->stringService->reverseString('Rate Limited');
        $output->writeln("   Final result: $result2b");
        $output->writeln('');

        // Test 3: uppercaseString (with validation)
        $output->writeln('📝 Test 3: uppercaseString() - Normal input');
        $result3 = $this->stringService->uppercaseString('Hello World');
        $output->writeln("   Final result: $result3");
        $output->writeln('');
        
        // Test 3b: uppercaseString with empty input (validation failure)
        $output->writeln('📝 Test 3b: uppercaseString() - Empty input (validation failure)');
        $result3b = $this->stringService->uppercaseString('');
        $output->writeln("   Final result: $result3b");
        $output->writeln('');
        
        // Test 3c: uppercaseString with very long input (validation failure)
        $output->writeln('📝 Test 3c: uppercaseString() - Very long input (validation failure)');
        $longInput = str_repeat('A', 150);
        $result3c = $this->stringService->uppercaseString($longInput);
        $output->writeln("   Final result: $result3c");
        $output->writeln('');

        // Test 4: riskyStringOperation (success case)
        $output->writeln('📝 Test 4: riskyStringOperation() - Success case');
        try {
            $result4 = $this->stringService->riskyStringOperation('Hello');
            $output->writeln("   Final result: $result4");
        } catch (\Throwable $e) {
            $output->writeln("   Exception caught: " . $e->getMessage());
        }
        $output->writeln('');

        // Test 5: riskyStringOperation (exception case - too long)
        $output->writeln('📝 Test 5: riskyStringOperation() - Exception case (too long)');
        try {
            $result5 = $this->stringService->riskyStringOperation('This string is way too long and will cause an exception');
            $output->writeln("   Final result: $result5");
        } catch (\Throwable $e) {
            $output->writeln("   Exception caught: " . $e->getMessage());
        }
        $output->writeln('');

        // Test 6: riskyStringOperation (exception case - forbidden word)
        $output->writeln('📝 Test 6: riskyStringOperation() - Exception case (forbidden word)');
        try {
            $result6 = $this->stringService->riskyStringOperation('This contains error word');
            $output->writeln("   Final result: $result6");
        } catch (\Throwable $e) {
            $output->writeln("   Exception caught: " . $e->getMessage());
        }
        $output->writeln('');
        
        // Test 7: riskyStringOperation (Around advice - dangerous keyword)
        $output->writeln('📝 Test 7: riskyStringOperation() - Around advice blocking dangerous input');
        try {
            $result7 = $this->stringService->riskyStringOperation('This is dangerous content');
            $output->writeln("   Final result: $result7");
        } catch (\Throwable $e) {
            $output->writeln("   Exception caught: " . $e->getMessage());
        }
        $output->writeln('');
        
        // Test 8: riskyStringOperation (Around advice - cached short input)
        $output->writeln('📝 Test 8: riskyStringOperation() - Around advice using cached result');
        try {
            $result8 = $this->stringService->riskyStringOperation('Hi');
            $output->writeln("   Final result: $result8");
        } catch (\Throwable $e) {
            $output->writeln("   Exception caught: " . $e->getMessage());
        }
        $output->writeln('');

        $output->writeln('✅ All tests completed!');
        return Command::SUCCESS;
    }
}

