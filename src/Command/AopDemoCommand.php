<?php

declare(strict_types=1);

namespace App\Command;

use App\Demo\Trace;
use App\Service\OrderServiceInterface;
use App\Service\StringServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:aop-demo', description: 'Runs the AOP demo scenarios and prints the advice trace')]
final class AopDemoCommand extends Command
{
    public function __construct(
        private readonly StringServiceInterface $strings,
        private readonly OrderServiceInterface $orders,
        private readonly Trace $trace,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("reverse('hello') = ".$this->strings->reverse('hello'));
        $output->writeln("upper('hello') = ".$this->strings->upper('hello'));
        $output->writeln("upper('hello') = ".$this->strings->upper('hello'));
        $output->writeln("greet('World', 2) = ".$this->strings->greet('World', 2));
        try {
            $this->strings->greet('');
        } catch (\InvalidArgumentException $e) {
            $output->writeln(sprintf("greet('') failed: %s: %s", (new \ReflectionClass($e))->getShortName(), $e->getMessage()));
        }
        $output->writeln("flaky('x') = ".$this->strings->flaky('x'));
        $output->writeln("placeOrder('SKU-1', 2) = ".$this->orders->placeOrder('SKU-1', 2));

        $output->writeln('Trace:');
        foreach ($this->trace->lines as $line) {
            $output->writeln($line);
        }

        return Command::SUCCESS;
    }
}
