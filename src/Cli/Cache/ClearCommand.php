<?php
declare(strict_types=1);

namespace LessAbstractService\Cli\Cache;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ClearCommand extends Command
{
    public function __construct(private readonly CacheInterface $cache)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cache->clear();

        return self::SUCCESS;
    }
}
