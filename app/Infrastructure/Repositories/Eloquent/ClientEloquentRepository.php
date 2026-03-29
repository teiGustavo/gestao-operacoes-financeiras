<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Client\Contracts\Repositories\ClientRepositoryInterface;
use App\Domain\Client\Entities\Client;
use App\Infrastructure\Data\Client\ClientData;
use App\Infrastructure\Exceptions\InfrastructureUnavailableException;
use App\Infrastructure\Repositories\Mappers\Client\ClientDataMapper;
use App\Models\Client as ClientModel;
use Illuminate\Database\QueryException;

final readonly class ClientEloquentRepository implements ClientRepositoryInterface
{
    public function __construct(
        private ClientModel $clientModel,
        private ClientDataMapper $clientDataMapper,
    ) {
    }

    public function findById(int $clientId): ?Client
    {
        try {
            $model = $this->clientModel->newQuery()->find($clientId);

            if ($model === null) {
                return null;
            }

            return $this->mapModelToDomain($model);
        } catch (QueryException $queryException) {
            throw new InfrastructureUnavailableException('Nao foi possivel consultar cliente por id.', 0, $queryException);
        }
    }

    public function findByCpf(string $cpf): ?Client
    {
        try {
            $model = $this->clientModel->newQuery()
                ->where('cpf', $cpf)
                ->first();

            if ($model === null) {
                return null;
            }

            return $this->mapModelToDomain($model);
        } catch (QueryException $queryException) {
            throw new InfrastructureUnavailableException('Nao foi possivel consultar cliente por cpf.', 0, $queryException);
        }
    }

    public function findByEmail(string $email): ?Client
    {
        try {
            $model = $this->clientModel->newQuery()
                ->where('email', $email)
                ->first();

            if ($model === null) {
                return null;
            }

            return $this->mapModelToDomain($model);
        } catch (QueryException $queryException) {
            throw new InfrastructureUnavailableException('Nao foi possivel consultar cliente por email.', 0, $queryException);
        }
    }

    public function existsByCpf(string $cpf, ?int $ignoreClientId = null): bool
    {
        try {
            $query = $this->clientModel->newQuery()->where('cpf', $cpf);

            if ($ignoreClientId !== null) {
                $query->where('id', '!=', $ignoreClientId);
            }

            return $query->exists();
        } catch (QueryException $queryException) {
            throw new InfrastructureUnavailableException('Nao foi possivel verificar cpf de cliente.', 0, $queryException);
        }
    }

    public function existsByEmail(string $email, ?int $ignoreClientId = null): bool
    {
        try {
            $query = $this->clientModel->newQuery()->where('email', $email);

            if ($ignoreClientId !== null) {
                $query->where('id', '!=', $ignoreClientId);
            }

            return $query->exists();
        } catch (QueryException $queryException) {
            throw new InfrastructureUnavailableException('Nao foi possivel verificar email de cliente.', 0, $queryException);
        }
    }

    public function save(Client $client): Client
    {
        try {
            $clientData = $this->clientDataMapper->toPersistence($client);

            if (! $clientData instanceof ClientData) {
                throw new InfrastructureUnavailableException('Mapper de client retornou payload invalido.');
            }

            $model = $clientData->id === null
                ? $this->clientModel->newInstance()
                : $this->clientModel->newQuery()->findOrFail($clientData->id);

            $model->fill([
                'name' => $clientData->name,
                'cpf' => $clientData->cpf,
                'birth_date' => $clientData->birthDate,
                'gender' => $clientData->gender,
                'email' => $clientData->email,
            ]);

            $model->save();

            return $this->mapModelToDomain($model);
        } catch (\Throwable $throwable) {
            if ($throwable instanceof InfrastructureUnavailableException) {
                throw $throwable;
            }

            throw new InfrastructureUnavailableException('Nao foi possivel persistir cliente.', 0, $throwable);
        }
    }

    private function mapModelToDomain(ClientModel $clientModel): Client
    {
        $clientData = new ClientData(
            id: $clientModel->id,
            name: (string) $clientModel->name,
            cpf: (string) $clientModel->cpf,
            birthDate: $clientModel->birth_date->format('Y-m-d'),
            gender: $clientModel->gender->value,
            email: (string) $clientModel->email,
        );

        $mappedClient = $this->clientDataMapper->toDomain($clientData);

        if (! $mappedClient instanceof Client) {
            throw new InfrastructureUnavailableException('Mapper de client retornou entidade invalida.');
        }

        return $mappedClient;
    }
}

