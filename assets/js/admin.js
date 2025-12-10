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

    // Clear error styling when URL or Organization ID changes
    $('input[name="kursorganizer_settings[main_app_url]"], #ko_organization_id').on('input change', function() {
        var $field = $(this);
        var resultSpan = $('#test-org-id-result');
        
        // Clear error styling
        $field.removeClass('error').css({
            'border-color': '',
            'box-shadow': ''
        });
        
        // Clear result message
        resultSpan.html('');
    });
    
    // Test Organization ID
    $('#test-org-id-btn').on('click', function() {
        var btn = $(this);
        var resultSpan = $('#test-org-id-result');
        var urlField = $('input[name="kursorganizer_settings[main_app_url]"]');
        var orgIdField = $('#ko_organization_id');
        
        var url = urlField.val().trim();
        var orgId = orgIdField.val().trim();
        
        if (!url || !orgId) {
            resultSpan.html('<span style="color: #d63638;">Bitte geben Sie sowohl die URL als auch die Organization ID ein.</span>');
            return;
        }
        
        btn.prop('disabled', true).text('Wird getestet...');
        resultSpan.html('<span style="color: #666;">Teste Verbindung...</span>');
        
        $.ajax({
            url: kursorganizerAdmin.ajaxurl || ajaxurl,
            type: 'POST',
            data: {
                action: 'kursorganizer_test_org_id',
                nonce: kursorganizerAdmin.nonce,
                url: url,
                org_id: orgId
            },
            success: function(response) {
                var urlField = $('input[name="kursorganizer_settings[main_app_url]"]');
                
                if (response.success) {
                    resultSpan.html('<span style="color: #00a32a; font-weight: bold;">✓ ' + response.data + '</span>');
                    // Remove error styling from both fields
                    orgIdField.removeClass('error').css({
                        'border-color': '',
                        'box-shadow': ''
                    });
                    urlField.removeClass('error').css({
                        'border-color': '',
                        'box-shadow': ''
                    });
                    // Re-enable all form fields if they were disabled
                    $('#kursorganizer-settings-form input, #kursorganizer-settings-form select, #kursorganizer-settings-form textarea').prop('disabled', false).css('opacity', '1');
                    $('#kursorganizer-submit-btn').prop('disabled', false);
                } else {
                    var errorMessage = response.data || 'Unbekannter Fehler';
                    resultSpan.html('<span style="color: #d63638; font-weight: bold;">✗ ' + errorMessage + '</span>');
                    
                    // Check if error is related to URL (no company found, invalid URL, etc.)
                    var isUrlError = errorMessage.indexOf('URL') !== -1 || 
                                     errorMessage.indexOf('Schwimmschule') !== -1 || 
                                     errorMessage.indexOf('gefunden') !== -1 ||
                                     errorMessage.indexOf('ungültig') !== -1;
                    
                    if (isUrlError) {
                        // Mark URL field as error
                        urlField.addClass('error').css({
                            'border-color': '#d63638',
                            'box-shadow': '0 0 0 1px #d63638'
                        });
                    } else {
                        // Mark Organization ID field as error
                        orgIdField.addClass('error').css({
                            'border-color': '#d63638',
                            'box-shadow': '0 0 0 1px #d63638'
                        });
                    }
                    
                    // Disable all form fields except URL and Organization ID
                    $('#kursorganizer-settings-form input:not([name*="main_app_url"]):not([name*="ko_organization_id"]), #kursorganizer-settings-form select, #kursorganizer-settings-form textarea').prop('disabled', true).css('opacity', '0.6');
                    $('#kursorganizer-submit-btn').prop('disabled', true);
                }
                btn.prop('disabled', false).text('Verbindung testen');
            },
            error: function(xhr, status, error) {
                var errorMessage = 'Fehler beim Testen der Verbindung. Bitte überprüfen Sie die URL und Ihre Internetverbindung.';
                resultSpan.html('<span style="color: #d63638; font-weight: bold;">✗ ' + errorMessage + '</span>');
                
                // Mark URL field as error
                var urlField = $('input[name="kursorganizer_settings[main_app_url]"]');
                urlField.addClass('error').css({
                    'border-color': '#d63638',
                    'box-shadow': '0 0 0 1px #d63638'
                });
                
                btn.prop('disabled', false).text('Verbindung testen');
            }
        });
    });
});
