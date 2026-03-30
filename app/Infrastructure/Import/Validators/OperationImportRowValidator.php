<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\Validators;

use App\Domain\Client\ClientGender;
use App\Domain\Operation\OperationStatus;
use App\Domain\Operation\ProductType;
use App\Infrastructure\Import\Normalizers\DateValueNormalizer;
use App\Infrastructure\Import\Normalizers\DecimalValueNormalizer;
use DateTimeImmutable;
use InvalidArgumentException;

final class OperationImportRowValidator
{
    private const array GENERATED_COLUMNS = [
        'conveniada_id',
        'status_id',
    ];

    /**
     * @var array<string, OperationStatus>
     */
    private const array STATUS_BY_ID = [
        '1' => OperationStatus::DRAFT,
        '2' => OperationStatus::PRE_ANALYSIS,
        '3' => OperationStatus::UNDER_REVIEW,
        '4' => OperationStatus::AWAITING_SIGNATURE,
        '5' => OperationStatus::SIGNATURE_COMPLETED,
        '6' => OperationStatus::APPROVED,
        '7' => OperationStatus::CANCELED,
        '8' => OperationStatus::DISBURSED,
    ];

    /**
     * @var array<string, ProductType>
     */
    private const array PRODUCT_TYPE_BY_CSV_VALUE = [
        'CONSIGNADO' => ProductType::PAYROLL_LOAN,
        'NAO_CONSIGNADO' => ProductType::PERSONAL_LOAN,
        'NÃO_CONSIGNADO' => ProductType::PERSONAL_LOAN,
    ];

    /**
     * @var array<string, ClientGender>
     */
    private const array GENDER_BY_CSV_VALUE = [
        'M' => ClientGender::MALE,
        'F' => ClientGender::FEMALE,
        'O' => ClientGender::OTHER,
        'P' => ClientGender::PREFER_NOT_TO_SAY,
    ];

    public function __construct(
        private readonly DateValueNormalizer $dateValueNormalizer,
        private readonly DecimalValueNormalizer $decimalValueNormalizer,
    ) {}

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $expectedHeaders
     * @return array<string, string>
     */
    public function validateAndNormalizeRow(array $row, int $lineNumber, array $expectedHeaders): array
    {
        foreach ($expectedHeaders as $column) {
            if (! array_key_exists($column, $row)) {
                throw new InvalidArgumentException("$column: Coluna obrigatoria ausente na linha $lineNumber");
            }

            $row[$column] = trim($row[$column]);
        }

        $requiredColumns = array_values(array_filter(
            $expectedHeaders,
            static fn (string $column): bool => $column !== 'data_pagamento',
        ));

        foreach ($requiredColumns as $column) {
            if ($row[$column] === '' && ! in_array($column, self::GENERATED_COLUMNS, true)) {
                throw new InvalidArgumentException("$column: valor obrigatorio ausente na linha $lineNumber");
            }
        }

        $row['dt_nasc'] = $this->dateValueNormalizer->normalizeToYmd($row['dt_nasc']) ?? '';
        $row['data_criacao'] = $this->dateValueNormalizer->normalizeToYmd($row['data_criacao']) ?? '';

        if ($row['data_pagamento'] === '') {
            $row['data_pagamento'] = '';
        } else {
            $parsedPaymentDate = $this->dateValueNormalizer->normalizeToYmd($row['data_pagamento']);
            $row['data_pagamento'] = $parsedPaymentDate ?? $row['data_pagamento'];
        }

        $row['data_primeiro_vencimento'] =
            $this->dateValueNormalizer->normalizeToYmd($row['data_primeiro_vencimento']) ?? '';

        $requestedValue = $this->decimalValueNormalizer->normalize($row['valor_requerido']);
        $disbursementValue = $this->decimalValueNormalizer->normalize($row['valor_desembolso']);
        $totalInterest = $this->decimalValueNormalizer->normalize($row['total_juros']);
        $interestRate = $this->decimalValueNormalizer->normalize($row['taxa_juros']);
        $lateInterestRate = $this->decimalValueNormalizer->normalize($row['taxa_mora']);
        $lateFeeRate = $this->decimalValueNormalizer->normalize($row['taxa_multa']);
        $installmentValue = $this->decimalValueNormalizer->normalize($row['valor_parcela']);

        $row['valor_requerido'] = $requestedValue !== null ? (string) $requestedValue : '';
        $row['valor_desembolso'] = $disbursementValue !== null ? (string) $disbursementValue : '';
        $row['total_juros'] = $totalInterest !== null ? (string) $totalInterest : '';
        $row['taxa_juros'] = $interestRate !== null ? (string) $interestRate : '';
        $row['taxa_mora'] = $lateInterestRate !== null ? (string) $lateInterestRate : '';
        $row['taxa_multa'] = $lateFeeRate !== null ? (string) $lateFeeRate : '';
        $row['valor_parcela'] = $installmentValue !== null ? (string) $installmentValue : '';

        $this->assertDate($row['dt_nasc'], 'dt_nasc', $lineNumber);
        $this->assertDecimal($row['valor_requerido'], 'valor_requerido', $lineNumber);
        $this->assertDecimal($row['valor_desembolso'], 'valor_desembolso', $lineNumber);
        $this->assertDecimal($row['total_juros'], 'total_juros', $lineNumber);
        $this->assertNullableInteger($row['status_id'], 'status_id', $lineNumber);
        $this->assertDecimal($row['taxa_juros'], 'taxa_juros', $lineNumber);
        $this->assertDecimal($row['taxa_mora'], 'taxa_mora', $lineNumber);
        $this->assertDecimal($row['taxa_multa'], 'taxa_multa', $lineNumber);
        $this->assertDate($row['data_criacao'], 'data_criacao', $lineNumber);
        $this->assertNullableDate($row['data_pagamento'], 'data_pagamento', $lineNumber);
        $this->assertNullableInteger($row['conveniada_id'], 'conveniada_id', $lineNumber);
        $this->assertInteger($row['quantidade_parcelas'], 'quantidade_parcelas', $lineNumber);
        $this->assertDate($row['data_primeiro_vencimento'], 'data_primeiro_vencimento', $lineNumber);
        $this->assertDecimal($row['valor_parcela'], 'valor_parcela', $lineNumber);
        $this->assertInteger($row['quantidade_parcelas_pagas'], 'quantidade_parcelas_pagas', $lineNumber);

        if ((int) $row['quantidade_parcelas_pagas'] > (int) $row['quantidade_parcelas']) {
            throw new InvalidArgumentException(
                "quantidade_parcelas_pagas: valor nao pode ser maior que quantidade_parcelas na linha $lineNumber",
            );
        }

        $row['status_id'] = $this->mapStatus($row['status_id'], $lineNumber)->value;
        $row['produto'] = $this->mapProductType($row['produto'], $lineNumber)->value;
        $row['sexo'] = $this->mapGender($row['sexo'], $lineNumber)->value;

        return $row;
    }

    private function assertInteger(string $value, string $column, int $lineNumber): void
    {
        if (! preg_match('/^\d+$/', $value)) {
            throw new InvalidArgumentException("$column: Tipo invalido na linha $lineNumber");
        }
    }

    private function assertNullableInteger(?string $value, string $column, int $lineNumber): void
    {
        if ($value === '' || $value === null) {
            return;
        }

        $this->assertInteger($value, $column, $lineNumber);
    }

    private function assertDecimal(string $value, string $column, int $lineNumber): void
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("$column: Tipo invalido na linha $lineNumber");
        }
    }

    private function assertDate(string $value, string $column, int $lineNumber): void
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("$column: Tipo invalido na linha $lineNumber");
        }
    }

    private function assertNullableDate(?string $value, string $column, int $lineNumber): void
    {
        if ($value === '' || $value === null) {
            return;
        }

        $this->assertDate($value, $column, $lineNumber);
    }

    private function mapStatus(string $statusId, int $lineNumber): OperationStatus
    {
        $mappedStatus = self::STATUS_BY_ID[$statusId] ?? null;

        if ($mappedStatus === null && $statusId !== '') {
            throw new InvalidArgumentException("status_id: Mapeamento invalido na linha $lineNumber");
        }

        return $mappedStatus ?? OperationStatus::DRAFT;
    }

    private function mapProductType(string $productType, int $lineNumber): ProductType
    {
        $normalizedProductType = strtoupper($productType);
        $mappedProductType = self::PRODUCT_TYPE_BY_CSV_VALUE[$normalizedProductType] ?? null;

        if ($mappedProductType === null) {
            throw new InvalidArgumentException("produto: Mapeamento invalido na linha $lineNumber");
        }

        return $mappedProductType;
    }

    private function mapGender(string $gender, int $lineNumber): ClientGender
    {
        $normalizedGender = strtoupper($gender);
        $mappedGender = self::GENDER_BY_CSV_VALUE[$normalizedGender] ?? null;

        if ($mappedGender === null) {
            throw new InvalidArgumentException("sexo: Mapeamento invalido na linha $lineNumber");
        }

        return $mappedGender;
    }
}
