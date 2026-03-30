<?php

declare(strict_types=1);

namespace App\Http\ViewModels;

final class HomeViewModel
{
    /**
     * @return array{page: array{title: string, welcome_heading: string, welcome_description: string}}
     */
    public function toArray(): array
    {
        return [
            'page' => [
                'title' => config('app.name', 'Gestao de Operacoes Financeiras'),
                'welcome_heading' => 'Bem-vindo(a) ao sistema',
                'welcome_description' => 'Gerencie operacoes financeiras com autenticação e rastreabilidade de status.',
            ],
        ];
    }
}
