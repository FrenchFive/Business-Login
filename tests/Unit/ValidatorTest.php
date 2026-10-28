<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\HttpException;
use App\Support\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testReturnsCleanValues(): void
    {
        $v = new Validator(['name' => '  Alex  ', 'email' => ' Alex@ACME.test ', 'password' => ' secret ', 'remember' => 'true']);

        self::assertSame('Alex', $v->string('name', 'Name'));
        self::assertSame('alex@acme.test', $v->email('email'));
        self::assertSame(' secret ', $v->password('password'));
        self::assertTrue($v->boolean('remember'));
        self::assertFalse($v->boolean('missing'));
        self::assertSame([], $v->errors());
    }

    public function testCollectsOneMessagePerField(): void
    {
        $v = new Validator(['name' => 'A', 'email' => 'nope', 'bio' => str_repeat('x', 11)]);

        $v->string('name', 'Name', min: 2);
        $v->email('email');
        $v->optionalString('bio', 'Bio', max: 10);
        $v->string('company', 'Company');
        $v->addError('name', 'A second message is ignored.');

        self::assertSame([
            'name' => 'Name must be at least 2 characters.',
            'email' => 'Enter a valid e-mail address, like name@company.com.',
            'bio' => 'Bio must be at most 10 characters.',
            'company' => 'Company is required.',
        ], $v->errors());
    }

    public function testValidateThrowsA422WithAllFieldErrors(): void
    {
        $v = new Validator([]);
        $v->email('email');

        try {
            $v->validate();
            self::fail('Expected a validation exception.');
        } catch (HttpException $e) {
            self::assertSame(422, $e->status);
            self::assertSame('validation_failed', $e->errorCode);
            self::assertArrayHasKey('email', $e->fields);
        }
    }

    public function testNonScalarInputIsTreatedAsMissing(): void
    {
        $v = new Validator(['name' => ['array' => 'injection']]);
        $v->string('name', 'Name');

        self::assertSame(['name' => 'Name is required.'], $v->errors());
    }
}
