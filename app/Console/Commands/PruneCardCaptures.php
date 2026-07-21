<?php

namespace App\Console\Commands;

use App\Models\CardCapture;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneCardCaptures extends Command
{
    protected $signature = 'captures:prune';

    protected $description = 'Remove capturas pendentes com mais de 7 dias e seus arquivos de staging (ver specs/16).';

    public function handle(): int
    {
        $stale = CardCapture::pending()->where('created_at', '<', now()->subDays(7))->get();

        foreach ($stale as $capture) {
            Storage::disk('local')->delete($capture->path);
            $capture->delete();
        }

        $this->info("{$stale->count()} captura(s) pendente(s) removida(s).");

        return self::SUCCESS;
    }
}
