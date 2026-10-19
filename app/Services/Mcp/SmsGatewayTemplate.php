<?php

namespace App\Services\Mcp;

/**
 * The "template" an institute's SMS gateway row carries, as the Send SMS Parents module applies it.
 *
 * That module keeps no list of SMS templates. What it has is the gateway row's `last_var`, added to the end of
 * the request after the message: either text that becomes part of every SMS (a signature such as
 * ".(Generated at CMA)") or a parameter that carries the template id the gateway registered
 * ("&templateid=1707…"). The sender applies it silently, so a person previewing a message never saw what the
 * parents would actually receive. This describes it, so every assistant flow that sends an SMS can show it.
 *
 * Credentials never pass through here: only `last_var` is read, and anything in it that looks like one is masked.
 */
class SmsGatewayTemplate
{
    /**
     * @param  array<string, mixed>  $gateway  A `sms_api_details` row.
     * @return array{kind:string, name:?string, text:?string, note:string}
     */
    public static function describe(array $gateway): array
    {
        $last = trim((string) ($gateway['last_var'] ?? ''));

        if ($last === '') {
            return ['kind' => 'none', 'name' => null, 'text' => null, 'note' => 'No template or signature is saved for this institute; the message goes out exactly as written.'];
        }

        if (str_starts_with($last, '&')) {
            $masked = preg_replace('/((?:key|pass(?:word)?|token|secret|user(?:id|name)?|sid)=)[^&]+/i', '$1***', $last) ?? $last;

            return [
                'kind' => 'gateway_parameter',
                'name' => $masked,
                'text' => null,
                'note' => 'The gateway sends this registered template parameter with every SMS. Your wording must match the registered template, or the gateway may reject it.',
            ];
        }

        return [
            'kind' => 'signature',
            'name' => null,
            'text' => $last,
            'note' => 'This saved text is added to the end of every SMS, exactly as in Send SMS to Parents.',
        ];
    }

    /**
     * The SMS as the parents receive it: the message, then the saved signature when there is one. A gateway
     * parameter is not text and is not added.
     *
     * @param  array<string, mixed>  $template  `describe()`'s result.
     */
    public static function finalText(string $message, array $template): string
    {
        return ($template['kind'] ?? '') === 'signature' ? $message.(string) $template['text'] : $message;
    }
}
