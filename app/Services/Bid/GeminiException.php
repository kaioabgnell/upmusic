<?php

namespace App\Services\Bid;

use RuntimeException;

/**
 * Falha na integração com o Gemini (ver specs/21 §5.1).
 *
 * Carrega uma mensagem já pronta para o usuário (PT-BR, sem detalhe interno) separada da mensagem
 * técnica, que vai para o log e para `bid_notices.error_message`.
 */
class GeminiException extends RuntimeException
{
    public function __construct(
        string $technicalMessage,
        private readonly string $userMessage,
        private readonly ?int $httpStatus = null,
        private readonly ?string $rawResponse = null,
    ) {
        parent::__construct($technicalMessage);
    }

    public function userMessage(): string
    {
        return $this->userMessage;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function rawResponse(): ?string
    {
        return $this->rawResponse;
    }
}
