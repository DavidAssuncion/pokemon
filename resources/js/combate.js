// Combate: carga bootstrap.js PRIMERO (Livewire ESM + Livewire.start() registra $wire)
// y después Bootstrap JS. Importar './bootstrap' antes que bootstrap.bundle.min.js
// garantiza que el magic $wire siga disponible en el x-init del combate.
import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
