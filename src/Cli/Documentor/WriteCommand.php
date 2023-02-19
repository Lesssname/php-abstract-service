<?php
declare(strict_types=1);

namespace LessAbstractService\Cli\Documentor;

use JsonException;
use LessDocumentor\Route\Document\RouteDocument;
use LessDocumentor\Route\RouteDocumentor;
use LessDocumentor\Type\Document\BoolTypeDocument;
use LessDocumentor\Type\Document\CollectionTypeDocument;
use LessDocumentor\Type\Document\CompositeTypeDocument;
use LessDocumentor\Type\Document\EnumTypeDocument;
use LessDocumentor\Type\Document\NumberTypeDocument;
use LessDocumentor\Type\Document\StringTypeDocument;
use LessDocumentor\Type\Document\TypeDocument;
use LessAbstractService\Cli\Documentor\Attribute\Format;
use LessResource\Model\ResourceModel;
use LessValueObject\Composite;
use LessValueObject\Enum;
use LessValueObject\Number;
use LessValueObject\String as StringVo;
use LessValueObject\String\Format\AbstractRegexpFormattedStringValueObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class WriteCommand extends Command
{
    private const RESPONSE_MESSAGE = [
        200 => 'Ok, see content',
        201 => 'Resource created',
        202 => 'Accepted, action will be done in due time',
        204 => 'Call successful, nothing to output',
        400 => 'Bad request, see body for more info',
        401 => 'Authentication failed',
        403 => 'Forbidden to do this request',
        404 => 'Resource not found',
        405 => 'HTTP method not allowed',
        409 => 'Conflict, could not process request',
        422 => 'Given body is not valid',
        429 => 'Too many requests',
        500 => 'Internal error, try later or seek contact',
    ];

    private const SHARED_REFERENCES = [
        // Composite
        Composite\Occurred::class,
        Composite\Activity::class,
        Composite\Content::class,
        Composite\ForeignReference::class,
        Composite\Paginate::class,
        // Enum
        Enum\OrderDirection::class,
        // Number
        Number\Int\Date\Day::class,
        Number\Int\Date\MilliTimestamp::class,
        Number\Int\Date\Month::class,
        Number\Int\Date\Timestamp::class,
        Number\Int\Date\Week::class,
        Number\Int\Date\Year::class,
        Number\Int\Time\Hour::class,
        Number\Int\Time\Minute::class,
        Number\Int\Time\Second::class,
        Number\Int\Negative::class,
        Number\Int\Positive::class,
        Number\Int\Unsigned::class,
        // String
        StringVo\Format\Resource\Identifier::class,
        StringVo\Format\Resource\Type::class,
        StringVo\Format\Uri\Https::class,
        StringVo\Format\Date::class,
        StringVo\Format\EmailAddress::class,
        StringVo\Format\Ip::class,
        StringVo\Format\SearchTerm::class,
        StringVo\PhoneNumber::class,
        StringVo\UserAgent::class,
    ];

    /**
     * @param array<array<mixed>> $routes
     */
    public function __construct(
        private readonly RouteDocumentor $routeDocumentor,
        private readonly array $routes,
        private readonly string $fileLocation,
        private readonly string $baseUri,
        private readonly ?string $name,
    ) {
        parent::__construct($name);
    }

    /**
     * @throws JsonException
     * @throws ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $document = $this->getBaseDocument();
        assert(is_array($document['paths']));

        $document['paths'] = $this->composePaths();
        $document['components'] = [
            'schemas' => $this->composeSchemaComponents(),
        ];

        assert(is_array($document['info']));
        $document['info']['version'] = md5(json_encode($document, flags: JSON_THROW_ON_ERROR));
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($this->fileLocation, $json);

        return self::SUCCESS;
    }

    /**
     * @return array<mixed>
     */
    private function getBaseDocument(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->name,
                'contact' => [
                    'name' => 'Development',
                    'email' => 'development@boekscout.nl',
                ],
            ],
            'servers' => [
                ['url' => $this->baseUri],
            ],
            'paths' => [],
            'tags' => $this->getResourceTags(),
        ];
    }

    /**
     * @return array<array{name: string}>
     */
    private function getResourceTags(): array
    {
        $tags = [];

        foreach ($this->routes as $route) {
            if (isset($route['resource']) && is_string($route['resource'])) {
                $tags[$route['resource']] = ['name' => $route['resource']];
            }
        }

        return array_values($tags);
    }

    /**
     * @return array<mixed>
     *
     * @throws ReflectionException
     */
    private function composePaths(): array
    {
        $paths = [];

        foreach ($this->routes as $route) {
            $routeDocument = $this->routeDocumentor->document($route);

            $paths[(string)$routeDocument->path] = [
                $routeDocument->method->value => $this->composePathDocument($routeDocument),
            ];
        }

        return $paths;
    }

    /**
     * @return array<mixed>
     *
     * @throws ReflectionException
     */
    private function composeSchemaComponents(): array
    {
        $document = [];

        foreach ($this->getSchemas() as $schema) {
            $reference = $schema->getReference();
            assert(is_string($reference));

            $document[$this->getReferenceName($reference)] = $this->composeTypeDocument($schema, false);
        }

        return $document;
    }

    /**
     * @return iterable<TypeDocument>
     */
    private function getSchemas(): iterable
    {
        foreach ($this->routes as $route) {
            $routeDocument = $this->routeDocumentor->document($route);

            yield from $this->getSchemasFromTypeDocument($routeDocument->input);

            foreach ($routeDocument->getRespones() as $response) {
                if ($response->output) {
                    yield from $this->getSchemasFromTypeDocument($response->output);
                }
            }
        }
    }

    /**
     * @return iterable<TypeDocument>
     */
    private function getSchemasFromTypeDocument(TypeDocument $typeDocument): iterable
    {
        if ($typeDocument instanceof CompositeTypeDocument) {
            foreach ($typeDocument->properties as $property) {
                yield from $this->getSchemasFromTypeDocument($property->type);
            }
        } elseif ($typeDocument instanceof CollectionTypeDocument) {
            yield from $this->getSchemasFromTypeDocument($typeDocument->item);
        }

        if ($this->isReference($typeDocument)) {
            $reference = $typeDocument->getReference();
            assert(is_string($reference));

            yield $typeDocument;
        }
    }

    /**
     * @return array<mixed>
     *
     * @throws ReflectionException
     */
    private function composePathDocument(RouteDocument $routeDocument): array
    {
        return [
            'tags' => [
                $routeDocument->resource,
                $routeDocument->category,
            ],
            'deprecated' => $routeDocument->deprecated !== null,
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => $this->composeTypeDocument($routeDocument->input, true),
                    ],
                ],
            ],
            'responses' => $this->composeResponses($routeDocument),
        ];
    }

    /**
     * @return array<mixed>
     * @throws ReflectionException
     */
    private function composeTypeDocument(TypeDocument $typeDocument, bool $useRef): array
    {
        if ($useRef && $this->isReference($typeDocument)) {
            $reference = $typeDocument->getReference();
            assert(is_string($reference));

            $name = $this->getReferenceName($reference);
            $document = ['$ref' => "#/components/schemas/{$name}"];

            if ($typeDocument->isNullable()) {
                $document = [
                    'anyOf' => [
                        $document,
                        ['type' => 'null'],
                    ],
                ];
            }
        } else {
            $document = match ($typeDocument::class) {
                BoolTypeDocument::class => $this->composeBoolDocument(),
                CollectionTypeDocument::class => $this->composeCollectionDocument($typeDocument),
                CompositeTypeDocument::class => $this->composeCompositeDocument($typeDocument),
                EnumTypeDocument::class => $this->composeEnumDocument($typeDocument),
                NumberTypeDocument::class => $this->composeNumberDocument($typeDocument),
                StringTypeDocument::class => $this->composeStringDocument($typeDocument),
                default => throw new RuntimeException($typeDocument::class),
            };

            if ($typeDocument->isNullable()) {
                $document['type'] = [$document['type'], 'null'];
            }
        }

        if ($typeDocument->getDescription()) {
            $document['description'] = $typeDocument->getDescription();
        }

        if ($typeDocument->getDeprecated()) {
            $document['deprecated'] = true;
        }

        return $document;
    }

    private function isReference(TypeDocument $typeDocument): bool
    {
        $class = $typeDocument->getReference();

        return $class !== null && (
                in_array($class, self::SHARED_REFERENCES, true)
                || is_subclass_of($class, ResourceModel::class)
            );
    }

    private function getReferenceName(string $class): string
    {
        $parts = explode('\\', $class);

        return array_pop($parts);
    }

    /**
     * @return array<mixed>
     */
    private function composeBoolDocument(): array
    {
        return ['type' => 'boolean'];
    }

    /**
     * @return array<mixed>
     *
     * @throws ReflectionException
     */
    private function composeCollectionDocument(CollectionTypeDocument $typeDocument): array
    {
        $document = [
            'type' => 'array',
            'items' => $this->composeTypeDocument($typeDocument->item, true),
        ];

        if ($typeDocument->size) {
            $document['minItems'] = $typeDocument->size->minimal;
            $document['maxItems'] = $typeDocument->size->maximal;
        }

        return $document;
    }

    /**
     * @return array<mixed>
     *
     * @throws ReflectionException
     */
    private function composeCompositeDocument(CompositeTypeDocument $typeDocument): array
    {
        $properties = $required = [];

        foreach ($typeDocument->properties as $key => $property) {
            $properties[$key] = $this->composeTypeDocument($property->type, true);

            if ($property->required === false) {
                $properties[$key]['default'] = $property->default;
            }

            if ($property->required) {
                $required[] = $key;
            }
        }

        return [
            'type' => 'object',
            'additionalProperties' => $typeDocument->allowExtraProperties,
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * @return array<mixed>
     */
    private function composeEnumDocument(EnumTypeDocument $typeDocument): array
    {
        return [
            'type' => 'string',
            'enum' => $typeDocument->cases,
        ];
    }

    /**
     * @return array<mixed>
     */
    private function composeNumberDocument(NumberTypeDocument $typeDocument): array
    {
        $document = [
            'type' => $typeDocument->precision === 0
                ? 'integer'
                : 'number',
        ];

        if ($typeDocument->precision !== null) {
            $document['multipleOf'] = 1 / pow(10, $typeDocument->precision);
        }

        if ($typeDocument->format) {
            $document['format'] = $typeDocument->format;
        }

        if ($typeDocument->range) {
            $document['minimum'] = $typeDocument->range->minimal;
            $document['maximum'] = $typeDocument->range->maximal;
        }

        return $document;
    }

    /**
     * @return array<mixed>
     */
    private function composeStringDocument(StringTypeDocument $typeDocument): array
    {
        $reference = $typeDocument->getReference();
        $document = ['type' => 'string'];

        if ($typeDocument->length) {
            $document['minLength'] = $typeDocument->length->minimal;
            $document['maxLength'] = $typeDocument->length->maximal;
        }

        if ($typeDocument->format) {
            $document['format'] = $typeDocument->format;
        }

        if ($reference) {
            if (!class_exists($reference)) {
                throw new RuntimeException("Reference '{$reference}' unknown");
            }

            if (is_subclass_of($reference, AbstractRegexpFormattedStringValueObject::class)) {
                $document['pattern'] = $reference::getRegexPattern();
            }

            $reflection = new ReflectionClass($reference);

            foreach ($reflection->getAttributes(Format::class) as $refAttribute) {
                $attribute = $refAttribute->newInstance();
                $document['format'] = $attribute->name;
            }
        }

        return $document;
    }

    /**
     * @return array<mixed>
     *
     * @throws ReflectionException
     */
    private function composeResponses(RouteDocument $routeDocument): array
    {
        /** @var array<int, array<mixed>> $responses */
        $responses = [];

        foreach ($routeDocument->getRespones() as $response) {
            $key = $response->code->value;

            if ($response->output) {
                $responses[$key]  = [
                    'description' => self::RESPONSE_MESSAGE[$key],
                    'content' => [
                        'application/json' => [
                            'schema' => $this->composeTypeDocument($response->output, true),
                        ],
                    ],
                ];
            } else {
                $responses[$key]  = [
                    'description' => self::RESPONSE_MESSAGE[$key],
                ];
            }
        }

        return $responses;
    }
}
