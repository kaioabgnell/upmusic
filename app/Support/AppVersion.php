<?php

namespace App\Support;

class AppVersion
{
    /**
     * Hash curto do commit git da build atual, exibido no rodapé da sidebar. Em produção é lido de
     * um arquivo `.version` (gerado pelo GitHub Actions no deploy — ver .github/workflows/main.yml),
     * pois o servidor não tem `.git` (deploy é só FTP, sem SSH). Em dev local, cai para `git`
     * diretamente, já que o `.git` do projeto existe na máquina do desenvolvedor.
     */
    public static function current(): string
    {
        $versionFile = base_path('.version');

        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile)) ?: 'dev';
        }

        $sha = @shell_exec('git -C '.escapeshellarg(base_path()).' rev-parse --short HEAD 2>/dev/null');

        return $sha ? trim($sha) : 'dev';
    }
}
