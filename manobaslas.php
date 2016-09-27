<?php
class Scrapper_Manobalsas {
	public static $domain = 'http://www.manobalsas.lt';
	function get( $url ) {
		$html = file_get_contents( self::$domain.$url );
		$html = mb_convert_encoding($html, 'HTML-ENTITIES', "UTF-8");
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML( $html );
		return $dom;
	}
	function find( $needle, $haystack ) {
		$xpath = new DOMXPath( $haystack );
		$result = $xpath->query( $needle );
		return $result;
	}
	function prop( $name, $haystack ) {
		$result = array();
		foreach ( $haystack as $twig ) {
			$result[] =  $twig->getAttribute( $name );
		}
		return $result;
	}
	function search( $pattern, $haystack ) {
		$subject = $haystack->saveHTML();
		preg_match_all( $pattern, $subject, $m );
		return $m;
	}
	function get_person( $url ) {
		$person = Scrapper_Manobalsas::get( $url );
		$data = array(
			'name' 				=> Scrapper_Manobalsas::find( '//h1', $person )->item(0)->textContent,
			'party'				=> trim(Scrapper_Manobalsas::find( "//div[contains(@class, 'subtitle')]", $person )->item(0)->textContent),
			'person_id' 	=> Scrapper_Manobalsas::search( '/parodyti_visus_atsakymus\(([0-9]+)/ims', $person )[1][0],
			'votes'				=> array(),
			'comments'		=> array(),
		);

		$table = Scrapper_Manobalsas::get( "/politikai/atsakymu_lentele.php?pol_id={$data['person_id']}&tst=11&all=1" );
		$temp = Scrapper_Manobalsas::find( "//table/tbody/td[contains(@class, 'noborder')]/following::td[6]", $table );
		foreach( $temp as $key => $item ) {
			$data['comments'][ $key+1 ] = trim( $item->textContent );
			$data['votes'][ $key+1 ] = 0;
		}
		for ( $i = 1; $i < 5; ++$i ) {
			$temp = Scrapper_Manobalsas::find( "//table/tbody/td[contains(@class, 'noborder')]/following::td[{$i}]", $table );
			foreach( $temp as $key => $item ) {
				if ( 2 == $item->childNodes->length ) {
					$data['votes'][ $key+1 ] = $i;
				}
			}
		}
		return $data;
	}
	function get_all() {
		$toc = Scrapper_Manobalsas::get('/politikai/politikai.php');
		$list = Scrapper_Manobalsas::find( "//div[contains(@class, 'list')]/ul/li/a", $toc );
		$list = Scrapper_Manobalsas::prop( 'href', $list );
		foreach ( $list as $no => $item ) {
			$item = str_replace( '..', '', $item );
			$item = str_replace( ' ', urlencode(' '), $item );
			$list[ $no ] = Scrapper_Manobalsas::get_person( $item );
		}
		return $list;
	}
	function get_json() {
		$data = Scrapper_Manobalsas::get_all();
		file_put_contents( 'data.json', json_encode( $data, JSON_PRETTY_PRINT ) );
		echo "Done.";
	}
	function flatten() {
		$data = file_get_contents( 'data.json' );
		$data = json_decode( $data, true );
		foreach( $data as $key => $person ) {
			foreach( $person['votes'] as $k => $vote ) {
				$data[ $key ][ 'vote_'.$k ] = $vote;
			}
			foreach( $person['comments'] as $k => $vote ) {
				$data[ $key ][ 'comment_'.$k ] = $vote;
			}
			unset( $data[$key]['votes'] );
			unset( $data[$key]['comments'] );
		}
		$fp = fopen('data.csv', 'w');
		fputcsv($fp, array_keys( $data[0] ) );
		foreach ($data as $fields) {
		    fputcsv($fp, $fields);
		}
		fclose($fp);
		echo 'Done.';
	}
}
Scrapper_Manobalsas::flatten();
// collect
// flatten
?>
