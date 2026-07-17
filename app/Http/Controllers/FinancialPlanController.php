<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Models\Card;
use App\Models\Empresa;
use App\Models\FinancialPlan;
use App\Support\Br;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialPlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = FinancialPlan::query()
            ->with('empresa:id,corporate_name')
            ->withCount('entries')
            ->withSum('entries as estimated_sum', 'estimated_value')
            ->withSum('entries as actual_sum', 'actual_value')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('financeiro.planos.index', compact('plans'));
    }

    public function create()
    {
        return view('financeiro.planos.create', ['empresas' => $this->empresas()]);
    }

    public function store(StorePlanRequest $request)
    {
        $plan = FinancialPlan::create($request->validated());

        return redirect()->route('plans.edit', $plan)->with('success', 'Plano criado. Adicione os lançamentos.');
    }

    public function edit(FinancialPlan $plan)
    {
        $plan->load(['entries' => fn ($q) => $q->orderBy('id'), 'entries.card:id,title', 'empresa']);

        $cards = Card::query()
            ->when($plan->empresa_id, fn ($q, $v) => $q->where('empresa_id', $v))
            ->orderByDesc('id')->limit(200)->get(['id', 'title']);

        return view('financeiro.planos.edit', [
            'plan' => $plan,
            'empresas' => $this->empresas(),
            'cards' => $cards,
            'totals' => [
                'estimated' => (float) $plan->entries->sum('estimated_value'),
                'actual' => (float) $plan->entries->sum('actual_value'),
            ],
        ]);
    }

    public function update(UpdatePlanRequest $request, FinancialPlan $plan)
    {
        $plan->update($request->validated());

        return redirect()->route('plans.edit', $plan)->with('success', 'Plano atualizado.');
    }

    public function destroy(FinancialPlan $plan)
    {
        $plan->delete();

        return redirect()->route('plans.index')->with('success', 'Plano excluído.');
    }

    /** Pré-visualização da importação de CSV. */
    public function importPreview(Request $request, FinancialPlan $plan)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $rows = $this->parseCsv($request->file('file')->getRealPath());

        if (empty($rows)) {
            return back()->with('error', 'Não foi possível ler linhas do arquivo.');
        }

        return view('financeiro.planos.import-preview', compact('plan', 'rows'));
    }

    /** Confirma e grava os lançamentos importados. */
    public function import(Request $request, FinancialPlan $plan)
    {
        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.description' => ['nullable', 'string', 'max:180'],
            'rows.*.category' => ['nullable', 'string', 'max:80'],
            'rows.*.estimated_value' => ['nullable', 'string'],
            'rows.*.actual_value' => ['nullable', 'string'],
            'rows.*.estimated_date' => ['nullable', 'date'],
            'rows.*.actual_date' => ['nullable', 'date'],
        ]);

        $count = DB::transaction(function () use ($plan, $data) {
            $n = 0;
            foreach ($data['rows'] as $row) {
                if (empty($row['description'])) {
                    continue;
                }
                $plan->entries()->create([
                    'empresa_id' => $plan->empresa_id,
                    'description' => $row['description'],
                    'category' => $row['category'] ?? null,
                    'estimated_value' => Br::money($row['estimated_value'] ?? null) ?? 0,
                    'actual_value' => Br::money($row['actual_value'] ?? null) ?? 0,
                    'estimated_date' => $row['estimated_date'] ?? null,
                    'actual_date' => $row['actual_date'] ?? null,
                ]);
                $n++;
            }

            return $n;
        });

        return redirect()->route('plans.edit', $plan)->with('success', "{$count} lançamento(s) importado(s).");
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) === false) {
            return $rows;
        }

        // Detecta delimitador pela primeira linha (BR costuma usar ;).
        $first = fgets($handle);
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        rewind($handle);

        $lineNo = 0;
        while (($cols = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNo++;
            if ($lineNo === 1) {
                continue; // cabeçalho
            }
            if (count(array_filter($cols, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $rows[] = [
                'description' => trim($cols[0] ?? ''),
                'category' => trim($cols[1] ?? ''),
                'estimated_value' => trim($cols[2] ?? ''),
                'actual_value' => trim($cols[3] ?? ''),
                'estimated_date' => $this->normalizeDate($cols[4] ?? ''),
                'actual_date' => $this->normalizeDate($cols[5] ?? ''),
            ];
        }
        fclose($handle);

        return $rows;
    }

    private function normalizeDate(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        // Aceita dd/mm/aaaa ou aaaa-mm-dd.
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return $value;
    }

    private function empresas()
    {
        return Empresa::active()->orderBy('corporate_name')->get(['id', 'corporate_name']);
    }
}
