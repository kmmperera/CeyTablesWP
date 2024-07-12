jQuery(document).ready(function($) {
    $('.ceymulticall-open-modal').on('click', function() {
        var table = $(this).data('table');
        var row = $(this).data('row');

        $('#ceymulticall-modal-table').val(table);

        var fieldsHtml = '';
        $.each(row, function(key, value) {
            fieldsHtml += '<div class="form-group">';
            fieldsHtml += '<label for="ceymulticall-modal-' + key + '">' + key + ':</label>';
            fieldsHtml += '<input type="text" name="' + key + '" id="ceymulticall-modal-' + key + '" value="' + value + '">';
            fieldsHtml += '</div>';
        });
        $('#ceymulticall-modal-fields').html(fieldsHtml);

        $('#ceymulticall-update-modal').dialog({
            modal: true,
            width: 400
        });
    });
});