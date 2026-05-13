<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

// --- Типы ---
interface Category {
    id: number;
    name: string;
}

interface Product {
    id: number;
    name: string;
    description: string;
    price: number;
    category_id: number;
    category?: Category;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface ProductQueryParams {
    page?: number;
    per_page?: number;
    category_id?: number;
    search?: string;
    sort?: 'name' | 'price' | 'created_at';
    order?: 'asc' | 'desc';
}

// --- Состояние ---
const products = ref<Product[]>([]);
const categories = ref<Category[]>([]);
const loading = ref(false);
const pagination = ref<PaginationMeta | null>(null);

// Фильтрация
const selectedCategoryId = ref<number | ''>('');

// Редактирование
const editingProductId = ref<number | null>(null);

// Форма (все поля из модели + валидация)
const form = ref({
    name: '',
    description: '',
    price: 0,
    category_id: 1,
});

// --- Методы ---

// Загрузка категорий
const fetchCategories = async () => {
    try {
        const response = await axios.get('/api/categories');
        categories.value = response.data;
        // Если категории есть, ставим первую по умолчанию для новой формы
        if (categories.value.length > 0 && !form.value.category_id) {
            form.value.category_id = categories.value[0].id;
        }
    } catch (error) {
        console.error('Ошибка загрузки категорий:', error);
    }
};

// Получение товаров с фильтрацией и пагинацией
const fetchProducts = async (page: number = 1) => {
    loading.value = true;
    try {
        const params: ProductQueryParams = { page };
        
        if (selectedCategoryId.value) {
            params.category_id = selectedCategoryId.value;
        }
        
        const response = await axios.get('/api/products', { params });
        
        products.value = response.data.data;
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            per_page: response.data.per_page,
            total: response.data.total,
            next_page_url: response.data.next_page_url,
            prev_page_url: response.data.prev_page_url,
        };
    } catch (error) {
        console.error('Ошибка GET:', error);
    } finally {
        loading.value = false;
    }
};

// Сброс фильтра
const resetFilter = () => {
    selectedCategoryId.value = '';
    fetchProducts(1);
};

// Создание или обновление
const saveProduct = async () => {
    if (!form.value.name.trim()) return alert('Поле `Название товара` не заполнено');
    if (!form.value.category_id) return alert('Выберите категорию');
    if (!form.value.description) return alert('Поле `Описание` не заполнено');
    if (!form.value.price) return alert('Поле `Цена` не заполнено');

    try {
        if (editingProductId.value) {
            await axios.put(`/api/products/${editingProductId.value}`, form.value);
            alert('Товар обновлен!');
        } else {
            await axios.post('/api/products', form.value);
            alert('Товар создан!');
        }

        resetForm();
        await fetchProducts(pagination.value?.current_page || 1);

    } catch (error: any) {
        console.error('Ошибка сохранения:', error);
        if (error.response?.data?.errors) {
            const errors = Object.values(error.response.data.errors).flat().join('\n');
            alert('Ошибка валидации:\n' + errors);
        } else {
            alert('Ошибка сервера: ' + (error.response?.data?.message || 'Неизвестная ошибка'));
        }
    }
};

// Подготовка к редактированию
const editProduct = (product: Product) => {
    editingProductId.value = product.id;
    form.value.name = product.name;
    form.value.description = product.description || '';
    form.value.price = product.price;
    form.value.category_id = product.category_id;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// Отмена редактирования
const cancelEdit = () => resetForm();

// Сброс формы
const resetForm = () => {
    editingProductId.value = null;
    form.value.name = '';
    form.value.description = '';
    form.value.price = 999;
    form.value.category_id = categories.value[0]?.id || 1;
};

// Удаление
const deleteProduct = async (id: number) => {
    if (!confirm('Удалить этот товар?')) return;
    try {
        await axios.delete(`/api/products/${id}`);
        await fetchProducts(pagination.value?.current_page || 1);
    } catch (error) {
        console.error('Ошибка DELETE:', error);
    }
};

// Пагинация
const nextPage = () => pagination.value?.next_page_url && fetchProducts(pagination.value.current_page + 1);
const prevPage = () => pagination.value?.prev_page_url && fetchProducts(pagination.value.current_page - 1);

onMounted(async () => {
    await fetchCategories();
    await fetchProducts(1);
});
</script>

<template>
    <Head title="Управление товарами" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 overflow-auto">
        
        <!-- Форма создания / редактирования -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-xl font-bold text-gray-800">
                {{ editingProductId ? `Редактирование товара #${editingProductId}` : 'Создание нового товара' }}
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Название -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                    <input 
                        v-model="form.name" 
                        type="text"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-indigo-500 text-black focus:ring-1 focus:ring-indigo-500"
                        placeholder="Например: Беспроводные наушники" 
                    />
                </div>

                <!-- Категория -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Категория *</label>
                    <select 
                        v-model="form.category_id"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-black bg-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                </div>

                <!-- Цена -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Цена ($) *</label>
                    <input 
                        v-model.number="form.price" 
                        type="number" 
                        step="0.01"
                        min="0"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-black" 
                    />
                </div>

                <!-- Описание (полная ширина) -->
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Описание *</label>
                    <textarea 
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-black focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 resize-vertical"
                        placeholder="Подробное описание товара..."
                    ></textarea>
                </div>
            </div>

            <!-- Кнопки действий -->
            <div class="mt-4 flex gap-2">
                <button 
                    @click="saveProduct" 
                    class="px-6 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium transition shadow-sm"
                >
                    {{ editingProductId ? '💾 Сохранить изменения' : '✨ Создать товар' }}
                </button>
                
                <button 
                    v-if="editingProductId" 
                    @click="cancelEdit" 
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 font-medium transition"
                >
                    ✕ Отмена
                </button>
            </div>
        </div>

        <!-- Фильтр -->
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Фильтр по категории</label>
                <select 
                    v-model="selectedCategoryId" 
                    @change="fetchProducts(1)" 
                    class="rounded-md border border-gray-300 px-3 py-2 text-black bg-white focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 min-w-[200px]"
                >
                    <option :value="''">📂 Все категории</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                        {{ cat.name }}
                    </option>
                </select>
            </div>
            
            <button 
                @click="resetFilter" 
                :disabled="!selectedCategoryId"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
                🔄 Сбросить
            </button>
            
            <div class="text-sm text-gray-500 ml-auto">
                Показано: <span class="font-medium text-gray-900">{{ products.length }}</span> / 
                Всего: <span class="font-medium text-gray-900">{{ pagination?.total || 0 }}</span>
            </div>
        </div>

        <!-- Таблица -->
        <div class="rounded-xl border border-gray-200 bg-white  shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Название</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider ">Описание</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Категория</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Цена</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr 
                            v-for="product in products" 
                            :key="product.id" 
                            :class="{ 'bg-indigo-50 border-l-4 border-indigo-500': editingProductId === product.id }"
                        >
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ product.id }}</td>
                            
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ product.name }}</div>
                            </td>
                            
                            <td class="px-6 py-4">
                                <div 
                                    class="text-sm text-black max-w-xs " 
                                    :title="product.description || 'Нет описания'"
                                >
                                    {{ product.description || '—' }}
                                </div>
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 rounded-full bg-gray-100 text-xs font-medium text-gray-700">
                                    {{ product.category?.name || '—' }}
                                </span>
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                ${{ Number(product.price).toFixed(2) }}
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <button 
                                    @click="editProduct(product)" 
                                    class="text-indigo-600 hover:text-indigo-900 font-medium transition"
                                    title="Редактировать"
                                >
                                    ✏️
                                </button>
                                <button 
                                    @click="deleteProduct(product.id)" 
                                    class="text-red-600 hover:text-red-900 font-medium transition"
                                    title="Удалить"
                                >
                                    🗑️
                                </button>
                            </td>
                        </tr>
                        
                        <tr v-if="products.length === 0 && !loading">
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                <div class="text-lg mb-2">😕</div>
                                {{ selectedCategoryId ? 'По выбранной категории товаров нет' : 'Товары не найдены' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div v-if="loading" class="p-6 text-center text-gray-500">
                <div class="animate-spin inline-block w-6 h-6 border-2 border-indigo-600 border-t-transparent rounded-full"></div>
                <span class="ml-2">Загрузка...</span>
            </div>
        </div>

        <!-- Пагинация -->
        <div v-if="pagination && pagination.last_page > 1" class="flex justify-between items-center px-4 py-3 bg-white border rounded-xl shadow-sm">
            <span class="text-sm text-gray-700">
                Страница <span class="font-bold text-indigo-600">{{ pagination.current_page }}</span> 
                из <span class="font-medium">{{ pagination.last_page }}</span>
            </span>
            <div class="flex gap-2">
                <button 
                    @click="prevPage" 
                    :disabled="!pagination.prev_page_url" 
                    class="text-black px-4 py-2 border rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition"
                >
                    ← Назад
                </button>
                <button 
                    @click="nextPage" 
                    :disabled="!pagination.next_page_url" 
                    class="text-black px-4 py-2 border rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 transition"
                >
                    Вперед →
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Обрезка длинного описания с троеточием */
.max-w-xs {
    max-width: 12rem;
}

/* Разрешаем изменять размер textarea только по вертикали */
.resize-vertical {
    resize: vertical;
}
</style>