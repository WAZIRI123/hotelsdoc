<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class BeemSmsClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $secretKey,
        private readonly string $sourceAddr,
        private readonly string $endpoint,
    ) {}

    /**
     * Send an SMS to one recipient via the Beem Africa API.
     *
     * @return array<string, mixed>
     */
    public function send(string $destAddr, string $message): array
    {
        if ($this->apiKey === '' || $this->secretKey === '') {
            throw new RuntimeException('Beem SMS credentials are not configured.');
        }

        $normalized = $this->normalizePhone($destAddr);

        $response = Http::withBasicAuth($this->apiKey, $this->secretKey)
            ->acceptJson()
            ->asJson()
            ->post($this->endpoint, [
                'source_addr' => $this->sourceAddr,
                'encoding' => 0,
                'message' => $message,
                'recipients' => [
                    ['recipient_id' => 1, 'dest_addr' => $normalized],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                sprintf('Beem SMS request failed (HTTP %d): %s', $response->status(), $response->body()),
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Beem expects international format without a leading "+" or "0".
     * Tanzania numbers default to the 255 country code when a local prefix is given.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            throw new RuntimeException('Phone number is empty.');
        }

        if (str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        }

        return $digits;
    }
}
