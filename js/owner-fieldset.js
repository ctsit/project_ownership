$(document).ready(function() {
    // Place ownership fieldset at project create/edit page, right after
    // "Purpose" field.
    $('#row_purpose').after(projectOwnership.fieldsetContents);

    // Setting up autocomplete for username field.
    var $username = $('[name="project_ownership_username"]');

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
            $('.po-required-info').removeAttr('disabled').parent().removeClass('disabled');
        }
        else {
            // If username field is not empty, clear up and disable first name,
            // last name, and email fields.
            $('.po-required-info').attr('disabled', 'disabled').val('').parent().addClass('disabled');

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

    // Autocompleting first name, last name and email fields as the respective
    // PI fields are filled out.
    $.each(['firstname', 'lastname', 'email'], function(i, val) {
        $('[name="project_pi_' + val + '"]').change(function() {
            if (!$('[name="project_ownership_username"]').val()) {
                $('[name="project_ownership_' + val + '"]').val($(this).val());
            }
        });
    });

    // Handling ownership auto assign link.
    $('.po-auto-assign').click(function(event) {
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

    // Overriding submit callbacks for each case: create and edit project
    // settings.
    switch (projectOwnership.context) {
        case 'copy':
        case 'create':
            var saveCallback = function() {
                showProgress(1);
                document.createdb.submit();
            };

            // Overriding submit button's click callback.
            var $submit = $('form table tr').last().find('td button').first();
            $submit[0].onclick = projectOwnershipSubmit;

            break;

        case 'edit':
            var saveCallback = function() {
                $('#editprojectform').submit();
            };

            $('#edit_project').on('dialogopen', function() {
                var buttons = $(this).dialog('option', 'buttons');

                // Overriding dialog's save button.
                buttons.Save = projectOwnershipSubmit;
                $(this).dialog('option', 'buttons', buttons);
            });

            if (projectOwnership.openProjectEditPopup) {
                displayEditProjPopup();
            }

            break;
    }

    // The new submit callback, that runs extra validation checks for project
    // ownership fields.
    function projectOwnershipSubmit() {
        if (!setFieldsCreateFormChk()) {
            return false;
        }

        if (projectOwnership.context === 'copy') {
            if ($('#currenttitle').val() === $('#app_title').val()) {
                simpleDialog(projectOwnership.copyTitleErrorMsg);
                return false;
            }
        }

        var userId = $username.val();
        if (userId === '') {
            // If username is not set, we need to check for required fields.
            var fieldName = false;

            $('.po-required-info').each(function() {
                if ($(this).val() === '') {
                    fieldName = $(this).siblings('.po-info-label').text();
                    simpleDialog('Please provide a valid ' + fieldName  + '.', 'Invalid ' + fieldName + '.');
                    return false;
                }
            });

            if (fieldName) {
                return false;
            }

            // Go ahead with normal procedure.
            saveCallback();
        }
        else {
            // If username is set, we need to check it is valid.
            $.get(projectOwnership.userInfoAjaxPath, {username: userId}, function(result) {
                if (!result.success) {
                    simpleDialog('Please provide a valid REDCap username.', 'Invalid REDCap username.');
                    return false;
                }

                // Go ahead with normal procedure.
                saveCallback();
            }, 'json');

            return false;
        }
    }
});
