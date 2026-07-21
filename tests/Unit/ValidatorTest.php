<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Validation\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the input validator (no database required).
 */
final class ValidatorTest extends TestCase
{
    public function testRequiredFails(): void
    {
        $v = new Validator([], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function testEmailValidation(): void
    {
        $this->assertTrue((new Validator(['e' => 'bad'], ['e' => 'email']))->fails());
        $this->assertTrue((new Validator(['e' => 'a@b.com'], ['e' => 'email']))->passes());
    }

    public function testNullablePasses(): void
    {
        $v = new Validator(['age' => ''], ['age' => 'nullable|integer']);
        $this->assertTrue($v->passes());
    }

    public function testMinMax(): void
    {
        $this->assertTrue((new Validator(['n' => 3], ['n' => 'integer|min:5']))->fails());
        $this->assertTrue((new Validator(['n' => 7], ['n' => 'integer|min:5|max:10']))->passes());
    }

    public function testInRule(): void
    {
        $this->assertTrue((new Validator(['s' => 'x'], ['s' => 'in:a,b,c']))->fails());
        $this->assertTrue((new Validator(['s' => 'b'], ['s' => 'in:a,b,c']))->passes());
    }

    public function testConfirmed(): void
    {
        $data = ['password' => 'secret12', 'password_confirmation' => 'secret12'];
        $this->assertTrue((new Validator($data, ['password' => 'confirmed']))->passes());
    }
}
