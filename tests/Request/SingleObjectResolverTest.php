<?php

declare(strict_types=1);

namespace TerryApiBundle\Tests\Request;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use TerryApiBundle\HttpApi\HttpApi;
use TerryApiBundle\HttpApi\HttpApiReader;
use TerryApiBundle\Request\SingleObjectResolver;
use TerryApiBundle\Serialize\DeserializeEvent;
use TerryApiBundle\HttpApi\AnnotationNotFoundException;
use TerryApiBundle\Error\ValidationException;
use TerryApiBundle\HttpClient\HttpClient;
use TerryApiBundle\HttpClient\HttpClientFactory;
use TerryApiBundle\HttpClient\ServerSettings;
use TerryApiBundle\HttpClient\ServerSettingsFactory;
use TerryApiBundle\Serialize\Serializer;
use TerryApiBundle\Tests\Stubs\Candy;

class SingleObjectResolverTest extends TestCase
{
    private const TEST_STRING = 'this is a string';

    /**
     * @Mock
     * @var EventDispatcherInterface
     */
    private \Phake_IMock $eventDispatcher;

    /**
     * @Mock
     * @var ArgumentMetadata
     */
    private \Phake_IMock $argument;

    /**
     * @Mock
     * @var HttpFoundationRequest
     */
    private \Phake_IMock $request;

    /**
     * @Mock
     * @var SerializerInterface
     */
    private \Phake_IMock $serializer;

    /**
     * @Mock
     * @var HttpApiReader
     */
    private \Phake_IMock $httpApiReader;

    /**
     * @Mock
     * @var ValidatorInterface
     */
    private \Phake_IMock $validator;

    private SingleObjectResolver $resolver;

    public function setUp(): void
    {
        parent::setUp();

        \Phake::initAnnotations($this);
        \Phake::when($this->request)->getLocale->thenReturn('en_GB');

        $this->request->headers = new HeaderBag([
            'Content-Type' => 'application/json'
        ]);

        $this->resolver = new SingleObjectResolver(
            new HttpClientFactory(new ServerSettingsFactory([])),
            new Serializer($this->eventDispatcher, $this->serializer),
            $this->httpApiReader,
            $this->validator
        );
    }

    /**
     * @dataProvider providerSupportsShouldReturnFalse
     */
    public function testSupportsShouldReturnFalse(
        ?string $type,
        ?string $content,
        bool $isVariadic,
        bool $throwException
    ) {
        \Phake::when($this->argument)->getType->thenReturn($type);
        \Phake::when($this->request)->getContent->thenReturn($content);
        \Phake::when($this->argument)->isVariadic->thenReturn($isVariadic);

        if ($throwException) {
            \Phake::when($this->httpApiReader)->read->thenThrow(AnnotationNotFoundException::httpApi('test'));
        }

        $supports = $this->resolver->supports($this->request, $this->argument);

        $this->assertFalse($supports);
    }

    public function providerSupportsShouldReturnFalse(): array
    {
        return [
            ['string', self::TEST_STRING, false, false],
            [null, self::TEST_STRING, false, false],
            [Candy::class, null, false, false],
            [Candy::class, self::TEST_STRING, false, true],
            [Candy::class, self::TEST_STRING, true, false],
        ];
    }

    public function testSupportsShouldReturnTrue()
    {
        \Phake::when($this->request)->getContent->thenReturn(self::TEST_STRING);
        \Phake::when($this->argument)->getType->thenReturn(Candy::class);

        $structAnnotation = new HTTPApi();
        \Phake::when($this->httpApiReader)->read->thenReturn($structAnnotation);

        $supports = $this->resolver->supports($this->request, $this->argument);
        $this->assertTrue($supports);
    }

    /**
     * @dataProvider providerResolveShouldThrowException
     */
    public function testResolveShouldThrowException(?string $type, ?string $content)
    {
        \Phake::when($this->request)->getContent->thenReturn($content);
        \Phake::when($this->argument)->getType->thenReturn($type);

        $this->expectException(\LogicException::class);
        $result = $this->resolver->resolve($this->request, $this->argument);
        $result->current();
    }

    public function providerResolveShouldThrowException(): array
    {
        return [
            ['string', self::TEST_STRING],
            [null, self::TEST_STRING],
            [Candy::class, null],
        ];
    }

    /**
     * @dataProvider providerResolveShouldYield
     */
    public function testResolveShouldThrowValidationException()
    {
        $this->expectException(ValidationException::class);

        $candy = new Candy();
        $content = json_encode($candy);

        \Phake::when($this->request)->getContent->thenReturn($content);
        \Phake::when($this->argument)->isVariadic->thenReturn(false);
        \Phake::when($this->argument)->getType->thenReturn(Candy::class);
        \Phake::when($this->serializer)->deserialize->thenReturn($candy);
        \Phake::when($this->eventDispatcher)->dispatch->thenReturn(new DeserializeEvent(
            $content,
            HttpClient::new($this->request, ServerSettings::fromDefaults())
        ));

        $violationList = new ConstraintViolationList();
        $violationList->add(new ConstraintViolation('test', null, [], null, null, null));
        \Phake::when($this->validator)->validate->thenReturn($violationList);

        $result = $this->resolver->resolve($this->request, $this->argument);
        $result->current();
    }

    /**
     * @dataProvider providerResolveShouldYield
     */
    public function testResolveShouldYield($isVariadic, $expected, $expectedClassName)
    {
        $content = json_encode($expected);

        \Phake::when($this->request)->getContent->thenReturn(json_encode($expected));
        \Phake::when($this->argument)->isVariadic->thenReturn($isVariadic);
        \Phake::when($this->argument)->getType->thenReturn($expectedClassName);
        \Phake::when($this->serializer)->deserialize->thenReturn($expected);
        \Phake::when($this->validator)->validate->thenReturn(new ConstraintViolationList());
        \Phake::when($this->eventDispatcher)->dispatch->thenReturn(new DeserializeEvent(
            $content,
            HttpClient::new($this->request, ServerSettings::fromDefaults())
        ));

        $result = $this->resolver->resolve($this->request, $this->argument);
        $this->assertInstanceOf($expectedClassName, $result->current());
    }

    public function providerResolveShouldYield(): array
    {
        return [
            [
                false,
                new Candy(),
                Candy::class
            ],
        ];
    }
}
