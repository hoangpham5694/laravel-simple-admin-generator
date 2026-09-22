(function () {
    'use strict';

    function initializeDatetimeInputs() {
        if (typeof window.flatpickr !== 'function') {
            return;
        }

        document.querySelectorAll('[data-sag-flatpickr="datetime"]').forEach(function (element) {
            if (element._flatpickr) {
                return;
            }

            var enableSeconds = element.dataset.enableSeconds === 'true';
            window.flatpickr(element, {
                enableTime: true,
                time_24hr: true,
                enableSeconds: enableSeconds,
                dateFormat: element.dataset.dateFormat || (enableSeconds ? 'Y-m-d H:i:S' : 'Y-m-d H:i'),
                altInput: true,
                altFormat: enableSeconds ? 'd/m/Y H:i:S' : 'd/m/Y H:i',
                minuteIncrement: Number(element.dataset.minuteIncrement || 5),
                minDate: element.dataset.minDate || null,
                maxDate: element.dataset.maxDate || null,
                locale: element.dataset.locale || 'vi'
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDatetimeInputs);
    } else {
        initializeDatetimeInputs();
    }
}());
