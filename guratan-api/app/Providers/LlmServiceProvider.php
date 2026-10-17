<?php

namespace App\Providers;

use App\Services\Llm\ApiLlmProvider;
use App\Services\Llm\GeminiKeyPool;
use App\Services\Llm\GeminiLlmProvider;
use App\Services\Llm\LlmProviderInterface;
use App\Services\Llm\NullLlmProvider;
use App\Services\Llm\SelfHostedLlmProvider;
use Illuminate\Support\ServiceProvider;

class LlmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LlmProviderInterface::class, function () {
            return match (config('services.llm.provider', 'none')) {
                'api' => new ApiLlmProvider,
                'selfhosted' => new SelfHostedLlmProvider,
                'gemini' => new GeminiLlmProvider(GeminiKeyPool::fromConfig()),
                default => new NullLlmProvider, // MVP default - tanpa LLM
            };
        });
    }
}
