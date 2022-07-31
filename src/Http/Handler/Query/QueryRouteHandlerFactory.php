<?php
declare(strict_types=1);

namespace LessAbstractService\Http\Handler\Query;

use LessHydrator\Hydrator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @deprecated use Resource namespaced
 */
final class QueryRouteHandlerFactory
{
    /**
     * @param ContainerInterface $container
     * @param class-string<AbstractQueryRouteHandler> $name
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, string $name): AbstractQueryRouteHandler
    {
        $config = $container->get('config');
        assert(is_array($config));
        assert(is_array($config['routes']));

        $responseFactory = $container->get(ResponseFactoryInterface::class);
        assert($responseFactory instanceof ResponseFactoryInterface);

        $streamFactory = $container->get(StreamFactoryInterface::class);
        assert($streamFactory instanceof StreamFactoryInterface);

        $hydrator = $container->get(Hydrator::class);
        assert($hydrator instanceof Hydrator);

        return new $name(
            $responseFactory,
            $streamFactory,
            $container,
            $hydrator,
            $config['routes'],
        );
    }
}
