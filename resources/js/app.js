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
import { bidDocument, bidMatrix, bidNotice } from './licitacoes';

Chart.register(LineController, LineElement, PointElement, LinearScale, TimeScale, Legend, Tooltip, Filler);

window.Sortable = Sortable;
window.Chart = Chart;

Alpine.plugin(mask);
Alpine.data('kanban', kanban);
Alpine.data('cardsHub', cardsHub);
// Módulo de Licitações (specs/21).
Alpine.data('bidDocument', bidDocument);
Alpine.data('bidNotice', bidNotice);
Alpine.data('bidMatrix', bidMatrix);
window.Alpine = Alpine;

Alpine.start();
