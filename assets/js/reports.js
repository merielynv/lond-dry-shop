(function () {
    'use strict';

    /* =========================================================
       PRINT REPORT
       ========================================================= */

    const printButton =
        document.getElementById('printReport');


    if (printButton) {

        printButton.addEventListener(
            'click',
            function () {

                window.print();

            }
        );

    }


    /* =========================================================
       DATE VALIDATION
       ========================================================= */

    const fromDate =
        document.getElementById('from');

    const toDate =
        document.getElementById('to');


    const reportForm =
        document.querySelector('.report-filter-form');


    if (reportForm) {

        reportForm.addEventListener(
            'submit',
            function (event) {

                if (
                    fromDate &&
                    toDate &&
                    fromDate.value !== '' &&
                    toDate.value !== ''
                ) {

                    if (fromDate.value > toDate.value) {

                        event.preventDefault();

                        alert(
                            'The From date cannot be later than the To date.'
                        );

                    }

                }

            }
        );

    }

})();