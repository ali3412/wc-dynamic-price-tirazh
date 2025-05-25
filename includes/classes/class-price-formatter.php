<?php
/**
 * Класс форматирования цен
 * 
 * Отвечает за корректное отображение цен на странице корзины и оформления заказа
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WC_Dynamic_Price_Price_Formatter {

    /**
     * Инициализация
     */
    public function init() {
        // Фильтры для корректного отображения цен в корзине
        add_filter('woocommerce_cart_item_price', array($this, 'filter_cart_item_price'), 99, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'filter_cart_item_subtotal'), 99, 3);
        
        // Фильтры для отображения скидки на странице товара
        add_filter('woocommerce_get_price_html', array($this, 'display_discount_on_product_page'), 99, 2);
        
        // Выводим логи при загрузке класса
        error_log('[DEBUG] WC_Dynamic_Price_Price_Formatter инициализирован, хуки добавлены');
    }
    
    /**
     * Отображает скидку на странице товара
     * 
     * @param string $price_html Оригинальный HTML цены
     * @param object $product Объект товара
     * @return string Отформатированный HTML цены
     */
    public function display_discount_on_product_page($price_html, $product) {
        // Добавляем логи для отслеживания вызовов
        error_log("[DEBUG] display_discount_on_product_page вызван для товара #{$product->get_id()}, тип: {$product->get_type()}");
        
        // Проверяем, является ли это вариацией и находимся ли мы на странице товара
        if (!$product->is_type('variation') || !is_product()) {
            error_log("[DEBUG] display_discount_on_product_page: не вариация или не страница товара, возвращаем стандартный формат");
            return $price_html;
        }

        // Создаем экземпляр калькулятора цен
        $price_calculator = new WC_Dynamic_Price_Calculator();
        
        // Получаем ID вариации
        $variation_id = $product->get_id();
        
        // Проверяем, имеет ли товар цены по тиражам
        if (!$price_calculator->has_tiered_pricing($variation_id)) {
            error_log("[DEBUG] display_discount_on_product_page: у товара #{$variation_id} нет цен по тиражам, возвращаем стандартный формат");
            return $price_html;
        }
        
        error_log("[DEBUG] display_discount_on_product_page: у товара #{$variation_id} есть цены по тиражам");
        
        // Получаем базовую цену
        $standard_price = floatval($product->get_regular_price('edit'));
        if (empty($standard_price)) {
            $standard_price = floatval($product->get_price('edit'));
        }
        
        // Получаем минимальное количество
        $minimum_quantity = $product->get_meta('_minimum_quantity', true);
        $minimum_quantity = empty($minimum_quantity) ? 1 : absint($minimum_quantity);
        
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
                
                // Пропускаем нулевые цены
                if ($price <= 0) {
                    continue;
                }
                
                $thresholds[] = $threshold;
                $prices[] = $price;
                
                error_log("[DEBUG] display_discount_on_product_page: найден порог тиража {$threshold} с ценой {$price}");
            }
        }
        
        // Если нет порогов, вернем стандартный формат
        if (empty($thresholds)) {
            error_log("[DEBUG] display_discount_on_product_page: не найдено порогов тиража для вариации #{$variation_id}");
            return $price_html;
        }
        
        // Сортируем пороги и цены по возрастанию порогов
        array_multisort($thresholds, SORT_ASC, $prices);
        
        // Получаем первый порог и его цену
        $first_threshold = $thresholds[0];
        $first_price = $prices[0];
        
        error_log("[DEBUG] display_discount_on_product_page: первый порог {$first_threshold}, цена {$first_price}, базовая цена {$standard_price}");
        
        // Рассчитываем процент скидки на основе первого порога
        if ($standard_price > 0 && $first_price < $standard_price) {
            $discount_percent = round((($standard_price - $first_price) / $standard_price) * 100);
            
            error_log("[DEBUG] display_discount_on_product_page: рассчитана скидка {$discount_percent}% для порога {$first_threshold}");
            
            // Формируем новый HTML с информацией о скидке
            if ($discount_percent > 0) {
                // Добавляем информацию о скидке
                $price_html .= ' <span class="tirazh-discount">(скидка ' . $discount_percent . '% при тираже от ' . $first_threshold . ' шт.)</span>';
                
                error_log("[DEBUG] display_discount_on_product_page: добавлена информация о скидке в HTML");
            } else {
                error_log("[DEBUG] display_discount_on_product_page: нет скидки для отображения, процент скидки не положительный");
            }
        } else {
            error_log("[DEBUG] display_discount_on_product_page: цена по тиражу не меньше стандартной, скидка не отображается");
        }
        
        return $price_html;
    }

    /**
     * Корректное отображение цены за единицу товара в корзине
     * 
     * @param string $price_html Форматированная цена
     * @param array $cart_item Элемент корзины
     * @param string $cart_item_key Ключ элемента корзины
     * @return string Отформатированная цена
     */
    public function filter_cart_item_price($price_html, $cart_item, $cart_item_key) {
        // Создаем экземпляр калькулятора цен для проверки наличия цен по тиражам
        $price_calculator = new WC_Dynamic_Price_Calculator();
        
        // Проверяем, есть ли данные о динамической цене
        if (isset($cart_item['dynamic_price']) && isset($cart_item['dynamic_price']['calculated_price'])) {
            $product = $cart_item['data'];
            
            // Получаем ID вариации
            $variation_id = $product->get_id();
            
            // Проверяем, имеет ли товар цены по тиражам
            if (!$price_calculator->has_tiered_pricing($variation_id)) {
                return $price_html; // Сохраняем исходное форматирование для товаров без цен по тиражам
            }
            
            $calculated_price = $cart_item['dynamic_price']['calculated_price'];
            
            // Форматируем цену
            $price_html = wc_price($calculated_price);
            
            // Добавляем информацию о регулярной цене, если она отличается
            $minimum_price = $cart_item['dynamic_price']['minimum_price'];
            if ($calculated_price < $minimum_price) {
                $price_html .= ' <small class="dynamic-price-discount">(базовая цена: ' . wc_price($minimum_price) . ')</small>';
            }
        } else {
            // Проверяем, является ли продукт вариацией
            $product = $cart_item['data'];
            if (!$product->is_type('variation')) {
                return $price_html;
            }
            
            // Получаем ID вариации
            $variation_id = $product->get_id();
            
            // Проверяем, имеет ли товар цены по тиражам
            if (!$price_calculator->has_tiered_pricing($variation_id)) {
                return $price_html; // Сохраняем исходное форматирование для товаров без цен по тиражам
            }
            
            // Получаем базовую цену
            $minimum_price = floatval($product->get_regular_price('edit'));
            if (empty($minimum_price)) {
                $minimum_price = floatval($product->get_price('edit'));
            }
            
            $quantity = $cart_item['quantity'];
            
            // Получаем цену на основе порога тиража
            $tiered_price = $price_calculator->get_tiered_price_for_quantity($variation_id, $quantity);
            
            // Если не нашли цену по тиражу, сохраняем исходное форматирование
            if ($tiered_price === false) {
                return $price_html;
            }
            
            // Форматируем цену
            $price_html = wc_price($tiered_price);
            
            // Добавляем информацию о базовой цене, если она отличается
            if ($tiered_price < $minimum_price) {
                $price_html .= ' <small class="dynamic-price-discount">(базовая цена: ' . wc_price($minimum_price) . ')</small>';
            }
        }
        
        return $price_html;
    }
    
    /**
     * Корректное отображение подытога для элемента корзины
     * 
     * @param string $subtotal Форматированный подытог
     * @param array $cart_item Элемент корзины
     * @param string $cart_item_key Ключ элемента корзины
     * @return string Отформатированный подытог
     */
    public function filter_cart_item_subtotal($subtotal, $cart_item, $cart_item_key) {
        // Создаем экземпляр калькулятора цен
        $price_calculator = new WC_Dynamic_Price_Calculator();
        
        // Проверяем, является ли товар вариацией
        $product = $cart_item['data'];
        if (!$product->is_type('variation')) {
            return $subtotal;
        }
        
        // Получаем ID вариации
        $variation_id = $product->get_id();
        
        // Проверяем, имеет ли товар цены по тиражам
        if (!$price_calculator->has_tiered_pricing($variation_id)) {
            return $subtotal; // Сохраняем исходное форматирование для товаров без цен по тиражам
        }
        
        // Проверяем, есть ли данные о динамической цене
        if (isset($cart_item['dynamic_price']) && isset($cart_item['dynamic_price']['total_price'])) {
            // Получаем рассчитанную общую сумму с округлением до целых рублей
            $total_price = round($cart_item['dynamic_price']['total_price']);
            $subtotal = wc_price($total_price);
            
            // Добавляем дополнительную информацию о цене за единицу
            if (isset($cart_item['dynamic_price']['calculated_price'])) {
                $unit_price = $cart_item['dynamic_price']['calculated_price'];
                $quantity = $cart_item['quantity'];
                
                // Добавляем информативную строку о расчете итоговой суммы
                $subtotal .= '<div class="dynamic-price-details"><small>' . sprintf(
                    '%s x %d = %s',
                    wc_price($unit_price),
                    $quantity,
                    $subtotal
                ) . '</small></div>';
            }
        } else {
            // Получаем цену на основе порога тиража
            $tiered_price = $price_calculator->get_tiered_price_for_quantity($variation_id, $cart_item['quantity']);
            
            // Если не нашли цену по тиражу, сохраняем исходное форматирование
            if ($tiered_price === false) {
                return $subtotal;
            }
            
            // Получаем данные для расчета
            $minimum_price = floatval($product->get_regular_price('edit'));
            if (empty($minimum_price)) {
                $minimum_price = floatval($product->get_price('edit'));
            }
            
            $quantity = $cart_item['quantity'];
            
            // Рассчитываем и округляем общую сумму
            $discounted_total = round($tiered_price * $quantity);
            
            // Рассчитываем общую сумму без скидки
            $original_unit_price = $minimum_price;
            $original_total = $original_unit_price * $quantity;
            
            // Рассчитываем сумму скидки
            $discount_amount = $original_total - $discounted_total;
            
            // Форматируем итоговую сумму со скидкой
            $subtotal = '<div class="dynamic-cart-price-details">';
            
            // Если есть скидка, показываем оригинальную цену и скидку
            if ($discount_amount > 0) {
                $subtotal .= '<span class="original-total">' . wc_price($original_total) . '</span>';
                $subtotal .= '<br><span class="discount-amount">-' . wc_price($discount_amount) . ' скидка</span>';
                $subtotal .= '<br><strong class="final-price">' . wc_price($discounted_total) . '</strong>';
            } else {
                // Если скидки нет, просто показываем итоговую сумму
                $subtotal = wc_price($discounted_total);
            }
            
            $subtotal .= '</div>';
        }
        
        return $subtotal;
    }
}
