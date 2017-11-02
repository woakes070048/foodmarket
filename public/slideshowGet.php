<?php
//
// Description
// ===========
// This method will return all the information about an slideshow.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:         The ID of the business the slideshow is attached to.
// slideshow_id:          The ID of the slideshow to get the details for.
//
// Returns
// -------
//
function ciniki_foodmarket_slideshowGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'),
        'slideshow_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Slideshow'),
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
    $rc = ciniki_foodmarket_checkAccess($ciniki, $args['business_id'], 'ciniki.foodmarket.slideshowGet');
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
    // Return default for new Slideshow
    //
    if( $args['slideshow_id'] == 0 ) {
        $slideshow = array('id'=>0,
            'name'=>'',
            'permalink'=>'',
            'type'=>'10',
            'effect'=>'10',
            'speed'=>'10',
            'flags'=>'1',
        );
    }

    //
    // Get the details for an existing Slideshow
    //
    else {
        $strsql = "SELECT ciniki_foodmarket_slideshows.id, "
            . "ciniki_foodmarket_slideshows.name, "
            . "ciniki_foodmarket_slideshows.permalink, "
            . "ciniki_foodmarket_slideshows.type, "
            . "ciniki_foodmarket_slideshows.effect, "
            . "ciniki_foodmarket_slideshows.speed, "
            . "ciniki_foodmarket_slideshows.flags "
            . "FROM ciniki_foodmarket_slideshows "
            . "WHERE ciniki_foodmarket_slideshows.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
            . "AND ciniki_foodmarket_slideshows.id = '" . ciniki_core_dbQuote($ciniki, $args['slideshow_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.foodmarket', array(
            array('container'=>'slideshows', 'fname'=>'id', 
                'fields'=>array('name', 'permalink', 'type', 'effect', 'speed', 'flags'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.foodmarket.89', 'msg'=>'Slideshow not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['slideshows'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.foodmarket.90', 'msg'=>'Unable to find Slideshow'));
        }
        $slideshow = $rc['slideshows'][0];
    }

    return array('stat'=>'ok', 'slideshow'=>$slideshow);
}
?>
