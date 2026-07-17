import './bootstrap';
import './alerts';

import Alpine from 'alpinejs';
import mask from '@alpinejs/mask';
import Sortable from 'sortablejs';
import kanban from './kanban';
import cardsHub from './cards-hub';

window.Sortable = Sortable;

Alpine.plugin(mask);
Alpine.data('kanban', kanban);
Alpine.data('cardsHub', cardsHub);
window.Alpine = Alpine;

Alpine.start();
