<?php
/**
 * Класс для расчета динамических цен
 * 
 * Содержит основную логику расчета цен на основе порогов тиража
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WC_Dynamic_Price_Calculator {

    /**
     * Инициализация
     */
    public function init() {
        // Применяем динамическую цену к вариациям
        add_filter('woocommerce_product_variation_get_price', array($this, 'calculate_dynamic_price'), 99, 2);
        add_filter('woocommerce_product_variation_get_regular_price', array($this, 'calculate_dynamic_price'), 99, 2);
    }
    
    /**
     * Проверяет, имеет ли товар цены по тиражам
     * 
     * @param int $variation_id ID вариации товара
     * @return bool True, если у товара есть хотя бы одна настройка цены по тиражу
     */
    public function has_tiered_pricing($variation_id) {
        // Получаем все метаданные вариации
        $all_meta = get_post_meta($variation_id);
        
        // Флаг наличия цен по тиражам
        $has_tirazh_prices = false;
        
        // Перебираем метаданные и ищем поля с порогами тиража
        foreach ($all_meta as $meta_key => $meta_value) {
            // Проверяем, является ли это полем тиража
            if (strpos($meta_key, '_price_tirazh_') === 0) {
                $price = (float) $meta_value[0]; // meta_value хранится как массив
                
                // Если цена больше 0, значит цена по тиражу задана
                if ($price > 0) {
                    $has_tirazh_prices = true;
                    break;
                }
            }
        }
        
        return $has_tirazh_prices;
    }

    /**
     * Расчет динамической цены для товара на основе порогов тиража
     * 
     * @param float $price Исходная цена
     * @param object $product Объект товара
     * @return float Рассчитанная цена
     */
    public function calculate_dynamic_price($price, $product) {
        // Добавляем логи для отслеживания входных данных
        $product_id = $product ? $product->get_id() : 'unknown';
        $product_type = $product ? $product->get_type() : 'unknown';
        error_log("[DEBUG] calculate_dynamic_price вызван для товара #{$product_id}, тип: {$product_type}, исходная цена: {$price}");
        
        // Проверяем, находимся ли мы на странице товара или в корзине/оформлении заказа
        if (!is_product() && !is_cart() && !is_checkout()) {
            error_log("[DEBUG] calculate_dynamic_price: не на странице товара/корзины/оформления заказа, выход");
            return $price;
        }
        
        // Проверяем, является ли товар вариацией
        if (!$product->is_type('variation')) {
            error_log("[DEBUG] calculate_dynamic_price: товар #{$product_id} не является вариацией, выход");
            return $price;
        }
        
        // Получаем ID вариации для проверки наличия цен по тиражам
        $variation_id = $product->get_id();
        
        error_log("[DEBUG] calculate_dynamic_price: проверка цен по тиражам для вариации #{$variation_id}");
        
        // Проверяем, имеет ли товар цены по тиражам
        if (!$this->has_tiered_pricing($variation_id)) {
            error_log("[DEBUG] calculate_dynamic_price: у товара #{$variation_id} нет цен по тиражам, выход");
            return $price; // Если у товара нет цен по тиражам, возвращаем исходную цену
        }
        
        error_log("[DEBUG] calculate_dynamic_price: у товара #{$variation_id} есть цены по тиражам");
        
        // Получаем минимальное количество из атрибутов товара (если оно используется)
        $minimum_quantity = $product->get_meta('_minimum_quantity', true);
        
        // Преобразуем минимальное количество в целое число
        $minimum_quantity = empty($minimum_quantity) ? 1 : absint($minimum_quantity);
        
        error_log("[DEBUG] calculate_dynamic_price: минимальное количество для товара #{$variation_id}: {$minimum_quantity}");
        
        // Текущее количество товара (по умолчанию 1 для страницы товара)
        $quantity = 1;
        
        // Получаем количество из корзины, если доступно
        if (is_cart() || is_checkout()) {
            $cart = WC()->cart;
            if ($cart) {
                foreach ($cart->get_cart() as $cart_item) {
                    if ($cart_item['product_id'] == $product->get_id() || 
                        ($product->is_type('variation') && $cart_item['variation_id'] == $product->get_id())) {
                        $quantity = $cart_item['quantity'];
                        break;
                    }
                }
            }
        }
        
        // Применяем динамическое ценообразование только если количество удовлетворяет минимальному
        if ($quantity < $minimum_quantity) {
            error_log("[DEBUG] calculate_dynamic_price: текущее количество {$quantity} меньше минимального {$minimum_quantity}, выход");
            return $price; // Возвращаем исходную цену, если минимальное количество не достигнуто
        }
        
        error_log("[DEBUG] calculate_dynamic_price: текущее количество {$quantity} >= минимального {$minimum_quantity}, продолжаем");
        
        // Получаем ID вариации для доступа к метаданным
        $variation_id = $product->get_id();
        
        // Найдем подходящий порог тиража и соответствующую цену
        $tiered_price = $this->get_tiered_price_for_quantity($variation_id, $quantity);
        
        // Если нашли подходящую цену для тиража, возвращаем её
        if ($tiered_price !== false) {
            error_log("[DEBUG] calculate_dynamic_price: найдена цена по тиражу {$tiered_price} для товара #{$variation_id}, исходная цена: {$price}");
            
            // Рассчитываем процент скидки для отладки
            if ($price > 0) {
                $discount_percent = round((($price - $tiered_price) / $price) * 100);
                error_log("[DEBUG] calculate_dynamic_price: процент скидки для товара #{$variation_id}: {$discount_percent}%");
            }
            
            return $tiered_price;
        }
        
        error_log("[DEBUG] calculate_dynamic_price: не найдена подходящая цена по тиражу для товара #{$variation_id}, возвращаем стандартную цену {$price}");
        
        // Если подходящий порог не найден, возвращаем стандартную цену
        return $price;
    }

    /**
     * Получает цену товара на основе порога тиража для указанного количества
     * 
     * @param int $variation_id ID вариации товара
     * @param int $quantity Количество товара
     * @return float|bool Цена за единицу или false, если не найдено подходящих порогов
     */
    public function get_tiered_price_for_quantity($variation_id, $quantity) {
        // Получаем все метаданные вариации
        $all_meta = get_post_meta($variation_id);
        
        // Массивы для хранения порогов и цен
        $thresholds = array();
        $prices = array();
        
        // Перебираем метаданные и ищем поля с порогами тиража
        foreach ($all_meta as $meta_key => $meta_value) {
            // Проверяем, является ли это полем тиража
            if (strpos($meta_key, '_price_tirazh_') === 0) {
                // Извлекаем число порога из имени поля
                $threshold = (int) str_replace('_price_tirazh_', '', $meta_key);
                $price = (float) $meta_value[0]; // meta_value хранится как массив
                
                // Пропускаем нулевые цены - они означают, что такой тираж недоступен
                if ($price <= 0) {
                    error_log("Пропущен порог тиража {$threshold} с нулевой ценой");
                    continue;
                }
                
                $thresholds[] = $threshold;
                $prices[] = $price;
                
                // Отладочная информация
                error_log("Найден порог тиража: {$threshold}, цена: {$price}");
            }
        }
        
        // Если нет порогов, вернем false
        if (empty($thresholds)) {
            error_log("Не найдено порогов тиража для вариации ID: {$variation_id}");
            return false;
        }
        
        // Сортируем пороги и цены по возрастанию порогов
        array_multisort($thresholds, SORT_ASC, $prices);
        
        // Находим подходящий ценовой порог
        $applicable_price = false;
        
        // Перебираем отсортированные пороги
        for ($i = 0; $i < count($thresholds); $i++) {
            // Если количество меньше или равно порогу, это наш ценовой порог
            if ($quantity <= $thresholds[$i]) {
                $applicable_price = $prices[$i];
                error_log("Применяется цена {$applicable_price} для количества {$quantity} (порог {$thresholds[$i]})");
                break;
            }
        }
        
        // Если количество больше всех порогов, берем цену для максимального порога
        if ($applicable_price === false && !empty($prices)) {
            $applicable_price = end($prices);
            error_log("Количество {$quantity} превышает все пороги, применяется цена последнего порога: {$applicable_price}");
        }
        
        return $applicable_price;
    }
}
