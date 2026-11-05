/**
 * User Panel Logic for Pre-buy and Pre-invoice pages.
 * Version: 5.1.1 (Fixed: Grand Total calculation bug in Pre-buy)
 */

const InvoiceManager = (function() {
    'use strict';

    // 1. Configuration
    const CONFIG = {
        selectors: {
            card: '.product-item-card, .pinvoice-card',
            qtyInput: '.up-qty-logic',
            checkbox: 'input[type="checkbox"]',
            rowTotal: '.row-total',
            prebuyTotal: '#prebuy-grand-total',
            pinvoiceTotal: '#pinvoice-grand-total',
            msgWrap: '.up-msg-wrap',
            wrapper: '.qty-field-wrapper'
        },
        classes: {
            selectedCard: 'is-selected',
            errorShake: 'up-input-error-shake',
            successMsg: 'up-inline-success-msg',
            errorMsg: 'up-inline-error-msg'
        },
        delays: {
            inputDebounce: 300,
            clickIgnore: 250
        },
        currencyFormatter: new Intl.NumberFormat('fa-IR')
    };

    // State
    const state = {
        lastInputBlurTime: 0,
        lastInputBlurCard: null,
        debounceTimers: new WeakMap() 
    };

    // 2. Core Functions
    const init = () => {
        setupInitialCalculations();
        attachEventListeners();
    };

    const setupInitialCalculations = () => {
        document.querySelectorAll(CONFIG.selectors.qtyInput).forEach(updateRowTotal);
        updateGrandTotal();
    };

    const attachEventListeners = () => {
        document.body.addEventListener('click', handleCardClick);
        document.body.addEventListener('change', handleCheckboxChange);
        document.body.addEventListener('blur', handleInputBlur, true);
        document.body.addEventListener('input', handleInputEvent);
        document.body.addEventListener('submit', handleFormSubmit);
        document.body.addEventListener('keydown', handleKeyboardInteraction);
    };

    // 3. Event Handlers
    const handleCardClick = (e) => {
        const interactiveSelector = 'label, input, button, a, select, textarea';
        if (e.target.closest(interactiveSelector)) return;

        const card = e.target.closest(CONFIG.selectors.card);
        if (!card) return;

        if (state.lastInputBlurCard === card && (Date.now() - state.lastInputBlurTime) < CONFIG.delays.clickIgnore) {
            return;
        }

        const checkbox = card.querySelector(CONFIG.selectors.checkbox);
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    const handleKeyboardInteraction = (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            const card = e.target.closest(CONFIG.selectors.card);
            if (card && e.target === card) {
                e.preventDefault();
                card.click();
            }
        }
    };

    const handleCheckboxChange = (e) => {
        if (!e.target.matches(CONFIG.selectors.checkbox)) return;
        
        const card = e.target.closest(CONFIG.selectors.card);
        if (card) {
            e.target.checked ? card.classList.add(CONFIG.classes.selectedCard) : card.classList.remove(CONFIG.classes.selectedCard);
            updateGrandTotal();
        }
    };

    const handleInputBlur = (e) => {
        if (!e.target.matches(CONFIG.selectors.qtyInput)) return;

        state.lastInputBlurTime = Date.now();
        state.lastInputBlurCard = e.target.closest(CONFIG.selectors.card);

        enforceCorrection(e.target);
        updateRowTotal(e.target);
        updateGrandTotal();
    };

    const handleInputEvent = (e) => {
        if (!e.target.matches(CONFIG.selectors.qtyInput)) return;

        const input = e.target;
        
        input.value = input.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');

        const card = input.closest(CONFIG.selectors.card);
        if (card) {
            const checkbox = card.querySelector(CONFIG.selectors.checkbox);
            if (checkbox && !checkbox.checked) {
                checkbox.checked = true;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        if (state.debounceTimers.has(input)) {
            clearTimeout(state.debounceTimers.get(input));
        }

        const timer = setTimeout(() => {
            realtimeValidation(input);
            updateRowTotal(input);
            updateGrandTotal();
        }, CONFIG.delays.inputDebounce);

        state.debounceTimers.set(input, timer);
    };

    const handleFormSubmit = (e) => {
        const form = e.target;
        const formInputs = form.querySelectorAll(CONFIG.selectors.qtyInput);
        let formHasErrors = false;

        formInputs.forEach(input => {
            const card = input.closest(CONFIG.selectors.card);
            const checkbox = card ? card.querySelector(CONFIG.selectors.checkbox) : null;
            
            if (!checkbox || checkbox.checked) {
                const wasCorrected = enforceCorrection(input);
                if (wasCorrected) {
                    formHasErrors = true;
                    updateRowTotal(input);
                }
            }
        });

        if (formHasErrors) {
            e.preventDefault(); 
            updateGrandTotal(); 
        }
    };

    // 4. Math & Logic Functions

    const floatSafeMultiply = (a, b, c = 1) => {
        return Math.round((a * b * c) * 10000) / 10000;
    };

    // قیمت سطر (روی کارت) همیشه محاسبه می‌شود
    const updateRowTotal = (input) => {
        const userQty = parseFloat(input.value) || 0; 
        const basePrice = parseFloat(input.dataset.price) || 0;
        const capacity = parseFloat(input.dataset.capacity) || 1;
        
        // $TotalPrice = Quantity \times Capacity \times BasePrice$
        const totalPrice = floatSafeMultiply(userQty, capacity, basePrice);
        
        const cardContainer = input.closest(CONFIG.selectors.card);
        if (cardContainer) {
            const totalDisplay = cardContainer.querySelector(CONFIG.selectors.rowTotal);
            if (totalDisplay) {
                totalDisplay.textContent = CONFIG.currencyFormatter.format(totalPrice);
            }
        }
    };

    const updateGrandTotal = () => {
        let grandTotal = 0;
        
        // ⭐ حل باگ اصلی: سلکتور ایمن برای گرفتن فقط چک‌باکس‌های تیک‌خورده در کل صفحه
        const checkedBoxes = document.querySelectorAll('input[type="checkbox"]:checked');
        
        checkedBoxes.forEach(checkbox => {
            // اطمینان حاصل می‌کنیم که چک‌باکس متعلق به یکی از کارت‌های محصولات/فاکتورهاست
            const card = checkbox.closest(CONFIG.selectors.card);
            if (!card) return;

            const input = card.querySelector(CONFIG.selectors.qtyInput);
            if (input) {
                const userQty = parseFloat(input.value) || 0; 
                const basePrice = parseFloat(input.dataset.price) || 0;
                const capacity = parseFloat(input.dataset.capacity) || 1;
                
                // $GrandTotal = \sum (Quantity \times Capacity \times BasePrice)$
                grandTotal += floatSafeMultiply(userQty, capacity, basePrice);
            }
        });

        const formattedTotal = CONFIG.currencyFormatter.format(grandTotal);

        const prebuyTotalEl = document.querySelector(CONFIG.selectors.prebuyTotal);
        const pinvoiceTotalEl = document.querySelector(CONFIG.selectors.pinvoiceTotal);

        if (prebuyTotalEl) prebuyTotalEl.textContent = formattedTotal;
        if (pinvoiceTotalEl) pinvoiceTotalEl.textContent = formattedTotal;
    };

    const realtimeValidation = (input) => {
        const minVal = parseFloat(input.dataset.min) || 1;
        let stepVal = parseFloat(input.dataset.step);
        stepVal = (isNaN(stepVal) || stepVal <= 0) ? minVal : stepVal; 
        
        const currentVal = parseFloat(input.value);
        const validationType = input.dataset.validationType || 'multiplier';

        if (isNaN(currentVal) || input.value.trim() === '') {
            return showMessage(input, 'لطفاً یک مقدار وارد کنید', 'error');
        }

        if (currentVal <= 0) {
            return showMessage(input, 'عدد وارد شده نامعتبر است', 'error');
        }

        if (validationType === 'minimum') {
            if (currentVal < minVal) {
                showMessage(input, `حداقل سفارش ${minVal} می‌باشد`, 'error');
            } else {
                removeMessage(input);
            }
        } else {
            const remainder = Math.abs((currentVal % stepVal) / stepVal);
            const isMultiple = remainder < Number.EPSILON || Math.abs(1 - remainder) < Number.EPSILON;
            
            if (!isMultiple || currentVal < minVal) {
                showMessage(input, `تعداد باید مضربی از ${stepVal} باشد`, 'error');
            } else {
                removeMessage(input);
            }
        }
    };

    const enforceCorrection = (input) => {
        const minVal = parseFloat(input.dataset.min) || 1;
        let stepVal = parseFloat(input.dataset.step);
        stepVal = (isNaN(stepVal) || stepVal <= 0) ? minVal : stepVal; 
        
        const currentVal = parseFloat(input.value);
        const validationType = input.dataset.validationType || 'multiplier';
        
        if (isNaN(currentVal) || currentVal <= 0) {
            applyValue(input, minVal, `✓ حداقل مقدار اعمال شد (${minVal})`);
            return true;
        }

        if (validationType === 'minimum') {
            if (currentVal < minVal) {
                applyValue(input, minVal, `✓ حداقل مقدار اعمال شد (${minVal})`);
                return true;
            } else {
                removeMessage(input);
                return false;
            }
        } else { 
            let nearestMultiple = Math.round(currentVal / stepVal) * stepVal;
            nearestMultiple = Math.max(nearestMultiple, minVal);
            nearestMultiple = parseFloat(nearestMultiple.toFixed(4));

            if (currentVal !== nearestMultiple) {
                applyValue(input, nearestMultiple, `✓ عدد به طور خودکار اصلاح شد (${nearestMultiple})`);
                return true;
            } else {
                removeMessage(input);
                return false;
            }
        }
    };

    // 5. Utility Functions
    const applyValue = (input, value, msg) => {
        input.value = value;
        showMessage(input, msg, 'success');
    };

    const showMessage = (input, message, type = 'error') => {
        removeMessage(input); 
        
        if (type === 'error') {
            input.classList.add(CONFIG.classes.errorShake);
            setTimeout(() => input.classList.remove(CONFIG.classes.errorShake), 400);
        }
        
        const wrapper = input.closest(CONFIG.selectors.wrapper);
        if (!wrapper) return; 

        const msgWrap = wrapper.querySelector(CONFIG.selectors.msgWrap);
        if (msgWrap) {
            if (!msgWrap.hasAttribute('aria-live')) {
                msgWrap.setAttribute('aria-live', 'polite');
            }
            
            const msgSpan = document.createElement('span');
            msgSpan.innerText = message;
            msgSpan.className = type === 'success' ? CONFIG.classes.successMsg : CONFIG.classes.errorMsg;
            msgWrap.appendChild(msgSpan);
        }
    };

    const removeMessage = (input) => {
        input.classList.remove(CONFIG.classes.errorShake);
        const wrapper = input.closest(CONFIG.selectors.wrapper);
        if (wrapper) {
            const msgWrap = wrapper.querySelector(CONFIG.selectors.msgWrap);
            if (msgWrap) msgWrap.innerHTML = '';
        }
    };

    return {
        init: init
    };

})();

document.addEventListener('DOMContentLoaded', InvoiceManager.init);
