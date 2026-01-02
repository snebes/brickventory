import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import app from './vue/app.js';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

// Mount Vue app when DOM is ready
if (document.getElementById('app')) {
    app.mount('#app');
}
