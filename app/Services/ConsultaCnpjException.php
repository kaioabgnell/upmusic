<?php

namespace App\Services;

use RuntimeException;

/**
 * Falha na consulta de CNPJ (ver ConsultaCnpjService). A mensagem já vem pronta em PT-BR para
 * ser exibida direto ao usuário — não há distinção entre mensagem técnica e mensagem de usuário
 * porque nada aqui é persistido em log estruturado.
 */
class ConsultaCnpjException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 502)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
