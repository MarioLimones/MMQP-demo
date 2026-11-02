import './bootstrap';
import './arqueo-editor';
import './arqueo-template-transfer';
import './arqueo-daily';
import './consumos-editor';
import './consumos-history-viewer';
import './consumos-history-calendar';
import './consumos-navigation';
import { dayHeaderDidMount, viewDidMount } from './calendar-accessibility';

import Alpine from 'alpinejs';

window.Alpine = Alpine;
window.mmqpCalendarAccessibility = { dayHeaderDidMount, viewDidMount };

Alpine.start();
