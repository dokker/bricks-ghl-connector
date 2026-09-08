/**
 * Bricks GHL Connector - dataLayer bridge.
 *
 * Pushes one dataLayer event when a Bricks form that uses the GHL action was
 * submitted successfully. The form is identified by the tracking ID set in the
 * builder, falling back to the Bricks element ID.
 */
(function () {
    'use strict';

    document.addEventListener('bricks/form/success', function (event) {
        var elementId = (event.detail || {}).elementId || '';
        var form = elementId ? document.querySelector('[data-bghl-form="' + elementId + '"]') : null;

        if (!form) {
            return;
        }

        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: (window.bghlTracking || {}).event || 'ghl_lead',
            form_id: form.getAttribute('data-bghl-tracking-id') || elementId
        });
    });
}());
