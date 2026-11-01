<?php
/**
 * In-app Claude features (suggest resolution categories, suggest action
 * items from minutes). Every feature here only *suggests*: results go back
 * to the browser for a person to review, edit and save through the normal
 * API endpoints. Nothing here writes to the database.
 *
 * Access is limited to the usernames in CLAUDE_ALLOWED_USERS (see
 * canUseClaude() in config/auth.php). Needs the Anthropic PHP SDK
 * (`composer require anthropic-ai/sdk guzzlehttp/guzzle`) and an API key in the
 * ANTHROPIC_API_KEY environment variable or config constant.
 */

require_once __DIR__ . '/../config/config.php';

const CLAUDE_SDK_AUTOLOAD = __DIR__ . '/../vendor/autoload.php';

/** The API key from the environment, else the config constant ('' if neither). */
function claudeApiKey(): string {
    $key = getenv('ANTHROPIC_API_KEY');
    if (is_string($key) && $key !== '') {
        return $key;
    }
    return defined('ANTHROPIC_API_KEY') ? (string)ANTHROPIC_API_KEY : '';
}

/**
 * Why Claude can't be used on this server, or null if it can.
 * (Separate from canUseClaude(), which is about who is asking.)
 */
function claudeSetupProblem(): ?string {
    if (!file_exists(CLAUDE_SDK_AUTOLOAD)) {
        return 'The Anthropic PHP SDK is not installed. Run `composer require anthropic-ai/sdk guzzlehttp/guzzle` in the app folder on the server.';
    }
    if (claudeApiKey() === '') {
        return 'No Anthropic API key is configured. Set ANTHROPIC_API_KEY in the web server environment (or in config/config.php).';
    }
    return null;
}

/**
 * Ask Claude for a JSON object matching $schema and return it decoded.
 *
 * Uses structured outputs so the response always parses, and server-side
 * refusal fallbacks so a request declined by one model is retried on its
 * default fallback rather than failing outright.
 *
 * @throws RuntimeException with a message safe to show the user
 */
function claudeJson(string $system, string $prompt, array $schema, string $effort = 'medium', int $maxTokens = 16000): array {
    $problem = claudeSetupProblem();
    if ($problem !== null) {
        throw new RuntimeException($problem);
    }
    require_once CLAUDE_SDK_AUTOLOAD;

    try {
        $client = new \Anthropic\Client(apiKey: claudeApiKey());
        $message = $client->beta->messages->create(
            model: CLAUDE_MODEL,
            maxTokens: $maxTokens,
            system: $system,
            messages: [['role' => 'user', 'content' => $prompt]],
            outputConfig: [
                'effort' => $effort,
                'format' => ['type' => 'json_schema', 'schema' => $schema],
            ],
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );
    } catch (\Anthropic\Core\Exceptions\AuthenticationException $e) {
        throw new RuntimeException('The Anthropic API key was rejected. Check ANTHROPIC_API_KEY.');
    } catch (\Anthropic\Core\Exceptions\RateLimitException $e) {
        throw new RuntimeException('Claude is rate limited right now. Try again in a minute.');
    } catch (\Anthropic\Core\Exceptions\APIConnectionException $e) {
        throw new RuntimeException('Could not reach the Anthropic API from this server (' . $e->getMessage() . ').');
    } catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
        error_log('Claude API error: ' . $e->getMessage());
        throw new RuntimeException('The Claude request failed: ' . $e->getMessage());
    } catch (\Anthropic\Core\Exceptions\AnthropicException $e) {
        error_log('Claude SDK error: ' . $e->getMessage());
        throw new RuntimeException('The Claude request failed: ' . $e->getMessage());
    }

    if ($message->stopReason === 'refusal') {
        throw new RuntimeException('Claude declined this request.');
    }
    if ($message->stopReason === 'max_tokens') {
        throw new RuntimeException('Claude\'s answer was too long and got cut off. Try a smaller selection.');
    }

    foreach ($message->content as $block) {
        if ($block->type === 'text') {
            $data = json_decode($block->text, true);
            if (is_array($data)) {
                return $data;
            }
            break;
        }
    }
    throw new RuntimeException('Claude returned an unexpected response.');
}
