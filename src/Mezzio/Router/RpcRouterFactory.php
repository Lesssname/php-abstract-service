<?php
declare(strict_types=1);

namespace LessAbstractService\Mezzio\Router;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class RpcRouterFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): RpcRouter
    {
        $config = $container->get('config');
        assert(is_array($config));
        assert(is_array($config['routes']));

        return new RpcRouter($container, $config['routes']);
    }
}
