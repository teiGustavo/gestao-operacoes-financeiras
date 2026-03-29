<?php

declare(strict_types=1);

namespace App\Domain\Client\ValueObjects;

use App\Domain\Shared\Result\DomainError;
use App\Domain\Shared\Result\Result;

final readonly class ClientCpf
{
    private function __construct(private string $value)
    {
    }

    /**
     * @return Result<self>
     */
    public static function fromString(string $rawCpf): Result
    {
        $digitsOnlyCpf = preg_replace('/\D+/', '', $rawCpf) ?? '';

        if (strlen($digitsOnlyCpf) !== 11) {
            return Result::failure(new DomainError(
                code: 'CLIENT_CPF_INVALID',
                message: 'CPF deve conter 11 digitos.',
                context: ['cpf' => $rawCpf],
            ));
        }

        return Result::success(new self(self::format($digitsOnlyCpf)));
    }

    public function value(): string
    {
        return $this->value;
    }

    private static function format(string $digitsOnlyCpf): string
    {
        return sprintf(
            '%s.%s.%s-%s',
            substr($digitsOnlyCpf, 0, 3),
            substr($digitsOnlyCpf, 3, 3),
            substr($digitsOnlyCpf, 6, 3),
            substr($digitsOnlyCpf, 9, 2),
        );
    }
}

