<?php

declare(strict_types=1);

namespace App\Domain\Client\ValueObjects;

use App\Domain\Shared\Result\DomainError;
use App\Domain\Shared\Result\ErrorCode;
use App\Domain\Shared\Result\Result;

final readonly class ClientCpf
{
    private function __construct(private string $value) {}

    /**
     * @return Result<self>
     */
    public static function fromString(string $rawCpf): Result
    {
        if (strlen($rawCpf) > 14 || ! ctype_alnum($rawCpf)) {
            return Result::failure(new DomainError(
                code: ErrorCode::ClientCpfInvalid,
                message: 'CPF anonimizado deve conter somente caracteres alfanumericos e deve ter no maximo 14 de tamanho.',
                context: ['cpf' => $rawCpf],
            ));
        }

        return Result::success(new self($rawCpf));
    }

    public function value(): string
    {
        return $this->value;
    }
}
