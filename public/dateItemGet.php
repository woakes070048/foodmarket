<?php
//
// Description
// ===========
// This method will return all the information about an order date item.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:         The ID of the business the order date item is attached to.
// dateitem_id:          The ID of the order date item to get the details for.
//
// Returns
// -------
//
function ciniki_foodmarket_dateItemGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'),
        'dateitem_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order Date Item'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Make sure this module is activated, and
    // check permission to run this function for this business
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'foodmarket', 'private', 'checkAccess');
    $rc = ciniki_foodmarket_checkAccess($ciniki, $args['business_id'], 'ciniki.foodmarket.dateItemGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load business settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
    $rc = ciniki_businesses_intlSettings($ciniki, $args['business_id']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Return default for new Order Date Item
    //
    if( $args['dateitem_id'] == 0 ) {
        $dateitem = array('id'=>0,
            'date_id'=>'',
            'output_id'=>'',
            'quantity'=>'0',
        );
    }

    //
    // Get the details for an existing Order Date Item
    //
    else {
        $strsql = "SELECT ciniki_foodmarket_date_items.id, "
            . "ciniki_foodmarket_date_items.date_id, "
            . "ciniki_foodmarket_date_items.output_id, "
            . "ciniki_foodmarket_date_items.quantity "
            . "FROM ciniki_foodmarket_date_items "
            . "WHERE ciniki_foodmarket_date_items.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
            . "AND ciniki_foodmarket_date_items.id = '" . ciniki_core_dbQuote($ciniki, $args['dateitem_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.foodmarket', array(
            array('container'=>'dateitems', 'fname'=>'id', 
                'fields'=>array('date_id', 'output_id', 'quantity'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.foodmarket.46', 'msg'=>'Order Date Item not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['dateitems'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.foodmarket.47', 'msg'=>'Unable to find Order Date Item'));
        }
        $dateitem = $rc['dateitems'][0];
    }

    return array('stat'=>'ok', 'dateitem'=>$dateitem);
}
?>
