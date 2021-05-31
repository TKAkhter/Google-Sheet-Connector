jQuery(document).ready(function() {
    jQuery('.set-adds-interval').click(function() {
        var data = {
            action: 'set_adds_interval',
            security: jQuery('#gs_adds_ajax_nonce').val()
        };

        jQuery.post(ajaxurl, data, function(response) {
            if (response.success) {
                jQuery('.gs-adds').slideUp('slow');
            }
        });
    });

    jQuery('.close-adds-interval').click(function() {
        var data = {
            action: 'close_adds_interval',
            security: jQuery('#gs_adds_ajax_nonce').val()
        };

        jQuery.post(ajaxurl, data, function(response) {
            if (response.success) {
                jQuery('.gs-adds').slideUp('slow');
            }
        });
    });

    // Upgrade notification scripts
    jQuery('.cf7gsc_upgrade_later').click(function() {
        var data = {
            action: 'set_upgrade_notification_interval',
            security: jQuery('#gs_upgrade_ajax_nonce').val()
        };

        jQuery.post(ajaxurl, data, function(response) {
            if (response.success) {
                jQuery('.gs-upgrade').slideUp('slow');
            }
        });
    });

    jQuery('.cf7gsc_upgrade').click(function() {
        var data = {
            action: 'close_upgrade_notification_interval',
            security: jQuery('#gs_upgrade_ajax_nonce').val()
        };

        jQuery.post(ajaxurl, data, function(response) {
            if (response.success) {
                jQuery('.gs-upgrade').slideUp('slow');
            }
        });
    });
    // dropdown not working issue resolved
    jQuery(".cd-faq-trigger").click(function() {
        jQuery(this).parent('li').toggleClass('content-visible content-hide');
        jQuery(this).siblings('.cd-faq-content').toggle('fast');
    });

    //checkbox disabled input field
    jQuery(".gs-fields input[type=checkbox]").click(function() {
        jQuery(this).parent('li').toggleClass('content-visible content-hide');
        jQuery(this).siblings('.cd-faq-content').toggle('fast');
    });

    jQuery(".gs-fields input[type=checkbox]").click(function() {
        var inputField = jQuery(this).parent('td').siblings('.gs-r-pad').children('input'); 
        console.log(jQuery(this));
        if (this.checked) {
            inputField.prop('disabled', false);
        }else {
            inputField.prop('disabled', true);
        }
    });
});
// jQuery(this).parent('td').siblings('.gs-r-pad').children('input')