import axios from 'axios';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.axios = axios;
window.Alpine = Alpine;
window.Livewire = Livewire;

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Livewire.start() inicia Livewire y su Alpine bundled (una sola instancia),
// registra el magic $wire y deja Alpine disponible globalmente (window.Alpine)
// para las directivas x-* del layout y de las vistas (pokedexApp, habitatShow, etc.).
Livewire.start();
