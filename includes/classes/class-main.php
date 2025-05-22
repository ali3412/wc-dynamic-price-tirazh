<?php
/**
 * Основной класс плагина Dynamic Price
 * 
 * Отвечает за загрузку всех компонентов плагина
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WC_Dynamic_Price_Main {

    /**
     * Экземпляр класса для реализации паттерна Singleton
     */
    private static $instance = null;
    
    /**
     * Получение единственного экземпляра класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Конструктор
     */
    private function __construct() {
        // Загружаем компоненты
        $this->load_components();
        
        // Инициализируем обработчики
        $this->init();
    }
    
    /**
     * Загружаем все компоненты плагина
     */
    private function load_components() {
        // Подключаем класс для работы с ценами
        require_once WC_DYNAMIC_PRICE_PLUGIN_DIR . 'includes/classes/class-price-calculator.php';
        
        // Подключаем класс для работы с корзиной
        require_once WC_DYNAMIC_PRICE_PLUGIN_DIR . 'includes/classes/class-cart-handler.php';
        
        // Подключаем класс для работы с минимальным количеством
        require_once WC_DYNAMIC_PRICE_PLUGIN_DIR . 'includes/classes/class-quantity-manager.php';
        
        // Класс для скрытия технических атрибутов больше не используется, так как все данные хранятся в произвольных полях
        
        // Подключаем класс для отображения цен
        require_once WC_DYNAMIC_PRICE_PLUGIN_DIR . 'includes/classes/class-price-formatter.php';
        
        // Подключаем класс для работы с AJAX
        require_once WC_DYNAMIC_PRICE_PLUGIN_DIR . 'includes/classes/class-ajax-handler.php';
        
        // Подключаем класс для работы с полями вариаций товаров
        require_once WC_DYNAMIC_PRICE_PLUGIN_DIR . 'includes/classes/class-variations-fields.php';
    }

    /**
     * Инициализация хуков плагина
     */
    public function init() {
        // Создаем экземпляры классов
        $price_calculator = new WC_Dynamic_Price_Calculator();
        $cart_handler = new WC_Dynamic_Price_Cart_Handler();
        $quantity_manager = new WC_Dynamic_Price_Quantity_Manager();
        // Класс атрибутов больше не используется
        $price_formatter = new WC_Dynamic_Price_Price_Formatter();
        $ajax_handler = new WC_Dynamic_Price_AJAX_Handler();
        $variations_fields = new WC_Dynamic_Price_Variations_Fields();
        
        // Инициализируем каждый компонент
        $price_calculator->init();
        $cart_handler->init();
        $quantity_manager->init();
        // Инициализация класса атрибутов удалена
        $price_formatter->init();
        $ajax_handler->init();
        $variations_fields->init();
        
        // Добавляем JavaScript и CSS
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Подключение скриптов и стилей
     */
    public function enqueue_scripts() {
        // Регистрируем и подключаем CSS-файл со стилями
        wp_register_style(
            'wc-dynamic-price-css',
            WC_DYNAMIC_PRICE_PLUGIN_URL . 'assets/css/dynamic-price.css',
            array(),
            WC_DYNAMIC_PRICE_VERSION . '.' . time()
        );
        
        wp_enqueue_style('wc-dynamic-price-css');
        
        // Регистрируем общий скрипт плагина
        wp_register_script(
            'wc-dynamic-price',
            WC_DYNAMIC_PRICE_PLUGIN_URL . 'assets/js/dynamic-price.js',
            array('jquery'),
            WC_DYNAMIC_PRICE_VERSION . '.' . time(),
            true
        );
        
        wp_enqueue_script('wc-dynamic-price');
    }
}