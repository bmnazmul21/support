<?php
/**
 * 1. Remove the "Produkt" prefix from the attribute filter label.
 */
function custom_wpt_erhaltungszustand_label( $label, $taxonomy_details, $table_id ) {
    if ( isset( $taxonomy_details->name ) && $taxonomy_details->name === 'pa_erhaltungszustand' ) {
        return 'Erhaltungszustand';
    }
    return $label;
}
add_filter( 'wpto_searchbox_taxonomy_name', 'custom_wpt_erhaltungszustand_label', 10, 3 );


/**
 * 2. Change the default dropdown placeholder to plural ("Alle Erhaltungszustände").
 */
function custom_wpt_erhaltungszustand_dropdown_label( $defaults, $taxonomy_keyword, $taxonomy_details, $table_id ) {
    if ( $taxonomy_keyword === 'pa_erhaltungszustand' ) {
        $defaults['show_option_all'] = 'Alle Erhaltungszustände';
    }
    return $defaults;
}
add_filter( 'wpt_seachbox_tax_args', 'custom_wpt_erhaltungszustand_dropdown_label', 10, 4 );
add_filter( 'wpt_searchbox_tax_args', 'custom_wpt_erhaltungszustand_dropdown_label', 10, 4 );