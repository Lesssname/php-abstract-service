<?php
declare(strict_types=1);

namespace LessAbstractService\Queue\Worker;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use LessIdentity\Account\Model\Account;
use LessIdentity\Account\Repository\AccountRepository;
use LessIdentity\Account\Repository\Vo\SortableOptions;
use LessQueue\Job\Job;
use LessQueue\Queue;
use LessQueue\Worker\Worker;
use LessResource\Set\ResourceSet;
use LessValueObject\Composite\Paginate;
use LessValueObject\Enum\OrderDirection;
use LessValueObject\Number\Exception\MaxOutBounds;
use LessValueObject\Number\Exception\MinOutBounds;
use LessValueObject\Number\Exception\PrecisionOutBounds;
use LessValueObject\Number\Int\Paginate\Page;
use LessValueObject\Number\Int\Paginate\PerPage;
use RuntimeException;

final class LoadAccountRolesWorker implements Worker
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly Connection $connection,
        private readonly Queue $queue,
    ) {}

    /**
     * @throws Exception
     * @throws MaxOutBounds
     * @throws MinOutBounds
     * @throws PrecisionOutBounds
     */
    public function process(Job $job): void
    {
        $accounts = $this->request($job);

        $this->insertAccountRoles($accounts);
        $this->queueNextPage($job, $accounts);
    }

    /**
     * @return ResourceSet<Account>
     *
     * @throws MaxOutBounds
     * @throws MinOutBounds
     * @throws PrecisionOutBounds
     */
    private function request(Job $job): ResourceSet
    {
        $page = $this->getPage($job);

        return $this
            ->accountRepository
            ->getByRegistered(
                new Paginate(
                    new PerPage(self::PER_PAGE),
                    new Page($page),
                ),
                new SortableOptions(
                    direction: OrderDirection::Ascending,
                ),
            );
    }

    /**
     * @param ResourceSet<Account> $accounts
     *
     * @throws Exception
     */
    private function insertAccountRoles(ResourceSet $accounts): void
    {
        $insertQuery = <<<'SQL'
INSERT INTO account_role (account_type, account_id, role)
VALUES (:account_type, :account_id, :role)
ON DUPLICATE KEY UPDATE role = :role
SQL;

        $statement = $this->connection->prepare($insertQuery);

        foreach ($accounts as $account) {
            $statement->executeStatement(
                [
                    'account_type' => $account->type,
                    'account_id' => $account->id,
                    'role' => $account->attributes->role->getValue(),
                ],
            );
        }
    }

    /**
     * @param ResourceSet<Account> $accounts
     */
    private function queueNextPage(Job $job, ResourceSet $accounts): void
    {
        $pages = (int)ceil($accounts->count() / self::PER_PAGE);
        $page = $this->getPage($job);

        if ($pages > $page) {
            $this->queue->put($job->name, ['page' => $page + 1]);
        }
    }

    private function getPage(Job $job): int
    {
        if (!isset($job->data['page'])) {
            return 1;
        }

        if (is_int($job->data['page'])) {
            return $job->data['page'];
        }

        throw new RuntimeException();
    }
}
