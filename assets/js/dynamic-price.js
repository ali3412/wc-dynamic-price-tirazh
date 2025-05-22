(function($) {
    'use strict';

    $(document).ready(function() {
        // console.log('WC Dynamic Price script initialized (AJAX version)');
        
        // Check if jQuery is properly loaded
        // console.log('jQuery version:', $.fn.jquery);
        
        // Variables to store the pricing data
        let variationId = 0;
        let ajaxUrl = '';
        let nonce = '';
        let priceFormat = '%s руб.'; // Default price format, will be updated by localized data
        let variations = {}; // Store variation data

        // Get price data from the localized object
        // console.log('Checking for localized wc_dynamic_price_data object...');

        // Функция проверки данных и установки значений
        function checkAndSetPriceData() {
            if (typeof wc_dynamic_price_data !== 'undefined') {
                // console.log('WC Dynamic Price data found:', wc_dynamic_price_data);
                priceFormat = wc_dynamic_price_data.price_format;
                ajaxUrl = wc_dynamic_price_data.ajax_url;
                nonce = wc_dynamic_price_data.nonce;
                variations = wc_dynamic_price_data.variations || {};
                
                // console.log('Parsed values:', {
                //    priceFormat: priceFormat,
                //    ajaxUrl: ajaxUrl,
                //    variations: variations
                // });
                return true;
            } else {
                // console.log('wc_dynamic_price_data object not found, will try again later...');
                return false;
            }
        }

        // Проверяем наличие данных
        let dataFound = checkAndSetPriceData();

        // Если данных нет, установим интервал для повторной проверки
        if (!dataFound) {
            let checkInterval = setInterval(function() {
                if (checkAndSetPriceData()) {
                    clearInterval(checkInterval);
                    // После получения данных обновляем цену
                    updateDisplayedPrice();
                }
            }, 500); // Проверяем каждые 500 мс
        }

        // Function to calculate price based on quantity via AJAX
        function calculateDynamicPrice(quantity, callback) {
            // console.log('Requesting dynamic price calculation via AJAX for quantity:', quantity);
            
            // Get the current variation ID
            variationId = $('input[name="variation_id"]').val();
            
            // Validate variation ID and AJAX URL
            if (!variationId || !ajaxUrl) {
                // console.error('Missing variation ID or AJAX URL for dynamic price calculation');
                return callback(false);
            }
            
            // console.log('Calculating price for variation:', variationId);
            
            // Make AJAX request to calculate price on the server
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'calculate_dynamic_price',
                    variation_id: variationId,
                    quantity: quantity,
                    nonce: nonce
                },
                dataType: 'json',
                success: function(response) {
                    // console.log('AJAX response:', response);
                    if (response.success) {
                        callback(response.data);
                    } else {
                        // console.error('AJAX request failed:', response.data.message);
                        callback(false);
                    }
                },
                error: function(xhr, status, error) {
                    // console.error('AJAX request error:', error);
                    callback(false);
                }
            });
        }

        // Function to format price based on WooCommerce format
        function formatPrice(price) {
            const formattedNumber = parseFloat(price).toFixed(2); // Format to 2 decimal places
            const currencySymbol = '₽'; // Используем рубль как символ валюты по умолчанию
            
            // Проверяем формат цены WooCommerce
            if (priceFormat.includes('%1$s') && priceFormat.includes('%2$s')) {
                // Формат с двумя параметрами (обычно %2$s - цена, %1$s - символ валюты)
                return priceFormat.replace('%2$s', formattedNumber).replace('%1$s', currencySymbol);
            } else {
                // Простой формат с одним параметром
                return priceFormat.replace('%s', formattedNumber);
            }
        }

        // Function to update displayed price via AJAX
        function updateDisplayedPrice() {
            // console.log('Updating displayed price via AJAX...');
            
            // Get the current variation ID
            variationId = $('input[name="variation_id"]').val();
            
            // Check if we have required data and a variation is selected
            if (!variationId || variationId === '0') {
                // console.warn('No variation selected');
                return;
            }

            // Get current quantity - более гибкий поиск элемента количества
            let qtyInput = $('input.qty, .quantity input[type="number"]').first();
            // console.log('Quantity input found:', qtyInput.length > 0);
            
            if (qtyInput.length === 0) {
                // console.warn('Quantity input not found');
                return;
            }

            let quantity = parseInt(qtyInput.val());
            // console.log('Current quantity input value:', qtyInput.val());
            
            if (isNaN(quantity) || quantity < 1) {
                // console.log('Invalid quantity, defaulting to 1');
                quantity = 1;
            }
            
            // Проверяем наличие порогов тиража для данной вариации
            if (variations[variationId] && variations[variationId].tiered_prices) {
                // console.log('Found tiered prices for this variation:', variations[variationId].tiered_prices);
                
                // Проверяем минимальное количество
                if (variations[variationId].min_quantity && quantity < variations[variationId].min_quantity) {
                    quantity = variations[variationId].min_quantity;
                    qtyInput.val(quantity);
                    // console.log('Updated quantity to minimum:', quantity);
                }
                
                // Добавляем визуальный эффект затухания старой цены только для товаров с тиражами
                if ($('.dynamic-price-total .amount').length > 0) {
                    $('.dynamic-price-total .amount').css('opacity', '0.5');
                }
                // Также затухаем блок скидки, если он есть
                if ($('.dynamic-price-discount-value').length > 0) {
                    $('.dynamic-price-discount-value').css('opacity', '0.5');
                }
            }
            
            // Calculate new price via AJAX
            calculateDynamicPrice(quantity, function(priceData) {
                if (!priceData) {
                    // console.error('Failed to calculate price');
                    return;
                }
                
                // Используем данные, полученные с сервера
                let formattedTotalPrice = priceData.formatted_total_price;
                let formattedUnitPrice = priceData.formatted_unit_price;
                let discountPercent = priceData.discount_percent;
                
                // console.log('New calculated prices from server:', {
                //    unitPrice: priceData.unit_price,
                //    totalPrice: priceData.total_price,
                //    formattedUnitPrice: formattedUnitPrice,
                //    formattedTotalPrice: formattedTotalPrice
                // });

                // Ищем контейнер цены вариации
                let variationPrice = $('.woocommerce-variation-price');
                // console.log('Variation price container found:', variationPrice.length);

                if (variationPrice.length === 0) {
                    // console.warn('Variation price container not found');
                    return;
                }
                
                // Используем контейнер цены вариации для добавления нашей информации
                let priceContainer = variationPrice;
                
                // Проверяем наличие порогов тиража для данной вариации
                let hasTieredPrices = variations[variationId] && 
                                      variations[variationId].tiered_prices && 
                                      variations[variationId].tiered_prices.length > 0;
                
                // Отображаем итого и цену за единицу
                if ($('.dynamic-price-total').length === 0) {
                    // console.log('Adding total price elements');
                    
                    // Создаем контейнер для информации о цене
                    let priceInfoContainer = $('<div class="dynamic-price-info"></div>');
                    
                    // Добавляем элементы в контейнер (style="opacity:1" для видимости сразу)
                    
                    // Создаем иконку информации для показа таблицы тиражей
                    let infoIcon = '<span class="tirazh-info-icon" title="Показать цены по тиражам">?</span>';
                    
                    priceInfoContainer.append('\
                    <div class="dynamic-price-block">\
                        <div class="dynamic-price-label">Стоимость</div>\
                        <div class="dynamic-price-total">\
                            <span class="amount" style="opacity:1">' + formattedTotalPrice + '</span>\
                            ' + (hasTieredPrices ? infoIcon : '') + '\
                        </div>\
                    </div>');
                    
                    // Добавляем блок со скидкой, если она есть
                    if (discountPercent && discountPercent > 0) {
                        priceInfoContainer.append('\
                        <div class="dynamic-price-block">\
                            <div class="dynamic-price-label">Скидка</div>\
                            <div class="dynamic-price-discount-block">\
                                <span class="dynamic-price-discount-value" style="opacity:1">' + discountPercent + '%</span>\
                            </div>\
                        </div>');
                    }
                    
                    // Блок вывода цены за штуку удален по запросу пользователя
                    
                    // Добавляем контейнер в конец контейнера цены вариации
                    priceContainer.append(priceInfoContainer);
                    
                    // console.log('Added price elements. Check if they were added successfully:', {
                    //    'dynamic-price-total elements': $('.dynamic-price-total').length
                    // });
                } else {
                    // console.log('Updating existing price elements');
                    // Обновляем стоимость с анимацией только для товаров с тиражами
                    if (hasTieredPrices) {
                        $('.dynamic-price-total .amount')
                            .html(formattedTotalPrice)
                            .css('opacity', '0')
                            .animate({opacity: 1}, 1000);
                    } else {
                        // Для товаров без тиражей просто обновляем текст без анимации
                        $('.dynamic-price-total .amount').html(formattedTotalPrice);
                    }
                    
                    // Обновляем или добавляем блок скидки
                    if (discountPercent && discountPercent > 0) {
                        if ($('.dynamic-price-discount-block').length > 0) {
                            // Обновляем значение скидки с анимацией только для товаров с тиражами
                            if (hasTieredPrices) {
                                $('.dynamic-price-discount-value')
                                    .text(discountPercent + '%')
                                    .css('opacity', '0')
                                    .animate({opacity: 1}, 1000);
                            } else {
                                // Для товаров без тиражей просто обновляем текст без анимации
                                $('.dynamic-price-discount-value').text(discountPercent + '%');
                            }
                        } else {
                            // Добавляем новый блок скидки
                            let discountBlock = $('\
                            <div class="dynamic-price-block">\
                                <div class="dynamic-price-label">Скидка:</div>\
                                <div class="dynamic-price-discount-block">\
                                    <span class="dynamic-price-discount-value" style="opacity:1">' + discountPercent + '%</span>\
                                </div>\
                            </div>');
                            $('.dynamic-price-info').append(discountBlock);
                        }
                    } else {
                        // Удаляем блок скидки, если скидка стала 0%
                        $('.dynamic-price-block').each(function() {
                            if ($(this).find('.dynamic-price-discount-block').length > 0) {
                                $(this).remove();
                            }
                        });
                    }
                }
            });
        }

        // Функция для обеспечения кратности количества минимальному значению
        function enforceMinimumQuantityMultiple() {
            // Только если выбрана вариация
            if (!variationId || variationId === '0') {
                return;
            }
            
            let qtyInput = $('input.qty, .quantity input[type="number"]').first();
            if (qtyInput.length === 0) {
                return;
            }
            
            // Получаем шаг из атрибута step
            let step = parseInt(qtyInput.attr('step'));
            if (isNaN(step) || step <= 0) {
                step = 1; // Значение по умолчанию, если атрибут не установлен
            }
            
            // Получаем минимальное значение из атрибута min
            let minQuantity = parseInt(qtyInput.attr('min'));
            if (isNaN(minQuantity) || minQuantity <= 0) {
                minQuantity = step; // Если min не установлен, используем step
            }
            
            let currentQty = parseInt(qtyInput.val());
            if (isNaN(currentQty) || currentQty < minQuantity) {
                qtyInput.val(minQuantity);
                return;
            }
            
            // Проверяем кратность шагу
            if (currentQty % step !== 0) {
                // Округляем до ближайшего кратного значения
                let newQty = Math.ceil(currentQty / step) * step;
                // console.log('Correcting quantity to be multiple of step:', currentQty, '->', newQty);
                qtyInput.val(newQty);
            }
        }
        
        // Bind event handlers to quantity input changes
        $(document).on('change', '.qty', function() {
            // console.log('Quantity changed, enforcing minimum multiple...');
            enforceMinimumQuantityMultiple();
            updateDisplayedPrice();
        });
        
        // Дополнительная проверка при потере фокуса для уверенности
        $(document).on('blur', '.qty', function() {
            enforceMinimumQuantityMultiple();
        });
        
        // Добавляем поддержку кнопок быстрого изменения количества с учетом минимального тиража
        $('.quantity').each(function() {
            const qtyInput = $(this).find('input.qty, input[type="number"]');
            const qtyContainer = $(this);
            
            // Обработчики кнопок +/- для количества
            qtyContainer.find('.plus').off('click.dp').on('click.dp', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                
                // Получаем шаг из атрибута step
                let step = parseInt(qtyInput.attr('step'));
                if (isNaN(step) || step <= 0) {
                    step = 1; // Значение по умолчанию
                }
                
                let currentQty = parseInt(qtyInput.val());
                if (isNaN(currentQty)) {
                    currentQty = 0;
                }
                
                // Увеличиваем количество на значение шага
                let newQty = currentQty + step;
                qtyInput.val(newQty).trigger('change');
                
                enforceMinimumQuantityMultiple();
                updateDisplayedPrice();
            });
            
            qtyContainer.find('.minus').off('click.dp').on('click.dp', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                
                // Получаем шаг из атрибута step
                let step = parseInt(qtyInput.attr('step'));
                if (isNaN(step) || step <= 0) {
                    step = 1; // Значение по умолчанию
                }
                
                // Получаем минимальное значение из атрибута min
                let minQty = parseInt(qtyInput.attr('min'));
                if (isNaN(minQty) || minQty <= 0) {
                    minQty = step; // Если min не установлен, используем step
                }
                
                let currentQty = parseInt(qtyInput.val());
                if (isNaN(currentQty)) {
                    currentQty = minQty * 2;
                }
                
                // Уменьшаем количество на значение шага, но не меньше минимального
                let newQty = Math.max(minQty, currentQty - step);
                // console.log('Changing quantity from', currentQty, 'to', newQty, '(min:', minQty, ')');
                
                // Устанавливаем новое значение и вызываем обновление цены
                qtyInput.val(newQty).trigger('change');
                enforceMinimumQuantityMultiple();
                updateDisplayedPrice();
                
                return false;
            });
        });
        
        // Перехватываем нажатия на кнопки +/- для количества (страховка)
        $(document).on('click', '.plus', function() {
            setTimeout(function() {
                enforceMinimumQuantityMultiple();
                updateDisplayedPrice();
            }, 100);
        });

        // Handle variation selection changes
        $(document).on('found_variation', 'form.variations_form', function(event, variation) {
            // console.log('Variation selected:', variation);
            variationId = variation.variation_id;
            
            // Обновляем атрибуты min и step в поле ввода количества
            if (variations[variationId] && variations[variationId].min_quantity) {
                const minQty = parseInt(variations[variationId].min_quantity);
                if (minQty > 0) {
                    // Находим поле ввода количества
                    const qtyInput = $('input.qty, .quantity input[type="number"]').first();
                    if (qtyInput.length > 0) {
                        console.log('Обновляем атрибуты поля ввода количества: min и step =', minQty);
                        
                        // Устанавливаем атрибуты min и step
                        qtyInput.attr('min', minQty);
                        qtyInput.attr('step', minQty);
                        qtyInput.attr('data-min-qty', minQty);
                        
                        // Если текущее значение меньше минимального, обновляем его
                        const currentQty = parseInt(qtyInput.val());
                        if (isNaN(currentQty) || currentQty < minQty) {
                            qtyInput.val(minQty);
                        }
                        
                        // Если текущее значение не кратно минимальному, округляем
                        else if (currentQty % minQty !== 0) {
                            qtyInput.val(Math.ceil(currentQty / minQty) * minQty);
                        }
                    }
                }
            }
            
            // Check if we have data for this variation
            if (variations[variationId]) {
                // console.log('Found variation data:', variations[variationId]);
                
                // Проверяем наличие порогов тиража для этой вариации
                if (variations[variationId].tiered_prices && variations[variationId].tiered_prices.length > 0) {
                    // console.log('Tiered prices found:', variations[variationId].tiered_prices);
                    
                    // Проверяем минимальное количество
                    let minQty = variations[variationId].min_quantity || 1;
                    let qtyInput = $('input.qty, .quantity input[type="number"]').first();
                    
                    if (qtyInput.length > 0) {
                        let currentQty = parseInt(qtyInput.val());
                        if (isNaN(currentQty) || currentQty < minQty) {
                            qtyInput.val(minQty);
                            // console.log('Setting minimum quantity:', minQty);
                        }
                    }
                    
                    // Создаем всплывающее окно с таблицей тиражей, если его ещё нет
                    if ($('.tirazh-popup').length === 0) {
                        let tirazhTable = '<div class="tirazh-popup" style="display:none;">' +
                                        '<div class="tirazh-popup-content">' +
                                            '<div class="tirazh-popup-header">' +
                                                '<h4>Цены по тиражам</h4>' +
                                                '<span class="tirazh-popup-close">&times;</span>' +
                                            '</div>' +
                                            '<div class="tirazh-popup-body">' +
                                                '<table class="tirazh-table"><thead><tr><th>Тираж</th><th>Цена за шт</th></tr></thead><tbody>';
                        
                        variations[variationId].tiered_prices.forEach(function(tier) {
                            tirazhTable += '<tr><td>до ' + tier.threshold + '</td><td>' + formatPrice(tier.price) + '</td></tr>';
                        });
                        
                        tirazhTable += '</tbody></table>' +
                                    '</div>' +
                                '</div>' +
                            '</div>';
                        
                        $('body').append(tirazhTable);
                    } else {
                        // Обновляем существующую таблицу
                        let tableBody = '';
                        variations[variationId].tiered_prices.forEach(function(tier) {
                            tableBody += '<tr><td>до ' + tier.threshold + '</td><td>' + formatPrice(tier.price) + '</td></tr>';
                        });
                        $('.tirazh-table tbody').html(tableBody);
                    }
                    
                    // Добавляем обработчики событий для всплывающего окна
                    $(document).off('click', '.tirazh-info-icon').on('click', '.tirazh-info-icon', function() {
                        $('.tirazh-popup').fadeIn(300);
                    });
                    
                    $(document).off('click', '.tirazh-popup-close').on('click', '.tirazh-popup-close', function() {
                        $('.tirazh-popup').fadeOut(300);
                    });
                    
                    // Закрываем попап при клике вне его содержимого
                    $(document).off('click', '.tirazh-popup').on('click', '.tirazh-popup', function(e) {
                        if ($(e.target).hasClass('tirazh-popup')) {
                            $('.tirazh-popup').fadeOut(300);
                        }
                    });
                    
                    // Закрываем попап при нажатии клавиши ESC
                    $(document).off('keydown.tirazhPopup').on('keydown.tirazhPopup', function(e) {
                        if (e.key === 'Escape' && $('.tirazh-popup').is(':visible')) {
                            $('.tirazh-popup').fadeOut(300);
                        }
                    });
                }
                
                updateDisplayedPrice();
            } else {
                // console.log('No dynamic price data for this variation');
                $('.dynamic-price-info').hide();
                $('.tirazh-popup').hide();
            }
        });

        $(document).on('reset_data', 'form.variations_form', function() {
            // console.log('Variation selection reset');
            variationId = 0;
            $('.dynamic-price-info').hide();
            $('.tirazh-popup').hide();
        });

        // Check if variation is pre-selected
        let preSelectedVariation = $('input[name="variation_id"]').val();
        if (preSelectedVariation && preSelectedVariation !== '0' && variations[preSelectedVariation]) {
            // console.log('Pre-selected variation found with dynamic price data:', preSelectedVariation);
            variationId = preSelectedVariation;
            
            // Симулируем событие выбора вариации для предварительного заполнения таблицы тиражей
            $('form.variations_form').trigger('found_variation', [{ variation_id: preSelectedVariation }]);
        }
        
        // Check DOM structure for debugging
        // console.log('DOM structure:', {
        //    'form.variations_form': $('form.variations_form').length,
        //    'input.qty': $('input.qty').length,
        //    '.price': $('.price').length,
        //    'span.woocommerce-Price-amount': $('span.woocommerce-Price-amount').length,
        //    'variation_id input': $('input[name="variation_id"]').length,
        //    'body classes': $('body').attr('class')
        // });
        
    });

})(jQuery);