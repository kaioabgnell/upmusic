/**
 * Módulo de Licitações (ver specs/21) — componentes Alpine das telas de acervo e de edital.
 *
 * Regra que atravessa este arquivo: a IA sugere, o usuário confirma. Nenhuma sugestão é salva
 * sozinha, e falha de IA nunca bloqueia o cadastro manual.
 */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** Campos que a leitura assistida pode preencher no formulário de documento. */
const AI_FIELDS = [
    'name',
    'bid_document_category_id',
    'bid_document_type_id',
    'issuer',
    'issued_at',
    'expires_at',
    'no_expiry',
    'control_code',
];

/** Formulário vazio do documento — usado na abertura e no reset do modal. */
const blankDocumentForm = () => ({
    name: '',
    bid_document_category_id: '',
    bid_document_type_id: '',
    issuer: '',
    issued_at: '',
    expires_at: '',
    no_expiry: false,
    control_code: '',
    notes: '',
});

/**
 * Modal de documento do acervo: criar, renovar (nova versão) e editar metadados.
 * `config` vem do Blade: { storeUrl, readUrl, companyId, types: [...] }
 */
export function bidDocument(config = {}) {
    return {
        open: false,
        mode: 'create', // create | renew | edit
        action: config.storeUrl,
        method: 'POST',
        requiresFile: true,
        reading: false,
        fileName: '',
        suggested: {},
        warnings: [],
        confidence: null,
        extracted: null,
        documentId: null,
        form: blankDocumentForm(),

        blank() {
            return blankDocumentForm();
        },

        reset() {
            this.form = this.blank();
            this.suggested = {};
            this.warnings = [];
            this.confidence = null;
            this.extracted = null;
            this.fileName = '';
            this.reading = false;
            this.documentId = null;
        },

        openCreate() {
            this.reset();
            this.mode = 'create';
            this.method = 'POST';
            this.action = config.storeUrl;
            this.requiresFile = true;
            this.open = true;
        },

        /** Renovar: arquivo novo obrigatório, metadados do documento atual como ponto de partida. */
        openRenew(doc) {
            this.reset();
            this.mode = 'renew';
            this.method = 'POST';
            this.action = doc.renewUrl;
            this.requiresFile = true;
            this.documentId = doc.id;
            this.form = {
                ...this.blank(),
                name: doc.name ?? '',
                bid_document_category_id: doc.category_id ?? '',
                bid_document_type_id: doc.type_id ?? '',
                issuer: doc.issuer ?? '',
                no_expiry: !!doc.no_expiry,
            };
            this.open = true;
        },

        /** Editar só os metadados — trocar arquivo é renovação, para não reescrever o histórico. */
        openEdit(doc) {
            this.reset();
            this.mode = 'edit';
            this.method = 'PUT';
            this.action = doc.updateUrl;
            this.requiresFile = false;
            this.documentId = doc.id;
            this.form = {
                name: doc.name ?? '',
                bid_document_category_id: doc.category_id ?? '',
                bid_document_type_id: doc.type_id ?? '',
                issuer: doc.issuer ?? '',
                issued_at: doc.issued_at ?? '',
                expires_at: doc.expires_at ?? '',
                no_expiry: !!doc.no_expiry,
                control_code: doc.control_code ?? '',
                notes: doc.notes ?? '',
            };
            this.open = true;
        },

        get title() {
            return { create: 'Novo documento', renew: 'Renovar documento', edit: 'Editar documento' }[this.mode];
        },

        get selectedType() {
            return (config.types ?? []).find((t) => String(t.id) === String(this.form.bid_document_type_id));
        },

        /** Tipos com código de autenticação exigem o campo — o catálogo é quem manda. */
        get controlCodeRequired() {
            return !!this.selectedType?.requires_control_code;
        },

        /** O tipo canônico define a categoria: evita documento classificado fora do lugar. */
        typeChanged() {
            this.touched('bid_document_type_id');
            if (this.selectedType) {
                this.form.bid_document_category_id = this.selectedType.category_id;
            }
        },

        /** Editar um campo derruba o selo "sugerido pela IA" daquele campo. */
        touched(field) {
            if (this.suggested[field]) {
                delete this.suggested[field];
            }
        },

        async onFileChange(event) {
            const file = event.target.files?.[0];
            if (!file) return;

            this.fileName = file.name;

            // Na edição não há arquivo; na criação/renovação, tenta a leitura assistida.
            if (this.mode === 'edit' || !config.readUrl) return;

            this.reading = true;

            try {
                const body = new FormData();
                body.append('arquivo', file);
                if (config.companyId) body.append('bid_company_id', config.companyId);

                const response = await fetch(config.readUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                    body,
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok || !payload.ok) {
                    // Degradação graciosa: o formulário segue utilizável manualmente (§9.4).
                    window.upAlerts?.notifyInfo(
                        payload.message ?? 'Não foi possível ler o documento automaticamente. Preencha os campos manualmente.',
                    );
                    return;
                }

                this.apply(payload.suggestion);
            } catch (error) {
                window.upAlerts?.notifyInfo('Leitura automática indisponível. Preencha os campos manualmente.');
            } finally {
                this.reading = false;
            }
        },

        apply(suggestion) {
            if (!suggestion) return;

            this.extracted = JSON.stringify(suggestion);
            this.confidence = suggestion.confidence ?? null;
            this.warnings = suggestion.warnings ?? [];

            AI_FIELDS.forEach((field) => {
                const value = suggestion[field];
                if (value === null || value === undefined || value === '') return;

                this.form[field] = value;
                this.suggested[field] = true;
            });

            if (suggestion.no_expiry) {
                this.form.expires_at = '';
            }
        },
    };
}

/**
 * Envio do edital (tela "Nova análise"). A análise roda na própria requisição — sem fila e sem
 * worker (§5.2) —, então a espera é real e a mensagem diz isso em vez de simular etapas.
 */
export function bidNotice() {
    return {
        mode: 'file', // file | text
        fileName: '',
        submitting: false,

        onFileChange(event) {
            this.fileName = event.target.files?.[0]?.name ?? '';
        },

        async submit(event) {
            event.preventDefault();
            if (this.submitting) return;

            const form = event.target;
            this.submitting = true;

            window.upAlerts?.showLoading(
                'Lendo o edital com a IA e cruzando com o acervo. Isso pode levar até 2 minutos — mantenha esta aba aberta.',
            );

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                    body: new FormData(form),
                });

                const payload = await response.json().catch(() => ({}));
                window.upAlerts?.closeLoading();

                if (response.ok && payload.ok) {
                    window.location = payload.redirect;
                    return;
                }

                // 422 de validação do Laravel traz `errors`; falha de IA traz `message` + `redirect`.
                const message = payload.message
                    ?? Object.values(payload.errors ?? {})[0]?.[0]
                    ?? 'Não foi possível concluir a análise.';

                await window.upAlerts?.notifyError(message);

                if (payload.redirect) {
                    window.location = payload.redirect;
                }
            } catch (error) {
                window.upAlerts?.closeLoading();
                await window.upAlerts?.notifyError('Falha de comunicação ao enviar o edital. Tente novamente.');
            } finally {
                this.submitting = false;
            }
        },

        /** Reprocessar/recuperar análise interrompida — mesmo endpoint JSON. */
        async reprocess(url) {
            if (this.submitting) return;
            this.submitting = true;

            window.upAlerts?.showLoading('Reprocessando o edital com a IA...');

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                });
                const payload = await response.json().catch(() => ({}));
                window.upAlerts?.closeLoading();

                if (response.ok && payload.ok) {
                    window.location.reload();
                    return;
                }

                await window.upAlerts?.notifyError(payload.message ?? 'Não foi possível reprocessar o edital.');
            } catch (error) {
                window.upAlerts?.closeLoading();
                await window.upAlerts?.notifyError('Falha de comunicação ao reprocessar.');
            } finally {
                this.submitting = false;
            }
        },
    };
}

/**
 * Matriz de conformidade: filtros das linhas e painel lateral do requisito (§9.6).
 *
 * Os dados chegam prontos do Blade (requisitos, empresas, conferências e acervo), então clicar em
 * uma célula não dispara requisição nenhuma — só abre o painel com o que já está na página.
 */
export function bidMatrix(config = {}) {
    return {
        filter: 'all', // all | mandatory | issues
        panelRequirementId: null,
        panelCompanyId: null,
        editingRequirement: false,

        requirements: config.requirements ?? {},
        companies: config.companies ?? {},
        matrix: config.matrix ?? {},
        documents: config.documents ?? {},

        rowVisible(row) {
            if (this.filter === 'mandatory') return row.mandatory;
            if (this.filter === 'issues') return row.hasIssue;
            return true;
        },

        openCell(requirementId, companyId) {
            this.panelRequirementId = requirementId;
            this.panelCompanyId = companyId;
            this.editingRequirement = false;
        },

        close() {
            this.panelRequirementId = null;
            this.panelCompanyId = null;
            this.editingRequirement = false;
        },

        get open() {
            return this.panelRequirementId !== null;
        },

        get requirement() {
            return this.requirements[this.panelRequirementId] ?? null;
        },

        get company() {
            return this.companies[this.panelCompanyId] ?? null;
        },

        get match() {
            return this.matrix[this.panelRequirementId]?.[this.panelCompanyId] ?? null;
        },

        /** Acervo vigente da empresa do painel — alimenta o select de vínculo manual. */
        get companyDocuments() {
            return this.documents[this.panelCompanyId] ?? [];
        },
    };
}
