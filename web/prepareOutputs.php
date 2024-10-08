<?php
//
// Description
// ===========
// This function loads all the details for a product.
//
// Arguments
// ---------
// ciniki:
// tnid:         The ID of the tenant the product is attached to.
// product_id:          The ID of the product to get the details for.
//
// Returns
// -------
//
function ciniki_foodmarket_web_prepareOutputs($ciniki, $settings, $tnid, $args) {

    //
    // Load the list of items for a date
    //
    $date_items = array();
    if( isset($ciniki['session']['ciniki.poma']['date']['id']) && $ciniki['session']['ciniki.poma']['date']['id'] > 0 ) {
        $strsql = "SELECT output_id "
            . "FROM ciniki_foodmarket_date_items "
            . "WHERE date_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['ciniki.poma']['date']['id']) . "' "
            . "AND tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQueryList');
        $rc = ciniki_core_dbQueryList($ciniki, $strsql, 'ciniki.foodmarket', 'outputs', 'output_id');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['outputs']) ) {
            $date_items = $rc['outputs'];
        } 
    }

    //
    // Load the list of items in the queue
    //
    $strsql = "SELECT object_id, SUM(quantity) "
        . "FROM ciniki_poma_queued_items AS items "
        . "WHERE items.object = 'ciniki.foodmarket.output' "
        . "AND items.status = 10 "
        . "AND items.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "GROUP BY object_id "
        . "ORDER BY object_id "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQueryList2');
    $rc = ciniki_core_dbQueryList2($ciniki, $strsql, 'ciniki.foodmarket', 'items');
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.foodmarket.138', 'msg'=>'Unable to load item', 'err'=>$rc['err']));
    }
    $queued = isset($rc['items']) ? $rc['items'] : array();

    if( !isset($ciniki['session']['customer']['id']) || $ciniki['session']['customer']['id'] < 1 ) {
        foreach($args['outputs'] as $oid => $o) {
            //
            // Remove unavailable items
            //
            if( isset($o['ctype']) && $o['ctype'] == '90' && ($o['flags']&0x0200) > 0 && !in_array($o['id'], $date_items) ) {
                unset($args['outputs'][$oid]);
            }
            //
            // Check if price is to be hidden
            //
            if( isset($settings['page-foodmarket-public-prices']) && $settings['page-foodmarket-public-prices'] == 'no' ) {
                $args['outputs'][$oid]['price_text'] = '';
            }
        }

        return array('stat'=>'ok', 'outputs'=>$args['outputs']);
    }

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $tnid);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'datetimeFormat');
    $datetime_format = ciniki_users_datetimeFormat($ciniki, 'php');

    //
    // Get the object_ids
    //
    $output_ids = array();
    foreach($args['outputs'] as $output) {
        $output_ids[] = $output['id'];
    }

    //
    // Load the items from the current order
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'web', 'orderItemsByObjectID');
    $rc = ciniki_poma_web_orderItemsByObjectID($ciniki, $tnid, array(
        'customer_id'=>$ciniki['session']['customer']['id'],
        'object'=>'ciniki.foodmarket.output',
        'object_ids'=>$output_ids,
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['items']) ) {
        $order_items = $rc['items'];
    } else {
        $order_items = array();
    }

    //
    // Load the items the customer has favourited, repeat order, or queued
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'hooks', 'customerItemsByType');
    $rc = ciniki_poma_hooks_customerItemsByType($ciniki, $tnid, array(
        'customer_id'=>$ciniki['session']['customer']['id'],
        'object'=>'ciniki.foodmarket.output',
        'object_ids'=>$output_ids,
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['types']) ) {
        $item_types = $rc['types'];
    } else {
        $item_types = array();
    }

    $outputs = array();
    foreach($args['outputs'] as $oid => $output) {
        //
        // Check for sale pricing
        //
        if( isset($output['sale_price']) && $output['sale_price'] > 0 ) {
            $output['price_text'] = '$' . number_format($output['price'], 2);
        }

        //
        // Check if is should be member pricing
        //
        if( isset($ciniki['session']['customer']['foodmarket.member']) 
            && $ciniki['session']['customer']['foodmarket.member'] = 'yes' 
            && isset($output['retail_mdiscount_percent']) && $output['retail_mdiscount_percent'] > 0 
            ) {
            $output['sale_price'] = $output['member_price'];
            $output['sale_price_text'] = $output['member_price_text'];
        }
        //
        // Check if repeating order is available for this output
        //
        if( ($output['flags']&0x0100) == 0x0100 ) {
            $output['repeat'] = 'yes';
            $output['available'] = 'yes';
        } else {
            $output['repeat'] = 'no';
            $output['available'] = 'no';
        }

        //
        // Check if date specific
        //
        if( ($output['flags']&0x0200) == 0x0200 ) {
            $output['available'] = 'no';
            $output['repeat'] = 'no';
            if( in_array($output['id'], $date_items) ) {
                $output['available'] = 'yes';
            }

/*            ** This code may be useful when implementing date range availablity
            if( $output['start_date'] != '' && $output['start_date'] != '0000-00-00' 
                && $output['end_date'] != '' && $output['end_date'] != '0000-00-00' 
                && isset($ciniki['session']['ciniki.poma']['date']['order_dt'])
                ) {
                $sdt = new DateTime($output['start_date'] + ' 00:00:00', new DateTimezone($intl_timezone));
                $edt = new DateTime($output['end_date'] + ' 23:59:59', new DateTimezone($intl_timezone));
                //
                // Check if start date is before current order date and end 
                //
                if( $sdt < $ciniki['session']['ciniki.poma']['date']['order_dt'] && $edt > $ciniki['session']['ciniki.poma']['date']['order_dt'] ) {
                    $output['available'] = 'yes';
                }
            } */
        
            //
            // Check if category type is Available products, and reject unavailable ones
            //
            if( isset($output['ctype']) && $output['ctype'] == '90' && $output['available'] == 'no' ) {
                continue;
            }
        }

        //
        // Check if queue is available for this output
        //
        $output['queue_size'] = 0;
        if( ($output['flags']&0x0400) == 0x0400 ) {
            $output['queue'] = 'yes';
            if( isset($item_types['queueactive']['items'][$output['id']]) ) {
                $output['queue_quantity'] = $item_types['queueactive']['items'][$output['id']]['quantity'];
            } else {
                $output['queue_quantity'] = 0;
            }
            if( isset($item_types['queueordered']['items'][$output['id']]) ) {
                $output['queue_ordered_quantity'] = $item_types['queueordered']['items'][$output['id']]['quantity'];
            } else {
                $output['queue_ordered_quantity'] = 0;
            }
            if( (isset($output['itype']) && $output['itype'] == 50 && $output['otype'] == 30) || ($output['otype'] > 50 && $output['otype'] <= 60) ) {
                $output['queue_slots_total'] = 1;
                if( isset($queued[$output['id']]) ) {
                    $output['queue_size'] = $queued[$output['id']];
                } else {
                    $output['queue_size'] = 0;
                }
                switch($output['otype']) {
                    case 30: $output['queue_slots_total'] = $output['case_units']; break;
                    case 52: $output['queue_slots_total'] = 2; break;
                    case 53: $output['queue_slots_total'] = 3; break;
                    case 54: $output['queue_slots_total'] = 4; break;
                    case 55: $output['queue_slots_total'] = 5; break;
                    case 56: $output['queue_slots_total'] = 6; break;
                }
                if( isset($output['queue_size']) && $output['queue_size'] > 0 ) {
                    $output['queue_slots_filled'] = ($output['queue_size'] % $output['queue_slots_total']);
                    // All slots filled, display all filled not empty
                    if( $output['queue_slots_filled'] == 0 ) {
                        $output['queue_slots_filled'] = $output['queue_slots_total'];
                    }
                } else {
                    $output['queue_slots_filled'] = 0;
                }
/*                $output['name'] .= '<span class="queue-slots">';
                for($i = 1; $i <= $total_slots; $i++) {
                    if( $i <= $filled_slots ) {
                        $output['name'] .= '<span class="fa-icon order-icon order-options-queue-slot-filled">&#xf14a;</span>';
                    } else {
                        $output['name'] .= '<span class="fa-icon order-icon order-options-queue-slot-open">&#xf096;</span>';
                    }
                }
                $output['name'] .= '</span>'; */
            }
        } else {
            $output['queue'] = 'no';
        }
//$output['queue'] = 'no';

        //
        // Check if limited
        //
        if( ($output['flags']&0x0800) == 0x0800 ) {
            $output['repeat'] = 'no';
            if( isset($output['inventory']) && $output['inventory'] > 0 ) {
                $output['available'] = 'yes';
                $output['quantity_limit'] = $output['inventory'];
            }
        }

        //
        // Check if available and if already ordered
        //
        if( $output['available'] == 'yes' && isset($order_items[$output['id']]['quantity']) ) {
            $output['order_quantity'] = (float)$order_items[$output['id']]['quantity'];
        } else {
            $output['order_quantity'] = 0;
        }

        //
        // Check if already in list of repeating items
        //
        if( $output['repeat'] == 'yes' && isset($item_types['repeat']['items'][$output['id']]['repeat_text']) ) {
            $output['repeat_value'] = 'on';
            $output['repeat_text'] = $item_types['repeat']['items'][$output['id']]['repeat_text'];
            $output['repeat_quantity'] = (float)$item_types['repeat']['items'][$output['id']]['quantity'];
            $output['repeat_days'] = $item_types['repeat']['items'][$output['id']]['repeat_days'];
            $output['repeat_next_date'] = $item_types['repeat']['items'][$output['id']]['next_order_date'];
        } else {
            $output['repeat_value'] = 'off';
            $output['repeat_quantity'] = 0;
            $output['repeat_days'] = 7;
            $output['repeat_next_date'] = '';
        }

        //
        // Always available as a favourite
        //
        $output['favourite'] = 'yes';
        if( isset($item_types['favourite']['items'][$output['id']]) ) {
            $output['favourite_value'] = 'on';
        } else {
            $output['favourite_value'] = 'off';
        }

        //
        // Group items
        //
        if( isset($output['input_id']) ) {
            $output['group_name'] = $output['input_id'];
        }

        $outputs[] = $output;
    }

    return array('stat'=>'ok', 'outputs'=>$outputs);
}
?>
