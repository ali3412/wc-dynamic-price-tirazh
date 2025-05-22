<?php
/**
 * Класс управления минимальным количеством товара
 * 
 * Отвечает за установку и проверку минимального количества товара 
 * для вариаций с динамическим ценообразованием
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WC_Dynamic_Price_Quantity_Manager {

    /**
     * Инициализация
     */
    public function init() {
        // Установка минимального количества на странице товара
        add_filter('woocommerce_quantity_input_min', array($this, 'set_minimum_quantity'), 10, 2);
        add_filter('woocommerce_quantity_input_args', array($this, 'customize_quantity_input'), 10, 2);
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_minimum_quantity'), 10, 5);
        
        // Установка значения количества по умолчанию на странице товара - удалено, т.к. нужные параметры заданы в атрибутах поля input
        // add_filter('woocommerce_quantity_input_args', array($this, 'set_default_quantity'), 10, 2);
        
        // Обработка страницы корзины
        add_filter('woocommerce_cart_item_quantity', array($this, 'customize_cart_item_quantity'), 10, 3);
        add_filter('woocommerce_update_cart_validation', array($this, 'validate_cart_update'), 10, 4);
        
        // Добавляем скрипт для корзины
        add_action('wp_enqueue_scripts', array($this, 'enqueue_cart_scripts'));
        
        // Добавляем скрипт для передачи данных о минимальных количествах в блочную корзину
        add_action('wp_enqueue_scripts', array($this, 'enqueue_block_cart_support'));
    }

    /**
     * Установка минимального количества для товаров с динамическим ценообразованием
     *
     * @param int $min Минимальное количество
     * @param object $product Объект товара WC_Product
     * @return int Минимальное количество
     */
    public function set_minimum_quantity($min, $product) {
        if (!$product || !$product->is_type('variation')) {
            return $min;
        }
        
        // Получаем минимальное количество из произвольного поля
        $minimum_quantity = $product->get_meta('_minimum_quantity', true);
        
        if (empty($minimum_quantity)) {
            return $min;
        }
        
        return max(absint($minimum_quantity), 1);
    }
    
    /**
     * Настройка аргументов ввода количества для обеспечения минимального количества
     *
     * @param array $args Аргументы ввода количества
     * @param object $product Объект товара WC_Product
     * @return array Измененные аргументы
     */
    public function customize_quantity_input($args, $product) {
        if (!$product || !$product->is_type('variation')) {
            return $args;
        }
        
        // Получаем минимальное количество из произвольного поля
        $minimum_quantity = $product->get_meta('_minimum_quantity', true);
        
        if (!empty($minimum_quantity)) {
            $min_value = max(absint($minimum_quantity), 1);
            $args['min_value'] = $min_value;
            
            // Устанавливаем шаг равным минимальному количеству для обеспечения кратности
            $args['step'] = $min_value;
            
            // На странице товара устанавливаем значение по умолчанию равным минимальному
            if (!is_cart() && !is_checkout() && $args['input_value'] < $min_value) {
                $args['input_value'] = $min_value;
            }
            
            // Не меняем значение в корзине, чтобы отображалось реальное количество товара
        }
        
        return $args;
    }
    
    // Функция set_default_quantity удалена, т.к. нужные параметры заданы в атрибутах поля input
    
    /**
     * Проверка минимального количества при добавлении в корзину
     *
     * @param bool $passed Статус проверки
     * @param int $product_id ID товара
     * @param int $quantity Количество добавляемого товара
     * @param int $variation_id ID вариации, если применимо
     * @param array $variations Данные вариации товара
     * @return bool Статус проверки
     */
    public function validate_minimum_quantity($passed, $product_id, $quantity, $variation_id = 0, $variations = array()) {
        if (!$variation_id) {
            return $passed;
        }
        
        // Получаем объект вариации
        $product = wc_get_product($variation_id);
        
        if (!$product || !$product->is_type('variation')) {
            return $passed;
        }
        
        // Получаем минимальное количество из произвольного поля
        $minimum_quantity = $product->get_meta('_minimum_quantity', true);
        
        if (!empty($minimum_quantity)) {
            $min_value = max(absint($minimum_quantity), 1);
            $product_title = get_the_title($product_id);
            
            // Проверка на минимальное количество
            if ($quantity < $min_value) {
                wc_add_notice(
                    sprintf(__('Минимальное количество для "%s" - %s.', 'wc-dynamic-price'), $product_title, $min_value),
                    'error'
                );
                return false;
            }
            
            // Проверка на кратность минимальному количеству
            if ($quantity % $min_value !== 0) {
                // Рассчитываем ближайшее кратное значение
                $corrected_quantity = ceil($quantity / $min_value) * $min_value;
                
                wc_add_notice(
                    sprintf(__('Количество товара "%s" должно быть кратно %s. Рекомендуемое количество: %s.', 'wc-dynamic-price'), 
                        $product_title, $min_value, $corrected_quantity),
                    'error'
                );
                return false;
            }
        }
        
        return $passed;
    }
    
    /**
     * Модифицируем поле ввода количества в корзине
     *
     * @param string $product_quantity HTML код поля ввода количества
     * @param string $cart_item_key Ключ элемента корзины
     * @param array $cart_item Данные элемента корзины
     * @return string Модифицированный HTML код
     */
    public function customize_cart_item_quantity($product_quantity, $cart_item_key, $cart_item) {
        if (!isset($cart_item['data']) || !$cart_item['data']->is_type('variation')) {
            return $product_quantity;
        }
        
        $product = $cart_item['data'];
        $minimum_quantity = $product->get_meta('_minimum_quantity', true);
        
        if (empty($minimum_quantity)) {
            return $product_quantity;
        }
        
        $min_value = max(absint($minimum_quantity), 1);
        
        // Получаем текущее количество
        $quantity = $cart_item['quantity'];
        
        // Добавляем атрибуты step, min и data для поля ввода в классической корзине
        $product_quantity = str_replace('<input type="number"', '<input type="number" step="' . esc_attr($min_value) . '" min="' . esc_attr($min_value) . '" data-min-qty="' . esc_attr($min_value) . '" data-cart-item-key="' . esc_attr($cart_item_key) . '" data-product-id="' . esc_attr($product->get_id()) . '"', $product_quantity);
        
        return $product_quantity;
    }
    
    /**
     * Проверка обновления корзины на кратность минимальному количеству
     *
     * @param bool $passed Результат проверки
     * @param string $cart_item_key Ключ элемента корзины
     * @param array $cart_item_data Данные элемента корзины
     * @param int $quantity Новое количество
     * @return bool Результат проверки
     */
    public function validate_cart_update($passed, $cart_item_key, $cart_item_data, $quantity) {
        // Получаем элемент корзины
        $cart_item = WC()->cart->get_cart_item($cart_item_key);
        
        if (!$cart_item || !isset($cart_item['data']) || !$cart_item['data']->is_type('variation')) {
            return $passed;
        }
        
        $product = $cart_item['data'];
        $variation_id = $product->get_id();
        $product_id = $product->get_parent_id();
        
        // Получаем минимальное количество из произвольного поля
        $minimum_quantity = $product->get_meta('_minimum_quantity', true);
        
        if (!empty($minimum_quantity)) {
            $min_value = max(absint($minimum_quantity), 1);
            $product_title = get_the_title($product_id);
            
            // Проверка на минимальное количество
            if ($quantity < $min_value) {
                wc_add_notice(
                    sprintf(__('Минимальное количество для "%s" - %s.', 'wc-dynamic-price'), $product_title, $min_value),
                    'error'
                );
                return false;
            }
            
            // Проверка на кратность минимальному количеству
            if ($quantity % $min_value !== 0) {
                // Рассчитываем ближайшее кратное значение
                $corrected_quantity = ceil($quantity / $min_value) * $min_value;
                
                wc_add_notice(
                    sprintf(__('Количество товара "%s" должно быть кратно %s. Рекомендуемое количество: %s.', 'wc-dynamic-price'), 
                        $product_title, $min_value, $corrected_quantity),
                    'error'
                );
                return false;
            }
        }
        
        return $passed;
    }
    
    /**
     * Добавление JavaScript для обработки количества в корзине
     */
    public function enqueue_cart_scripts() {
        // Проверяем, что мы находимся на странице корзины
        if (!is_cart()) {
            return;
        }
        
        wp_register_script(
            'wc-dynamic-price-cart', 
            WC_DYNAMIC_PRICE_PLUGIN_URL . 'assets/js/cart-quantity.js',
            array('jquery'), 
            WC_DYNAMIC_PRICE_VERSION . '.' . time(), 
            true
        );
        
        wp_enqueue_script('wc-dynamic-price-cart');
    }
    
    /**
     * Добавляем скрипт для поддержки блочного интерфейса корзины
     */
    public function enqueue_block_cart_support() {
        // Выполняем только на странице корзины
        if (!is_cart()) {
            return;
        }
        
        // Получаем данные о минимальных количествах для всех товаров в корзине
        $cart_items_min_qty = array();
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (!isset($cart_item['data']) || !$cart_item['data']->is_type('variation')) {
                continue;
            }
            
            $product = $cart_item['data'];
            $minimum_quantity = $product->get_meta('_minimum_quantity', true);
            
            if (!empty($minimum_quantity)) {
                $min_value = max(absint($minimum_quantity), 1);
                
                $cart_items_min_qty[$cart_item_key] = array(
                    'product_id' => $product->get_id(),
                    'min_qty' => $min_value,
                    'cart_item_key' => $cart_item_key
                );
            }
        }
        
        // Передаем данные в JavaScript
        wp_localize_script('wc-dynamic-price-cart-quantity', 'wc_dynamic_price_cart_data', array(
            'cart_items' => $cart_items_min_qty,
            'is_block_cart' => wp_is_block_theme() || has_block('woocommerce/cart')
        ));
    }
}