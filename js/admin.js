jQuery(document).ready(function($) {
    $('#choices').on('change', function() {
        try {
            JSON.parse($(this).val());
            $(this).css('border', '2px solid green');
        } catch (e) {
            $(this).css('border', '2px solid red');
        }
    });

    $('#background_image, #character_image').on('blur', function() {
        let url = $(this).val();
        if (url && !url.match(/^https?:\/\//)) {
            alert('URLは http:// または https:// で始まる必要があります。');
            $(this).val('');
        }
    });
});
