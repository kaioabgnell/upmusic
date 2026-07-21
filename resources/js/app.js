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

Chart.register(LineController, LineElement, PointElement, LinearScale, TimeScale, Legend, Tooltip, Filler);

window.Sortable = Sortable;
window.Chart = Chart;

Alpine.plugin(mask);
Alpine.data('kanban', kanban);
Alpine.data('cardsHub', cardsHub);
window.Alpine = Alpine;

Alpine.start();
