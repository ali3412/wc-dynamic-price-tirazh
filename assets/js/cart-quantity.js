(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('WC Dynamic Price Cart Quantity script initialized');
        
        // Проверка типа корзины
        const isBlockBasedCart = $('.wc-block-cart').length > 0 || $('.wp-block-woocommerce-cart').length > 0;
        console.log('Тип корзины: ' + (isBlockBasedCart ? 'блочная' : 'классическая'));
        
        // Проверка наличия данных о минимальных количествах
        let cartItemsData = {};
        if (typeof wc_dynamic_price_cart_data !== 'undefined' && wc_dynamic_price_cart_data.cart_items) {
            cartItemsData = wc_dynamic_price_cart_data.cart_items;
            console.log('Данные о минимальных количествах:', cartItemsData);
        }

        // Функция для получения минимального количества по ключу товара в корзине
        function getMinQuantityForInput(qtyInput) {
            // Сначала пытаемся получить из data-атрибута (классическая корзина)
            let minQty = parseInt(qtyInput.data('min-qty') || 1);
            
            // Если minQty не установлен в data-атрибуте и есть данные из PHP
            if (minQty <= 1 && Object.keys(cartItemsData).length > 0) {
                // Пытаемся найти по ключу товара в корзине
                let cartItemKey = qtyInput.data('cart-item-key');
                
                // Для блочной корзины нам нужно попытаться идентифицировать товар
                if (!cartItemKey && isBlockBasedCart) {
                    // Попытка найти ключ товара в блочной корзине
                    // Проходим вверх по DOM до нахождения элемента с данными о товаре
                    let parentElement = qtyInput.closest('.wc-block-cart-item');
                    if (parentElement.length > 0) {
                        // В блочной корзине мы можем попытаться получить ключ из data-атрибута
                        // или по атрибуту id или по тексту названия товара
                        // Проходим по всем данным из cartItemsData и сравниваем название товара
                        for (let key in cartItemsData) {
                            // Используем первый найденный ключ
                            cartItemKey = key;
                            break;
                        }
                    }
                }
                
                // Если нашли ключ товара и есть данные по нему
                if (cartItemKey && cartItemsData[cartItemKey]) {
                    minQty = parseInt(cartItemsData[cartItemKey].min_qty || 1);
                    console.log('Найдены данные о минимальном количестве:', minQty, 'для ключа:', cartItemKey);
                    
                    // Устанавливаем data-атрибут для будущего использования
                    qtyInput.data('min-qty', minQty);
                    qtyInput.attr('data-min-qty', minQty);
                    qtyInput.attr('step', minQty);
                }
            }
            
            return minQty;
        }
        
        // Функция для обеспечения кратности количества шагу изменения
        function enforceMinimumQuantityMultiple(qtyInput) {
            if (!qtyInput || qtyInput.length === 0) {
                console.log('Поле ввода количества не найдено');
                return;
            }
            
            // Получаем шаг изменения из атрибута step
            let step = parseInt(qtyInput.attr('step'));
            if (isNaN(step) || step <= 0) {
                // Если атрибут step не установлен, пытаемся получить из data-min-qty
                step = parseInt(qtyInput.data('min-qty') || 1);
            }
            
            // Получаем минимальное количество из атрибута min
            let minQuantity = parseInt(qtyInput.attr('min'));
            if (isNaN(minQuantity) || minQuantity <= 0) {
                // Если атрибут min не установлен, используем step или data-min-qty
                minQuantity = step;
            }
            
            let currentQty = parseInt(qtyInput.val());
            console.log('enforceMinimumQuantityMultiple: step =', step, 'minQuantity =', minQuantity, 'currentQty =', currentQty, 'поле:', qtyInput.attr('name'));
            
            if (isNaN(currentQty) || currentQty < minQuantity) {
                qtyInput.val(minQuantity);
                return;
            }
            
            // Проверяем кратность шагу
            if (currentQty % step !== 0) {
                // Округляем до ближайшего кратного значения
                let newQty = Math.ceil(currentQty / step) * step;
                console.log('Correcting cart quantity to be multiple of step:', currentQty, '->', newQty);
                qtyInput.val(newQty);
                
                // Для блочной корзины может потребоваться триггер события изменения
                qtyInput.trigger('change');
            }
        }

        // Расширенный поиск полей ввода количества для всех типов корзины
        function findQuantityInputs() {
            let selectors = [
                'input.qty', 
                '.quantity input[type="number"]',
                '.wc-block-components-quantity-selector input[type="number"]',
                '.wp-block-woocommerce-cart input[type="number"]',
                'form.woocommerce-cart-form input.qty',
                '.woocommerce-cart-form__contents input.qty'
            ];
            
            return $(selectors.join(', '));
        }
        
        // Применяем проверку ко всем полям ввода количества в корзине
        function applyToAllQuantityInputs() {
            console.log('Применяем проверку ко всем полям ввода количества');
            let inputs = findQuantityInputs();
            console.log('Найдено полей ввода:', inputs.length);
            
            if (inputs.length === 0) {
                console.log('Поля ввода количества не найдены. Возможно, DOM еще не полностью загружен или структура изменилась.');
            }
            
            inputs.each(function() {
                let qtyInput = $(this);
                enforceMinimumQuantityMultiple(qtyInput);
            });
        }
        
        // Обработчик изменения значения количества
        $(document).on('change', 'input.qty, .quantity input[type="number"]', function() {
            let qtyInput = $(this);
            console.log('Изменение значения в поле:', qtyInput.attr('name'), 'значение:', qtyInput.val(), 'min-qty:', qtyInput.data('min-qty'));
            enforceMinimumQuantityMultiple(qtyInput);
        });
        
        // Обработчик потери фокуса
        $(document).on('blur', 'input.qty, .quantity input[type="number"]', function() {
            let qtyInput = $(this);
            enforceMinimumQuantityMultiple(qtyInput);
        });
        
        // Добавляем поддержку кнопок WooCommerce и блочного интерфейса
        const plusMinusSelectors = [
            '.plus', '.minus',
            '.wc-block-components-quantity-selector__button--plus', 
            '.wc-block-components-quantity-selector__button--minus',
            '.wc-block-components-quantity-selector button'
        ];
        
        // Обработчик нажатий на кнопки плюс/минус
        $(document).on('click', plusMinusSelectors.join(', '), function() {
            console.log('Нажата кнопка +/- (селектор:', this.className, ')');
            
            // Попытка найти ближайшее поле ввода для разных типов интерфейса
            let qtyInput = $(this).siblings('input.qty, input[type="number"]');
            if (qtyInput.length === 0) {
                qtyInput = $(this).closest('.quantity, .wc-block-components-quantity-selector').find('input[type="number"]');
            }
            
            console.log('Найдено поле ввода:', qtyInput.length > 0, 'min-qty:', qtyInput.data('min-qty'));
            setTimeout(function() {
                enforceMinimumQuantityMultiple(qtyInput);
            }, 100);
        });
        
        // Перехватываем событие обновления корзины
        $(document).on('click', 'button[name="update_cart"]', function() {
            applyToAllQuantityInputs();
        });
        
        // Применяем проверку при загрузке страницы и после небольшой задержки
        // для учета динамически загружаемых элементов
        applyToAllQuantityInputs();
        
        // Проверяем поля через небольшую задержку, чтобы учесть асинхронную загрузку
        setTimeout(function() {
            console.log('Повторная проверка полей ввода через setTimeout...');
            applyToAllQuantityInputs();
        }, 1000);
        
        // Также следим за изменениями в DOM с помощью MutationObserver
        const observeDOM = (function(){
            const MutationObserver = window.MutationObserver || window.WebKitMutationObserver;
            return function(obj, callback){
                if(!obj || obj.nodeType !== 1) return;
                if(MutationObserver){
                    const mutationObserver = new MutationObserver(callback);
                    mutationObserver.observe(obj, { childList:true, subtree:true });
                    return mutationObserver;
                }
            };
        })();
        
        // Отслеживаем изменения DOM для содержимого корзины
        observeDOM(document.querySelector('.woocommerce-cart-form, .wc-block-cart, .wp-block-woocommerce-cart'), function(mutations) {
            console.log('Обнаружены изменения в DOM корзины, повторная проверка полей...');
            setTimeout(applyToAllQuantityInputs, 100);
        });
        
        // Функция добавления обработчиков для кнопок +/- с учетом минимального количества
        function addMinQtyButtonHandlers() {
            console.log('Добавление обработчиков для кнопок +/- с учетом минимального шага');
            /*
            $('.quantity, .wc-block-components-quantity-selector').each(function() {
                const qtyInput = $(this).find('input.qty, input[type="number"]');
                if (qtyInput.length === 0) return;
                
                // Получаем шаг изменения из атрибута step
                let step = parseInt(qtyInput.attr('step'));
                if (isNaN(step) || step <= 0) {
                    // Если атрибут step не установлен, пытаемся получить из data-min-qty
                    step = parseInt(qtyInput.data('min-qty') || 1);
                }
                console.log('Поле с шагом:', step, 'элемент:', qtyInput.attr('name'));
                
                if (step > 1) {
                // Функция для увеличения количества на значение шага
                $(this).find('.plus').on('click', function(e) {
                    console.log('Нажата кнопка + для поля с шагом:', step);
                    e.preventDefault();
                    e.stopPropagation();
                    
                    let currentQty = parseInt(qtyInput.val());
                    if (isNaN(currentQty)) {
                        currentQty = 0;
                    }
                    
                    let newQty = currentQty + step;
                    qtyInput.val(newQty).trigger('change');
                });
                
                // Функция для уменьшения количества на значение шага
                $(this).find('.minus').on('click', function(e) {
                    console.log('Нажата кнопка - для поля с шагом:', step);
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Получаем минимальное количество из атрибута min
                    let minQty = parseInt(qtyInput.attr('min'));
                    if (isNaN(minQty) || minQty <= 0) {
                        // Если атрибут min не установлен, используем step
                        minQty = step;
                    }
                    
                    let currentQty = parseInt(qtyInput.val());
                    if (isNaN(currentQty)) {
                        currentQty = minQty * 2;
                    }
                    
                    let newQty = Math.max(minQty, currentQty - step);
                    qtyInput.val(newQty).trigger('change');
                });
                }
            });
            */
        }
        
        // Вызываем функцию добавления обработчиков
        // setTimeout(addMinQtyButtonHandlers, 500); // Закомментировано, чтобы избежать дублирования обработчиков
        
        // Добавляем обработчик изменения корзины (для AJAX-обновлений)
        $(document.body).on('updated_cart_totals', function() {
            console.log('Корзина обновлена, повторная проверка полей...');
            setTimeout(function() {
                applyToAllQuantityInputs();
                // addMinQtyButtonHandlers(); // Закомментировано, чтобы избежать дублирования обработчиков
            }, 100);
        });
    });
    
    // Добавляем обработчик события DOMContentLoaded для дополнительной защиты
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOMContentLoaded: проверка инициализации скрипта');
        if (typeof jQuery === 'function') {
            // Проверяем, были ли найдены поля ввода
            setTimeout(function() {
                const $ = jQuery;
                const inputs = $('input.qty, .quantity input[type="number"], .wc-block-components-quantity-selector input[type="number"]');
                console.log('DOMContentLoaded: найдено полей ввода:', inputs.length);
            }, 1000);
        }
    });
})(jQuery);