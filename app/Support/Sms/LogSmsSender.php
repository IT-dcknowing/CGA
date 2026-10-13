<?php

namespace App\Support\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Pilote de développement : aucun SMS ne part, chaque message est écrit dans le journal
 * SMS_LOG_CHANNEL (storage/logs/sms.log par défaut, rien si « null ») et gardé en mémoire
 * pour la requête en cours.
 */
class LogSmsSender implements SmsSender
{
    /**
     * @var list<array{to: string, message: string}>
     */
    private array $sent = [];

    public function __construct(private ?string $channel) {}

    public function send(string $to, string $message): void
    {
        if ($this->channel !== null) {
            Log::channel($this->channel)->info("SMS à {$to} : {$message}");
        }

        $this->sent[] = ['to' => $to, 'message' => $message];
    }

    /**
     * @return list<array{to: string, message: string}>
     */
    public function sent(): array
    {
        return $this->sent;
    }
}
