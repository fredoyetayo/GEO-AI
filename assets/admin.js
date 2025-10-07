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

    // Media library for OG image
    let mediaUploader;

    $('#geoai-upload-og-image').on('click', function (e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: 'Select OpenGraph Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        mediaUploader.on('select', function () {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            
            $('#geoai_og_image_id').val(attachment.id);
            $('#geoai_og_image_url').val(attachment.url);
            
            $('#geoai-og-preview').attr('src', attachment.url);
            $('#geoai-og-preview-wrap').show();

            // Show image insights
            showImageInsights(attachment);
        });

        mediaUploader.open();
    });

    // Remove OG image
    $(document).on('click', '.geoai-remove-image', function (e) {
        e.preventDefault();
        $('#geoai_og_image_id').val('');
        $('#geoai_og_image_url').val('');
        $('#geoai-og-preview-wrap').hide();
        $('#geoai-image-insights').hide();
    });

    // Show image insights and recommendations
    function showImageInsights(attachment) {
        const width = attachment.width;
        const height = attachment.height;
        const size = attachment.filesizeInBytes;
        const ratio = (width / height).toFixed(2);
        const idealRatio = 1.91;
        const idealWidth = 1200;
        const idealHeight = 630;

        let insights = [];
        let warnings = [];

        // Check dimensions
        if (width === idealWidth && height === idealHeight) {
            insights.push('<li class="geoai-insight-good">✓ Perfect dimensions (1200x630px)</li>');
        } else if (width >= idealWidth && ratio >= 1.85 && ratio <= 1.95) {
            insights.push('<li class="geoai-insight-good">✓ Good dimensions (' + width + 'x' + height + 'px)</li>');
        } else {
            warnings.push('<li class="geoai-insight-warning">⚠ Recommended: 1200x630px (Current: ' + width + 'x' + height + 'px)</li>');
        }

        // Check aspect ratio
        if (Math.abs(ratio - idealRatio) < 0.1) {
            insights.push('<li class="geoai-insight-good">✓ Ideal aspect ratio (1.91:1)</li>');
        } else {
            warnings.push('<li class="geoai-insight-warning">⚠ Best ratio: 1.91:1 (Current: ' + ratio + ':1)</li>');
        }

        // Check file size
        if (size < 1048576) { // Less than 1MB
            insights.push('<li class="geoai-insight-good">✓ Good file size (' + formatBytes(size) + ')</li>');
        } else if (size < 8388608) { // Less than 8MB
            insights.push('<li class="geoai-insight-ok">○ File size: ' + formatBytes(size) + ' (acceptable, but could be optimized)</li>');
        } else {
            warnings.push('<li class="geoai-insight-error">✕ File too large: ' + formatBytes(size) + ' (Max: 8MB, Recommended: <1MB)</li>');
        }

        // Check format
        const ext = attachment.filename.split('.').pop().toLowerCase();
        if (ext === 'jpg' || ext === 'jpeg' || ext === 'png') {
            insights.push('<li class="geoai-insight-good">✓ Good format (' + ext.toUpperCase() + ')</li>');
        } else {
            warnings.push('<li class="geoai-insight-warning">⚠ Recommended format: JPG or PNG (Current: ' + ext.toUpperCase() + ')</li>');
        }

        const allInsights = insights.concat(warnings);
        $('#geoai-image-insights-list').html(allInsights.join(''));
        $('#geoai-image-insights').show();
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    // Tooltip functionality
    $('.geoai-tooltip').hover(
        function () {
            const tip = $(this).data('tip');
            const tooltip = $('<div class="geoai-tooltip-content">' + tip + '</div>');
            $('body').append(tooltip);
            
            const pos = $(this).offset();
            tooltip.css({
                top: pos.top - tooltip.outerHeight() - 10,
                left: pos.left - (tooltip.outerWidth() / 2) + 10
            });
        },
        function () {
            $('.geoai-tooltip-content').remove();
        }
    );

    // Toggle variables box
    $('.geoai-toggle-heading').on('click', function () {
        const content = $(this).next('.geoai-variables-content');
        const icon = $(this).find('.dashicons');
        
        content.slideToggle();
        icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
    });

    // Character counters for title/meta inputs
    function updateCharCounter(input) {
        const $input = $(input);
        const length = $input.val().length;
        const $counter = $input.closest('td').find('.geoai-char-count');
        const $indicator = $input.closest('td').find('.geoai-status-indicator');
        const type = $input.hasClass('geoai-title-input') ? 'title' : 'desc';
        
        $counter.text(length);

        // Update status indicator
        if (type === 'title') {
            if (length >= 50 && length <= 60) {
                $indicator.html('<span class="geoai-status-good">✓ Perfect</span>');
            } else if (length > 60 && length <= 70) {
                $indicator.html('<span class="geoai-status-warning">⚠ Too long</span>');
            } else if (length < 50 && length > 0) {
                $indicator.html('<span class="geoai-status-ok">○ Could be longer</span>');
            } else {
                $indicator.html('');
            }
        } else {
            if (length >= 150 && length <= 160) {
                $indicator.html('<span class="geoai-status-good">✓ Perfect</span>');
            } else if (length > 160 && length <= 180) {
                $indicator.html('<span class="geoai-status-warning">⚠ Too long</span>');
            } else if (length < 150 && length > 0) {
                $indicator.html('<span class="geoai-status-ok">○ Could be longer</span>');
            } else {
                $indicator.html('');
            }
        }
    }

    // Initialize character counters
    $('.geoai-title-input, .geoai-desc-input').each(function () {
        updateCharCounter(this);
    }).on('input', function () {
        updateCharCounter(this);
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
