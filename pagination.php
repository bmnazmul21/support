<?php
add_action( 'wp_footer', function() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        'use strict';

        var wptPageClicked = false;
        var wptTarget = null;

        $(document.body).on('click', '.wpt_pagination_ajax .wpt_my_pagination a, .wpt_table_pagination a', function() {
            wptPageClicked = true;
            wptTarget = $(this).closest('.wpt-wrap, [id^="table_id_"]');
        });

        $(document).ajaxStop(function() {
            if (wptPageClicked && wptTarget && wptTarget.length) {
                var scrollTo = wptTarget;
                wptPageClicked = false;
                wptTarget = null;
                setTimeout(function() {
                    $('html, body').stop().animate({
                        scrollTop: scrollTo.offset().top - 120
                    }, 500);
                }, 200);
            }
        });
    });
    </script>
    <?php
}, 99 );