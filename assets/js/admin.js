jQuery(document).ready(function($) {
    'use strict';

    // Generate Shortcode
    $('#generate-shortcode-btn').on('click', function() {
        var shortcode = '[kursorganizer_iframe';
        var params = [];

        // Get selected course types
        // Always use coursetypeids (plural), even for single selection
        var selectedCourseTypes = $('#generator-coursetypes').val();
        if (selectedCourseTypes && selectedCourseTypes.length > 0) {
            params.push('coursetypeids="' + selectedCourseTypes.join(',') + '"');
        }

        // Get location
        var location = $('#generator-location').val();
        if (location) {
            params.push('locationid="' + location + '"');
        }

        // Get city
        var city = $('#generator-city').val().trim();
        if (city && !location) { // Only use city if no location is selected
            params.push('city="' + city + '"');
        }

        // Get category
        var category = $('#generator-category').val();
        if (category) {
            params.push('coursecategoryid="' + category + '"');
        }

        // Get instructor
        var instructor = $('#generator-instructor').val();
        if (instructor) {
            params.push('instructorid="' + instructor + '"');
        }

        // Get selected days - improved selection
        var selectedDays = [];
        var dayCheckboxes = $('#day-filter-fieldset input[type="checkbox"]:checked');
        
        if (dayCheckboxes.length === 0) {
            // Fallback: try alternative selector
            dayCheckboxes = $('input[name="days[]"]:checked');
        }
        
        dayCheckboxes.each(function() {
            var dayValue = $(this).val();
            if (dayValue && dayValue.trim() !== '') {
                selectedDays.push(dayValue.trim());
            }
        });
        
        if (selectedDays.length > 0) {
            params.push('dayfilter="' + selectedDays.join(',') + '"');
        }

        // Get show filter menu setting
        var showFilterMenu = $('#generator-showfiltermenu').is(':checked');
        if (!showFilterMenu) {
            params.push('showfiltermenu="false"');
        }

        // Build final shortcode
        if (params.length > 0) {
            shortcode += ' ' + params.join(' ');
        }
        shortcode += ']';

        // Display the shortcode
        $('#generated-shortcode').val(shortcode);
        $('#generated-shortcode-container').slideDown();
        $('#copy-success-message').hide();
    });

    // Copy to clipboard
    $('#copy-shortcode-btn').on('click', function() {
        var shortcodeText = $('#generated-shortcode');
        var shortcodeValue = shortcodeText.val();
        
        try {
            // Modern Clipboard API - copy as plain text
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shortcodeValue).then(function() {
                    showCopySuccess();
                }).catch(function() {
                    // Fallback to execCommand
                    fallbackCopy(shortcodeText, shortcodeValue);
                });
            } else {
                // Fallback for older browsers
                fallbackCopy(shortcodeText, shortcodeValue);
            }
        } catch (err) {
            fallbackCopy(shortcodeText, shortcodeValue);
        }
    });

    function fallbackCopy(element, textToCopy) {
        // Create a temporary textarea to copy the text
        var tempTextarea = $('<textarea>');
        $('body').append(tempTextarea);
        tempTextarea.val(textToCopy).select();
        
        var successful = document.execCommand('copy');
        tempTextarea.remove();
        
        if (successful) {
            showCopySuccess();
        } else {
            alert('Bitte kopieren Sie den Shortcode manuell (Strg+C / Cmd+C)');
        }
    }

    function showCopySuccess() {
        $('#copy-success-message').fadeIn().delay(2000).fadeOut();
    }

    // Clear cache
    $('#clear-cache-btn').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Wird geleert...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'kursorganizer_clear_cache',
                nonce: kursorganizerAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Cache erfolgreich geleert! Die Seite wird neu geladen.');
                    location.reload();
                } else {
                    alert('Fehler beim Leeren des Cache: ' + (response.data || 'Unbekannter Fehler'));
                    btn.prop('disabled', false).text('Cache leeren');
                }
            },
            error: function() {
                alert('Fehler beim Leeren des Cache.');
                btn.prop('disabled', false).text('Cache leeren');
            }
        });
    });

    // Auto-disable city field when location is selected
    $('#generator-location').on('change', function() {
        if ($(this).val()) {
            $('#generator-city').prop('disabled', true).css('opacity', '0.5');
        } else {
            $('#generator-city').prop('disabled', false).css('opacity', '1');
        }
    });

    // Reset all form fields
    $('#reset-form-btn').on('click', function() {
        // Reset course types (multi-select)
        $('#generator-coursetypes').val(null).trigger('change');
        
        // Reset location dropdown
        $('#generator-location').val('');
        
        // Reset city input
        $('#generator-city').val('').prop('disabled', false).css('opacity', '1');
        
        // Reset category dropdown
        $('#generator-category').val('');
        
        // Reset instructor dropdown
        $('#generator-instructor').val('');
        
        // Reset day checkboxes
        $('input[name="days[]"]').prop('checked', false);
        
        // Reset show filter menu checkbox
        $('#generator-showfiltermenu').prop('checked', true);
        
        // Hide generated shortcode container
        $('#generated-shortcode-container').slideUp();
        $('#generated-shortcode').val('');
        $('#copy-success-message').hide();
    });
});
