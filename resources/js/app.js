import './bootstrap';
import './alerts';
import './pwa';

import Alpine from 'alpinejs';
import mask from '@alpinejs/mask';
import Sortable from 'sortablejs';
import {
    Chart, LineController, LineElement, PointElement, LinearScale, TimeScale, Legend, Tooltip, Filler,
    // Pizza e barras: usados no Relatório do evento (specs/23). O Chart.js v4 não registra nada
    // sozinho — sem estes, o gráfico falha com "pie is not a registered controller".
    PieController, DoughnutController, ArcElement, BarController, BarElement, CategoryScale,
} from 'chart.js';
import 'chartjs-adapter-date-fns';
import kanban from './kanban';
import cardsHub from './cards-hub';
import notifications from './notifications';
import { bidDocument, bidMatrix, bidNotice } from './licitacoes';
import { financeCosts, financeRevenues, financeSettlements } from './finance';
import { rentalsBoard, rentalsItems } from './rentals';
import { maskMoneyDigits } from './money';

Chart.register(
    LineController, LineElement, PointElement, LinearScale, TimeScale, Legend, Tooltip, Filler,
    PieController, DoughnutController, ArcElement, BarController, BarElement, CategoryScale,
);

window.Sortable = Sortable;
window.Chart = Chart;
// Páginas públicas (formulário externo/Solicitação de Nota) não têm o x-data dos componentes do
// Financeiro/card-panel — ficam fora desse escopo e precisam da máscara direto no window.
window.maskMoneyDigits = maskMoneyDigits;

Alpine.plugin(mask);
Alpine.data('kanban', kanban);
Alpine.data('cardsHub', cardsHub);
// Sino de notificações da topbar (specs/22).
Alpine.data('notifications', notifications);
// Módulo de Licitações (specs/21).
Alpine.data('bidDocument', bidDocument);
Alpine.data('bidNotice', bidNotice);
Alpine.data('bidMatrix', bidMatrix);
// Financeiro do Evento (specs/23).
Alpine.data('financeCosts', financeCosts);
Alpine.data('financeRevenues', financeRevenues);
Alpine.data('financeSettlements', financeSettlements);
// Gestão de Locações (specs/24).
Alpine.data('rentalsBoard', rentalsBoard);
Alpine.data('rentalsItems', rentalsItems);
window.Alpine = Alpine;

Alpine.start();
