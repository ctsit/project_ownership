$(document).ready(function() {
    if ($('#row_purpose').length === 0) {
        $('form table tbody').prepend(projectOwnership.fieldsetContents);
    }
    else {
        $('#row_purpose').after(projectOwnership.fieldsetContents);
    }

    $.each(['firstname', 'lastname', 'email'], function(i, val) {
        $('[name="project_pi_' + val + '"]').change(function() {
            if (!$('[name="project_ownership_username"]').val()) {
                $('[name="project_ownership_' + val + '"]').val($(this).val());
            }
        });
    });

    var $username = $('[name="project_ownership_username"]');
    $username.autocomplete({
        source: app_path_webroot + 'UserRights/search_user.php?searchEmail=1',
        minLength: 2,
        delay: 150,
        select: function(event, ui) {
            $(this).val(ui.item.value);
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
        if ($username.val() === '') {
            $('.user-info').removeAttr('disabled').parent().removeClass('disabled');
        }
        else {
            $('.user-info').attr('disabled', 'disabled').val('').parent().addClass('disabled');
        }
    }

    $username.on('input', switchUserInfoFieldsStatus);

    $username.keydown(function() {
        var userParts = trim($(this).val()).split(' ');

        $(this).val(trim(userParts[0]));
        $(this).trigger('focus');
    });

    $('.project_ownership_auto_assign').click(function(event) {
        $username.val(projectOwnership.userId);
        switchUserInfoFieldsStatus();

        event.preventDefault();
        return false;
    });

    $('[name="project_ownership_email"]').blur(function() {
        if (redcap_validate(this, '', '', 'hard', 'email')) {
            emailInDomainWhitelist(this);
        }
    });
});
