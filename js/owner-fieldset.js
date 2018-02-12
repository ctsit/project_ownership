$(document).ready(function() {
    if ($('#row_purpose').length === 0) {
        $('form table tbody').prepend(projectOwnership.fieldsetContents);

        var $submit = $('form input[type="submit"]');
        var submitCallback = function() {
            $('#form').submit();
            return false;
        }
    }
    else {
        $('#row_purpose').after(projectOwnership.fieldsetContents);
        var $submit = $('form table tr').last().find('td button').first();
        var submitCallback = $submit[0].onclick;
    }

    var $username = $('[name="project_ownership_username"]');

    // Overriding onclick callback of submit buttons.
    $submit[0].onclick = function(event) {
        var userId = $username.val();
        if (userId === '') {
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

    $.each(['firstname', 'lastname', 'email'], function(i, val) {
        $('[name="project_pi_' + val + '"]').change(function() {
            if (!$('[name="project_ownership_username"]').val()) {
                $('[name="project_ownership_' + val + '"]').val($(this).val());
            }
        });
    });

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

    var switchUserInfoFieldsStatus = function() {
        var userId = $username.val();

        if (userId === '') {
            $('.owner-required-info').removeAttr('disabled').parent().removeClass('disabled');
        }
        else {
            $('.owner-required-info').attr('disabled', 'disabled').val('').parent().addClass('disabled');
            $.get(projectOwnership.userInfoAjaxPath, {username: userId}, function(result) {
                if (result.success) {
                    $.each(result.data, function(key, value) {
                        $('[name="project_ownership_' + key + '"').val(value);
                    });
                }
            }, 'json');
        }
    }

    switchUserInfoFieldsStatus();

    $username.on('input', switchUserInfoFieldsStatus);
    $username.change(switchUserInfoFieldsStatus);

    $username.keydown(function() {
        var userParts = trim($(this).val()).split(' ');

        $(this).val(trim(userParts[0]));
        $(this).trigger('focus');
    });

    $('.project_ownership_auto_assign').click(function(event) {
        $username.val(projectOwnership.userId);
        $username.change();

        event.preventDefault();
        return false;
    });

    $('[name="project_ownership_email"]').blur(function() {
        if (redcap_validate(this, '', '', 'hard', 'email')) {
            emailInDomainWhitelist(this);
        }
    });
});
