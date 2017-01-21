<?php
//
// Description
// ===========
// This function will convert a product output to a poma order item.
//
// Arguments
// ---------
// ciniki:
// business_id:         The ID of the business the product is attached to.
// product_id:          The ID of the product to get the details for.
//
// Returns
// -------
//
function ciniki_foodmarket_convertOutputItem($ciniki, $business_id, $output) {

    $item = array(
        'object'=>'ciniki.foodmarket.output',
        'object_id'=>$output['id'],
        'description'=>$output['pio_name'],
        'flags'=>0,
        'itype'=>(isset($output['otype']) ? $output['otype'] : 30),
        'units'=>(isset($output['units']) ? $output['units'] : 0),
        'unit_amount'=>$output['retail_price'],
        'taxtype_id'=>(isset($output['retail_taxtype_id']) ? $output['retail_taxtype_id'] : 0),
        'packing_order'=>(isset($output['packing_order']) ? $output['packing_order'] : 10),
        );

    //
    // Adjust output type to be inline with poma order item types
    //
    if( $item['itype'] == 71 ) {
        $item['itype'] = 10;
    } elseif( $item['itype'] > 30 ) {
        $item['itype'] = 30;
    }

    //
    // Set the substitution flag
    //
    if( $output['otype'] == 70 ) {
        $item['flags'] |= 0x02;
    }

    //
    // Setup weight units if applicable
    //
    if( $item['itype'] < 30 ) {
        if( ($item['units']&0x02) == 0x02 ) {
            $item['weight_units'] = 20;
        } elseif( ($item['units']&0x04) == 0x04 ) {
            $item['weight_units'] = 25;
        } elseif( ($item['units']&0x20) == 0x20 ) {
            $item['weight_units'] = 60;
        } elseif( ($item['units']&0x40) == 0x40 ) {
            $item['weight_units'] = 65;
        } else {
            $item['weight_units'] = 0;
        }
    } else {
        if( ($item['units']&0x0100) == 0x0100 ) {
            $item['unit_suffix'] = 'each';
        } elseif( ($item['units']&0x0200) == 0x0200 ) {
            $item['unit_suffix'] = 'pair';
        } elseif( ($item['units']&0x0400) == 0x0400 ) {
            $item['unit_suffix'] = 'bunch';
        } elseif( ($item['units']&0x0800) == 0x0800 ) {
            $item['unit_suffix'] = 'bag';
        }
    }

    return array('stat'=>'ok', 'item'=>$item);
}
?>