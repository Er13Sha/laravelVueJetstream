<script setup>
import { reactive, ref, watch, computed, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    initialFilters: { type: Object, required: true },
    meta: { type: Object, required: true },
});

const bounds = props.meta.area_bounds;
const defaultPerPage = props.meta.default_per_page;

// --- Состояние фильтра (инициализируется из URL через серверные пропсы) ---
const filters = reactive({
    title: props.initialFilters.title ?? '',
    has_photo: props.initialFilters.has_photo ?? false,
    rooms: props.initialFilters.rooms ?? [],
    area: [
        props.initialFilters.area_min ?? bounds.min,
        props.initialFilters.area_max ?? bounds.max,
    ],
    sort: props.initialFilters.sort ?? 'relevance',
    direction: props.initialFilters.direction ?? 'desc',
    per_page: props.initialFilters.per_page ?? defaultPerPage,
});

const items = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: filters.per_page });
const page = ref(1);
const loading = ref(false);
let requestId = 0;

const directionSortable = computed(() => ['area', 'rooms', 'floor'].includes(filters.sort));

const hasActiveFilters = computed(() =>
    filters.title !== '' ||
    filters.has_photo ||
    filters.rooms.length > 0 ||
    filters.area[0] > bounds.min ||
    filters.area[1] < bounds.max,
);

const user = computed(() => usePage().props.auth?.user);

function activeParams() {
    const p = {};
    if (filters.title.trim() !== '') p.title = filters.title.trim();
    if (filters.has_photo) p.has_photo = 1;
    if (filters.rooms.length) p.rooms = [...filters.rooms].sort((a, b) => a - b);
    if (filters.area[0] > bounds.min) p.area_min = filters.area[0];
    if (filters.area[1] < bounds.max) p.area_max = filters.area[1];
    if (filters.sort !== 'relevance') p.sort = filters.sort;
    if (directionSortable.value && filters.direction !== 'desc') p.direction = filters.direction;
    if (filters.per_page !== defaultPerPage) p.per_page = filters.per_page;
    if (page.value > 1) p.page = page.value;
    return p;
}

function toQueryString(params) {
    const usp = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
        if (Array.isArray(value)) {
            value.forEach((v) => usp.append(`${key}[]`, v));
        } else {
            usp.append(key, value);
        }
    }
    return usp.toString();
}

// --- Сохранение состояния фильтра в URL (восстанавливается после перезагрузки) ---
function syncUrl(query) {
    window.history.replaceState({}, '', query ? `?${query}` : window.location.pathname);
}

// --- Загрузка результатов (нативный fetch — без внешних зависимостей) ---
async function reload() {
    const id = ++requestId;
    loading.value = true;
    const query = toQueryString(activeParams());

    try {
        const response = await fetch(`/properties/search?${query}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();
        if (id !== requestId) return; // пришёл устаревший ответ — игнорируем

        items.value = data.data;
        pagination.value = {
            current_page: data.meta.current_page,
            last_page: data.meta.last_page,
            total: data.meta.total,
            per_page: data.meta.per_page,
        };
        syncUrl(query);
    } catch {
        if (id === requestId) items.value = [];
    } finally {
        if (id === requestId) loading.value = false;
    }
}

// --- Debounce для live-фильтрации ---
function debounce(fn, delay) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}
const debouncedReload = debounce(reload, 350);

// Любое изменение фильтра → сброс на 1-ю страницу и перезагрузка (с задержкой).
watch(filters, () => {
    page.value = 1;
    debouncedReload();
}, { deep: true });

function goToPage(p) {
    page.value = p;
    reload();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetFilters() {
    filters.title = '';
    filters.has_photo = false;
    filters.rooms = [];
    filters.area = [bounds.min, bounds.max];
    filters.sort = 'relevance';
    filters.direction = 'desc';
    filters.per_page = defaultPerPage;
}

function formatNumber(value) {
    return Number(value).toLocaleString('ru-RU');
}

onMounted(reload);
</script>

<template>
    <Head title="Поиск недвижимости" />

    <div class="min-h-screen bg-gray-50 text-gray-900">
        <!-- Шапка -->
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex h-14 max-w-[1900px] items-center justify-between px-4 sm:px-6">
                <span class="font-semibold">Недвижимость</span>
                <Link v-if="user" href="/dashboard" class="text-sm text-gray-500 hover:text-gray-900">
                    Кабинет
                </Link>
                <Link v-else href="/login" class="text-sm text-gray-500 hover:text-gray-900">
                    Войти
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-[1900px] px-4 py-6 sm:px-6">
            <div class="flex flex-col gap-6 lg:flex-row">
                <!-- Фильтры -->
                <aside class="lg:w-72 lg:flex-shrink-0">
                    <div class="space-y-5 rounded-lg border border-gray-200 bg-white p-4 lg:sticky lg:top-6">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold">Фильтр</h2>
                            <el-button v-if="hasActiveFilters" link size="small" @click="resetFilters">
                                Сбросить
                            </el-button>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm text-gray-600">Название</label>
                            <el-input v-model="filters.title" placeholder="Поиск по названию" clearable />
                        </div>

                        <el-checkbox v-model="filters.has_photo">Только с фото</el-checkbox>

                        <div>
                            <label class="mb-1.5 block text-sm text-gray-600">Комнат</label>
                            <el-checkbox-group v-model="filters.rooms" class="!flex flex-wrap gap-2">
                                <el-checkbox-button
                                    v-for="opt in meta.room_options"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </el-checkbox-button>
                            </el-checkbox-group>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm text-gray-600">
                                Площадь: {{ filters.area[0] }}–{{ filters.area[1] }} м²
                            </label>
                            <el-slider
                                v-model="filters.area"
                                range
                                :min="bounds.min"
                                :max="bounds.max"
                                :step="1"
                            />
                        </div>
                    </div>
                </aside>

                <!-- Результаты -->
                <section class="min-w-0 flex-1">
                    <!-- Панель управления -->
                    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <p class="text-sm text-gray-500">
                            Найдено:
                            <span class="font-medium text-gray-900">{{ formatNumber(pagination.total) }}</span>
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <el-select v-model="filters.sort" class="!w-44">
                                <el-option
                                    v-for="o in meta.sort_options"
                                    :key="o.value"
                                    :value="o.value"
                                    :label="o.label"
                                />
                            </el-select>
                            <el-button
                                v-if="directionSortable"
                                @click="filters.direction = filters.direction === 'asc' ? 'desc' : 'asc'"
                            >
                                {{ filters.direction === 'asc' ? '↑' : '↓' }}
                            </el-button>
                        </div>
                    </div>

                    <!-- Сетка карточек -->
                    <div v-loading="loading" class="min-h-[300px]">
                        <div
                            v-if="items.length"
                            class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-5 xl:grid-cols-5"
                        >
                            <article
                                v-for="item in items"
                                :key="item.id"
                                class="overflow-hidden rounded-lg border border-gray-200 bg-white"
                            >
                                <div class="aspect-[4/3] bg-gray-100">
                                    <img
                                        v-if="item.preview_url"
                                        :src="item.preview_url"
                                        :alt="item.title"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full items-center justify-center text-xs text-gray-400"
                                    >
                                        Без фото
                                    </div>
                                </div>
                                <div class="p-3">
                                    <h3 class="line-clamp-1 text-sm font-medium">{{ item.title }}</h3>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ item.rooms_label }} · {{ item.area }} м² · {{ item.floor }} этаж
                                    </p>
                                    <p class="mt-1 line-clamp-2 text-xs text-gray-400">{{ item.description }}</p>
                                </div>
                            </article>
                        </div>

                        <el-empty v-else-if="!loading" description="Ничего не найдено" />
                    </div>

                    <!-- Пагинация -->
                    <div v-if="pagination.total > pagination.per_page" class="mt-6 flex justify-center">
                        <el-pagination
                            background
                            layout="prev, pager, next"
                            :total="pagination.total"
                            :page-size="pagination.per_page"
                            :current-page="pagination.current_page"
                            :pager-count="5"
                            @current-change="goToPage"
                        />
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>
