<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Configuration\AiConfigurationException;
use App\Domain\AI\Configuration\AiModelClientFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * A text completion for the web frontend's own server routes — through AI Providers.
 *
 * WHY THIS EXISTS
 *
 * Two Next.js server routes (candidate screening and procedure conversion) used to call DeepSeek,
 * OpenRouter and Gemini themselves with keys from the frontend's `.env`. That put provider
 * credentials on a second server, outside the credential store, the module priorities, the
 * fallback chain and the usage ledger. They now call this endpoint instead, with the signed-in
 * user's own token, and the frontend holds no provider key at all.
 *
 * WHAT IT WILL AND WILL NOT DO
 *
 * It resolves the named module's providers exactly as the backend's own callers do — the saved
 * configuration, that module's priority order, fallback on failure or quota — for the school in the
 * caller's token. The module must be on the allow-list below: this is not a general-purpose proxy
 * to every module's providers. A module with nothing configured answers with the configuration
 * error, never with a provider the environment happens to name.
 *
 * No credential is ever returned. The response names the provider and model that answered.
 */
class AiCompletionController extends AiController
{
    /** The modules the frontend's server routes may complete under. */
    private const MODULES = ['recruitment_ai', 'knowledge_sop'];

    public function __construct(private readonly AiModelClientFactory $clients)
    {
    }

    public function complete(Request $request)
    {
        try {
            $institute = $this->scope($request)->selectedInstituteId;

            $data = $request->validate([
                'module' => ['required', 'string', Rule::in(self::MODULES)],
                'messages' => ['required', 'array', 'min:1', 'max:8'],
                'messages.*.role' => ['required', 'string', Rule::in(['system', 'user', 'assistant'])],
                'messages.*.content' => ['required', 'string', 'max:80000'],
                'json' => ['nullable', 'boolean'],
                'max_tokens' => ['nullable', 'integer', 'min:16', 'max:8192'],
                'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            ]);

            $client = $this->clients->for($data['module'], $institute);

            $text = $client->chat(
                array_map(fn (array $m) => ['role' => $m['role'], 'content' => $m['content']], $data['messages']),
                null,
                isset($data['max_tokens']) ? (int) $data['max_tokens'] : null,
                isset($data['temperature']) ? (float) $data['temperature'] : null,
                (bool) ($data['json'] ?? false),
                60,
            );

            if ($text === null || trim($text) === '') {
                return $this->failure('The AI provider returned no answer.', 502);
            }

            $used = $client->lastUsed();

            return $this->success('Completion ready.', [
                'text' => $text,
                'provider' => $used?->provider,
                'model' => $used?->model,
            ]);
        } catch (AiConfigurationException $exception) {
            // Says what the administrator must set up — never a provider the environment names.
            return $this->failure($exception->getMessage(), 503, ['reason' => $exception->reason]);
        } catch (\App\Domain\AI\Support\AiProviderException $exception) {
            // Every configured provider failed. Quota is the one case a caller can act on differently.
            return $this->failure(
                $exception->status === 429 ? 'The AI provider quota is exhausted. Try again later.' : 'The AI provider could not answer.',
                $exception->status === 429 ? 429 : 502,
                array_filter(['retry_after' => $exception->retryAfter])
            );
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }
}
