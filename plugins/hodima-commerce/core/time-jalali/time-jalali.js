/* Hodima Unified Localizer JS (Modern Vanilla JS) */
'use strict';

document.addEventListener('DOMContentLoaded', () => {

    // 1. Highlight New Orders
    const dateElements = document.querySelectorAll('.hodima-wc-date');
    if (dateElements.length > 0) {
        const now = Date.now();
        const oneDayInMs = 24 * 60 * 60 * 1000;
        
        dateElements.forEach(dateEl => {
            const timestamp = Number(dateEl.dataset.timestamp);
            if (timestamp && (now - (timestamp * 1000)) < oneDayInMs) {
                dateEl.classList.add('is-new-order');
            }
        });
    }

    // 2. Wrap Admin Date Pickers
    const adminDateInputs = document.querySelectorAll('.wrap.woocommerce input[type="date"], .wrap.woocommerce input.date-picker');
    if (adminDateInputs.length > 0) {
        adminDateInputs.forEach(input => {
            if (!input.parentNode.classList.contains('hodima-admin-datepicker-wrapper')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'hodima-admin-datepicker-wrapper';
                input.parentNode.insertBefore(wrapper, input);
                wrapper.appendChild(input);
            }
        });
    }

    // 3. WooCommerce Cities Dropdown Logic
    if (typeof hodimaCitiesData !== 'undefined') {
        const stateInputs = document.querySelectorAll('#billing_state, #shipping_state');
        
        const updateCitiesDropdown = (stateElement) => {
            const type = stateElement.id.includes('billing') ? 'billing' : 'shipping';
            const citySelect = document.getElementById(`${type}_city`);
            
            if (!citySelect) return;

            const cities = hodimaCitiesData[stateElement.value] || [];
            
            // Modern DOM insertion
            const fragment = document.createDocumentFragment();
            const defaultOption = document.createElement('option');
            defaultOption.value = "";
            defaultOption.textContent = "انتخاب شهر...";
            fragment.appendChild(defaultOption);

            cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city; 
                option.textContent = city;
                fragment.appendChild(option);
            });

            citySelect.replaceChildren(fragment);

            // Trigger jQuery event strictly for WooCommerce compatibility
            if (window.jQuery) {
                window.jQuery(citySelect).trigger('change');
            } else {
                citySelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };

        stateInputs.forEach(stateInput => {
            stateInput.addEventListener('change', (e) => updateCitiesDropdown(e.target));
            
            if (window.jQuery) {
                window.jQuery(stateInput).on('change', function() { updateCitiesDropdown(this); });
            }
            if (stateInput.value) updateCitiesDropdown(stateInput);
        });
    }

    // 4. Number Normalization & Validation
    const persianNumbers = [/۰/g, /۱/g, /۲/g, /۳/g, /۴/g, /۵/g, /۶/g, /۷/g, /۸/g, /۹/g];
    const arabicNumbers  = [/٠/g, /١/g, /٢/g, /٣/g, /٤/g, /٥/g, /٦/g, /٧/g, /٨/g, /٩/g];
    
    const convertToEnglishNumbers = (str) => {
        if (typeof str !== 'string') return '';
        for (let i = 0; i < 10; i++) {
            str = str.replace(persianNumbers[i], i).replace(arabicNumbers[i], i);
        }
        return str;
    };

    const validateInput = (input, regex) => {
        if (!input) return;
        
        input.addEventListener('input', (e) => {
            const normalizedValue = convertToEnglishNumbers(e.target.value);
            e.target.value = normalizedValue;
            
            if (normalizedValue.length > 0) {
                const isValid = regex.test(normalizedValue);
                input.classList.toggle('hodima-input-success', isValid);
                input.classList.toggle('hodima-input-error', !isValid);
            } else {
                input.classList.remove('hodima-input-success', 'hodima-input-error');
            }
        });
    };

    validateInput(document.getElementById('billing_phone'), /^09\d{9}$/);
    validateInput(document.getElementById('billing_postcode'), /^\d{10}$/);
    validateInput(document.getElementById('shipping_postcode'), /^\d{10}$/);
});