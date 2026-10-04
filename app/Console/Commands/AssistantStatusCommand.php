<?php

namespace App\Console\Commands;

use App\Services\Assistant\Contracts\AiProviderInterface;
use App\Services\Assistant\Providers\OpenAiProvider;
use Illuminate\Console\Command;

/**
 * Safe configuration diagnostic for the AI assistant — never prints the
 * API key itself, only whether one is present and which provider/model
 * is actually active after resolving config('ai.*').
 */
class AssistantStatusCommand extends Command
{
    protected $signature = 'assistant:status';

    protected $description = 'Muestra el proveedor de IA activo del asistente sin exponer credenciales';

    public function handle(): int
    {
        $enabled = (bool) config('ai.enabled', false);
        $configuredProvider = (string) config('ai.provider', 'local');
        $openAi = app(OpenAiProvider::class);
        $active = app(AiProviderInterface::class);

        $this->line('Configuración (.env):');
        $this->table(['Clave', 'Valor'], [
            ['AI_ENABLED', $enabled ? 'true' : 'false'],
            ['AI_PROVIDER', $configuredProvider],
            ['OPENAI_API_KEY', $openAi->isConfigured() ? '(definida)' : '(vacía)'],
            ['OPENAI_MODEL', config('ai.openai.model') ?: '(vacío)'],
        ]);

        $this->newLine();
        $this->line('Proveedor realmente activo para el asistente:');
        $this->table(['Proveedor', 'Modelo', 'Estado'], [[
            $active->name(),
            $active->modelIdentifier(),
            $active->name() === 'openai' ? 'Configurado' : 'Modo local (fallback determinista)',
        ]]);

        if ($enabled && $configuredProvider === 'openai' && ! $openAi->isConfigured()) {
            $this->warn('AI_PROVIDER=openai pero falta OPENAI_API_KEY u OPENAI_MODEL — el asistente está usando el fallback local.');
        }

        return self::SUCCESS;
    }
}
