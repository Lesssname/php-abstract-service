<?php
declare(strict_types=1);

namespace LessAbstractService\Http\Resource\Handler;

use JsonException;
use LessHydrator\Hydrator;
use LessDomain\Event\Event;
use LessDomain\Event\Store\Store;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use LessValueObject\String\Exception\TooLong;
use LessValueObject\String\Exception\TooShort;
use Psr\Http\Message\ResponseFactoryInterface;
use LessValueObject\Number\Exception\MaxOutBounds;
use LessValueObject\Number\Exception\MinOutBounds;
use LessValueObject\Number\Exception\NotMultipleOf;
use LessDocumentor\Route\Attribute\DocHttpResponse;
use LessDocumentor\Route\Attribute\DocInputProvided;
use LessValueObject\String\Format\Exception\NotFormat;
use LessDomain\Identifier\Generator\IdentifierGenerator;
use LessAbstractService\Http\Resource\Handler\Response\CreatedResponse;

#[DocInputProvided(['id', 'occurredOn', 'headers'])]
#[DocHttpResponse(CreatedResponse::class, 201)]
final class CreateEventRouteHandler extends AbstractEventRouteHandler
{
    /**
     * @param array<mixed> $routes
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly IdentifierGenerator $identifierGenerator,
        private readonly string $projectName,
        Hydrator $hydrator,
        Store $store,
        array $routes,
    ) {
        parent::__construct($hydrator, $store, $routes);
    }

    /**
     * @throws JsonException
     */
    protected function createResponse(ServerRequestInterface $request, Event $event): ResponseInterface
    {
        assert(isset($event->id));

        $body = $this->streamFactory->createStream(
            json_encode(
                [
                    'type' => "{$this->projectName}.{$event->getTarget()}",
                    'id' => $event->id,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        return $this
            ->responseFactory
            ->createResponse(201)
            ->withBody($body)
            ->withAddedHeader('content-type', 'application/json');
    }

    /**
     * @return array<mixed>
     *
     * @throws MinOutBounds
     * @throws TooLong
     * @throws TooShort
     * @throws NotFormat
     * @throws NotMultipleOf
     * @throws MaxOutBounds
     */
    protected function getEventData(ServerRequestInterface $request): array
    {
        $data = parent::getEventData($request);

        $data['id'] = $this->identifierGenerator->generate();

        return $data;
    }
}
