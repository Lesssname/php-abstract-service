<?php
declare(strict_types=1);

namespace LessAbstractService\Cli\Service;

use LessQueue\Job\Property\Name;
use LessQueue\Queue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @deprecated
 */
final class LoadAccountRolesCommand extends Command
{
    public function __construct(private readonly Queue $queue)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->queue->publish(new Name('service:loadAccountRoles'), []);

        return self::SUCCESS;
    }
}
