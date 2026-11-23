import axios from 'axios';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { registrarStoreModales, trampaDeFoco } from './stores/comun';
import { registrarStoreAvistamientos } from './stores/avistamientos';
import { registrarStoreExpediciones } from './stores/expediciones';
import { registrarStoreReclutamiento } from './stores/reclutamiento';

window.axios = axios;
window.Alpine = Alpine;
window.Livewire = Livewire;
window.trampaDeFoco = trampaDeFoco;

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Stores Alpine singleton compartidos por el layout, las páginas y los modales.
// Deben registrarse antes de Livewire.start(), que es quien arranca Alpine.
registrarStoreModales(Alpine);
registrarStoreExpediciones(Alpine);
registrarStoreReclutamiento(Alpine);
registrarStoreAvistamientos(Alpine);

// Livewire.start() inicia Livewire y su Alpine bundled (una sola instancia),
// registra el magic $wire y deja Alpine disponible globalmente (window.Alpine)
// para las directivas x-* del layout y de las vistas (pokedexApp, habitatShow, etc.).
Livewire.start();
