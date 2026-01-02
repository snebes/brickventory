import { createApp } from 'vue';
import router from './router.js';

const app = createApp({
    data() {
        return {};
    },
    computed: {
        currentRoute() {
            return this.$route.path;
        }
    }
});

app.use(router);

export default app;
