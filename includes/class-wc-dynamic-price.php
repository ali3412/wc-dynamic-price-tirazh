<?php
/**
 * WC_Dynamic_Price Class
 * 
 * Основной класс для WooCommerce Dynamic Price Calculator plugin.
 * Теперь работает как класс-загрузчик для модульной структуры плагина.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Загружаем все необходимые классы для модульной структуры плагина
 */
require_once WC_DYNAMIC_PRICE_PLUGIN_DIR . 'includes/classes/class-main.php';

class WC_Dynamic_Price {

    /**
     * Конструктор
     */
    public function __construct() {
        // Initialize the plugin
    }

    /**
     * Initialize the plugin functions
     */
    public function init() {
        // Инициализируем основной класс плагина
        WC_Dynamic_Price_Main::get_instance();
    }
}