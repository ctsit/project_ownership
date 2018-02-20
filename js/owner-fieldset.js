$(document).ready(function() {
    if ($('#row_purpose').length === 0) {
        // Place ownership fieldset at project edit page.
        $('form table tbody').prepend(projectOwnership.fieldsetContents);

        var $submit = $('form input[type="submit"]');
        var submitCallback = function() {
            $('#form').submit();
            return false;
        }
    }
    else {
        // Place ownership fieldset at project create page, right after
        // "Purpose" field.
        $('#row_purpose').after(projectOwnership.fieldsetContents);
        var $submit = $('form table tr').last().find('td button').first();
        var submitCallback = $submit[0].onclick;
    }

    var $username = $('[name="project_ownership_username"]');

    // Overriding onclick callback of submit buttons.
    $submit[0].onclick = function(event) {
        var userId = $username.val();
        if (userId === '') {
            // If username is not set, we need to check for required fields.
            var fieldName = false;

            $('.owner-required-info').each(function() {
                if ($(this).val() === '') {
                    fieldName = $(this).siblings('.owner-info-label').text();
                    simpleDialog('Please provide a valid ' + fieldName  + '.', 'Invalid ' + fieldName + '.');
                    return false;
                }
            });

            if (fieldName) {
                return false;
            }

            // Go ahead with normal procedure.
            return submitCallback();
        }
        else {
            // If username is set, we need to check it is valid.
            $.get(projectOwnership.userInfoAjaxPath, {username: userId}, function(result) {
                if (!result.success) {
                    simpleDialog('Please provide a valid REDCap username.', 'Invalid REDCap username.');
                    return false;
                }

                // Go ahead with normal procedure.
                return submitCallback();
            }, 'json');

            return false;
        }
    };

    // Autocompleting first name, last name and email fields as the respective
    // PI fields are filled out.
    $.each(['firstname', 'lastname', 'email'], function(i, val) {
        $('[name="project_pi_' + val + '"]').change(function() {
            if (!$('[name="project_ownership_username"]').val()) {
                $('[name="project_ownership_' + val + '"]').val($(this).val());
            }
        });
    });

    // Setting up autocomplete for username field.
    $username.autocomplete({
        source: app_path_webroot + 'UserRights/search_user.php?searchEmail=1',
        minLength: 2,
        delay: 150,
        select: function(event, ui) {
            $(this).val(ui.item.value);
            $(this).change();
            return false;
        }
    })
    .data('ui-autocomplete')._renderItem = function(ul, item) {
        return $('<li></li>')
            .data('item', item)
            .append('<a>' + item.label + '</a>')
            .appendTo(ul);
    };

    // Update callback for Username field.
    var usernameFieldUpdateCallback = function() {
        var userId = $username.val();

        if (userId === '') {
            $('.owner-required-info').removeAttr('disabled').parent().removeClass('disabled');
        }
        else {
            // If username field is not empty, clear up and disable first name,
            // last name, and email fields.
            $('.owner-required-info').attr('disabled', 'disabled').val('').parent().addClass('disabled');

            // If the given username is valid, fill out first name, last name
            // and email by pulling account information.
            $.get(projectOwnership.userInfoAjaxPath, {username: userId}, function(result) {
                if (result.success) {
                    $.each(result.data, function(key, value) {
                        $('[name="project_ownership_' + key + '"').val(value);
                    });
                }
            }, 'json');
        }
    }

    // Setting up initial fieldset state.
    usernameFieldUpdateCallback();

    // Listening changes on username field.
    $username.on('input', usernameFieldUpdateCallback);
    $username.change(usernameFieldUpdateCallback);

    // Handling ownership auto assign link.
    $('.project_ownership_auto_assign').click(function(event) {
        $username.val(projectOwnership.userId);
        $username.change();

        event.preventDefault();
        return false;
    });

    // Validating email field.
    $('[name="project_ownership_email"]').blur(function() {
        if (redcap_validate(this, '', '', 'hard', 'email')) {
            emailInDomainWhitelist(this);
        }
    });
});
