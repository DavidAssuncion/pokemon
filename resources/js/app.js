import './bootstrap';
import Chart from 'chart.js/auto';

window.Chart = Chart;

// Bootstrap JS solo se necesita en la página de combate (modales, dropdowns).
// Se carga de forma diferida para no engordar el resto de páginas.
if (document.querySelector('.battle-container')) {
    import('bootstrap/dist/js/bootstrap.bundle.min.js');
    window.battleAnimationsReady = import('./battle-animations/runtime.js').catch((error) => {
        console.warn('[battle-animations] No se pudo cargar el adaptador', error);
    });
}
