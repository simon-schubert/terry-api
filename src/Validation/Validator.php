<?php

declare(strict_types=1);

namespace Violines\RestBundle\Validation;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Violines\RestBundle\Error\ValidationException;

/**
 * @internal
 */
final class Validator
{
    private ValidatorInterface $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param object[]|object $data
     *
     * @throws ValidationException when the validator returns any violation
     */
    public function validate($data): void
    {
        $violations = $this->validator->validate($data);

        if (0 < \count($violations)) {
            throw ValidationException::fromViolationList($violations);
        }
    }
}
