/**
 * Frontend behavior for Callback Request Admin form
 * Enqueued via wp_enqueue_script('cra-form')
 * Data is passed via craForm (localized) + data attributes on .cra-form-wrap
 */
(function () {
    'use strict';

    var ajaxUrl = (window.craForm && window.craForm.ajaxUrl) || '/wp-admin/admin-ajax.php';
    var action  = (window.craForm && window.craForm.action)  || 'cra_submit_callback';

    function initFormWrap(wrap) {
        var card       = wrap.querySelector('.cra-form');
        var form       = card ? card.querySelector('.cra-form-element') : null;
        if (!form) return;

        var successBox = wrap.querySelector('.cra-success');
        var msgBox     = wrap.querySelector('.cra-form-message');
        var typeRadios = form.querySelectorAll('input[name="contact_type"]');
        var contactFields = form.querySelectorAll('.cra-contact-field');
        var submitBtn  = form.querySelector('.cra-submit');

        var isPreview = wrap.getAttribute('data-preview') === '1';

        // --- Contact type switching (radio pills) ---
        function updateActiveLabel(selectedValue) {
            form.querySelectorAll('.cra-type-label').forEach(function (label) {
                label.classList.remove('active');
            });

            var activeLabel = form.querySelector('label[for="ct_' + selectedValue + '"]');
            if (activeLabel) {
                activeLabel.classList.add('active');
            }
        }

        function showOnlyContactField(selectedValue) {
            contactFields.forEach(function (field) {
                if (field.getAttribute('data-type') === selectedValue) {
                    field.style.display = '';
                } else {
                    field.style.display = 'none';
                }
            });
        }

        typeRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                var selected = this.value;
                updateActiveLabel(selected);
                showOnlyContactField(selected);
            });
        });

        // Set initial state
        var initialRadio = form.querySelector('input[name="contact_type"]:checked');
        if (initialRadio) {
            updateActiveLabel(initialRadio.value);
            showOnlyContactField(initialRadio.value);
        } else if (typeRadios.length > 0) {
            // Fallback: activate first
            typeRadios[0].checked = true;
            updateActiveLabel(typeRadios[0].value);
            showOnlyContactField(typeRadios[0].value);
        }

        // --- Submission handling ---
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // Clear previous messages
            if (msgBox) {
                msgBox.style.display = 'none';
                msgBox.className = 'cra-form-message';
                msgBox.textContent = '';
            }

            if (isPreview) {
                if (msgBox) {
                    msgBox.textContent = 'Це прев\'ю — заявки не надсилаються.';
                    msgBox.classList.add('cra-error');
                    msgBox.style.display = 'block';
                }
                // Auto-hide after a moment
                setTimeout(function () {
                    if (msgBox) msgBox.style.display = 'none';
                }, 2300);
                return;
            }

            // Real submission
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            var originalBtnText = submitBtn ? submitBtn.textContent : '';
            if (submitBtn) {
                submitBtn.textContent = 'Надсилаємо...';
            }

            var formData = new FormData(form);
            // Ensure action is present (in case)
            if (!formData.get('action')) {
                formData.append('action', action);
            }

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    // Hide form, show success
                    form.style.display = 'none';
                    if (successBox) {
                        successBox.style.display = 'block';
                    }
                } else {
                    var errorMsg = (data && data.data) ? data.data : 'Сталася помилка. Спробуйте ще раз.';
                    if (msgBox) {
                        msgBox.textContent = errorMsg;
                        msgBox.classList.add('cra-error');
                        msgBox.style.display = 'block';
                    }
                    // Re-enable button
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalBtnText;
                    }
                }
            })
            .catch(function () {
                if (msgBox) {
                    msgBox.textContent = 'Помилка з\'єднання. Перевірте інтернет.';
                    msgBox.classList.add('cra-error');
                    msgBox.style.display = 'block';
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                }
            });
        });
    }

    // Initialize all forms present on the page
    function initAll() {
        var wraps = document.querySelectorAll('.cra-form-wrap');
        wraps.forEach(function (wrap) {
            initFormWrap(wrap);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Expose for advanced use / re-init after dynamic content
    window.CRAForm = window.CRAForm || {};
    window.CRAForm.init = initAll;
})();
