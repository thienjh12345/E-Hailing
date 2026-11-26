/* Driver car photos & PUSPAKOM upload boxes: preview, remove / undo, quick checks.
 * The server checks every file again — this is only for a nicer experience. */
(function () {
    'use strict';
    var IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    document.querySelectorAll('[data-doc-slot]').forEach(function (slot) {
        var input = slot.querySelector('[data-doc-input]');
        var preview = slot.querySelector('[data-doc-preview]');
        var removeBtn = slot.querySelector('[data-doc-remove]');
        var keep = slot.querySelector('[data-doc-keep]');
        var status = slot.querySelector('[data-doc-status]');
        var errorBox = slot.querySelector('[data-doc-error]');
        var chooseLabel = slot.querySelector('.doc-actions label');
        var mode = slot.getAttribute('data-mode');
        var max = parseInt(slot.getAttribute('data-max'), 10);
        var allowPdf = slot.getAttribute('data-pdf') === '1';
        var originalHTML = slot.querySelector('[data-doc-original]').innerHTML;
        var emptyHTML = slot.querySelector('[data-doc-empty]').innerHTML;
        var originalStatus = status.textContent;
        var objectUrl = null;

        function setError(msg) {
            errorBox.textContent = msg || '';
            errorBox.hidden = !msg;
            slot.classList.toggle('has-error', !!msg);
        }
        function freeUrl() { if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; } }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) return;
            var isImage = IMAGE_TYPES.indexOf(file.type) !== -1;
            var isPdf = allowPdf && file.type === 'application/pdf';
            if (!isImage && !isPdf) {
                input.value = '';
                setError(allowPdf ? 'Please choose a JPG, PNG, WebP image or a PDF.' : 'Please choose a JPG, PNG or WebP photo.');
                return;
            }
            if (file.size > max) {
                input.value = '';
                setError('This file is ' + (file.size / 1048576).toFixed(1) + ' MB — the limit is ' + Math.round(max / 1048576) + ' MB.');
                return;
            }
            setError('');
            freeUrl();
            objectUrl = URL.createObjectURL(file);
            preview.textContent = '';
            if (isImage) {
                var img = document.createElement('img');
                img.alt = '';
                img.src = objectUrl;
                preview.appendChild(img);
            } else {
                var frame = document.createElement('iframe');   // browsers show PDFs in an iframe
                frame.src = objectUrl;
                frame.title = 'Preview of ' + file.name;
                preview.appendChild(frame);
            }
            status.textContent = (mode === 'replace' ? 'New file (not saved yet): ' : 'Selected: ') + file.name;
            if (keep) keep.value = '1';
            removeBtn.hidden = false;
            chooseLabel.textContent = 'Change';
            slot.classList.add('changed');
        });

        removeBtn.addEventListener('click', function () {
            freeUrl();
            input.value = '';
            setError('');
            slot.classList.remove('changed');
            if (mode === 'replace') {
                // Undo: go back to the file that's already saved
                preview.innerHTML = originalHTML;
                status.textContent = originalStatus;
                removeBtn.hidden = true;
                chooseLabel.textContent = 'Replace';
            } else {
                // Remove: nothing attached any more (also drops a file kept from an earlier attempt)
                preview.innerHTML = emptyHTML;
                if (keep) keep.value = '0';
                status.textContent = 'Removed — choose a file before submitting';
                removeBtn.hidden = true;
                chooseLabel.textContent = 'Choose file';
            }
        });
    });

    /* Registration: warn before submitting if any required box is empty */
    var regForm = document.querySelector('[data-doc-required-form]');
    if (regForm) regForm.addEventListener('submit', function (e) {
        var missing = [];
        regForm.querySelectorAll('[data-doc-slot][data-mode="register"]').forEach(function (slot) {
            var hasNew = slot.querySelector('[data-doc-input]').files.length > 0;
            var keep = slot.querySelector('[data-doc-keep]');
            var hasKept = slot.hasAttribute('data-has-file') && keep && keep.value === '1';
            if (!hasNew && !hasKept) {
                missing.push(slot);
                var box = slot.querySelector('[data-doc-error]');
                box.textContent = 'This is required.';
                box.hidden = false;
                slot.classList.add('has-error');
            }
        });
        if (missing.length) {
            e.preventDefault();
            missing[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
})();
