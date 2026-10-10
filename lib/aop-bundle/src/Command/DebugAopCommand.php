<?php

declare(strict_types=1);

namespace Phauthentic\AOP\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'debug:aop', description: 'Lists the services woven with AOP proxies and the advice applied to each method')]
final class DebugAopCommand extends Command
{
    /**
     * @param array{advised: array<string, array{class: string, proxy: string, strategy: string, methods: array<string, list<string>>}>, skipped: array<string, array{class: string, reason: string}>} $report
     */
    public function __construct(private readonly array $report)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Advised services');
        $rows = [];
        foreach ($this->report['advised'] as $id => $service) {
            foreach ($service['methods'] as $method => $advisors) {
                $rows[] = [$id, $service['strategy'], $method, implode("\n", $advisors)];
            }
        }[] === $rows ? $io->text('No service is advised.') : $io->table(['Service', 'Strategy', 'Method', 'Advice'], $rows);

        $io->section('Skipped services');
        $skipped = [];
        foreach ($this->report['skipped'] as $id => $service) {
            $skipped[] = sprintf('%s (%s): %s', $id, $service['class'], $service['reason']);
        }[] === $skipped ? $io->text('No advised service was skipped.') : $io->listing($skipped);

        return Command::SUCCESS;
    }
}
