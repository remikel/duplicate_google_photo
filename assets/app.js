/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.scss';

// start the Stimulus application
import './bootstrap';
import './scripts/materialize-int.js';

$('#checkAll').on('click', function () {
    console.log('check');
    if ($(this).is(':checked')) {
        $('.rowToDelete').attr('checked', true);
    } else {
        $('.rowToDelete').attr('checked', false);
    }
})
$('#deleteRows').on('click', function () {
    $('.rowToDelete').each(function () {
        if ($(this).is(':checked')) {
            $(this).parent().parent().parent().fadeOut();
        }
    })
})