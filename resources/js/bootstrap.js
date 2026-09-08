import axios from 'axios';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Register Alpine plugins before Alpine.start() runs (app.js).
Alpine.plugin(collapse);
