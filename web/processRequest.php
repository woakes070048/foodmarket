<?php
//
// Description
// -----------
// This function will process a web request for the food market module.
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure.
// business_id:     The ID of the business to get food market request for.
//
// args:            The possible arguments for posts
//
//
// Returns
// -------
//
function ciniki_foodmarket_web_processRequest(&$ciniki, $settings, $business_id, $args) {

    //
    // Check to make sure the module is enabled
    //
    if( !isset($ciniki['business']['modules']['ciniki.foodmarket']) ) {
        return array('stat'=>'404', 'err'=>array('code'=>'ciniki.foodmarket.33', 'msg'=>"I'm sorry, the page you requested does not exist."));
    }

    //
    // Load any customer details, session information, settings, etc,
    //


    //
    // Decide where to direct the request
    //
    if( isset($args['module_page']) && $args['module_page'] == 'ciniki.foodmarket.products' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'foodmarket', 'web', 'processRequestProducts');
        return ciniki_foodmarket_web_processRequestProducts($ciniki, $settings, $business_id, $args);
    }

    return array('stat'=>'404', 'err'=>array('code'=>'ciniki.foodmarket.34', 'msg'=>'Page not found'));
}
?>
