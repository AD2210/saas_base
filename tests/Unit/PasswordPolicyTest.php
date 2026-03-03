<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Demo\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testValidateReturnsNoErrorForStrongPassword(): void
    {
        $policy = new PasswordPolicy();

        $errors = $policy->validate('StrongPassw0rd!');

        self::assertSame([], $errors);
    }

    public function testValidateReturnsAllExpectedErrors(): void
    {
        $policy = new PasswordPolicy();

        $errors = $policy->validate('weak');

        self::assertContains('Password must contain at least 12 characters.', $errors);
        self::assertContains('Password must contain at least one uppercase letter.', $errors);
        self::assertContains('Password must contain at least one number.', $errors);
        self::assertContains('Password must contain at least one special character.', $errors);
    }
}
