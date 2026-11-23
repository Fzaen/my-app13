import "../css/app.css";
// import './bootstrap';

import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, h } from "vue";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";

const savedTheme = window.localStorage.getItem("theme");

document.documentElement.classList.toggle("dark", savedTheme !== "light");

createInertiaApp({
    title: (title) => title || "SPI",
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue"),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        delay: 0,
        color: "#22d3ee",
        showSpinner: false,
    },
});
