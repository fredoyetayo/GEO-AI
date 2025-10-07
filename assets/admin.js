jQuery(document).ready(function ($) {
    'use strict';

    // Redirect management
    let redirectIndex = $('#geoai-redirects-tbody tr').length;

    $('#geoai-add-redirect').on('click', function () {
        const newRow = `
            <tr>
                <td><input type="text" name="geoai_redirects[${redirectIndex}][from]" value="" class="regular-text" placeholder="/old-page" /></td>
                <td><input type="text" name="geoai_redirects[${redirectIndex}][to]" value="" class="regular-text" placeholder="/new-page" /></td>
                <td>
                    <select name="geoai_redirects[${redirectIndex}][type]">
                        <option value="301">301 Permanent</option>
                        <option value="302">302 Temporary</option>
                    </select>
                </td>
                <td><button type="button" class="button geoai-remove-redirect">Remove</button></td>
            </tr>
        `;
        $('#geoai-redirects-tbody').append(newRow);
        redirectIndex++;
    });

    $(document).on('click', '.geoai-remove-redirect', function () {
        $(this).closest('tr').remove();
    });

    // Export settings
    $('#geoai-export-settings').on('click', function (e) {
        e.preventDefault();

        const settings = {
            api_key: '',
            autorun_on_save: $('input[name="geoai_autorun_on_save"]').is(':checked'),
            compat_mode: $('select[name="geoai_compat_mode"]').val(),
            titles_templates: {},
            social_defaults: {},
            schema_defaults: {},
            sitemaps: {},
            crawler_prefs: {},
        };

        // Collect all settings
        $('input[name^="geoai_titles_templates"]').each(function () {
            const name = $(this).attr('name').match(/\[(.*?)\]/)[1];
            settings.titles_templates[name] = $(this).val();
        });

        const blob = new Blob([JSON.stringify(settings, null, 2)], {
            type: 'application/json',
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'geoai-settings-' + Date.now() + '.json';
        link.click();
        URL.revokeObjectURL(url);
    });

    // Import settings
    $('#geoai-import-settings').on('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            try {
                const settings = JSON.parse(e.target.result);
                if (
                    confirm(
                        'Are you sure you want to import these settings? This will overwrite your current configuration.'
                    )
                ) {
                    applyImportedSettings(settings);
                }
            } catch (err) {
                alert('Invalid settings file: ' + err.message);
            }
        };
        reader.readAsText(file);
    });

    function applyImportedSettings(settings) {
        if (settings.autorun_on_save !== undefined) {
            $('input[name="geoai_autorun_on_save"]').prop(
                'checked',
                settings.autorun_on_save
            );
        }

        if (settings.compat_mode) {
            $('select[name="geoai_compat_mode"]').val(settings.compat_mode);
        }

        if (settings.titles_templates) {
            Object.keys(settings.titles_templates).forEach(function (key) {
                $('input[name="geoai_titles_templates[' + key + ']"]').val(
                    settings.titles_templates[key]
                );
            });
        }

        alert('Settings imported successfully. Click "Save Changes" to apply.');
    }

    // Title/Description character counter
    function updateCharacterCount(element, max, indicator) {
        const length = element.val().length;
        const remaining = max - length;
        let status = 'good';

        if (length > max) {
            status = 'error';
        } else if (length > max * 0.9) {
            status = 'warning';
        }

        indicator
            .text(length + ' / ' + max + ' characters')
            .removeClass('geoai-snippet-length-good geoai-snippet-length-warning geoai-snippet-length-error')
            .addClass('geoai-snippet-length-' + status);
    }

    // Add character counters to meta fields
    $('#geoai_title').after(
        '<div class="geoai-snippet-length-indicator geoai-title-counter"></div>'
    );
    $('#geoai_meta_desc').after(
        '<div class="geoai-snippet-length-indicator geoai-desc-counter"></div>'
    );

    $('#geoai_title').on('input', function () {
        updateCharacterCount($(this), 60, $('.geoai-title-counter'));
    });

    $('#geoai_meta_desc').on('input', function () {
        updateCharacterCount($(this), 160, $('.geoai-desc-counter'));
    });

    // Trigger on page load
    if ($('#geoai_title').length) {
        updateCharacterCount($('#geoai_title'), 60, $('.geoai-title-counter'));
    }
    if ($('#geoai_meta_desc').length) {
        updateCharacterCount($('#geoai_meta_desc'), 160, $('.geoai-desc-counter'));
    }
});
