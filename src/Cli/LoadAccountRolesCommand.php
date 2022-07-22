<?php
declare(strict_types=1);

namespace LessAbstractService\Cli;

use LessQueue\Job\Property\Name;
use LessQueue\Queue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class LoadAccountRolesCommand extends Command
{
    public function __construct(private readonly Queue $queue)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->queue->put(new Name('loadAccountRoles'), []);

        return self::SUCCESS;
    }
}
