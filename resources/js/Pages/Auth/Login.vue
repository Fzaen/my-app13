<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import BrandLogo from "@/Components/BrandLogo.vue";
import InputError from "@/Components/InputError.vue";

const props = defineProps({
    canResetPassword: {
        type: Boolean,
    },
    databaseSites: {
        type: Array,
        default: () => [],
    },
    selectedDatabase: {
        type: String,
        default: "",
    },
    status: {
        type: String,
    },
});

const form = useForm({
    database: props.selectedDatabase || props.databaseSites[0]?.value || "",
    userid: "",
    password: "",
});

const showPassword = ref(false);

const submit = () => {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
    });
};
</script>

<template>
    <main
        class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#edf0e9] p-4 sm:p-8"
    >
        <Head title="Masuk" />

        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 opacity-40"
            style="
                background-image:
                    linear-gradient(#cbd2c7 1px, transparent 1px),
                    linear-gradient(90deg, #cbd2c7 1px, transparent 1px);
                background-size: 38px 38px;
                mask-image: linear-gradient(to bottom, black, transparent 88%);
            "
        ></div>

        <section
            class="relative grid w-full max-w-6xl overflow-hidden rounded-[5px] bg-white shadow-[0_28px_80px_-38px_rgba(22,42,35,0.42)] md:min-h-165 md:grid-cols-[0.92fr_1.08fr]"
        >
            <aside
                class="relative hidden flex-col justify-between overflow-hidden bg-[#183d34] p-10 text-white md:flex lg:p-14"
            >
                <div class="absolute inset-0 opacity-20" aria-hidden="true">
                    <div
                        class="absolute inset-0"
                        style="
                            background-image: linear-gradient(
                                135deg,
                                transparent 49.8%,
                                #b6c9a8 50%,
                                transparent 50.2%
                            );
                            background-size: 46px 46px;
                        "
                    ></div>
                </div>

                <div class="relative flex items-center gap-3">
                    <BrandLogo class="h-10 w-10" />
                    <span
                        class="max-w-55 truncate text-sm font-semibold text-white/90"
                    >
                        {{ $page.props.appName || "Workspace" }}
                    </span>
                </div>

                <div class="relative py-12">
                    <div class="mb-6 h-px w-14 bg-[#e4b85d]"></div>
                    <p
                        class="mb-4 text-xs font-semibold uppercase text-[#e4b85d]"
                    >
                        Workspace access
                    </p>
                    <h1
                        class="max-w-sm font-serif text-4xl leading-[1.08] text-[#f4f1e8] lg:text-5xl"
                    >
                        Good to see you again.
                    </h1>
                    <p class="mt-5 max-w-xs text-sm leading-6 text-white/65">
                        Sign in to continue to your workspace.
                    </p>
                </div>

                <div
                    class="relative flex items-end justify-between border-t border-white/15 pt-5 text-[10px] font-medium uppercase text-white/50"
                >
                    <span>Secure sign in</span>
                    <span>01 / 01</span>
                </div>
                <div
                    aria-hidden="true"
                    class="absolute -bottom-20 -right-10 select-none font-serif text-[280px] leading-none text-white/[0.035]"
                >
                    W
                </div>
            </aside>

            <div
                class="flex items-center justify-center px-6 py-10 sm:px-12 md:px-14 lg:px-20"
            >
                <div class="w-full max-w-md">
                    <div class="mb-10 flex items-center gap-3 md:hidden">
                        <BrandLogo class="h-9 w-9" />
                        <span
                            class="max-w-55 truncate text-sm font-semibold text-[#183d34]"
                        >
                            {{ $page.props.appName || "Workspace" }}
                        </span>
                    </div>

                    <div class="mb-8">
                        <p
                            class="mb-3 text-xs font-semibold uppercase text-[#a76a3c] md:hidden"
                        >
                            Workspace access
                        </p>
                        <h2 class="font-serif text-4xl text-[#1b3029]">
                            Sign in
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-[#69736b]">
                            Enter your username and password to continue.
                        </p>
                    </div>

                    <div
                        v-if="status"
                        class="mb-6 border-l-2 border-[#62866d] bg-[#eff4ee] px-4 py-3 text-sm text-[#315840]"
                    >
                        {{ status }}
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div class="space-y-2">
                            <label
                                for="userid"
                                class="block text-sm font-medium text-[#293b33]"
                            >
                                Username
                            </label>
                            <input
                                id="userid"
                                v-model="form.userid"
                                type="text"
                                autocomplete="username"
                                autofocus
                                required
                                :aria-invalid="Boolean(form.errors.userid)"
                                class="block min-h-12 w-full rounded-[3px] border border-[#d4dbd2] bg-[#fbfcfa] px-4 text-sm text-[#1b3029] outline-none transition focus:border-[#416c57] focus:ring-2 focus:ring-[#416c57]/15"
                                placeholder="Enter your username"
                            />
                            <InputError :message="form.errors.userid" />
                        </div>

                        <div class="space-y-2">
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <label
                                    for="password"
                                    class="block text-sm font-medium text-[#293b33]"
                                >
                                    Password
                                </label>
                                <Link
                                    v-if="props.canResetPassword"
                                    :href="route('password.request')"
                                    class="text-xs font-medium text-[#416c57] underline decoration-[#b7c7b9] underline-offset-4 transition hover:text-[#244b39]"
                                >
                                    Forgot password?
                                </Link>
                            </div>
                            <div class="relative">
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    :aria-invalid="
                                        Boolean(form.errors.password)
                                    "
                                    class="block min-h-12 w-full rounded-[3px] border border-[#d4dbd2] bg-[#fbfcfa] px-4 pr-12 text-sm text-[#1b3029] outline-none transition focus:border-[#416c57] focus:ring-2 focus:ring-[#416c57]/15"
                                    placeholder="Enter your password"
                                />
                                <button
                                    type="button"
                                    :aria-label="
                                        showPassword
                                            ? 'Hide password'
                                            : 'Show password'
                                    "
                                    :aria-pressed="showPassword"
                                    class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-[#69736b] transition hover:text-[#183d34] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#416c57]"
                                    @click="showPassword = !showPassword"
                                >
                                    <svg
                                        v-if="!showPassword"
                                        class="h-5 w-5"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                    </svg>
                                    <svg
                                        v-else
                                        class="h-5 w-5"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"
                                        />
                                    </svg>
                                </button>
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="space-y-2">
                            <label
                                for="database"
                                class="block text-sm font-medium text-[#293b33]"
                            >
                                Database / Server
                            </label>
                            <select
                                id="database"
                                v-model="form.database"
                                required
                                :aria-invalid="Boolean(form.errors.database)"
                                class="block min-h-12 w-full rounded-[3px] border border-[#d4dbd2] bg-[#fbfcfa] px-4 text-sm text-[#1b3029] outline-none transition focus:border-[#416c57] focus:ring-2 focus:ring-[#416c57]/15"
                            >
                                <option
                                    v-for="site in props.databaseSites"
                                    :key="site.value"
                                    :value="site.value"
                                >
                                    {{ site.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.database" />
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex min-h-12 w-full items-center justify-center gap-2 rounded-[3px] bg-[#244b3b] px-4 text-sm font-semibold text-white transition hover:bg-[#183d34] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#416c57] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <svg
                                v-if="form.processing"
                                class="h-4 w-4 animate-spin"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                />
                            </svg>
                            {{ form.processing ? "Signing in..." : "Sign in" }}
                        </button>
                    </form>

                    <p
                        class="mt-8 border-t border-[#e4e8e1] pt-5 text-xs text-[#7b857c]"
                    >
                        Secure access to
                        {{ $page.props.appName || "your workspace" }}.
                    </p>
                </div>
            </div>
        </section>
    </main>
</template>
