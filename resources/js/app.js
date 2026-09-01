import './bootstrap';
import './alerts';
import './pwa';

import Alpine from 'alpinejs';
import mask from '@alpinejs/mask';
import Sortable from 'sortablejs';
import { Chart, LineController, LineElement, PointElement, LinearScale, TimeScale, Legend, Tooltip, Filler } from 'chart.js';
import 'chartjs-adapter-date-fns';
import kanban from './kanban';
import cardsHub from './cards-hub';
import notifications from './notifications';
import { bidDocument, bidMatrix, bidNotice } from './licitacoes';
import { financeCosts, financeRevenues, financeSettlements } from './finance';

Chart.register(LineController, LineElement, PointElement, LinearScale, TimeScale, Legend, Tooltip, Filler);

window.Sortable = Sortable;
window.Chart = Chart;

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
window.Alpine = Alpine;

Alpine.start();
