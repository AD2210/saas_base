<?php

declare(strict_types=1);

namespace App\Domain\Demo;

final class PasswordPolicy
{
    /**
     * @return list<string>
     */
    public function validate(string $plainPassword): array
    {
        $errors = [];

        if (strlen($plainPassword) < 12) {
            $errors[] = 'Password must contain at least 12 characters.';
        }

        if (!preg_match('/[A-Z]/', $plainPassword)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }

        if (!preg_match('/\d/', $plainPassword)) {
            $errors[] = 'Password must contain at least one number.';
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $plainPassword)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        return $errors;
    }
}
