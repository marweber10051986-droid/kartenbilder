
/* Verpackung nach Preis: 0,80 € Karten in Penny Sleeve + Team Bag, alles andere Penny Sleeve + Toploader. Greift bei jedem Speichern. */
add_action( 'woocommerce_before_product_object_save', function ( $p ) {
	$preis = (float) $p->get_regular_price();
	if ( $preis <= 0 ) {
		return;
	}
	$tl = 'Penny Sleeve und Toploader im gepolsterten';
	$tb = 'Penny Sleeve und Team Bag im gepolsterten';
	list( $von, $nach ) = $preis <= 0.80 ? array( $tl, $tb ) : array( $tb, $tl );
	foreach ( array( 'description', 'short_description' ) as $f ) {
		$t = $p->{"get_$f"}( 'edit' );
		if ( $t && false !== strpos( $t, $von ) ) {
			$p->{"set_$f"}( str_replace( $von, $nach, $t ) );
		}
	}
} );
