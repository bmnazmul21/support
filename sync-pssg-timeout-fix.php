<?php

// Sync Master Sheet - Fix cURL Timeout and Optimize Batch Size

// 1. Increase HTTP timeout for Google Sheets API requests from 5s to 120s
add_filter( 'http_request_timeout', function( $timeout, $url ) {
    if ( strpos( $url, 'sheets.googleapis.com' ) !== false ) {
        return 120; // Allows up to 120 seconds before timing out
    }
    return $timeout;
}, 10, 2 );

// 2. Set batch size to 1,000 products per request to prevent server memory exhaustion
add_filter( 'pssg_pro_sheet_update_per_request', function( $limit ) {
    return 1000; // Processes products in safe chunks of 1,000
});