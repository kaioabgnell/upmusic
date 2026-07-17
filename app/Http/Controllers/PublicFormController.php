<?php

namespace App\Http\Controllers;

use App\Actions\External\ProcessExternalSubmission;
use App\Models\ExternalForm;
use App\Rules\Cnpj;
use Illuminate\Http\Request;

class PublicFormController extends Controller
{
    public function show(string $token)
    {
        $form = ExternalForm::with('event')->where('token', $token)->where('active', true)->first();

        if (! $form) {
            return response()->view('external.unavailable', [], 404);
        }

        return view('external.form', ['form' => $form]);
    }

    public function submit(Request $request, string $token, ProcessExternalSubmission $action)
    {
        $form = ExternalForm::where('token', $token)->where('active', true)->first();

        if (! $form) {
            return response()->view('external.unavailable', [], 404);
        }

        // Honeypot anti-bot: se preenchido, finge sucesso sem processar.
        if ($request->filled('website')) {
            return redirect()->route('external.form.success', $token);
        }

        $data = $request->validate([
            'cnpj' => ['required', 'string', new Cnpj],
            'name' => ['required', 'string', 'max:180'],
            'value' => ['required', 'string'],
            'service_date' => ['required', 'date'],
            'service_description' => ['required', 'string'],
            'payment_data' => ['required', 'string'],
            'invoice' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ], [], [
            'cnpj' => 'CNPJ', 'name' => 'nome', 'value' => 'valor',
            'service_date' => 'data', 'service_description' => 'descrição do serviço',
            'payment_data' => 'dados para pagamento', 'invoice' => 'nota fiscal',
        ]);

        $action->execute($form, $data, $request->file('invoice'), $request->ip());

        return redirect()->route('external.form.success', $token);
    }

    public function success(string $token)
    {
        $form = ExternalForm::where('token', $token)->firstOrFail();

        return view('external.success', ['form' => $form]);
    }
}
