<?php
declare(strict_types=1);

namespace LessAbstractService\Http\Handler\Event;

use LessDomain\Event\Event;
use LessDomain\Event\Property\Headers;
use LessDomain\Event\Store\Store;
use LessHydrator\Hydrator;
use LessValueObject\Number\Exception\MaxOutBounds;
use LessValueObject\Number\Exception\MinOutBounds;
use LessValueObject\Number\Exception\PrecisionOutBounds;
use LessValueObject\Number\Int\Date\MilliTimestamp;
use LessValueObject\String\Exception\TooLong;
use LessValueObject\String\Exception\TooShort;
use LessValueObject\String\Format\Exception\NotFormat;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class AbstractEventRouteHandler implements RequestHandlerInterface
{
    abstract protected function createResponse(ServerRequestInterface $request, Event $event): ResponseInterface;

    /**
     * @param Hydrator $hydrator
     * @param Store $store
     * @param array<mixed> $routes
     */
    public function __construct(
        private readonly Hydrator $hydrator,
        private readonly Store $store,
        private readonly array $routes,
    ) {}

    /**
     * @throws MaxOutBounds
     * @throws MinOutBounds
     * @throws NotFormat
     * @throws PrecisionOutBounds
     * @throws TooLong
     * @throws TooShort
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $event = $this->makeEvent($request);
        $this->store->persist($event);

        return $this->createResponse($request, $event);
    }

    /**
     * @throws MaxOutBounds
     * @throws MinOutBounds
     * @throws NotFormat
     * @throws PrecisionOutBounds
     * @throws TooLong
     * @throws TooShort
     */
    protected function makeEvent(ServerRequestInterface $request): Event
    {
        return $this->hydrator->hydrate(
            $this->getEventClass($request),
            $this->getEventData($request),
        );
    }

    /**
     * @return class-string<Event>
     *
     * @psalm-suppress MixedAssignment
     */
    protected function getEventClass(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();
        $key = "{$method}:{$path}";

        assert(isset($this->routes[$key]));
        $route = $this->routes[$key];

        assert(is_array($route));
        assert(is_string($route['event']));
        assert(is_subclass_of($route['event'], Event::class));

        return $route['event'];
    }

    /**
     * @throws MaxOutBounds
     * @throws MinOutBounds
     * @throws PrecisionOutBounds
     * @throws TooLong
     * @throws TooShort
     * @throws NotFormat
     *
     * @return array<mixed>
     */
    protected function getEventData(ServerRequestInterface $request): array
    {
        $data = $request->getParsedBody();
        assert(is_array($data));

        $data['occurredOn'] = MilliTimestamp::now();
        $data['headers'] = Headers::fromRequest($request);

        return $data;
    }
}
